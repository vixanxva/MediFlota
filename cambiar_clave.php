<?php
/**
 * Cambiar mi contraseña. Cualquier usuario conectado.
 */

require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/modules/usuarios/logica.php';
requiere_login();

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $campo = fn(string $c): string => is_string($_POST[$c] ?? null) ? $_POST[$c] : '';
    $errores = cambiar_mi_clave(usuario_actual()['id_usuario'], $campo('actual'), $campo('clave'), $campo('confirmacion'));
    if (!$errores) {
        session_regenerate_id(true);
        guardar_mensaje('Tu contraseña se cambió. Úsala la próxima vez que inicies sesión.', 'success');
        redirigir('inicio.php');
    }
}

$titulo = 'Cambiar contraseña';
require __DIR__ . '/includes/header.php';
?>
<div class="contenido-angosto">
    <h1 class="h3 mb-3">Cambiar mi contraseña</h1>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
        <?= csrf_campo() ?>
        <!-- Para que el navegador asocie la contraseña con el usuario correcto. -->
        <input type="text" name="usuario" value="<?= e(usuario_actual()['usuario']) ?>" autocomplete="username" hidden>

        <div class="mb-3">
            <label for="actual" class="form-label">Contraseña actual</label>
            <input type="password" id="actual" name="actual" class="form-control" autocomplete="current-password" required autofocus>
        </div>
        <div class="mb-3">
            <label for="clave" class="form-label">Contraseña nueva</label>
            <input type="password" id="clave" name="clave" class="form-control"
                   minlength="<?= CLAVE_MINIMO ?>" maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
            <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres y distinta de tu usuario.</div>
        </div>
        <div class="mb-4">
            <label for="confirmacion" class="form-label">Repite la contraseña nueva</label>
            <input type="password" id="confirmacion" name="confirmacion" class="form-control"
                   maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
            <a href="<?= e(url('inicio.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
