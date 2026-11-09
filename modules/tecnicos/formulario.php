<?php
/**
 * Registrar (sin ?id) o editar (?id=N) un técnico (RF03). Solo administrador.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$idTecnico = null;
$tecnico   = null;
if (isset($_GET['id'])) {
    $idTecnico = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $tecnico   = $idTecnico ? obtener_tecnico($idTecnico) : null;
    if (!$tecnico) {
        guardar_mensaje('El técnico solicitado no existe.', 'danger');
        redirigir('modules/tecnicos/');
    }
}

$editando = $tecnico !== null;
$campos   = ['nombre', 'documento', 'telefono', 'especialidad'];
$errores  = [];
$datos    = $editando
    ? array_map('strval', array_intersect_key($tecnico, array_flip($campos)))
    : array_fill_keys($campos, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    foreach ($campos as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }

    $errores = $editando ? actualizar_tecnico($idTecnico, $datos) : crear_tecnico($datos, $idTecnico);

    if (!$errores) {
        $nombre = trim(preg_replace('/\s+/u', ' ', $datos['nombre']));
        guardar_mensaje($editando ? "Datos de $nombre actualizados." : "Técnico $nombre registrado.", 'success');
        redirigir('modules/tecnicos/' . ($editando && !(int) $tecnico['activo'] ? '?filtro=inactivos' : ''));
    }
}

$especialidades = especialidades_registradas();

$titulo = $editando ? 'Editar técnico' : 'Registrar técnico';
require __DIR__ . '/../../includes/header.php';
?>
<div class="contenido-angosto">
    <h1 class="h3 mb-3"><?= e($titulo) ?></h1>

    <?php if ($editando && !(int) $tecnico['activo']): ?>
        <div class="alert alert-secondary">Este técnico está desactivado. Para activarlo, búscalo en el listado, en «Inactivos».</div>
    <?php endif; ?>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
        <?= csrf_campo() ?>

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" class="form-control"
                   value="<?= e($datos['nombre']) ?>" maxlength="100" autocomplete="off" required
                   <?= $editando ? '' : 'autofocus' ?>>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="documento" class="form-label">Documento</label>
                <input type="text" id="documento" name="documento" class="form-control"
                       value="<?= e($datos['documento']) ?>" maxlength="25" autocomplete="off" required>
                <div class="form-text">Cédula u otro documento. No se puede repetir.</div>
            </div>
            <div class="col-sm-6">
                <label for="telefono" class="form-label">Teléfono <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="tel" id="telefono" name="telefono" class="form-control"
                       value="<?= e($datos['telefono']) ?>" maxlength="20" autocomplete="off">
            </div>
        </div>

        <div class="mb-4">
            <label for="especialidad" class="form-label">Especialidad</label>
            <input type="text" id="especialidad" name="especialidad" class="form-control" list="lista-especialidades"
                   value="<?= e($datos['especialidad']) ?>" maxlength="60" autocomplete="off" required>
            <datalist id="lista-especialidades">
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= e($esp) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <div class="form-text">Ej. mecánica general, electricidad automotriz, frenos. Puedes elegir una ya registrada.</div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Registrar técnico' ?></button>
            <a href="<?= e(url('modules/tecnicos/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
