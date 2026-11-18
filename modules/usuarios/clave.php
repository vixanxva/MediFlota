<?php
/**
 * Restablecer la contraseña de otro usuario (por ejemplo, si la olvidó).
 * Solo administrador. Para la propia se usa cambiar_clave.php.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$id  = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$usr = $id ? obtener_usuario($id) : null;
if (!$usr) {
    guardar_mensaje('El usuario solicitado no existe.', 'danger');
    redirigir('modules/usuarios/');
}
if ($id === usuario_actual()['id_usuario']) {
    redirigir('cambiar_clave.php');   // la propia se cambia con la contraseña actual
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $campo = fn(string $c): string => is_string($_POST[$c] ?? null) ? $_POST[$c] : '';
    $errores = restablecer_clave($id, $campo('clave'), $campo('confirmacion'));
    if (!$errores) {
        guardar_mensaje("Contraseña de {$usr['usuario']} restablecida. Entrégasela de forma privada y pídele que la cambie.", 'success');
        redirigir('modules/usuarios/');
    }
}

$titulo = 'Restablecer contraseña';
require __DIR__ . '/../../includes/header.php';
?>
<div class="contenido-angosto">
    <h1 class="h3 mb-1">Restablecer contraseña</h1>
    <p class="text-muted mb-3">
        Usuario <strong><?= e($usr['usuario']) ?></strong> · <?= e(nombre_perfil($usr['perfil'])) ?>
        <?= $usr['nombre_tecnico'] !== null ? '· ' . e($usr['nombre_tecnico']) : '' ?>
    </p>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
        <?= csrf_campo() ?>
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <label for="clave" class="form-label">Contraseña nueva</label>
                <input type="password" id="clave" name="clave" class="form-control"
                       minlength="<?= CLAVE_MINIMO ?>" maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required autofocus>
                <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres.</div>
            </div>
            <div class="col-sm-6">
                <label for="confirmacion" class="form-label">Repítela</label>
                <input type="password" id="confirmacion" name="confirmacion" class="form-control"
                       maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Restablecer</button>
            <a href="<?= e(url('modules/usuarios/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
