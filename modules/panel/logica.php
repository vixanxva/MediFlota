<?php
/**
 * Consultas del panel de disponibilidad de la flota (RF09).
 * Solo lee datos: no modifica nada.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

/**
 * Cuántos vehículos activos hay en cada estado.
 * Devuelve todos los estados, aunque tengan 0: ['disponible' => 3, ...].
 */
function contar_por_estado(): array
{
    $conteo = array_fill_keys(array_keys(ESTADOS_VEHICULO), 0);
    $filas = db()->query('SELECT estado, COUNT(*) AS total FROM vehiculo WHERE activo = 1 GROUP BY estado')->fetchAll();
    foreach ($filas as $f) {
        $conteo[$f['estado']] = (int) $f['total'];
    }
    return $conteo;
}

/**
 * Vehículos activos para la tabla del panel. Si están en el taller,
 * trae también su ingreso abierto (fecha y técnico).
 * $estado: uno de ESTADOS_VEHICULO, o '' para todos.
 */
function vehiculos_del_panel(string $estado = ''): array
{
    $sql = 'SELECT v.id_vehiculo, v.placa, v.tipo, v.marca, v.modelo, v.kilometraje,
                   v.vencimiento_rtm, v.estado,
                   i.id_ingreso, i.fecha_hora_ingreso, i.id_tecnico, t.nombre AS nombre_tecnico
              FROM vehiculo v
              LEFT JOIN ingreso i ON i.id_vehiculo = v.id_vehiculo AND i.fecha_hora_egreso IS NULL
              LEFT JOIN tecnico t ON t.id_tecnico = i.id_tecnico
             WHERE v.activo = 1';
    $parametros = [];
    if ($estado !== '') {
        $sql .= ' AND v.estado = ?';
        $parametros[] = $estado;
    }
    $sql .= ' ORDER BY v.placa';

    $stmt = db()->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

/** Vehículos activos con la revisión técnico-mecánica vencida o por vencer, la más urgente primero. */
function vehiculos_con_alerta_rtm(): array
{
    $stmt = db()->prepare(
        'SELECT placa, vencimiento_rtm
           FROM vehiculo
          WHERE activo = 1 AND vencimiento_rtm <= ?
          ORDER BY vencimiento_rtm, placa'
    );
    $stmt->execute([date('Y-m-d', strtotime('+' . DIAS_AVISO_RTM . ' days'))]);
    return $stmt->fetchAll();
}
