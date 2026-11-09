<?php
/**
 * Inicio de sesión (RF01).
 */

require_once __DIR__ . '/includes/sesion.php';

// Si ya hay sesión, ir directo al inicio.
if (usuario_actual() !== null) {
    redirigir('inicio.php');
}

$error   = null;
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave   = (string) ($_POST['clave'] ?? '');

    if ($usuario === '' || $clave === '') {
        $error = 'Escribe tu usuario y tu contraseña.';
    } elseif (iniciar_sesion($usuario, $clave)) {
        redirigir('inicio.php');
    } else {
        // Mensaje genérico: no revela si el usuario existe.
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$mensaje = tomar_mensaje();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - MediFlota</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<main class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="card shadow-sm w-100" style="max-width: 380px;">
        <div class="card-body p-4">
            <h1 class="h3 text-center mb-1">MediFlota</h1>
            <p class="text-center text-muted small mb-4">Taller de vehículos · Vida Sinú</p>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?= e($mensaje['tipo']) ?> py-2"><?= e($mensaje['texto']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('index.php')) ?>" novalidate>
                <?= csrf_campo() ?>
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           value="<?= e($usuario) ?>" maxlength="50" autocomplete="username" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="clave" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="clave" name="clave"
                           autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
