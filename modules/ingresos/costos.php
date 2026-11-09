<?php
/**
 * Fallas y costos de un ingreso (RF05, RF06): diagnóstico, tipo de
 * mantenimiento, técnico encargado y repuestos.
 *
 * Solo se puede modificar un ingreso ABIERTO. El costo total no se guarda:
 * se calcula como SUM(cantidad * costo_unitario) (ver database/README.md).
 *
 * Las funciones que modifican devuelven un arreglo de errores;
 * si está vacío, el cambio se guardó.
 */

require_once __DIR__ . '/logica.php';

const TIPOS_MANTENIMIENTO = ['preventivo' => 'Preventivo', 'correctivo' => 'Correctivo'];

// -------------------------------------------------------------
// Consultas
// -------------------------------------------------------------

/** Repuestos de un ingreso, con su subtotal (cantidad × costo unitario). */
function repuestos_de_ingreso(int $idIngreso): array
{
    $stmt = db()->prepare(
        'SELECT id_repuesto, descripcion, cantidad, costo_unitario,
                cantidad * costo_unitario AS subtotal
           FROM repuesto
          WHERE id_ingreso = ?
          ORDER BY id_repuesto'
    );
    $stmt->execute([$idIngreso]);
    return $stmt->fetchAll();
}

/** Costo total de un ingreso: suma de los subtotales de sus repuestos. */
function costo_total_ingreso(int $idIngreso): string
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(cantidad * costo_unitario), 0) FROM repuesto WHERE id_ingreso = ?'
    );
    $stmt->execute([$idIngreso]);
    return (string) $stmt->fetchColumn();
}

/**
 * Técnicos que se pueden asignar: los activos, más el que ya está
 * asignado aunque se haya desactivado (para no perderlo del formulario).
 */
function tecnicos_para_asignar(?int $idTecnicoActual = null): array
{
    $stmt = db()->prepare(
        'SELECT id_tecnico, nombre, especialidad, activo
           FROM tecnico
          WHERE activo = 1 OR id_tecnico = ?
          ORDER BY nombre'
    );
    $stmt->execute([$idTecnicoActual ?? 0]);
    return $stmt->fetchAll();
}

// -------------------------------------------------------------
// Modificaciones
// -------------------------------------------------------------

/**
 * Ejecuta $accion dentro de una transacción, con la fila del ingreso
 * bloqueada, solo si el ingreso existe, sigue abierto y el usuario
 * tiene permiso. Así nadie agrega repuestos a un ingreso que se está
 * cerrando al mismo tiempo, ni a uno que le reasignaron a otro técnico.
 */
function en_ingreso_editable(int $idIngreso, array $usuario, callable $accion): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id_ingreso, id_tecnico, fecha_hora_egreso FROM ingreso WHERE id_ingreso = ? FOR UPDATE'
        );
        $stmt->execute([$idIngreso]);
        $ingreso = $stmt->fetch();

        if (!$ingreso) {
            $errores = ['El ingreso no existe.'];
        } elseif (!puede_gestionar_ingreso($usuario, $ingreso)) {
            $errores = ['Solo puedes modificar los ingresos que tienes asignados.'];
        } elseif ($ingreso['fecha_hora_egreso'] !== null) {
            $errores = ['El ingreso ya tiene egreso; no se puede modificar.'];
        } else {
            $errores = $accion($pdo, $ingreso);
        }

        $errores ? $pdo->rollBack() : $pdo->commit();
        return $errores;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Guarda diagnóstico, tipo de mantenimiento y (solo administrador) técnico encargado.
 * $datos: diagnostico, tipo_mantenimiento, id_tecnico ('' = sin asignar)
 */
function guardar_diagnostico(int $idIngreso, array $datos, array $usuario): array
{
    $errores = [];

    $diagnostico = trim((string) ($datos['diagnostico'] ?? ''));
    if ($diagnostico === '') {
        $errores[] = 'Escribe el diagnóstico.';
    } elseif (mb_strlen($diagnostico) > 2000) {
        $errores[] = 'El diagnóstico no puede superar 2000 caracteres.';
    }

    $tipo = (string) ($datos['tipo_mantenimiento'] ?? '');
    if (!isset(TIPOS_MANTENIMIENTO[$tipo])) {
        $errores[] = 'Selecciona el tipo de mantenimiento (preventivo o correctivo).';
    }

    $esAdmin    = $usuario['perfil'] === 'administrador';
    $idTecnico  = null;
    $textoTec   = (string) ($datos['id_tecnico'] ?? '');
    if ($esAdmin && $textoTec !== '') {
        $idTecnico = filter_var($textoTec, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idTecnico === false) {
            $errores[] = 'El técnico seleccionado no es válido.';
        }
    }

    if ($errores) {
        return $errores;
    }

    return en_ingreso_editable($idIngreso, $usuario, function (PDO $pdo, array $ingreso) use ($esAdmin, $idTecnico, $diagnostico, $tipo) {
        // El técnico no puede cambiar la asignación: se conserva la actual.
        $nuevoTecnico = $esAdmin ? $idTecnico : (int) $ingreso['id_tecnico'];

        // Si cambia el técnico, el nuevo debe existir y estar activo.
        if ($nuevoTecnico !== null && $nuevoTecnico !== (int) $ingreso['id_tecnico']) {
            $stmt = $pdo->prepare('SELECT activo FROM tecnico WHERE id_tecnico = ?');
            $stmt->execute([$nuevoTecnico]);
            if ((string) $stmt->fetchColumn() !== '1') {
                return ['El técnico seleccionado no existe o está inactivo.'];
            }
        }

        $pdo->prepare(
            'UPDATE ingreso SET diagnostico = ?, tipo_mantenimiento = ?, id_tecnico = ? WHERE id_ingreso = ?'
        )->execute([$diagnostico, $tipo, $nuevoTecnico, $ingreso['id_ingreso']]);
        return [];
    });
}

/**
 * Agrega un repuesto a un ingreso abierto.
 * $datos: descripcion, cantidad, costo_unitario (ej. '68000' o '68000.50')
 */
function agregar_repuesto(int $idIngreso, array $datos, array $usuario): array
{
    $errores = [];

    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    if ($descripcion === '') {
        $errores[] = 'Escribe la descripción del repuesto.';
    } elseif (mb_strlen($descripcion) > 150) {
        $errores[] = 'La descripción del repuesto no puede superar 150 caracteres.';
    }

    $cantidad = filter_var($datos['cantidad'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    if ($cantidad === false) {
        $errores[] = 'La cantidad debe ser un número entero mayor que 0.';
    }

    // DECIMAL(12,2): hasta 10 dígitos enteros y 2 decimales, sin negativos.
    $costo = trim((string) ($datos['costo_unitario'] ?? ''));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $costo)) {
        $errores[] = 'El costo unitario debe ser un valor en pesos mayor o igual a 0 (sin puntos de miles).';
    }

    if ($errores) {
        return $errores;
    }

    return en_ingreso_editable($idIngreso, $usuario, function (PDO $pdo, array $ingreso) use ($descripcion, $cantidad, $costo) {
        $pdo->prepare(
            'INSERT INTO repuesto (id_ingreso, descripcion, cantidad, costo_unitario) VALUES (?, ?, ?, ?)'
        )->execute([$ingreso['id_ingreso'], $descripcion, $cantidad, $costo]);
        return [];
    });
}

/** Quita un repuesto de un ingreso abierto. */
function quitar_repuesto(int $idIngreso, int $idRepuesto, array $usuario): array
{
    return en_ingreso_editable($idIngreso, $usuario, function (PDO $pdo, array $ingreso) use ($idRepuesto) {
        $stmt = $pdo->prepare('DELETE FROM repuesto WHERE id_repuesto = ? AND id_ingreso = ?');
        $stmt->execute([$idRepuesto, $ingreso['id_ingreso']]);
        return $stmt->rowCount() === 1 ? [] : ['El repuesto no existe en este ingreso.'];
    });
}
