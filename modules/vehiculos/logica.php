<?php
/**
 * Lógica de vehículos (RF02): listar, registrar, editar y desactivar.
 *
 * Reglas:
 * - La placa no se repite. Se guarda en mayúsculas y sin espacios ni guiones.
 * - El estado 'en_mantenimiento' solo lo ponen y quitan los ingresos y
 *   egresos (módulo de ingresos). Aquí solo se elige entre 'disponible'
 *   y 'fuera_servicio', y no se puede cambiar mientras está en el taller.
 * - El kilometraje no puede quedar por debajo del registrado en sus ingresos.
 * - No se borra: se desactiva (activo = 0) para conservar el historial.
 *   No se puede desactivar mientras está en el taller.
 *
 * Las funciones que modifican devuelven un arreglo de errores;
 * si está vacío, el cambio se guardó.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

/** Estados que el administrador puede elegir a mano. */
const ESTADOS_ELEGIBLES = ['disponible', 'fuera_servicio'];

const KILOMETRAJE_MAXIMO = 9999999;

// -------------------------------------------------------------
// Consultas
// -------------------------------------------------------------

/**
 * Vehículos para el listado.
 * $placa:  texto a buscar dentro de la placa ('' = todos).
 * $filtro: 'activos', 'inactivos' o 'todos'.
 */
function listar_vehiculos(string $placa = '', string $filtro = 'activos'): array
{
    $sql = 'SELECT id_vehiculo, placa, tipo, marca, modelo, kilometraje, vencimiento_rtm, estado, activo
              FROM vehiculo
             WHERE 1 = 1';
    $parametros = [];

    // Las placas solo tienen letras y números: se quita todo lo demás
    // (así % o _ no actúan como comodines del LIKE).
    $placa = preg_replace('/[^A-Z0-9]/', '', normalizar_placa($placa));
    if ($placa !== '') {
        $sql .= ' AND placa LIKE ?';
        $parametros[] = '%' . $placa . '%';
    }
    if ($filtro === 'activos') {
        $sql .= ' AND activo = 1';
    } elseif ($filtro === 'inactivos') {
        $sql .= ' AND activo = 0';
    }
    $sql .= ' ORDER BY placa';

    $stmt = db()->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

/** Un vehículo por su id, o null si no existe. */
function obtener_vehiculo(int $idVehiculo): ?array
{
    $stmt = db()->prepare('SELECT * FROM vehiculo WHERE id_vehiculo = ?');
    $stmt->execute([$idVehiculo]);
    return $stmt->fetch() ?: null;
}

/** Mayor kilometraje registrado en los ingresos del vehículo (0 si no tiene). */
function kilometraje_minimo(int $idVehiculo): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(kilometraje_ingreso), 0) FROM ingreso WHERE id_vehiculo = ?');
    $stmt->execute([$idVehiculo]);
    return (int) $stmt->fetchColumn();
}

// -------------------------------------------------------------
// Validación
// -------------------------------------------------------------

/** 'abc-123 ' -> 'ABC123' */
function normalizar_placa(string $placa): string
{
    return strtoupper(preg_replace('/[\s\-]+/', '', $placa));
}

/**
 * Valida los datos del formulario.
 * Devuelve [errores, valores listos para guardar].
 */
function validar_vehiculo(array $datos): array
{
    $errores = [];
    $v = [];

    // Placas colombianas: ABC123 (carros) o ABC12D (motos).
    $v['placa'] = normalizar_placa((string) ($datos['placa'] ?? ''));
    if ($v['placa'] === '') {
        $errores[] = 'Escribe la placa.';
    } elseif (!preg_match('/^[A-Z]{3}[0-9]{2}[0-9A-Z]$/', $v['placa'])) {
        $errores[] = 'La placa debe tener 3 letras y 3 números (ej. ABC123), o 3 letras, 2 números y una letra (ej. ABC12D).';
    }

    $v['tipo'] = (string) ($datos['tipo'] ?? '');
    if (!isset(TIPOS_VEHICULO[$v['tipo']])) {
        $errores[] = 'Selecciona el tipo de vehículo.';
    }

    foreach (['marca' => 'la marca', 'modelo' => 'el modelo'] as $campo => $nombre) {
        $v[$campo] = trim((string) ($datos[$campo] ?? ''));
        if ($v[$campo] === '') {
            $errores[] = "Escribe $nombre.";
        } elseif (mb_strlen($v[$campo]) > 50) {
            $errores[] = ucfirst($nombre) . ' no puede superar 50 caracteres.';
        }
    }

    $km = filter_var(trim((string) ($datos['kilometraje'] ?? '')), FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => KILOMETRAJE_MAXIMO]]);
    if ($km === false) {
        $errores[] = 'El kilometraje debe ser un número entero entre 0 y ' . formato_numero(KILOMETRAJE_MAXIMO) . ' (sin puntos).';
    }
    $v['kilometraje'] = $km;

    $fecha = trim((string) ($datos['vencimiento_rtm'] ?? ''));
    $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
    $avisos = DateTime::getLastErrors();
    if ($dt === false || ($avisos && ($avisos['warning_count'] || $avisos['error_count']))) {
        $errores[] = 'Escribe una fecha válida de vencimiento de la revisión técnico-mecánica.';
    } elseif ($fecha < '2000-01-01' || $fecha > '2099-12-31') {
        $errores[] = 'La fecha de vencimiento de la revisión técnico-mecánica no es razonable.';
    }
    $v['vencimiento_rtm'] = $fecha;

    $v['estado'] = (string) ($datos['estado'] ?? '');
    if (!in_array($v['estado'], ESTADOS_ELEGIBLES, true)) {
        $errores[] = 'Selecciona si el vehículo está disponible o fuera de servicio.';
    }

    return [$errores, $v];
}

/** true si el error de PDO es una llave única repetida (la placa). */
function es_placa_repetida(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

function mensaje_placa_repetida(string $placa): string
{
    return "Ya existe un vehículo con la placa $placa. Si está desactivado, búscalo en \"Inactivos\" y actívalo.";
}

// -------------------------------------------------------------
// Modificaciones
// -------------------------------------------------------------

/** Registra un vehículo nuevo. Si se guarda, deja su id en $idVehiculo. */
function crear_vehiculo(array $datos, ?int &$idVehiculo = null): array
{
    [$errores, $v] = validar_vehiculo($datos);
    if ($errores) {
        return $errores;
    }

    try {
        db()->prepare(
            'INSERT INTO vehiculo (placa, tipo, marca, modelo, kilometraje, vencimiento_rtm, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$v['placa'], $v['tipo'], $v['marca'], $v['modelo'], $v['kilometraje'], $v['vencimiento_rtm'], $v['estado']]);
    } catch (PDOException $e) {
        if (es_placa_repetida($e)) {
            return [mensaje_placa_repetida($v['placa'])];
        }
        throw $e;
    }
    $idVehiculo = (int) db()->lastInsertId();
    return [];
}

/**
 * Actualiza un vehículo. Si está en el taller, su estado no cambia
 * (lo cambia el egreso), aunque el formulario envíe otro.
 */
function actualizar_vehiculo(int $idVehiculo, array $datos): array
{
    // Si está en el taller el formulario no envía estado; se valida con uno cualquiera.
    $datos['estado'] = $datos['estado'] ?? 'disponible';
    [$errores, $v] = validar_vehiculo($datos);
    if ($errores) {
        return $errores;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Bloquea la fila: si al mismo tiempo se registra un ingreso, uno espera al otro.
        $stmt = $pdo->prepare('SELECT estado FROM vehiculo WHERE id_vehiculo = ? FOR UPDATE');
        $stmt->execute([$idVehiculo]);
        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual === false) {
            $errores[] = 'El vehículo no existe.';
        } else {
            $minimo = kilometraje_minimo($idVehiculo);
            if ($v['kilometraje'] < $minimo) {
                $errores[] = 'El kilometraje no puede ser menor que el registrado en su último ingreso ('
                           . formato_numero($minimo) . ' km).';
            }
            if ($estadoActual === 'en_mantenimiento') {
                $v['estado'] = 'en_mantenimiento';
            }
        }

        if ($errores) {
            $pdo->rollBack();
            return $errores;
        }

        $pdo->prepare(
            'UPDATE vehiculo
                SET placa = ?, tipo = ?, marca = ?, modelo = ?, kilometraje = ?, vencimiento_rtm = ?, estado = ?
              WHERE id_vehiculo = ?'
        )->execute([$v['placa'], $v['tipo'], $v['marca'], $v['modelo'], $v['kilometraje'], $v['vencimiento_rtm'], $v['estado'], $idVehiculo]);

        $pdo->commit();
        return [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && es_placa_repetida($e)) {
            return [mensaje_placa_repetida($v['placa'])];
        }
        throw $e;
    }
}

/** Activa ($activo = true) o desactiva un vehículo. No se desactiva si está en el taller. */
function cambiar_activo_vehiculo(int $idVehiculo, bool $activo): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT placa, estado FROM vehiculo WHERE id_vehiculo = ? FOR UPDATE');
        $stmt->execute([$idVehiculo]);
        $vehiculo = $stmt->fetch();

        if (!$vehiculo) {
            $errores = ['El vehículo no existe.'];
        } elseif (!$activo && $vehiculo['estado'] === 'en_mantenimiento') {
            $errores = ["El vehículo {$vehiculo['placa']} está en el taller. Registra su egreso antes de desactivarlo."];
        } else {
            $pdo->prepare('UPDATE vehiculo SET activo = ? WHERE id_vehiculo = ?')
                ->execute([$activo ? 1 : 0, $idVehiculo]);
            $errores = [];
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
