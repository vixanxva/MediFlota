<?php
/**
 * Listado de técnicos (RF03). Solo administrador.
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
    return 'modules/tecnicos/' . ($parametros ? '?' . http_build_query($parametros) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $id     = filter_var($_POST['id_tecnico'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!in_array($accion, ['activar', 'desactivar'], true) || !$id) {
        http_response_code(400);
        exit('Solicitud no válida.');
    }

    $errores = cambiar_activo_tecnico($id, $accion === 'activar');
    if ($errores) {
        guardar_mensaje(implode(' ', $errores), 'danger');
    } else {
        $nombre = obtener_tecnico($id)['nombre'];
        guardar_mensaje($accion === 'activar'
            ? "$nombre quedó activo."
            : "$nombre quedó desactivado: ya no puede iniciar sesión ni recibir ingresos. Su historial se conserva.", 'success');
    }
    redirigir(url_listado($filtro, $busqueda));
}

$tecnicos = listar_tecnicos($busqueda, $filtro);

$titulo = 'Técnicos';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Técnicos</h1>
    <a href="<?= e(url('modules/tecnicos/formulario.php')) ?>" class="btn btn-primary">+ Registrar técnico</a>
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

    <form method="get" action="<?= e(url('modules/tecnicos/')) ?>" class="d-flex gap-2" role="search">
        <?php if ($filtro !== 'activos'): ?>
            <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
        <?php endif; ?>
        <input type="search" name="q" class="form-control" placeholder="Nombre o documento"
               value="<?= e($busqueda) ?>" maxlength="100" aria-label="Buscar por nombre o documento">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($busqueda !== ''): ?>
            <a href="<?= e(url(url_listado($filtro, ''))) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!$tecnicos): ?>
    <div class="alert alert-info">
        <?= $busqueda !== '' ? 'Ningún técnico coincide con «' . e($busqueda) . '».' : 'No hay técnicos para mostrar.' ?>
    </div>
<?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Teléfono</th>
                    <th>Especialidad</th>
                    <th class="text-center">En el taller</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tecnicos as $t): $activo = (int) $t['activo'] === 1; $abiertos = (int) $t['ingresos_abiertos']; ?>
                <tr class="<?= $activo ? '' : 'text-muted' ?>">
                    <td class="columna-texto">
                        <strong><?= e($t['nombre']) ?></strong>
                        <?php if (!$activo): ?>
                            <span class="badge text-bg-secondary">Inactivo</span>
                        <?php endif; ?>
                        <div class="small text-muted">
                            <?= $t['usuario'] !== null ? 'Usuario: ' . e($t['usuario']) : 'Sin usuario para entrar al sistema' ?>
                        </div>
                    </td>
                    <td class="text-nowrap"><?= e($t['documento']) ?></td>
                    <td class="text-nowrap"><?= $t['telefono'] !== null ? e($t['telefono']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= e($t['especialidad']) ?></td>
                    <td class="text-center">
                        <?php if ($abiertos > 0): ?>
                            <a href="<?= e(url('modules/ingresos/?estado=abiertos')) ?>" class="badge text-bg-warning text-decoration-none"
                               title="Ingresos abiertos asignados"><?= $abiertos ?></a>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('modules/tecnicos/formulario.php?id=' . $t['id_tecnico'])) ?>">Editar</a>
                        <form method="post" class="d-inline"
                              action="<?= e(url(url_listado($filtro, $busqueda))) ?>"
                              <?php if ($activo): ?>
                                  data-confirmar="<?= e('¿Desactivar a ' . $t['nombre'] . '? No podrá iniciar sesión ni recibir ingresos. Su historial no se borra.') ?>"
                                  onsubmit="return confirm(this.dataset.confirmar);"
                              <?php endif; ?>>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id_tecnico" value="<?= (int) $t['id_tecnico'] ?>">
                            <?php if (!$activo): ?>
                                <button type="submit" name="accion" value="activar" class="btn btn-sm btn-outline-secondary">Activar</button>
                            <?php elseif ($abiertos > 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                        title="Tiene ingresos abiertos: reasígnalos antes de desactivarlo">Desactivar</button>
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
        <?= count($tecnicos) === 1 ? '1 técnico' : count($tecnicos) . ' técnicos' ?>.
        «En el taller» son los ingresos abiertos que tiene asignados.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
