<?php
/**
 * Usuarios del sistema. Solo administrador.
 * Crear, restablecer contraseña, activar y desactivar.
 */

require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/logica.php';
requiere_perfil('administrador');

$yo = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $id     = filter_var($_POST['id_usuario'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!in_array($accion, ['activar', 'desactivar'], true) || !$id) {
        http_response_code(400);
        exit('Solicitud no válida.');
    }
    $errores = cambiar_activo_usuario($id, $accion === 'activar', $yo['id_usuario']);
    if ($errores) {
        guardar_mensaje(implode(' ', $errores), 'danger');
    } else {
        $nombre = obtener_usuario($id)['usuario'];
        guardar_mensaje($accion === 'activar'
            ? "El usuario $nombre puede volver a iniciar sesión."
            : "El usuario $nombre quedó desactivado: ya no puede iniciar sesión.", 'success');
    }
    redirigir('modules/usuarios/');
}

$usuarios = listar_usuarios();

$titulo = 'Usuarios';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Usuarios</h1>
    <a href="<?= e(url('modules/usuarios/formulario.php')) ?>" class="btn btn-primary">+ Crear usuario</a>
</div>

<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Usuario</th>
                <th>Perfil</th>
                <th>Técnico</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $usr): $activo = (int) $usr['activo'] === 1; $soyYo = (int) $usr['id_usuario'] === $yo['id_usuario']; ?>
            <tr class="<?= $activo ? '' : 'text-muted' ?>">
                <td>
                    <strong><?= e($usr['usuario']) ?></strong>
                    <?php if ($soyYo): ?><span class="badge text-bg-light border">Tú</span><?php endif; ?>
                </td>
                <td><?= e(nombre_perfil($usr['perfil'])) ?></td>
                <td>
                    <?php if ($usr['nombre_tecnico'] !== null): ?>
                        <?= e($usr['nombre_tecnico']) ?>
                        <?php if (!(int) $usr['tecnico_activo']): ?>
                            <div class="small text-danger">Técnico inactivo: no puede entrar</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($soyYo): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('cambiar_clave.php')) ?>">Cambiar mi contraseña</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('modules/usuarios/clave.php?id=' . $usr['id_usuario'])) ?>">Restablecer contraseña</a>
                        <form method="post" class="d-inline"
                              <?php if ($activo): ?>
                                  data-confirmar="<?= e('¿Desactivar al usuario ' . $usr['usuario'] . '? No podrá iniciar sesión.') ?>"
                                  onsubmit="return confirm(this.dataset.confirmar);"
                              <?php endif; ?>>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id_usuario" value="<?= (int) $usr['id_usuario'] ?>">
                            <?php if ($activo): ?>
                                <button type="submit" name="accion" value="desactivar" class="btn btn-sm btn-outline-danger">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="accion" value="activar" class="btn btn-sm btn-outline-secondary">Activar</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="small text-muted mt-2 mb-0">
    Cada técnico puede tener un usuario. Siempre debe quedar al menos un administrador activo.
</p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
