<?php
/**
 * Panel de disponibilidad de la flota (RF09). Página principal tras el login.
 * Administrador y técnico. Solo consulta.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
require_once __DIR__ . '/../ingresos/logica.php';   // puede_gestionar_ingreso()
requiere_perfil('administrador', 'tecnico');

$u = usuario_actual();

$estado = $_GET['estado'] ?? '';
if (!is_string($estado) || !isset(ESTADOS_VEHICULO[$estado])) {
    $estado = '';
}

$conteo    = contar_por_estado();
$total     = array_sum($conteo);
$vehiculos = vehiculos_del_panel($estado);
$alertaRtm = vehiculos_con_alerta_rtm();

// Color del borde de cada contador (mismo color que la etiqueta del estado).
$bordes = ['disponible' => 'border-success', 'en_mantenimiento' => 'border-warning', 'fuera_servicio' => 'border-danger'];

$titulo = 'Panel';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Disponibilidad de la flota</h1>
    <span class="text-muted small">Actualizado: <?= e(formato_fecha_hora(date('Y-m-d H:i:s'))) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="<?= e(url('modules/panel/')) ?>"
           class="contador card h-100 shadow-sm text-decoration-none text-reset border-0 border-start border-4 border-primary<?= $estado === '' ? ' seleccionado' : '' ?>">
            <div class="card-body">
                <div class="small text-muted">Total de la flota</div>
                <div class="display-6 fw-semibold"><?= $total ?></div>
            </div>
        </a>
    </div>
    <?php foreach (ESTADOS_VEHICULO as $clave => $info): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e(url('modules/panel/?estado=' . $clave)) ?>"
               class="contador card h-100 shadow-sm text-decoration-none text-reset border-0 border-start border-4 <?= $bordes[$clave] ?><?= $estado === $clave ? ' seleccionado' : '' ?>"
               <?= $estado === $clave ? 'aria-current="true"' : '' ?>>
                <div class="card-body">
                    <div class="small text-muted"><?= e($info['texto']) ?></div>
                    <div class="display-6 fw-semibold"><?= $conteo[$clave] ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($alertaRtm): ?>
    <div class="alert alert-warning">
        <strong>Revisión técnico-mecánica:</strong>
        <?php
        $textos = [];
        foreach ($alertaRtm as $a) {
            $textos[] = $a['placa'] . (estado_rtm($a['vencimiento_rtm']) === 'vencida' ? ' (vencida el ' : ' (vence el ')
                      . formato_fecha($a['vencimiento_rtm']) . ')';
        }
        echo e(unir_lista($textos)) . '.';
        ?>
    </div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $estado === '' ? 'active' : '' ?>" href="<?= e(url('modules/panel/')) ?>">Todos</a>
    </li>
    <?php foreach (ESTADOS_VEHICULO as $clave => $info): ?>
        <li class="nav-item">
            <a class="nav-link <?= $estado === $clave ? 'active' : '' ?>"
               href="<?= e(url('modules/panel/?estado=' . $clave)) ?>"><?= e($info['texto']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$vehiculos): ?>
    <div class="alert alert-info">
        <?= $estado === '' ? 'No hay vehículos activos registrados.' : 'No hay vehículos en estado «' . e(ESTADOS_VEHICULO[$estado]['texto']) . '».' ?>
    </div>
<?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Placa</th>
                    <th>Marca y modelo</th>
                    <th class="text-end">Km</th>
                    <th class="text-nowrap">Vence <abbr title="Revisión técnico-mecánica">RTM</abbr></th>
                    <th>Estado</th>
                    <th>En el taller</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehiculos as $v): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('modules/historial/?id_vehiculo=' . $v['id_vehiculo'])) ?>"
                           class="fw-bold text-reset" title="Ver historial"><?= e($v['placa']) ?></a>
                        <div class="small text-muted"><?= e(TIPOS_VEHICULO[$v['tipo']]) ?></div>
                    </td>
                    <td><?= e($v['marca'] . ' ' . $v['modelo']) ?></td>
                    <td class="text-end text-nowrap"><?= e(formato_numero($v['kilometraje'])) ?></td>
                    <td class="text-nowrap"><?= etiqueta_rtm($v['vencimiento_rtm']) ?></td>
                    <td class="text-nowrap"><?= etiqueta_estado_vehiculo($v['estado']) ?></td>
                    <td class="small">
                        <?php if ($v['id_ingreso'] !== null): ?>
                            <div class="text-nowrap">Desde <?= e(formato_fecha_hora($v['fecha_hora_ingreso'])) ?></div>
                            <div class="text-muted"><?= $v['nombre_tecnico'] !== null ? e($v['nombre_tecnico']) : 'Sin técnico asignado' ?></div>
                            <?php if (puede_gestionar_ingreso($u, $v)): ?>
                                <a href="<?= e(url('modules/ingresos/detalle.php?id=' . $v['id_ingreso'])) ?>">Ver ingreso</a>
                            <?php endif; ?>
                        <?php elseif (es_administrador()): ?>
                            <a href="<?= e(url('modules/ingresos/nuevo.php?id_vehiculo=' . $v['id_vehiculo'])) ?>">Ingresar al taller</a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">
        Solo vehículos activos. La revisión técnico-mecánica (RTM) se marca «Por vencer» <?= DIAS_AVISO_RTM ?> días antes.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
