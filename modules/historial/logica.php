<?php
/**
 * Consultas del historial de mantenimientos por vehículo (RF10).
 * Solo lee datos: no modifica nada.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

/**
 * Vehículos para el selector: todos, también los desactivados
 * (su historial se conserva), con cuántos ingresos tiene cada uno.
 */
function vehiculos_para_historial(): array
{
    return db()->query(
        'SELECT v.id_vehiculo, v.placa, v.marca, v.modelo, v.activo,
                (SELECT COUNT(*) FROM ingreso i WHERE i.id_vehiculo = v.id_vehiculo) AS total_ingresos
           FROM vehiculo v
          ORDER BY v.activo DESC, v.placa'
    )->fetchAll();
}

/** Un vehículo por su id, o null si no existe. */
function vehiculo_del_historial(int $idVehiculo): ?array
{
    $stmt = db()->prepare('SELECT * FROM vehiculo WHERE id_vehiculo = ?');
    $stmt->execute([$idVehiculo]);
    return $stmt->fetch() ?: null;
}

/**
 * Todos los ingresos de un vehículo, del más reciente al más antiguo,
 * con el técnico y el costo de cada uno (suma de sus repuestos).
 */
function historial_de_vehiculo(int $idVehiculo): array
{
    $stmt = db()->prepare(
        'SELECT i.id_ingreso, i.id_tecnico, i.fecha_hora_ingreso, i.kilometraje_ingreso, i.falla_reportada,
                i.diagnostico, i.tipo_mantenimiento, i.fecha_hora_egreso, i.trabajo_realizado,
                t.nombre AS nombre_tecnico,
                (SELECT COALESCE(SUM(r.cantidad * r.costo_unitario), 0)
                   FROM repuesto r WHERE r.id_ingreso = i.id_ingreso) AS costo_total
           FROM ingreso i
           LEFT JOIN tecnico t ON t.id_tecnico = i.id_tecnico
          WHERE i.id_vehiculo = ?
          ORDER BY i.fecha_hora_ingreso DESC, i.id_ingreso DESC'
    );
    $stmt->execute([$idVehiculo]);
    return $stmt->fetchAll();
}

/**
 * Resumen del historial (ordenado como lo devuelve historial_de_vehiculo()):
 *   costo_acumulado  suma del costo de todos los ingresos (incluido el que esté en curso)
 *   costo_por_tipo   ['preventivo' => ..., 'correctivo' => ..., 'sin_tipo' => ...]
 *   ultimo           el último mantenimiento TERMINADO (con egreso), o null
 *   en_curso         el ingreso abierto, si el vehículo está en el taller, o null
 */
function resumen_historial(array $ingresos): array
{
    $resumen = [
        'costo_acumulado' => 0.0,
        'costo_por_tipo'  => ['preventivo' => 0.0, 'correctivo' => 0.0, 'sin_tipo' => 0.0],
        'ultimo'          => null,
        'en_curso'        => null,
    ];

    foreach ($ingresos as $i) {
        $costo = (float) $i['costo_total'];
        $resumen['costo_acumulado'] += $costo;
        $resumen['costo_por_tipo'][$i['tipo_mantenimiento'] ?? 'sin_tipo'] += $costo;

        if ($i['fecha_hora_egreso'] === null) {
            $resumen['en_curso'] = $resumen['en_curso'] ?? $i;
        } elseif ($resumen['ultimo'] === null || $i['fecha_hora_egreso'] > $resumen['ultimo']['fecha_hora_egreso']) {
            $resumen['ultimo'] = $i;
        }
    }
    return $resumen;
}
