<?php
/**
 * Registrar el ingreso de un vehículo al taller (RF04).
 * Solo administrador.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
require_once __DIR__ . '/plantilla.php';
requiere_perfil('administrador');

$errores = [];
$datos = [
    // Permite llegar con el vehículo ya elegido, ej. desde el panel: nuevo.php?id_vehiculo=3
    'id_vehiculo'     => (string) ($_GET['id_vehiculo'] ?? ''),
    'fecha'           => date('Y-m-d'),
    'hora'            => date('H:i'),
    'kilometraje'     => '',
    'falla_reportada' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    foreach (array_keys($datos) as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }

    $errores = registrar_ingreso($datos, $idIngreso);
    if (!$errores) {
        guardar_mensaje('Ingreso registrado. El vehículo quedó en mantenimiento.', 'success');
        redirigir('modules/ingresos/');
    }
}

$vehiculos = vehiculos_para_ingreso();
$estados   = ['disponible' => 'Disponible', 'fuera_servicio' => 'Fuera de servicio'];

plantilla_inicio('Registrar ingreso');
?>
<div style="max-width: 640px;">
    <h1 class="h3 mb-3">Registrar ingreso</h1>

    <?php mostrar_errores($errores); ?>

    <?php if (!$vehiculos): ?>
        <div class="alert alert-info">Todos los vehículos activos están en el taller. No hay vehículos para ingresar.</div>
        <a href="<?= e(url('modules/ingresos/')) ?>" class="btn btn-secondary">Volver</a>
    <?php else: ?>
        <form method="post" class="bg-white p-4 rounded shadow-sm">
            <?= csrf_campo() ?>

            <div class="mb-3">
                <label for="id_vehiculo" class="form-label">Vehículo</label>
                <select id="id_vehiculo" name="id_vehiculo" class="form-select" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($vehiculos as $v): ?>
                        <option value="<?= (int) $v['id_vehiculo'] ?>"
                                data-km="<?= (int) $v['kilometraje'] ?>"
                                <?= (string) $v['id_vehiculo'] === $datos['id_vehiculo'] ? 'selected' : '' ?>>
                            <?= e($v['placa'] . ' — ' . $v['marca'] . ' ' . $v['modelo']
                                . ' (' . formato_numero($v['kilometraje']) . ' km, ' . $estados[$v['estado']] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Solo aparecen los vehículos activos que no están en el taller.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="fecha" class="form-label">Fecha de ingreso</label>
                    <input type="date" id="fecha" name="fecha" class="form-control"
                           value="<?= e($datos['fecha']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label for="hora" class="form-label">Hora</label>
                    <input type="time" id="hora" name="hora" class="form-control" value="<?= e($datos['hora']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="kilometraje" class="form-label">Kilometraje al ingresar</label>
                <input type="number" id="kilometraje" name="kilometraje" class="form-control"
                       value="<?= e($datos['kilometraje']) ?>" min="0" step="1" required>
                <div class="form-text" id="ayuda-km">No puede ser menor que el kilometraje actual del vehículo.</div>
            </div>

            <div class="mb-4">
                <label for="falla_reportada" class="form-label">Falla reportada</label>
                <textarea id="falla_reportada" name="falla_reportada" class="form-control" rows="4"
                          maxlength="2000" required><?= e($datos['falla_reportada']) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Registrar ingreso</button>
                <a href="<?= e(url('modules/ingresos/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Ayuda en pantalla: usa el kilometraje actual del vehículo como mínimo.
// La validación real se hace en el servidor.
(function () {
    var select = document.getElementById('id_vehiculo');
    var km = document.getElementById('kilometraje');
    var ayuda = document.getElementById('ayuda-km');
    if (!select) return;
    function actualizar() {
        var opcion = select.options[select.selectedIndex];
        var minimo = opcion && opcion.dataset.km ? parseInt(opcion.dataset.km, 10) : 0;
        km.min = minimo;
        ayuda.textContent = minimo
            ? 'Kilometraje actual: ' + minimo.toLocaleString('es-CO') + ' km. No puede ser menor.'
            : 'No puede ser menor que el kilometraje actual del vehículo.';
    }
    select.addEventListener('change', actualizar);
    actualizar();
})();
</script>
<?php plantilla_fin(); ?>
