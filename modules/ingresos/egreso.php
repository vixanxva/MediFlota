<?php
/**
 * Registrar el egreso (salida) de un vehículo del taller (RF07, RF08).
 * Administrador: cualquier ingreso abierto. Técnico: solo los que tiene asignados.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/costos.php';
require_once __DIR__ . '/plantilla.php';
requiere_perfil('administrador', 'tecnico');

$idIngreso = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ingreso   = $idIngreso ? obtener_ingreso($idIngreso) : null;

if (!$ingreso) {
    guardar_mensaje('El ingreso solicitado no existe.', 'danger');
    redirigir('modules/ingresos/');
}
if (!puede_gestionar_ingreso(usuario_actual(), $ingreso)) {
    denegar_acceso('Solo puedes registrar el egreso de los ingresos que tienes asignados.');
}
if ($ingreso['fecha_hora_egreso'] !== null) {
    guardar_mensaje("El ingreso del vehículo {$ingreso['placa']} ya tiene registrado su egreso.", 'warning');
    redirigir('modules/ingresos/detalle.php?id=' . $idIngreso);
}

$errores = [];
$datos = [
    'fecha'             => date('Y-m-d'),
    'hora'              => date('H:i'),
    'trabajo_realizado' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    foreach (array_keys($datos) as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }

    $errores = registrar_egreso($idIngreso, $datos);
    if (!$errores) {
        guardar_mensaje("Egreso registrado. El vehículo {$ingreso['placa']} quedó disponible.", 'success');
        redirigir('modules/ingresos/');
    }
}

plantilla_inicio('Registrar egreso');
?>
<div style="max-width: 640px;">
    <h1 class="h3 mb-3">Registrar egreso</h1>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <h2 class="h5 card-title mb-3">
                <?= e($ingreso['placa']) ?>
                <small class="text-muted"><?= e($ingreso['marca'] . ' ' . $ingreso['modelo']) ?></small>
            </h2>
            <dl class="row mb-0 small">
                <dt class="col-sm-4">Ingreso</dt>
                <dd class="col-sm-8"><?= e(formato_fecha_hora($ingreso['fecha_hora_ingreso'])) ?></dd>
                <dt class="col-sm-4">Kilometraje</dt>
                <dd class="col-sm-8"><?= e(formato_numero($ingreso['kilometraje_ingreso'])) ?> km</dd>
                <dt class="col-sm-4">Falla reportada</dt>
                <dd class="col-sm-8"><?= nl2br(e($ingreso['falla_reportada'])) ?></dd>
                <dt class="col-sm-4">Diagnóstico</dt>
                <dd class="col-sm-8"><?= $ingreso['diagnostico'] !== null ? nl2br(e($ingreso['diagnostico'])) : '<span class="text-muted">Sin registrar</span>' ?></dd>
                <dt class="col-sm-4">Tipo de mantenimiento</dt>
                <dd class="col-sm-8"><?= $ingreso['tipo_mantenimiento'] !== null ? e(TIPOS_MANTENIMIENTO[$ingreso['tipo_mantenimiento']]) : '<span class="text-muted">Sin registrar</span>' ?></dd>
                <dt class="col-sm-4">Técnico</dt>
                <dd class="col-sm-8"><?= $ingreso['nombre_tecnico'] !== null ? e($ingreso['nombre_tecnico']) : '<span class="text-muted">Sin asignar</span>' ?></dd>
                <dt class="col-sm-4">Costo total</dt>
                <dd class="col-sm-8 mb-0">
                    <?= e(formato_pesos(costo_total_ingreso($idIngreso))) ?>
                    <a href="<?= e(url('modules/ingresos/detalle.php?id=' . $idIngreso)) ?>" class="ms-2">Ver detalle</a>
                </dd>
            </dl>
        </div>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if ($faltantes = datos_faltantes_para_egreso($ingreso)): ?>
        <div class="alert alert-warning">
            <p class="mb-2">
                Para registrar el egreso, primero completa en el detalle del ingreso:
                <strong><?= e(implode(', ', $faltantes)) ?></strong>.
            </p>
            <a href="<?= e(url('modules/ingresos/detalle.php?id=' . $idIngreso)) ?>" class="btn btn-sm btn-primary">Ir al detalle</a>
            <a href="<?= e(url('modules/ingresos/')) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
        </div>
    <?php else: ?>
    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <?= csrf_campo() ?>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="fecha" class="form-label">Fecha de egreso</label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="<?= e($datos['fecha']) ?>"
                       min="<?= e(substr($ingreso['fecha_hora_ingreso'], 0, 10)) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-sm-6">
                <label for="hora" class="form-label">Hora</label>
                <input type="time" id="hora" name="hora" class="form-control" value="<?= e($datos['hora']) ?>" required>
            </div>
        </div>

        <div class="mb-4">
            <label for="trabajo_realizado" class="form-label">Trabajo realizado</label>
            <textarea id="trabajo_realizado" name="trabajo_realizado" class="form-control" rows="4"
                      maxlength="2000" required><?= e($datos['trabajo_realizado']) ?></textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">Registrar egreso</button>
            <a href="<?= e(url('modules/ingresos/')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php plantilla_fin(); ?>
