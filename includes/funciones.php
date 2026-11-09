<?php
/**
 * Funciones de ayuda usadas en todas las páginas.
 */

require_once __DIR__ . '/../config/config.php';

/** Escapa un valor para imprimirlo en HTML. Úsala en TODO lo que venga de la BD o del usuario. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Construye una URL absoluta dentro del proyecto. Ej.: url('modules/vehiculos/') */
function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/** Redirige a una ruta del proyecto y termina la ejecución. */
function redirigir(string $ruta = ''): void
{
    header('Location: ' . url($ruta));
    exit;
}

/** Token CSRF de la sesión actual (se crea la primera vez). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto con el token CSRF. Ponlo dentro de cada <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verifica el token CSRF de un POST. Si no es válido, corta con error 400. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        exit('La solicitud no es válida o la sesión expiró. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}
