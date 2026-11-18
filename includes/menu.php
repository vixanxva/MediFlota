<?php
/**
 * Menú principal por perfil (issue #5).
 *
 * El menú ya tiene TODOS los módulos. Una opción se activa sola cuando
 * existe modules/<modulo>/index.php, así que al crear un módulo nuevo
 * no hay que editar este archivo (y se evitan conflictos al fusionar).
 *
 * Para cambiar quién ve una opción, edita su lista de 'perfiles'.
 */

require_once __DIR__ . '/sesion.php';

const MENU_PRINCIPAL = [
    [
        'titulo'      => 'Panel',
        'ruta'        => 'modules/panel/',
        'descripcion' => 'Disponibilidad de la flota: vehículos disponibles, en mantenimiento y fuera de servicio.',
        'perfiles'    => ['administrador', 'tecnico'],
    ],
    [
        'titulo'      => 'Vehículos',
        'ruta'        => 'modules/vehiculos/',
        'descripcion' => 'Registrar, editar y desactivar los vehículos de la flota.',
        'perfiles'    => ['administrador'],
    ],
    [
        'titulo'      => 'Técnicos',
        'ruta'        => 'modules/tecnicos/',
        'descripcion' => 'Registrar y editar los técnicos del taller.',
        'perfiles'    => ['administrador'],
    ],
    [
        'titulo'      => 'Ingresos y egresos',
        'ruta'        => 'modules/ingresos/',
        'descripcion' => 'Entradas y salidas del taller, diagnóstico, repuestos y costos.',
        'perfiles'    => ['administrador', 'tecnico'],
    ],
    [
        'titulo'      => 'Historial',
        'ruta'        => 'modules/historial/',
        'descripcion' => 'Mantenimientos de cada vehículo y su costo acumulado.',
        'perfiles'    => ['administrador', 'tecnico'],
    ],
    [
        'titulo'      => 'Usuarios',
        'ruta'        => 'modules/usuarios/',
        'descripcion' => 'Cuentas para entrar al sistema y sus contraseñas.',
        'perfiles'    => ['administrador'],
    ],
];

/**
 * Opciones del menú que puede ver el usuario conectado. Cada una trae además:
 *   'disponible' => true si el módulo ya existe (tiene index.php)
 *   'actual'     => true si es el módulo de la página que se está viendo
 */
function menu_del_usuario(): array
{
    $perfil = usuario_actual()['perfil'] ?? null;
    $pagina = $_SERVER['SCRIPT_NAME'] ?? '';
    $opciones = [];

    foreach (MENU_PRINCIPAL as $opcion) {
        if (!in_array($perfil, $opcion['perfiles'], true)) {
            continue;
        }
        $opcion['disponible'] = is_file(__DIR__ . '/../' . $opcion['ruta'] . 'index.php');
        $opcion['actual']     = stripos($pagina, url($opcion['ruta'])) === 0;
        $opciones[] = $opcion;
    }
    return $opciones;
}

/** Nombre del perfil para mostrar: 'tecnico' -> 'Técnico'. */
function nombre_perfil(?string $perfil): string
{
    return ['administrador' => 'Administrador', 'tecnico' => 'Técnico'][$perfil] ?? (string) $perfil;
}
