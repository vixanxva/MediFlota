<?php
/**
 * Sesión y control de acceso por perfil.
 *
 * Al inicio de cada página protegida:
 *
 *   require_once __DIR__ . '/../../includes/sesion.php';
 *   requiere_login();                    // cualquier usuario con sesión
 *   requiere_perfil('administrador');    // solo administradores
 *   requiere_perfil('administrador', 'tecnico');  // varios perfiles
 *
 * Datos del usuario conectado: usuario_actual()['usuario'], ['perfil'],
 * ['id_tecnico'], ['nombre']. Atajo: es_administrador().
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('MEDIFLOTA');
    session_set_cookie_params([
        'lifetime' => 0,                  // hasta cerrar el navegador
        'path'     => BASE_URL . '/',
        'httponly' => true,               // JavaScript no puede leer la cookie
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/**
 * Valida usuario y contraseña. Si son correctos, abre la sesión y devuelve true.
 * Solo entran usuarios activos (y, si son técnicos, con el técnico activo).
 */
function iniciar_sesion(string $usuario, string $clave): bool
{
    $stmt = db()->prepare(
        'SELECT u.id_usuario, u.usuario, u.password_hash, u.perfil, u.id_tecnico, t.nombre AS nombre_tecnico
           FROM usuario u
           LEFT JOIN tecnico t ON t.id_tecnico = u.id_tecnico
          WHERE u.usuario = ?
            AND u.activo = 1
            AND (u.id_tecnico IS NULL OR t.activo = 1)'
    );
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila || !password_verify($clave, $fila['password_hash'])) {
        return false;
    }

    // Si PHP cambia su algoritmo por defecto, se actualiza el hash guardado.
    if (password_needs_rehash($fila['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?')
            ->execute([password_hash($clave, PASSWORD_DEFAULT), $fila['id_usuario']]);
    }

    // Nuevo id de sesión para evitar fijación de sesión.
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id_usuario' => (int) $fila['id_usuario'],
        'usuario'    => $fila['usuario'],
        'perfil'     => $fila['perfil'],
        'id_tecnico' => $fila['id_tecnico'] !== null ? (int) $fila['id_tecnico'] : null,
        'nombre'     => $fila['nombre_tecnico'] ?? $fila['usuario'],
    ];
    unset($_SESSION['csrf_token']);

    return true;
}

/** Cierra la sesión actual y borra la cookie. */
function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
            'secure'   => $p['secure'],
        ]);
    }
    session_destroy();
}

/** Usuario conectado, o null si no hay sesión. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function es_administrador(): bool
{
    return (usuario_actual()['perfil'] ?? null) === 'administrador';
}

/**
 * Exige una sesión válida. Si no hay, envía al login.
 * En cada petición confirma que el usuario siga activo, para que
 * desactivar a alguien lo saque del sistema de inmediato.
 */
function requiere_login(): void
{
    $u = usuario_actual();
    if ($u === null) {
        guardar_mensaje('Inicia sesión para continuar.', 'warning');
        redirigir('index.php');
    }

    $stmt = db()->prepare(
        'SELECT u.perfil
           FROM usuario u
           LEFT JOIN tecnico t ON t.id_tecnico = u.id_tecnico
          WHERE u.id_usuario = ?
            AND u.activo = 1
            AND (u.id_tecnico IS NULL OR t.activo = 1)'
    );
    $stmt->execute([$u['id_usuario']]);
    $perfil = $stmt->fetchColumn();

    if ($perfil === false) {
        cerrar_sesion();
        session_start();
        guardar_mensaje('Tu usuario fue desactivado. Comunícate con el administrador.', 'danger');
        redirigir('index.php');
    }

    // Si el administrador cambió el perfil, se aplica sin volver a iniciar sesión.
    $_SESSION['usuario']['perfil'] = $perfil;
}

/** Exige sesión y que el usuario tenga alguno de los perfiles indicados. Si no, error 403. */
function requiere_perfil(string ...$perfiles): void
{
    requiere_login();

    if (!in_array(usuario_actual()['perfil'], $perfiles, true)) {
        http_response_code(403);
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Acceso denegado - MediFlota</title></head>'
           . '<body style="font-family:sans-serif;text-align:center;padding:3rem 1rem">'
           . '<h1>Acceso denegado</h1>'
           . '<p>Tu perfil no tiene permiso para ver esta página.</p>'
           . '<p><a href="' . e(url('inicio.php')) . '">Volver al inicio</a></p>'
           . '</body></html>';
        exit;
    }
}

/** Guarda un mensaje para mostrarlo en la siguiente página (tipo: success, danger, warning, info). */
function guardar_mensaje(string $texto, string $tipo = 'info'): void
{
    $_SESSION['mensaje'] = ['texto' => $texto, 'tipo' => $tipo];
}

/** Devuelve el mensaje guardado (y lo borra), o null si no hay. */
function tomar_mensaje(): ?array
{
    $m = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return $m;
}
