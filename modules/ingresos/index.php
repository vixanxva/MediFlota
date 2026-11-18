<?php
/**
 * Lista de ingresos y egresos.
 * Administrador: todos los ingresos. Técnico: solo los que tiene asignados.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador', 'tecnico');

$u = usuario_actual();

$filtros = ['todos' => 'Todos', 'abiertos' => 'En el taller', 'cerrados' => 'Con egreso'];
$estado  = $_GET['estado'] ?? 'todos';
if (!is_string($estado) || !isset($filtros[$estado])) {
    $estado = 'todos';
}

$ingresos = listar_ingresos($estado, es_administrador() ? null : $u['id_tecnico']);

$titulo = 'Ingresos y egresos';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Ingresos y egresos</h1>
    <?php if (es_administrador()): ?>
        <a href="<?= e(url('modules/ingresos/nuevo.php')) ?>" class="btn btn-primary">+ Registrar ingreso</a>
    <?php endif; ?>
</div>

<?php if (!es_administrador()): ?>
    <p class="text-muted">Se muestran los ingresos que tienes asignados.</p>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($filtros as $clave => $texto): ?>
        <li class="nav-item">
            <a class="nav-link <?= $clave === $estado ? 'active' : '' ?>"
               href="<?= e(url('modules/ingresos/?estado=' . $clave)) ?>"><?= e($texto) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$ingresos): ?>
    <div class="alert alert-info">No hay ingresos para mostrar.</div>
<?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Vehículo</th>
                    <th>Ingreso</th>
                    <th class="text-end">Km</th>
                    <th>Falla reportada</th>
                    <th>Técnico</th>
                    <th>Estado</th>
                    <th>Egreso</th>
                    <th class="text-end">Costo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ingresos as $i): $abierto = $i['fecha_hora_egreso'] === null; ?>
                <tr>
                    <td>
                        <strong><?= e($i['placa']) ?></strong>
                        <div class="small text-muted"><?= $i['tipo'] === 'ambulancia' ? 'Ambulancia' : 'Otro' ?></div>
                    </td>
                    <td class="text-nowrap"><?= e(formato_fecha_hora($i['fecha_hora_ingreso'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(formato_numero($i['kilometraje_ingreso'])) ?></td>
                    <td class="columna-texto"><?= e(mb_strimwidth($i['falla_reportada'], 0, 90, '…')) ?></td>
                    <td><?= $i['nombre_tecnico'] !== null ? e($i['nombre_tecnico']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                    <td>
                        <?php if ($abierto): ?>
                            <span class="badge bg-warning text-dark">En el taller</span>
                        <?php else: ?>
                            <span class="badge bg-success">Con egreso</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= e(formato_fecha_hora($i['fecha_hora_egreso'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(formato_pesos($i['costo_total'])) ?></td>
                    <td class="text-nowrap">
                        <div class="d-flex flex-column gap-1">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('modules/ingresos/detalle.php?id=' . $i['id_ingreso'])) ?>">Ver</a>
                            <?php if ($abierto && puede_gestionar_ingreso($u, $i)): ?>
                                <a class="btn btn-sm btn-outline-success" title="Registrar egreso"
                                   href="<?= e(url('modules/ingresos/egreso.php?id=' . $i['id_ingreso'])) ?>">Egreso</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
