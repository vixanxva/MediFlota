<?php
/**
 * Página de inicio tras el login: accesos a los módulos del perfil.
 *
 * Cuando exista el panel de disponibilidad (issue #8, modules/panel/),
 * esta página envía directo allá, porque el panel es la página principal.
 */

require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/menu.php';
requiere_login();

if (is_file(__DIR__ . '/modules/panel/index.php')) {
    // Si hay un mensaje guardado, se muestra en el panel.
    redirigir('modules/panel/');
}

$u = usuario_actual();
$titulo = 'Inicio';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Hola, <?= e($u['nombre']) ?></h1>
    <p class="text-muted mb-0">Perfil: <?= e(nombre_perfil($u['perfil'])) ?>. Elige un módulo para empezar.</p>
</div>

<div class="row g-3">
    <?php foreach (menu_del_usuario() as $opcion): ?>
        <div class="col-sm-6 col-lg-4">
            <?php if ($opcion['disponible']): ?>
                <a href="<?= e(url($opcion['ruta'])) ?>"
                   class="tarjeta-modulo card h-100 shadow-sm text-decoration-none text-reset">
                    <div class="card-body">
                        <h2 class="h5 text-primary"><?= e($opcion['titulo']) ?></h2>
                        <p class="card-text text-muted small mb-0"><?= e($opcion['descripcion']) ?></p>
                    </div>
                </a>
            <?php else: ?>
                <div class="tarjeta-modulo card h-100 shadow-sm bg-body-tertiary">
                    <div class="card-body">
                        <h2 class="h5 text-secondary">
                            <?= e($opcion['titulo']) ?>
                            <span class="badge text-bg-light border fw-normal align-middle">En construcción</span>
                        </h2>
                        <p class="card-text text-muted small mb-0"><?= e($opcion['descripcion']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
