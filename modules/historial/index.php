<?php
/**
 * Historial de mantenimientos y costo acumulado por vehículo (RF10).
 * Administrador y técnico. Solo consulta.
 *
 * El técnico ve el historial completo del vehículo (le sirve para el
 * diagnóstico), pero el enlace al detalle solo aparece en sus ingresos.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
require_once __DIR__ . '/../ingresos/costos.php';   // TIPOS_MANTENIMIENTO y puede_gestionar_ingreso()
requiere_perfil('administrador', 'tecnico');

$u = usuario_actual();
$vehiculos = vehiculos_para_historial();

$idVehiculo = filter_var($_GET['id_vehiculo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$vehiculo   = $idVehiculo ? vehiculo_del_historial($idVehiculo) : null;
if ($idVehiculo && !$vehiculo) {
    guardar_mensaje('El vehículo solicitado no existe.', 'danger');
    redirigir('modules/historial/');
}

$ingresos = $vehiculo ? historial_de_vehiculo($idVehiculo) : [];
$resumen  = resumen_historial($ingresos);

$titulo = $vehiculo ? 'Historial ' . $vehiculo['placa'] : 'Historial';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Historial por vehículo</h1>

<form method="get" action="<?= e(url('modules/historial/')) ?>" class="input-group mb-4 contenido-angosto">
    <label for="id_vehiculo" class="visually-hidden">Vehículo</label>
    <select id="id_vehiculo" name="id_vehiculo" class="form-select" required
            onchange="this.form.submit()">
        <option value="">Selecciona un vehículo…</option>
        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= (int) $v['id_vehiculo'] ?>" <?= $vehiculo && (int) $v['id_vehiculo'] === $idVehiculo ? 'selected' : '' ?>>
                <?= e($v['placa'] . ' — ' . $v['marca'] . ' ' . $v['modelo']
                    . ' (' . ($v['total_ingresos'] == 1 ? '1 ingreso' : $v['total_ingresos'] . ' ingresos') . ')'
                    . ((int) $v['activo'] ? '' : ' — inactivo')) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Ver historial</button>
</form>

<?php if (!$vehiculo): ?>
    <div class="alert alert-info">Selecciona un vehículo para ver todos sus ingresos al taller y su costo acumulado.</div>
<?php else: ?>
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h4 mb-0"><?= e($vehiculo['placa']) ?></h2>
        <span class="text-muted"><?= e(TIPOS_VEHICULO[$vehiculo['tipo']] . ' · ' . $vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?></span>
        <?= etiqueta_estado_vehiculo($vehiculo['estado']) ?>
        <?php if (!(int) $vehiculo['activo']): ?>
            <span class="badge text-bg-secondary">Inactivo</span>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="small text-muted">Costo acumulado</div>
                    <div class="fs-4 fw-semibold"><?= e(formato_pesos($resumen['costo_acumulado'])) ?></div>
                    <?php if ($resumen['costo_acumulado'] > 0): ?>
                        <div class="small text-muted">
                            Preventivo <?= e(formato_pesos($resumen['costo_por_tipo']['preventivo'])) ?> ·
                            Correctivo <?= e(formato_pesos($resumen['costo_por_tipo']['correctivo'])) ?>
                            <?php if ($resumen['costo_por_tipo']['sin_tipo'] > 0): ?>
                                · Sin tipo <?= e(formato_pesos($resumen['costo_por_tipo']['sin_tipo'])) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-secondary">
                <div class="card-body">
                    <div class="small text-muted">Ingresos al taller</div>
                    <div class="fs-4 fw-semibold"><?= count($ingresos) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
                <div class="card-body">
                    <div class="small text-muted">Último mantenimiento</div>
                    <?php if ($resumen['ultimo']): ?>
                        <div class="fs-5 fw-semibold"><?= e(formato_fecha($resumen['ultimo']['fecha_hora_egreso'])) ?></div>
                        <div class="small text-muted"><?= e(formato_numero($resumen['ultimo']['kilometraje_ingreso'])) ?> km</div>
                    <?php else: ?>
                        <div class="text-muted">Sin mantenimientos terminados</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="small text-muted">Kilometraje actual</div>
                    <div class="fs-5 fw-semibold"><?= e(formato_numero($vehiculo['kilometraje'])) ?> km</div>
                    <div class="small text-muted">Vence <abbr title="Revisión técnico-mecánica">RTM</abbr>: <?= etiqueta_rtm($vehiculo['vencimiento_rtm']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($resumen['en_curso']): ?>
        <div class="alert alert-warning">
            Ingresó al taller el <?= e(formato_fecha_hora($resumen['en_curso']['fecha_hora_ingreso'])) ?> y sigue allí.
            Su costo hasta ahora (<?= e(formato_pesos($resumen['en_curso']['costo_total'])) ?>) ya está sumado en el acumulado.
        </div>
    <?php endif; ?>

    <?php if (!$ingresos): ?>
        <div class="alert alert-info">Este vehículo no tiene ingresos al taller registrados.</div>
    <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fechas</th>
                        <th class="text-end">Km</th>
                        <th>Falla y diagnóstico</th>
                        <th>Tipo</th>
                        <th>Técnico</th>
                        <th class="text-end">Costo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ingresos as $i): ?>
                    <tr>
                        <td class="text-nowrap small">
                            <div><span class="text-muted">Ingreso:</span> <?= e(formato_fecha_hora($i['fecha_hora_ingreso'])) ?></div>
                            <div>
                                <span class="text-muted">Egreso:</span>
                                <?= $i['fecha_hora_egreso'] !== null
                                    ? e(formato_fecha_hora($i['fecha_hora_egreso']))
                                    : '<span class="badge text-bg-warning">En el taller</span>' ?>
                            </div>
                        </td>
                        <td class="text-end text-nowrap"><?= e(formato_numero($i['kilometraje_ingreso'])) ?></td>
                        <td class="columna-texto small">
                            <div><span class="text-muted">Falla:</span> <?= e($i['falla_reportada']) ?></div>
                            <div><span class="text-muted">Diagnóstico:</span>
                                <?= $i['diagnostico'] !== null && trim($i['diagnostico']) !== '' ? e($i['diagnostico']) : '<span class="text-muted">Sin registrar</span>' ?></div>
                            <?php if ($i['trabajo_realizado'] !== null && trim($i['trabajo_realizado']) !== ''): ?>
                                <div><span class="text-muted">Trabajo realizado:</span> <?= e($i['trabajo_realizado']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $i['tipo_mantenimiento'] !== null ? e(TIPOS_MANTENIMIENTO[$i['tipo_mantenimiento']]) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $i['nombre_tecnico'] !== null ? e($i['nombre_tecnico']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                        <td class="text-end text-nowrap"><?= e(formato_pesos($i['costo_total'])) ?></td>
                        <td class="text-end">
                            <?php if (puede_gestionar_ingreso($u, $i)): ?>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?= e(url('modules/ingresos/detalle.php?id=' . $i['id_ingreso'])) ?>">Ver</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="5" class="text-end">Costo acumulado</th>
                        <th class="text-end text-nowrap"><?= e(formato_pesos($resumen['costo_acumulado'])) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
