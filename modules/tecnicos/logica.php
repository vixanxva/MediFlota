<?php
/**
 * Lógica de técnicos (RF03): listar, registrar, editar, desactivar y activar.
 *
 * Reglas:
 * - El documento no se repite. Se guarda sin puntos, espacios ni guiones.
 * - No se borra: se desactiva (activo = 0) para conservar el historial.
 *   Un técnico inactivo no puede iniciar sesión ni recibir ingresos nuevos.
 * - No se puede desactivar a quien tiene ingresos abiertos asignados:
 *   primero hay que reasignarlos o registrar su egreso.
 *
 * Las funciones que modifican devuelven un arreglo de errores;
 * si está vacío, el cambio se guardó.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

// -------------------------------------------------------------
// Consultas
// -------------------------------------------------------------

/**
 * Técnicos para el listado, con su usuario (si tiene) y cuántos
 * ingresos abiertos tiene asignados.
 * $busqueda: texto dentro del nombre o del documento ('' = todos).
 * $filtro:   'activos', 'inactivos' o 'todos'.
 */
function listar_tecnicos(string $busqueda = '', string $filtro = 'activos'): array
{
    $sql = 'SELECT t.id_tecnico, t.nombre, t.documento, t.telefono, t.especialidad, t.activo,
                   u.usuario,
                   (SELECT COUNT(*) FROM ingreso i
                     WHERE i.id_tecnico = t.id_tecnico AND i.fecha_hora_egreso IS NULL) AS ingresos_abiertos
              FROM tecnico t
              LEFT JOIN usuario u ON u.id_tecnico = t.id_tecnico
             WHERE 1 = 1';
    $parametros = [];

    $busqueda = trim($busqueda);
    if ($busqueda !== '') {
        // Se escapan los comodines del LIKE para buscar el texto tal cual.
        $patron = '%' . addcslashes($busqueda, '\\%_') . '%';
        $sql .= ' AND (t.nombre LIKE ? OR t.documento LIKE ?)';
        $parametros[] = $patron;
        $parametros[] = '%' . addcslashes(normalizar_documento($busqueda), '\\%_') . '%';
    }
    if ($filtro === 'activos') {
        $sql .= ' AND t.activo = 1';
    } elseif ($filtro === 'inactivos') {
        $sql .= ' AND t.activo = 0';
    }
    $sql .= ' ORDER BY t.nombre';

    $stmt = db()->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

/** Un técnico por su id, o null si no existe. */
function obtener_tecnico(int $idTecnico): ?array
{
    $stmt = db()->prepare('SELECT * FROM tecnico WHERE id_tecnico = ?');
    $stmt->execute([$idTecnico]);
    return $stmt->fetch() ?: null;
}

/** Especialidades ya registradas, para sugerirlas en el formulario. */
function especialidades_registradas(): array
{
    return db()->query('SELECT DISTINCT especialidad FROM tecnico ORDER BY especialidad')->fetchAll(PDO::FETCH_COLUMN);
}

// -------------------------------------------------------------
// Validación
// -------------------------------------------------------------

/** '1.067.894.512' -> '1067894512'; 'pe-12 345' -> 'PE12345' */
function normalizar_documento(string $documento): string
{
    return strtoupper(preg_replace('/[\s.\-]+/', '', $documento));
}

/**
 * Valida los datos del formulario.
 * Devuelve [errores, valores listos para guardar].
 */
function validar_tecnico(array $datos): array
{
    $errores = [];
    $v = [];

    $v['nombre'] = preg_replace('/\s+/u', ' ', trim((string) ($datos['nombre'] ?? '')));
    if ($v['nombre'] === '') {
        $errores[] = 'Escribe el nombre completo.';
    } elseif (mb_strlen($v['nombre']) > 100) {
        $errores[] = 'El nombre no puede superar 100 caracteres.';
    } elseif (!preg_match("/^[\\p{L}][\\p{L} .'\\-]*$/u", $v['nombre'])) {
        $errores[] = 'El nombre solo puede tener letras, espacios, puntos, apóstrofos y guiones.';
    }

    // Cédula (solo números) o documentos de extranjeros como el pasaporte (letras y números).
    $v['documento'] = normalizar_documento((string) ($datos['documento'] ?? ''));
    if ($v['documento'] === '') {
        $errores[] = 'Escribe el número de documento.';
    } elseif (!preg_match('/^[A-Z0-9]{5,20}$/', $v['documento'])) {
        $errores[] = 'El documento debe tener entre 5 y 20 letras o números (puedes escribirlo con o sin puntos).';
    }

    // Opcional. Se guarda tal como se escribe, pero debe parecer un teléfono.
    $v['telefono'] = trim((string) ($datos['telefono'] ?? ''));
    if ($v['telefono'] === '') {
        $v['telefono'] = null;
    } else {
        $digitos = strlen(preg_replace('/\D/', '', $v['telefono']));
        if (mb_strlen($v['telefono']) > 20 || !preg_match('/^\+?[0-9 ()\-]+$/', $v['telefono']) || $digitos < 7 || $digitos > 15) {
            $errores[] = 'El teléfono debe tener entre 7 y 15 números (puede llevar +, espacios, guiones o paréntesis).';
        }
    }

    $v['especialidad'] = preg_replace('/\s+/u', ' ', trim((string) ($datos['especialidad'] ?? '')));
    if ($v['especialidad'] === '') {
        $errores[] = 'Escribe la especialidad.';
    } elseif (mb_strlen($v['especialidad']) > 60) {
        $errores[] = 'La especialidad no puede superar 60 caracteres.';
    }

    return [$errores, $v];
}

/** true si el error de PDO es una llave única repetida (el documento). */
function es_documento_repetido(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

function mensaje_documento_repetido(string $documento): string
{
    return "Ya existe un técnico con el documento $documento. Si está desactivado, búscalo en \"Inactivos\" y actívalo.";
}

// -------------------------------------------------------------
// Modificaciones
// -------------------------------------------------------------

/** Registra un técnico nuevo (activo). Si se guarda, deja su id en $idTecnico. */
function crear_tecnico(array $datos, ?int &$idTecnico = null): array
{
    [$errores, $v] = validar_tecnico($datos);
    if ($errores) {
        return $errores;
    }

    try {
        db()->prepare('INSERT INTO tecnico (nombre, documento, telefono, especialidad) VALUES (?, ?, ?, ?)')
            ->execute([$v['nombre'], $v['documento'], $v['telefono'], $v['especialidad']]);
    } catch (PDOException $e) {
        if (es_documento_repetido($e)) {
            return [mensaje_documento_repetido($v['documento'])];
        }
        throw $e;
    }
    $idTecnico = (int) db()->lastInsertId();
    return [];
}

/** Actualiza los datos de un técnico. */
function actualizar_tecnico(int $idTecnico, array $datos): array
{
    [$errores, $v] = validar_tecnico($datos);
    if ($errores) {
        return $errores;
    }

    try {
        $stmt = db()->prepare(
            'UPDATE tecnico SET nombre = ?, documento = ?, telefono = ?, especialidad = ? WHERE id_tecnico = ?'
        );
        $stmt->execute([$v['nombre'], $v['documento'], $v['telefono'], $v['especialidad'], $idTecnico]);
    } catch (PDOException $e) {
        if (es_documento_repetido($e)) {
            return [mensaje_documento_repetido($v['documento'])];
        }
        throw $e;
    }
    // rowCount() es 0 también si no cambió nada, así que se confirma que exista.
    return $stmt->rowCount() > 0 || obtener_tecnico($idTecnico) ? [] : ['El técnico no existe.'];
}

/**
 * Activa ($activo = true) o desactiva un técnico.
 * No se desactiva si tiene ingresos abiertos asignados.
 */
function cambiar_activo_tecnico(int $idTecnico, bool $activo): array
{
    $pdo = db();
    // READ COMMITTED: el conteo de ingresos abiertos ve lo último confirmado
    // (incluida una asignación que terminó mientras se esperaba el bloqueo).
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    try {
        // Bloquea al técnico: guardar_diagnostico() también lo bloquea al asignarlo,
        // así no se le asigna un ingreso mientras se está desactivando. Solo se
        // bloquea esta fila (no los ingresos) para no cruzarse con quien ya tiene
        // bloqueado un ingreso y espera al técnico.
        $stmt = $pdo->prepare('SELECT nombre FROM tecnico WHERE id_tecnico = ? FOR UPDATE');
        $stmt->execute([$idTecnico]);
        $nombre = $stmt->fetchColumn();

        $errores = [];
        if ($nombre === false) {
            $errores[] = 'El técnico no existe.';
        } elseif (!$activo) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ingreso WHERE id_tecnico = ? AND fecha_hora_egreso IS NULL'
            );
            $stmt->execute([$idTecnico]);
            $abiertos = (int) $stmt->fetchColumn();
            if ($abiertos > 0) {
                $errores[] = "$nombre tiene " . ($abiertos === 1 ? '1 ingreso abierto asignado' : "$abiertos ingresos abiertos asignados")
                           . '. Reasígnalos a otro técnico (o registra su egreso) antes de desactivarlo.';
            }
        }

        if (!$errores) {
            $pdo->prepare('UPDATE tecnico SET activo = ? WHERE id_tecnico = ?')->execute([$activo ? 1 : 0, $idTecnico]);
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
