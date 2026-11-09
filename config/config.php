<?php
/**
 * Configuración general de MediFlota.
 *
 * Los valores por defecto sirven para XAMPP recién instalado.
 * Para cambiarlos en un equipo (por ejemplo, la contraseña de MySQL
 * en el taller), copia config.local.example.php como config.local.php
 * y define ahí solo lo que cambie. config.local.php no se sube a git.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Base de datos
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_NAME') || define('DB_NAME', 'mediflota');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Ruta del proyecto en la URL, sin "/" final.
// Ej.: '/MediFlota' para http://localhost/MediFlota/
// Si no se define, se calcula a partir de la carpeta del proyecto.
if (!defined('BASE_URL')) {
    $raiz    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base    = ($docRoot !== '' && stripos($raiz, $docRoot) === 0)
        ? substr($raiz, strlen($docRoot))
        : '';
    define('BASE_URL', rtrim($base, '/'));
    unset($raiz, $docRoot, $base);
}

// Zona horaria de Colombia
date_default_timezone_set('America/Bogota');
