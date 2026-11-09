<?php
/**
 * Funciones de ayuda usadas en todas las páginas.
 */

require_once __DIR__ . '/../config/config.php';

/** Escapa un valor para imprimirlo en HTML. Úsala en TODO lo que venga de la BD o del usuario. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Construye una URL absoluta dentro del proyecto. Ej.: url('modules/vehiculos/') */
function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/** Fecha y hora de la BD ('2026-11-09 14:20:00') como '09/11/2026 2:20 p. m.'. Vacío si es null. */
function formato_fecha_hora(?string $fechaHora): string
{
    if ($fechaHora === null || $fechaHora === '') {
        return '';
    }
    $t = strtotime($fechaHora);
    return date('d/m/Y g:i', $t) . (date('H', $t) < 12 ? ' a. m.' : ' p. m.');
}

/** Número con separador de miles colombiano: 152300 -> '152.300'. */
function formato_numero($numero, int $decimales = 0): string
{
    return number_format((float) $numero, $decimales, ',', '.');
}

/** Valor en pesos colombianos: 1450000 -> '$ 1.450.000'. Muestra centavos solo si los hay. */
function formato_pesos($valor): string
{
    $valor = (float) $valor;
    $decimales = ((int) round($valor * 100)) % 100 !== 0 ? 2 : 0;
    return '$ ' . formato_numero($valor, $decimales);
}

/** Une elementos en español: ['a'] -> 'a'; ['a','b','c'] -> 'a, b y c'. */
function unir_lista(array $elementos): string
{
    $ultimo = array_pop($elementos);
    return $elementos ? implode(', ', $elementos) . ' y ' . $ultimo : (string) $ultimo;
}

/** Imprime una lista de errores de validación en una alerta roja. No imprime nada si está vacía. */
function mostrar_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="alert alert-danger" role="alert"><ul class="mb-0">';
    foreach ($errores as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div>';
}

/** Estados de un vehículo (columna vehiculo.estado) y cómo se muestran. */
const ESTADOS_VEHICULO = [
    'disponible'       => ['texto' => 'Disponible',        'color' => 'text-bg-success'],
    'en_mantenimiento' => ['texto' => 'En mantenimiento',  'color' => 'text-bg-warning'],
    'fuera_servicio'   => ['texto' => 'Fuera de servicio', 'color' => 'text-bg-danger'],
];

/** Etiqueta de color para el estado de un vehículo: verde, amarillo o rojo. */
function etiqueta_estado_vehiculo(string $estado): string
{
    $info = ESTADOS_VEHICULO[$estado] ?? ['texto' => $estado, 'color' => 'text-bg-secondary'];
    return '<span class="badge ' . $info['color'] . '">' . e($info['texto']) . '</span>';
}

/** Redirige a una ruta del proyecto y termina la ejecución. */
function redirigir(string $ruta = ''): void
{
    header('Location: ' . url($ruta));
    exit;
}

/** Token CSRF de la sesión actual (se crea la primera vez). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto con el token CSRF. Ponlo dentro de cada <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verifica el token CSRF de un POST. Si no es válido, corta con error 400. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        exit('La solicitud no es válida o la sesión expiró. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}
