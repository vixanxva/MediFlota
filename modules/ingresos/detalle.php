<?php
/**
 * Detalle de un ingreso: diagnóstico, técnico y repuestos con costos (RF05, RF06).
 *
 * Administrador: cualquier ingreso; asigna el técnico encargado.
 * Técnico: solo los ingresos que tiene asignados.
 * Solo se puede modificar mientras el ingreso esté abierto.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/costos.php';
require_once __DIR__ . '/plantilla.php';
requiere_perfil('administrador', 'tecnico');

$u         = usuario_actual();
$idIngreso = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ingreso   = $idIngreso ? obtener_ingreso($idIngreso) : null;

if (!$ingreso) {
    guardar_mensaje('El ingreso solicitado no existe.', 'danger');
    redirigir('modules/ingresos/');
}
if (!puede_gestionar_ingreso($u, $ingreso)) {
    denegar_acceso('Solo puedes ver los ingresos que tienes asignados.');
}

$urlDetalle = 'modules/ingresos/detalle.php?id=' . $idIngreso;
$errores    = ['diagnostico' => [], 'repuesto' => []];

// Valores del formulario de diagnóstico (los guardados, o lo enviado si hubo error)
$diag = [
    'diagnostico'        => (string) $ingreso['diagnostico'],
    'tipo_mantenimiento' => (string) $ingreso['tipo_mantenimiento'],
    'id_tecnico'         => (string) $ingreso['id_tecnico'],
];
$rep = ['descripcion' => '', 'cantidad' => '1', 'costo_unitario' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $post = fn(string $campo): string => is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';

    switch ($post('accion')) {
        case 'diagnostico':
            foreach (array_keys($diag) as $c) {
                $diag[$c] = $post($c);
            }
            $errores['diagnostico'] = guardar_diagnostico($idIngreso, $diag, $u);
            if (!$errores['diagnostico']) {
                guardar_mensaje('Diagnóstico guardado.', 'success');
                redirigir($urlDetalle);
            }
            break;

        case 'agregar_repuesto':
            foreach (array_keys($rep) as $c) {
                $rep[$c] = $post($c);
            }
            $errores['repuesto'] = agregar_repuesto($idIngreso, $rep, $u);
            if (!$errores['repuesto']) {
                guardar_mensaje('Repuesto agregado.', 'success');
                redirigir($urlDetalle . '#repuestos');
            }
            break;

        case 'quitar_repuesto':
            $idRepuesto = filter_var($post('id_repuesto'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $errores['repuesto'] = $idRepuesto
                ? quitar_repuesto($idIngreso, $idRepuesto, $u)
                : ['El repuesto no es válido.'];
            if (!$errores['repuesto']) {
                guardar_mensaje('Repuesto quitado.', 'success');
                redirigir($urlDetalle . '#repuestos');
            }
            break;

        default:
            http_response_code(400);
            exit('Acción no válida.');
    }

    // Hubo errores: recargar el ingreso por si cambió (ej. se cerró o se reasignó mientras tanto).
    $ingreso = obtener_ingreso($idIngreso);
    if (!puede_gestionar_ingreso($u, $ingreso)) {
        denegar_acceso('Solo puedes ver los ingresos que tienes asignados.');
    }
}

$abierto   = $ingreso['fecha_hora_egreso'] === null;
$repuestos = repuestos_de_ingreso($idIngreso);
$total     = costo_total_ingreso($idIngreso);
$tecnicos  = es_administrador() ? tecnicos_para_asignar($ingreso['id_tecnico'] !== null ? (int) $ingreso['id_tecnico'] : null) : [];

plantilla_inicio('Ingreso ' . $ingreso['placa']);
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">
            <?= e($ingreso['placa']) ?>
            <small class="text-muted fs-6"><?= e($ingreso['marca'] . ' ' . $ingreso['modelo']) ?></small>
        </h1>
        <?php if ($abierto): ?>
            <span class="badge bg-warning text-dark">En el taller</span>
        <?php else: ?>
            <span class="badge bg-success">Con egreso</span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($abierto): ?>
            <a href="<?= e(url('modules/ingresos/egreso.php?id=' . $idIngreso)) ?>" class="btn btn-success">Registrar egreso</a>
        <?php endif; ?>
        <a href="<?= e(url('modules/ingresos/')) ?>" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <!-- Datos del ingreso -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 card-title">Ingreso</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Fecha de ingreso</dt>
                    <dd class="col-7"><?= e(formato_fecha_hora($ingreso['fecha_hora_ingreso'])) ?></dd>
                    <dt class="col-5">Kilometraje</dt>
                    <dd class="col-7"><?= e(formato_numero($ingreso['kilometraje_ingreso'])) ?> km</dd>
                    <dt class="col-5">Falla reportada</dt>
                    <dd class="col-7"><?= nl2br(e($ingreso['falla_reportada'])) ?></dd>
                    <?php if (!$abierto): ?>
                        <dt class="col-5">Fecha de egreso</dt>
                        <dd class="col-7"><?= e(formato_fecha_hora($ingreso['fecha_hora_egreso'])) ?></dd>
                        <dt class="col-5">Trabajo realizado</dt>
                        <dd class="col-7 mb-0"><?= nl2br(e($ingreso['trabajo_realizado'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Diagnóstico -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 card-title">Diagnóstico</h2>

                <?php if (!$abierto): ?>
                    <dl class="row small mb-0">
                        <dt class="col-5">Diagnóstico</dt>
                        <dd class="col-7"><?= $ingreso['diagnostico'] !== null ? nl2br(e($ingreso['diagnostico'])) : '<span class="text-muted">Sin registrar</span>' ?></dd>
                        <dt class="col-5">Tipo de mantenimiento</dt>
                        <dd class="col-7"><?= $ingreso['tipo_mantenimiento'] !== null ? e(TIPOS_MANTENIMIENTO[$ingreso['tipo_mantenimiento']]) : '<span class="text-muted">Sin registrar</span>' ?></dd>
                        <dt class="col-5">Técnico encargado</dt>
                        <dd class="col-7 mb-0"><?= $ingreso['nombre_tecnico'] !== null ? e($ingreso['nombre_tecnico']) : '<span class="text-muted">Sin asignar</span>' ?></dd>
                    </dl>
                <?php else: ?>
                    <?php mostrar_errores($errores['diagnostico']); ?>
                    <form method="post" action="<?= e(url($urlDetalle)) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="diagnostico">

                        <div class="mb-3">
                            <label for="diagnostico" class="form-label">Diagnóstico</label>
                            <textarea id="diagnostico" name="diagnostico" class="form-control" rows="3"
                                      maxlength="2000" required><?= e($diag['diagnostico']) ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="tipo_mantenimiento" class="form-label">Tipo de mantenimiento</label>
                                <select id="tipo_mantenimiento" name="tipo_mantenimiento" class="form-select" required>
                                    <option value="">Selecciona…</option>
                                    <?php foreach (TIPOS_MANTENIMIENTO as $valor => $texto): ?>
                                        <option value="<?= e($valor) ?>" <?= $diag['tipo_mantenimiento'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="id_tecnico" class="form-label">Técnico encargado</label>
                                <?php if (es_administrador()): ?>
                                    <select id="id_tecnico" name="id_tecnico" class="form-select">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($tecnicos as $t): ?>
                                            <option value="<?= (int) $t['id_tecnico'] ?>" <?= $diag['id_tecnico'] === (string) $t['id_tecnico'] ? 'selected' : '' ?>>
                                                <?= e($t['nombre'] . ' — ' . $t['especialidad'] . ((int) $t['activo'] ? '' : ' (inactivo)')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" id="id_tecnico" class="form-control" value="<?= e($ingreso['nombre_tecnico']) ?>" disabled>
                                    <div class="form-text">Solo el administrador cambia el técnico.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Guardar diagnóstico</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Repuestos y costos -->
<div class="card shadow-sm mt-3" id="repuestos">
    <div class="card-body">
        <h2 class="h5 card-title">Repuestos y costos</h2>

        <?php mostrar_errores($errores['repuesto']); ?>

        <div class="table-responsive">
            <table class="table align-middle mb-3">
                <thead class="table-light">
                    <tr>
                        <th>Descripción</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Costo unitario</th>
                        <th class="text-end">Subtotal</th>
                        <?php if ($abierto): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$repuestos): ?>
                    <tr><td colspan="<?= $abierto ? 5 : 4 ?>" class="text-muted text-center">No hay repuestos registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($repuestos as $r): ?>
                    <tr>
                        <td><?= e($r['descripcion']) ?></td>
                        <td class="text-end"><?= e(formato_numero($r['cantidad'])) ?></td>
                        <td class="text-end text-nowrap"><?= e(formato_pesos($r['costo_unitario'])) ?></td>
                        <td class="text-end text-nowrap"><?= e(formato_pesos($r['subtotal'])) ?></td>
                        <?php if ($abierto): ?>
                            <td class="text-end">
                                <form method="post" action="<?= e(url($urlDetalle)) ?>"
                                      onsubmit="return confirm('¿Quitar este repuesto?');">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="quitar_repuesto">
                                    <input type="hidden" name="id_repuesto" value="<?= (int) $r['id_repuesto'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Costo total</td>
                        <td class="text-end text-nowrap" id="costo-total"><?= e(formato_pesos($total)) ?></td>
                        <?php if ($abierto): ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($abierto): ?>
            <form method="post" action="<?= e(url($urlDetalle . '#repuestos')) ?>" class="row g-2 align-items-end">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="agregar_repuesto">
                <div class="col-md-6">
                    <label for="descripcion" class="form-label small mb-1">Repuesto</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control"
                           value="<?= e($rep['descripcion']) ?>" maxlength="150" required>
                </div>
                <div class="col-4 col-md-2">
                    <label for="cantidad" class="form-label small mb-1">Cantidad</label>
                    <input type="number" id="cantidad" name="cantidad" class="form-control"
                           value="<?= e($rep['cantidad']) ?>" min="1" step="1" required>
                </div>
                <div class="col-8 col-md-2">
                    <label for="costo_unitario" class="form-label small mb-1">Costo unitario estimado ($)</label>
                    <input type="number" id="costo_unitario" name="costo_unitario" class="form-control"
                           value="<?= e($rep['costo_unitario']) ?>" min="0" step="0.01" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php plantilla_fin(); ?>
