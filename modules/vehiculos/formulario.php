<?php
/**
 * Registrar (sin ?id) o editar (?id=N) un vehículo (RF02). Solo administrador.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$idVehiculo = null;
$vehiculo   = null;
if (isset($_GET['id'])) {
    $idVehiculo = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $vehiculo   = $idVehiculo ? obtener_vehiculo($idVehiculo) : null;
    if (!$vehiculo) {
        guardar_mensaje('El vehículo solicitado no existe.', 'danger');
        redirigir('modules/vehiculos/');
    }
}

$editando    = $vehiculo !== null;
$enTaller    = $editando && $vehiculo['estado'] === 'en_mantenimiento';
$kmMinimo    = $editando ? kilometraje_minimo($idVehiculo) : 0;
$campos      = ['placa', 'tipo', 'marca', 'modelo', 'kilometraje', 'vencimiento_rtm', 'estado'];
$errores     = [];

// Valores del formulario: los guardados, o los de un vehículo nuevo.
$datos = $editando
    ? array_intersect_key($vehiculo, array_flip($campos))
    : ['placa' => '', 'tipo' => 'ambulancia', 'marca' => '', 'modelo' => '', 'kilometraje' => '', 'vencimiento_rtm' => '', 'estado' => 'disponible'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    foreach ($campos as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }

    if ($editando) {
        if ($enTaller) {
            unset($datos['estado']);   // el estado lo cambia el egreso, no este formulario
        }
        $errores = actualizar_vehiculo($idVehiculo, $datos);
        $datos['estado'] = $datos['estado'] ?? $vehiculo['estado'];
    } else {
        $errores = crear_vehiculo($datos, $idVehiculo);
    }

    if (!$errores) {
        $placa = normalizar_placa($datos['placa']);
        guardar_mensaje($editando ? "Vehículo $placa actualizado." : "Vehículo $placa registrado.", 'success');
        redirigir('modules/vehiculos/' . ($editando && !(int) $vehiculo['activo'] ? '?filtro=inactivos' : ''));
    }
}

$titulo = $editando ? 'Editar vehículo ' . $vehiculo['placa'] : 'Registrar vehículo';
require __DIR__ . '/../../includes/header.php';
?>
<div class="contenido-angosto">
    <h1 class="h3 mb-3"><?= e($titulo) ?></h1>

    <?php if ($editando && !(int) $vehiculo['activo']): ?>
        <div class="alert alert-secondary">Este vehículo está desactivado. Para activarlo, búscalo en el listado, en «Inactivos».</div>
    <?php endif; ?>
    <?php if ($enTaller): ?>
        <div class="alert alert-warning">
            El vehículo está en el taller. Su estado cambia solo cuando se registre el egreso en
            <a href="<?= e(url('modules/ingresos/?estado=abiertos')) ?>" class="alert-link">Ingresos y egresos</a>.
        </div>
    <?php endif; ?>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm" novalidate>
        <?= csrf_campo() ?>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="placa" class="form-label">Placa</label>
                <input type="text" id="placa" name="placa" class="form-control text-uppercase"
                       value="<?= e($datos['placa']) ?>" maxlength="10" autocomplete="off" required
                       <?= $editando ? '' : 'autofocus' ?>>
                <div class="form-text">Ej. ABC123. Debe ser única.</div>
            </div>
            <div class="col-sm-6">
                <label for="tipo" class="form-label">Tipo</label>
                <select id="tipo" name="tipo" class="form-select" required>
                    <?php foreach (TIPOS_VEHICULO as $clave => $texto): ?>
                        <option value="<?= e($clave) ?>" <?= $clave === $datos['tipo'] ? 'selected' : '' ?>><?= e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="marca" class="form-label">Marca</label>
                <input type="text" id="marca" name="marca" class="form-control"
                       value="<?= e($datos['marca']) ?>" maxlength="50" required>
            </div>
            <div class="col-sm-6">
                <label for="modelo" class="form-label">Modelo</label>
                <input type="text" id="modelo" name="modelo" class="form-control"
                       value="<?= e($datos['modelo']) ?>" maxlength="50" required>
                <div class="form-text">Línea y año, ej. Sprinter 515 2021.</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="kilometraje" class="form-label">Kilometraje actual</label>
                <input type="number" id="kilometraje" name="kilometraje" class="form-control"
                       value="<?= e($datos['kilometraje']) ?>" min="<?= $kmMinimo ?>" max="<?= KILOMETRAJE_MAXIMO ?>" step="1" required>
                <?php if ($kmMinimo > 0): ?>
                    <div class="form-text">No puede ser menor que el de su último ingreso: <?= e(formato_numero($kmMinimo)) ?> km.</div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6">
                <label for="vencimiento_rtm" class="form-label">Vence la revisión técnico-mecánica</label>
                <input type="date" id="vencimiento_rtm" name="vencimiento_rtm" class="form-control"
                       value="<?= e($datos['vencimiento_rtm']) ?>" min="2000-01-01" max="2099-12-31" required>
            </div>
        </div>

        <fieldset class="mb-4">
            <legend class="form-label fs-6">Estado</legend>
            <?php if ($enTaller): ?>
                <p class="mb-0"><?= etiqueta_estado_vehiculo('en_mantenimiento') ?></p>
            <?php else: ?>
                <?php foreach (ESTADOS_ELEGIBLES as $estado): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="estado" id="estado-<?= e($estado) ?>"
                               value="<?= e($estado) ?>" <?= $estado === $datos['estado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="estado-<?= e($estado) ?>"><?= e(ESTADOS_VEHICULO[$estado]['texto']) ?></label>
                    </div>
                <?php endforeach; ?>
                <div class="form-text">«En mantenimiento» se pone solo al registrar un ingreso al taller.</div>
            <?php endif; ?>
        </fieldset>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Registrar vehículo' ?></button>
            <a href="<?= e(url('modules/vehiculos/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
