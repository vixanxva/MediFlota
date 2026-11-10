<?php
/**
 * Listado de vehículos con búsqueda por placa (RF02). Solo administrador.
 * Desde aquí también se desactivan y se vuelven a activar.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$filtros = ['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'];
$filtro  = $_GET['filtro'] ?? 'activos';
if (!is_string($filtro) || !isset($filtros[$filtro])) {
    $filtro = 'activos';
}
$busqueda = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

/** URL del listado conservando la búsqueda y el filtro actuales. */
function url_listado(string $filtro, string $busqueda): string
{
    $parametros = array_filter(['q' => $busqueda, 'filtro' => $filtro === 'activos' ? '' : $filtro]);
    return 'modules/vehiculos/' . ($parametros ? '?' . http_build_query($parametros) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $id     = filter_var($_POST['id_vehiculo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!in_array($accion, ['activar', 'desactivar'], true) || !$id) {
        http_response_code(400);
        exit('Solicitud no válida.');
    }

    $errores = cambiar_activo_vehiculo($id, $accion === 'activar');
    if ($errores) {
        guardar_mensaje(implode(' ', $errores), 'danger');
    } else {
        $placa = obtener_vehiculo($id)['placa'];
        guardar_mensaje($accion === 'activar'
            ? "Vehículo $placa activado."
            : "Vehículo $placa desactivado. Su historial se conserva; puedes activarlo de nuevo desde \"Inactivos\".", 'success');
    }
    redirigir(url_listado($filtro, $busqueda));
}

$vehiculos = listar_vehiculos($busqueda, $filtro);

$titulo = 'Vehículos';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Vehículos</h1>
    <a href="<?= e(url('modules/vehiculos/formulario.php')) ?>" class="btn btn-primary">+ Registrar vehículo</a>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <ul class="nav nav-pills">
        <?php foreach ($filtros as $clave => $texto): ?>
            <li class="nav-item">
                <a class="nav-link <?= $clave === $filtro ? 'active' : '' ?>"
                   href="<?= e(url(url_listado($clave, $busqueda))) ?>"><?= e($texto) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <form method="get" action="<?= e(url('modules/vehiculos/')) ?>" class="d-flex gap-2" role="search">
        <?php if ($filtro !== 'activos'): ?>
            <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
        <?php endif; ?>
        <input type="search" name="q" class="form-control" placeholder="Buscar por placa"
               value="<?= e($busqueda) ?>" maxlength="10" aria-label="Buscar por placa">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($busqueda !== ''): ?>
            <a href="<?= e(url(url_listado($filtro, ''))) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!$vehiculos): ?>
    <div class="alert alert-info">
        <?= $busqueda !== '' ? 'Ningún vehículo coincide con la placa «' . e($busqueda) . '».' : 'No hay vehículos para mostrar.' ?>
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehiculos as $v): $activo = (int) $v['activo'] === 1; ?>
                <tr class="<?= $activo ? '' : 'text-muted' ?>">
                    <td>
                        <strong><?= e($v['placa']) ?></strong>
                        <div class="small text-muted"><?= e(TIPOS_VEHICULO[$v['tipo']]) ?></div>
                    </td>
                    <td><?= e($v['marca'] . ' ' . $v['modelo']) ?></td>
                    <td class="text-end text-nowrap"><?= e(formato_numero($v['kilometraje'])) ?></td>
                    <td class="text-nowrap"><?= etiqueta_rtm($v['vencimiento_rtm']) ?></td>
                    <td class="text-nowrap">
                        <?= etiqueta_estado_vehiculo($v['estado']) ?>
                        <?php if (!$activo): ?>
                            <span class="badge text-bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('modules/vehiculos/formulario.php?id=' . $v['id_vehiculo'])) ?>">Editar</a>
                        <?php if ($activo && $v['estado'] !== 'en_mantenimiento'): ?>
                            <a class="btn btn-sm btn-outline-success"
                               href="<?= e(url('modules/ingresos/nuevo.php?id_vehiculo=' . $v['id_vehiculo'])) ?>">Ingresar al taller</a>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              action="<?= e(url(url_listado($filtro, $busqueda))) ?>"
                              <?php if ($activo): ?>
                                  data-confirmar="<?= e('¿Desactivar el vehículo ' . $v['placa'] . '? Su historial no se borra.') ?>"
                                  onsubmit="return confirm(this.dataset.confirmar);"
                              <?php endif; ?>>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id_vehiculo" value="<?= (int) $v['id_vehiculo'] ?>">
                            <?php if (!$activo): ?>
                                <button type="submit" name="accion" value="activar" class="btn btn-sm btn-outline-secondary">Activar</button>
                            <?php elseif ($v['estado'] === 'en_mantenimiento'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                        title="Está en el taller: registra su egreso antes de desactivarlo">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="accion" value="desactivar" class="btn btn-sm btn-outline-danger">Desactivar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">
        <?= count($vehiculos) === 1 ? '1 vehículo' : count($vehiculos) . ' vehículos' ?>.
        La revisión técnico-mecánica (RTM) se marca «Por vencer» <?= DIAS_AVISO_RTM ?> días antes.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
