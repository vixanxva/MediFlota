<?php
/**
 * Encabezado común: <head>, barra de navegación según el perfil y mensajes.
 *
 * Uso, DESPUÉS de requiere_login() / requiere_perfil() y de procesar el formulario:
 *
 *   $titulo = 'Vehículos';
 *   require __DIR__ . '/../../includes/header.php';
 *   ... contenido de la página ...
 *   require __DIR__ . '/../../includes/footer.php';
 */

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/menu.php';

// Por si la página olvidó exigir sesión: el menú necesita un usuario.
if (usuario_actual() === null) {
    requiere_login();
}

$encabezadoUsuario = usuario_actual();
$encabezadoMensaje = tomar_mensaje();
$encabezadoCss     = 'assets/css/estilos.css';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? '') !== '' ? $titulo . ' - MediFlota' : 'MediFlota') ?></title>
    <link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(url($encabezadoCss) . '?v=' . filemtime(__DIR__ . '/../' . $encabezadoCss)) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('inicio.php')) ?>">
            <img src="<?= e(url('assets/img/logo.svg')) ?>" width="30" height="30" alt="">
            MediFlota
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                aria-controls="menu-principal" aria-expanded="false" aria-label="Mostrar u ocultar el menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu-principal">
            <ul class="navbar-nav me-auto">
                <?php foreach (menu_del_usuario() as $opcion): ?>
                    <li class="nav-item">
                        <?php if ($opcion['disponible']): ?>
                            <a class="nav-link<?= $opcion['actual'] ? ' active' : '' ?>"
                               <?= $opcion['actual'] ? 'aria-current="page"' : '' ?>
                               href="<?= e(url($opcion['ruta'])) ?>"><?= e($opcion['titulo']) ?></a>
                        <?php else: ?>
                            <span class="nav-link disabled" title="En construcción"><?= e($opcion['titulo']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 py-2 py-lg-0">
                <span class="navbar-text lh-sm">
                    <span class="text-white"><?= e($encabezadoUsuario['nombre']) ?></span><br>
                    <small><?= e(nombre_perfil($encabezadoUsuario['perfil'])) ?> ·
                        <a href="<?= e(url('cambiar_clave.php')) ?>" class="link-light">Cambiar contraseña</a></small>
                </span>
                <a class="btn btn-outline-light btn-sm" href="<?= e(url('logout.php')) ?>">Cerrar sesión</a>
            </div>
        </div>
    </div>
</nav>
<main class="container pb-5 flex-grow-1">
    <?php if ($encabezadoMensaje): ?>
        <div class="alert alert-<?= e($encabezadoMensaje['tipo']) ?>" role="alert"><?= e($encabezadoMensaje['texto']) ?></div>
    <?php endif; ?>
