<?php
/**
 * Crear un usuario. Solo administrador.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$errores = [];
$datos   = ['usuario' => '', 'perfil' => 'tecnico', 'id_tecnico' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $campo = fn(string $c): string => is_string($_POST[$c] ?? null) ? $_POST[$c] : '';
    foreach (array_keys($datos) as $c) {
        $datos[$c] = $campo($c);
    }
    $errores = crear_usuario($datos + ['clave' => $campo('clave'), 'confirmacion' => $campo('confirmacion')]);
    if (!$errores) {
        guardar_mensaje('Usuario ' . normalizar_usuario($datos['usuario']) . ' creado. Entrégale su contraseña de forma privada y pídele que la cambie.', 'success');
        redirigir('modules/usuarios/');
    }
}

$tecnicos = tecnicos_sin_usuario();

$titulo = 'Crear usuario';
require __DIR__ . '/../../includes/header.php';
?>
<div class="contenido-angosto">
    <h1 class="h3 mb-3">Crear usuario</h1>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
        <?= csrf_campo() ?>

        <fieldset class="mb-3">
            <legend class="form-label fs-6">Perfil</legend>
            <?php foreach (['tecnico' => 'Técnico', 'administrador' => 'Administrador'] as $valor => $texto): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="perfil" id="perfil-<?= $valor ?>"
                           value="<?= $valor ?>" <?= $datos['perfil'] === $valor ? 'checked' : '' ?>>
                    <label class="form-check-label" for="perfil-<?= $valor ?>"><?= $texto ?></label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <div class="mb-3" id="bloque-tecnico">
            <label for="id_tecnico" class="form-label">Técnico</label>
            <select id="id_tecnico" name="id_tecnico" class="form-select">
                <option value="">Selecciona…</option>
                <?php foreach ($tecnicos as $t): ?>
                    <option value="<?= (int) $t['id_tecnico'] ?>" <?= (string) $t['id_tecnico'] === $datos['id_tecnico'] ? 'selected' : '' ?>>
                        <?= e($t['nombre'] . ' — ' . $t['documento']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <?= $tecnicos ? 'Solo aparecen los técnicos activos que aún no tienen usuario.'
                              : 'Todos los técnicos activos ya tienen usuario. Registra el técnico primero en Técnicos.' ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" id="usuario" name="usuario" class="form-control" value="<?= e($datos['usuario']) ?>"
                   maxlength="50" autocomplete="off" required>
            <div class="form-text">Letras sin tildes, números, punto o guion. Ej. carlos.petro</div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <label for="clave" class="form-label">Contraseña</label>
                <input type="password" id="clave" name="clave" class="form-control"
                       minlength="<?= CLAVE_MINIMO ?>" maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
                <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres.</div>
            </div>
            <div class="col-sm-6">
                <label for="confirmacion" class="form-label">Repite la contraseña</label>
                <input type="password" id="confirmacion" name="confirmacion" class="form-control"
                       maxlength="<?= CLAVE_MAXIMO ?>" autocomplete="new-password" required>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Crear usuario</button>
            <a href="<?= e(url('modules/usuarios/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
// Muestra el selector de técnico solo para el perfil técnico. La validación real está en el servidor.
(function () {
    var bloque = document.getElementById('bloque-tecnico');
    var radios = document.querySelectorAll('input[name="perfil"]');
    function actualizar() {
        bloque.hidden = document.getElementById('perfil-tecnico').checked === false;
    }
    radios.forEach(function (r) { r.addEventListener('change', actualizar); });
    actualizar();
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
