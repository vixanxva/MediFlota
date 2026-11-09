<?php
/**
 * Página de inicio tras el login.
 *
 * Provisional: la plantilla con menú por perfil se hace en el issue #5,
 * que reemplazará este HTML por header.php / footer.php.
 */

require_once __DIR__ . '/includes/sesion.php';
requiere_login();

$u = usuario_actual();
$mensaje = tomar_mensaje();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio - MediFlota</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 720px;">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo']) ?>"><?= e($mensaje['texto']) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h3 mb-0">Bienvenido, <?= e($u['nombre']) ?></h1>
        <a href="<?= e(url('logout.php')) ?>" class="btn btn-outline-secondary btn-sm">Cerrar sesión</a>
    </div>

    <p>
        Perfil:
        <span class="badge <?= es_administrador() ? 'bg-primary' : 'bg-success' ?>">
            <?= es_administrador() ? 'Administrador' : 'Técnico' ?>
        </span>
    </p>
    <p class="text-muted">Los módulos del sistema aparecerán aquí a medida que se integren.</p>
</main>
</body>
</html>
