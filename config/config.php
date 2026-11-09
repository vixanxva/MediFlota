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
// Si no se define, se calcula comparando la URL del script que se está
// ejecutando con su ubicación dentro del proyecto. Funciona aunque el
// proyecto esté enlazado (junction/symlink) dentro de htdocs.
if (!defined('BASE_URL')) {
    $raiz    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $archivo = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $urlScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = '';
    if ($raiz !== '' && stripos($archivo, $raiz . '/') === 0) {
        $relativa = substr($archivo, strlen($raiz));          // ej. /modules/vehiculos/index.php
        if (strcasecmp(substr($urlScript, -strlen($relativa)), $relativa) === 0) {
            $base = substr($urlScript, 0, -strlen($relativa)); // ej. /MediFlota
        }
    }
    define('BASE_URL', rtrim($base, '/'));
    unset($raiz, $archivo, $urlScript, $base, $relativa);
}

// Zona horaria de Colombia
date_default_timezone_set('America/Bogota');
