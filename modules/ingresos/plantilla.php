<?php
/**
 * Plantilla PROVISIONAL del módulo de ingresos.
 *
 * Cuando el issue #5 agregue includes/header.php e includes/footer.php,
 * se reemplazan las llamadas a plantilla_inicio() / plantilla_fin()
 * por esos archivos y se borra este.
 */

function plantilla_inicio(string $titulo): void
{
    $u = usuario_actual();
    $mensaje = tomar_mensaje();
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> - MediFlota</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('inicio.php')) ?>">MediFlota</a>
        <div class="d-flex align-items-center gap-3">
            <a class="link-light" href="<?= e(url('modules/ingresos/')) ?>">Ingresos y egresos</a>
            <span class="text-white-50 small d-none d-sm-inline"><?= e($u['nombre']) ?></span>
            <a class="btn btn-outline-light btn-sm" href="<?= e(url('logout.php')) ?>">Salir</a>
        </div>
    </div>
</nav>
<main class="container pb-5">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo']) ?>"><?= e($mensaje['texto']) ?></div>
    <?php endif; ?>
    <?php
}

function plantilla_fin(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

/** Lista de errores de validación en un alert de Bootstrap. */
function mostrar_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errores as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div>';
}
