<?php
/**
 * Cerrar sesión.
 */

require_once __DIR__ . '/includes/sesion.php';

cerrar_sesion();

// Sesión nueva y vacía, solo para mostrar el aviso en el login.
session_start();
guardar_mensaje('Cerraste sesión correctamente.', 'success');
redirigir('index.php');
