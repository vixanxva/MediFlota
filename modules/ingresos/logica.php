<?php
/**
 * Lógica de ingresos y egresos (RF04, RF07, RF08).
 *
 * Las funciones registrar_ingreso() y registrar_egreso() validan los datos
 * y guardan el movimiento y el cambio de estado del vehículo en UNA
 * transacción: o se guarda todo, o no se guarda nada.
 *
 * Devuelven un arreglo de errores; si está vacío, la operación se guardó.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

/** Margen para aceptar horas "del futuro" por relojes ligeramente desfasados. */
const MARGEN_FUTURO_MINUTOS = 5;

/**
 * Une una fecha (AAAA-MM-DD) y una hora (HH:MM) del formulario.
 * Devuelve 'AAAA-MM-DD HH:MM:00' o null si no son válidas.
 */
function unir_fecha_hora(string $fecha, string $hora): ?string
{
    $dt = DateTime::createFromFormat('!Y-m-d H:i', trim($fecha) . ' ' . trim($hora));
    $errores = DateTime::getLastErrors();
    if ($dt === false || ($errores && ($errores['warning_count'] || $errores['error_count']))) {
        return null;   // fechas imposibles como 2026-02-30 también se rechazan
    }
    return $dt->format('Y-m-d H:i:00');
}

/** true si la fecha está más allá del margen permitido hacia el futuro. */
function es_fecha_futura(string $fechaHora): bool
{
    return strtotime($fechaHora) > time() + MARGEN_FUTURO_MINUTOS * 60;
}

/** Vehículos que pueden ingresar al taller: activos y que no estén ya en mantenimiento. */
function vehiculos_para_ingreso(): array
{
    return db()->query(
        "SELECT id_vehiculo, placa, tipo, marca, modelo, kilometraje, estado
           FROM vehiculo
          WHERE activo = 1 AND estado <> 'en_mantenimiento'
          ORDER BY placa"
    )->fetchAll();
}

/** Un ingreso con los datos de su vehículo y técnico, o null si no existe. */
function obtener_ingreso(int $idIngreso): ?array
{
    $stmt = db()->prepare(
        'SELECT i.*, v.placa, v.tipo, v.marca, v.modelo, v.estado AS estado_vehiculo,
                t.nombre AS nombre_tecnico
           FROM ingreso i
           JOIN vehiculo v ON v.id_vehiculo = i.id_vehiculo
           LEFT JOIN tecnico t ON t.id_tecnico = i.id_tecnico
          WHERE i.id_ingreso = ?'
    );
    $stmt->execute([$idIngreso]);
    return $stmt->fetch() ?: null;
}

/**
 * Lista de ingresos.
 * $estado: 'abiertos', 'cerrados' o 'todos'.
 * $idTecnico: si se indica, solo los asignados a ese técnico.
 */
function listar_ingresos(string $estado = 'todos', ?int $idTecnico = null): array
{
    $where  = [];
    $params = [];

    if ($estado === 'abiertos') {
        $where[] = 'i.fecha_hora_egreso IS NULL';
    } elseif ($estado === 'cerrados') {
        $where[] = 'i.fecha_hora_egreso IS NOT NULL';
    }
    if ($idTecnico !== null) {
        $where[]  = 'i.id_tecnico = ?';
        $params[] = $idTecnico;
    }

    $sql = 'SELECT i.id_ingreso, i.id_tecnico, i.fecha_hora_ingreso, i.kilometraje_ingreso,
                   i.falla_reportada, i.fecha_hora_egreso, v.placa, v.tipo, t.nombre AS nombre_tecnico
              FROM ingreso i
              JOIN vehiculo v ON v.id_vehiculo = i.id_vehiculo
              LEFT JOIN tecnico t ON t.id_tecnico = i.id_tecnico'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY (i.fecha_hora_egreso IS NULL) DESC, i.fecha_hora_ingreso DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * ¿El usuario puede registrar el egreso de este ingreso?
 * Administrador: cualquiera. Técnico: solo los que tiene asignados.
 */
function puede_registrar_egreso(array $usuario, array $ingreso): bool
{
    if ($usuario['perfil'] === 'administrador') {
        return true;
    }
    return $usuario['perfil'] === 'tecnico'
        && $ingreso['id_tecnico'] !== null
        && (int) $ingreso['id_tecnico'] === (int) $usuario['id_tecnico'];
}

/**
 * Registra el ingreso de un vehículo al taller.
 * El vehículo pasa a 'en_mantenimiento' y se actualiza su kilometraje.
 *
 * $datos: id_vehiculo, fecha, hora, kilometraje, falla_reportada
 * $idIngreso (salida): id del ingreso creado.
 */
function registrar_ingreso(array $datos, ?int &$idIngreso = null): array
{
    $errores = [];

    $idVehiculo = filter_var($datos['id_vehiculo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idVehiculo === false) {
        $errores[] = 'Selecciona el vehículo.';
    }

    $fechaHora = unir_fecha_hora((string) ($datos['fecha'] ?? ''), (string) ($datos['hora'] ?? ''));
    if ($fechaHora === null) {
        $errores[] = 'La fecha o la hora de ingreso no son válidas.';
    } elseif (es_fecha_futura($fechaHora)) {
        $errores[] = 'La fecha y hora de ingreso no pueden estar en el futuro.';
    }

    $km = filter_var($datos['kilometraje'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($km === false) {
        $errores[] = 'El kilometraje debe ser un número entero mayor o igual a 0.';
    }

    $falla = trim((string) ($datos['falla_reportada'] ?? ''));
    if ($falla === '') {
        $errores[] = 'Describe la falla reportada.';
    } elseif (mb_strlen($falla) > 2000) {
        $errores[] = 'La falla reportada no puede superar 2000 caracteres.';
    }

    if ($errores) {
        return $errores;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Bloquea la fila del vehículo: dos registros simultáneos no pueden
        // abrir dos ingresos para el mismo vehículo.
        $stmt = $pdo->prepare(
            'SELECT placa, kilometraje, estado, activo FROM vehiculo WHERE id_vehiculo = ? FOR UPDATE'
        );
        $stmt->execute([$idVehiculo]);
        $vehiculo = $stmt->fetch();

        if (!$vehiculo || !(int) $vehiculo['activo']) {
            $errores[] = 'El vehículo no existe o está desactivado.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ingreso WHERE id_vehiculo = ? AND fecha_hora_egreso IS NULL'
            );
            $stmt->execute([$idVehiculo]);
            if ($vehiculo['estado'] === 'en_mantenimiento' || (int) $stmt->fetchColumn() > 0) {
                $errores[] = "El vehículo {$vehiculo['placa']} ya está en el taller. Registra primero su egreso.";
            }

            if ($km < (int) $vehiculo['kilometraje']) {
                $errores[] = 'El kilometraje no puede ser menor que el actual del vehículo ('
                           . formato_numero($vehiculo['kilometraje']) . ' km).';
            }

            $stmt = $pdo->prepare('SELECT MAX(fecha_hora_egreso) FROM ingreso WHERE id_vehiculo = ?');
            $stmt->execute([$idVehiculo]);
            $ultimoEgreso = $stmt->fetchColumn();
            if ($ultimoEgreso && $fechaHora < $ultimoEgreso) {
                $errores[] = 'La fecha de ingreso no puede ser anterior a la última salida del vehículo ('
                           . formato_fecha_hora($ultimoEgreso) . ').';
            }
        }

        if ($errores) {
            $pdo->rollBack();
            return $errores;
        }

        $pdo->prepare(
            'INSERT INTO ingreso (id_vehiculo, fecha_hora_ingreso, kilometraje_ingreso, falla_reportada)
             VALUES (?, ?, ?, ?)'
        )->execute([$idVehiculo, $fechaHora, $km, $falla]);
        $idIngreso = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "UPDATE vehiculo SET estado = 'en_mantenimiento', kilometraje = ? WHERE id_vehiculo = ?"
        )->execute([$km, $idVehiculo]);

        $pdo->commit();
        return [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Registra el egreso (salida) de un ingreso abierto.
 * El vehículo pasa a 'disponible'.
 *
 * $datos: fecha, hora, trabajo_realizado
 */
function registrar_egreso(int $idIngreso, array $datos): array
{
    $errores = [];

    $fechaHora = unir_fecha_hora((string) ($datos['fecha'] ?? ''), (string) ($datos['hora'] ?? ''));
    if ($fechaHora === null) {
        $errores[] = 'La fecha o la hora de egreso no son válidas.';
    } elseif (es_fecha_futura($fechaHora)) {
        $errores[] = 'La fecha y hora de egreso no pueden estar en el futuro.';
    }

    $trabajo = trim((string) ($datos['trabajo_realizado'] ?? ''));
    if ($trabajo === '') {
        $errores[] = 'Describe el trabajo realizado.';
    } elseif (mb_strlen($trabajo) > 2000) {
        $errores[] = 'El trabajo realizado no puede superar 2000 caracteres.';
    }

    if ($errores) {
        return $errores;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id_vehiculo, fecha_hora_ingreso, fecha_hora_egreso FROM ingreso WHERE id_ingreso = ? FOR UPDATE'
        );
        $stmt->execute([$idIngreso]);
        $ingreso = $stmt->fetch();

        if (!$ingreso) {
            $errores[] = 'El ingreso no existe.';
        } elseif ($ingreso['fecha_hora_egreso'] !== null) {
            $errores[] = 'Este ingreso ya tiene registrado su egreso.';
        } elseif ($fechaHora < $ingreso['fecha_hora_ingreso']) {
            $errores[] = 'El egreso no puede ser anterior al ingreso ('
                       . formato_fecha_hora($ingreso['fecha_hora_ingreso']) . ').';
        }

        if ($errores) {
            $pdo->rollBack();
            return $errores;
        }

        $pdo->prepare(
            'UPDATE ingreso SET fecha_hora_egreso = ?, trabajo_realizado = ? WHERE id_ingreso = ?'
        )->execute([$fechaHora, $trabajo, $idIngreso]);

        $pdo->prepare(
            "UPDATE vehiculo SET estado = 'disponible' WHERE id_vehiculo = ?"
        )->execute([$ingreso['id_vehiculo']]);

        $pdo->commit();
        return [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
