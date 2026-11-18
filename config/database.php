<?php
/**
 * Conexión a la base de datos con PDO.
 *
 * Uso:
 *   $stmt = db()->prepare('SELECT * FROM vehiculo WHERE id_vehiculo = ?');
 *   $stmt->execute([$id]);
 *   $vehiculo = $stmt->fetch();
 *
 * Usa SIEMPRE consultas preparadas con parámetros (? o :nombre);
 * nunca concatenes datos del usuario dentro del SQL.
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // El detalle va al log de PHP; al usuario no se le muestran datos de conexión.
            error_log('MediFlota - error de conexión: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo conectar con la base de datos. Verifica que MySQL esté iniciado en XAMPP.');
        }
    }

    return $pdo;
}
