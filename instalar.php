<?php
/**
 * Instalación: crea el primer administrador.
 *
 * Solo funciona mientras la tabla usuario esté vacía (base importada con
 * schema.sql, sin seed.sql). En cuanto exista un usuario, esta página ya
 * no permite crear nada: los demás usuarios se crean en el módulo Usuarios.
 */

require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/modules/usuarios/logica.php';

$instalado = hay_usuarios();
$errores   = [];
$usuario   = '';

if (!$instalado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $usuario = is_string($_POST['usuario'] ?? null) ? $_POST['usuario'] : '';
    $errores = crear_administrador_inicial(
        $usuario,
        is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '',
        is_string($_POST['confirmacion'] ?? null) ? $_POST['confirmacion'] : ''
    );
    if (!$errores) {
        guardar_mensaje('Administrador creado. Ya puedes iniciar sesión con él.', 'success');
        redirigir('index.php');
    }
    $instalado = hay_usuarios();   // por si otra persona lo creó al mismo tiempo
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalación - MediFlota</title>
    <link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css') . '?v=' . filemtime(__DIR__ . '/assets/css/estilos.css')) ?>">
</head>
<body class="pagina-login">
<main class="container d-flex flex-column align-items-center justify-content-center min-vh-100 py-4">
    <div class="card shadow w-100" style="max-width: 420px;">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <img src="<?= e(url('assets/img/logo.svg')) ?>" class="logo mb-3" alt="">
                <h1 class="h4 mb-1">Instalación de MediFlota</h1>
                <p class="text-muted small mb-0">Crea el primer administrador</p>
            </div>

            <?php if ($instalado): ?>
                <div class="alert alert-info">El sistema ya tiene usuarios. Los usuarios nuevos los crea un administrador desde el módulo Usuarios.</div>
                <a href="<?= e(url('index.php')) ?>" class="btn btn-primary w-100">Ir a iniciar sesión</a>
            <?php else: ?>
                <?php mostrar_errores($errores); ?>
                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="<?= e($usuario) ?>"
                               maxlength="50" autocomplete="username" required autofocus>
                        <div class="form-text">Letras sin tildes, números, punto o guion. Ej. jefe.taller</div>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="clave" name="clave"
                               minlength="<?= CLAVE_MINIMO ?>" maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
                        <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirmacion" class="form-label">Repite la contraseña</label>
                        <input type="password" class="form-control" id="confirmacion" name="confirmacion"
                               maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Crear administrador</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
