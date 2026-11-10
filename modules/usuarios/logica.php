<?php
/**
 * Lógica de usuarios y contraseñas.
 *
 * - crear_administrador_inicial(): solo funciona con la tabla usuario vacía
 *   (instalación en el taller, ver instalar.php).
 * - Usuarios (solo administrador): crear, restablecer contraseña, activar y desactivar.
 * - cambiar_mi_clave(): cualquier usuario conectado, con su contraseña actual.
 *
 * Reglas:
 * - Nombre de usuario único, en minúsculas: letras, números, punto, guion y guion bajo.
 * - Contraseña de 8 a 72 caracteres, distinta del nombre de usuario.
 *   Se guarda solo el hash (password_hash).
 * - Un usuario técnico se enlaza a un técnico activo que aún no tenga usuario.
 * - Siempre debe quedar al menos un administrador activo, y nadie se
 *   desactiva a sí mismo.
 *
 * Las funciones que modifican devuelven un arreglo de errores;
 * si está vacío, el cambio se guardó.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/funciones.php';

const CLAVE_MINIMO = 8;
const CLAVE_MAXIMO = 72;   // password_hash() con bcrypt solo usa los primeros 72 bytes

// -------------------------------------------------------------
// Consultas
// -------------------------------------------------------------

/** true si todavía no existe ningún usuario (instalación nueva). */
function hay_usuarios(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM usuario')->fetchColumn() > 0;
}

/** Usuarios con el nombre del técnico enlazado, activos primero. */
function listar_usuarios(): array
{
    return db()->query(
        'SELECT u.id_usuario, u.usuario, u.perfil, u.activo, u.id_tecnico,
                t.nombre AS nombre_tecnico, t.activo AS tecnico_activo
           FROM usuario u
           LEFT JOIN tecnico t ON t.id_tecnico = u.id_tecnico
          ORDER BY u.activo DESC, u.perfil, u.usuario'
    )->fetchAll();
}

/** Un usuario por su id (sin el hash), o null si no existe. */
function obtener_usuario(int $idUsuario): ?array
{
    $stmt = db()->prepare(
        'SELECT u.id_usuario, u.usuario, u.perfil, u.activo, u.id_tecnico, t.nombre AS nombre_tecnico
           FROM usuario u
           LEFT JOIN tecnico t ON t.id_tecnico = u.id_tecnico
          WHERE u.id_usuario = ?'
    );
    $stmt->execute([$idUsuario]);
    return $stmt->fetch() ?: null;
}

/** Técnicos activos que todavía no tienen usuario (para enlazarlos a uno nuevo). */
function tecnicos_sin_usuario(): array
{
    return db()->query(
        'SELECT t.id_tecnico, t.nombre, t.documento
           FROM tecnico t
          WHERE t.activo = 1
            AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_tecnico = t.id_tecnico)
          ORDER BY t.nombre'
    )->fetchAll();
}

// -------------------------------------------------------------
// Validación
// -------------------------------------------------------------

/** ' Carlos.Petro ' -> 'carlos.petro' */
function normalizar_usuario(string $usuario): string
{
    return strtolower(trim($usuario));
}

/** Errores del nombre de usuario (vacío si es válido). */
function validar_nombre_usuario(string $usuario): array
{
    if ($usuario === '') {
        return ['Escribe el nombre de usuario.'];
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $usuario)) {
        return ['El usuario debe tener de 3 a 50 caracteres: letras sin tildes, números, punto, guion o guion bajo (sin espacios).'];
    }
    return [];
}

/** Errores de una contraseña nueva y su confirmación (vacío si es válida). */
function validar_clave_nueva(string $clave, string $confirmacion, string $usuario): array
{
    $errores = [];
    if (mb_strlen($clave) < CLAVE_MINIMO) {
        $errores[] = 'La contraseña debe tener al menos ' . CLAVE_MINIMO . ' caracteres.';
    } elseif (strlen($clave) > CLAVE_MAXIMO) {
        $errores[] = 'La contraseña no puede superar ' . CLAVE_MAXIMO . ' caracteres.';
    } elseif ($usuario !== '' && strcasecmp($clave, $usuario) === 0) {
        $errores[] = 'La contraseña no puede ser igual al nombre de usuario.';
    }
    if ($clave !== $confirmacion) {
        $errores[] = 'La confirmación no coincide con la contraseña.';
    }
    return $errores;
}

/** Mensaje amigable para una llave única repetida (usuario o técnico ya enlazado). */
function mensaje_usuario_repetido(PDOException $e, string $usuario): ?string
{
    if (($e->errorInfo[1] ?? null) !== 1062) {
        return null;
    }
    return strpos($e->getMessage(), 'uq_usuario_id_tecnico') !== false
        ? 'Ese técnico ya tiene un usuario.'
        : "Ya existe el usuario «{$usuario}».";
}

// -------------------------------------------------------------
// Modificaciones
// -------------------------------------------------------------

/**
 * Crea el primer administrador. Solo funciona si no hay ningún usuario.
 * Un candado con nombre (GET_LOCK) hace que dos instalaciones simultáneas
 * pasen una por una: la primera lo crea y la segunda ve que ya hay usuarios.
 * (Bloquear la tabla vacía con FOR UPDATE no sirve: provoca deadlocks.)
 */
function crear_administrador_inicial(string $usuario, string $clave, string $confirmacion): array
{
    $usuario = normalizar_usuario($usuario);
    $errores = array_merge(validar_nombre_usuario($usuario), validar_clave_nueva($clave, $confirmacion, $usuario));
    if ($errores) {
        return $errores;
    }

    $pdo = db();
    if ((string) $pdo->query("SELECT GET_LOCK('mediflota_instalacion', 10)")->fetchColumn() !== '1') {
        return ['Otra persona está instalando el sistema en este momento. Inténtalo de nuevo.'];
    }
    try {
        if (hay_usuarios()) {
            return ['El sistema ya tiene usuarios. Inicia sesión con uno de ellos.'];
        }
        $pdo->prepare("INSERT INTO usuario (usuario, password_hash, perfil) VALUES (?, ?, 'administrador')")
            ->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT)]);
        return [];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mediflota_instalacion')");
    }
}

/**
 * Crea un usuario (lo hace el administrador).
 * $datos: usuario, perfil ('administrador' | 'tecnico'), id_tecnico (si es técnico), clave, confirmacion
 */
function crear_usuario(array $datos, ?int &$idUsuario = null): array
{
    $usuario = normalizar_usuario((string) ($datos['usuario'] ?? ''));
    $perfil  = (string) ($datos['perfil'] ?? '');
    $errores = validar_nombre_usuario($usuario);

    $idTecnico = null;
    if (!in_array($perfil, ['administrador', 'tecnico'], true)) {
        $errores[] = 'Selecciona el perfil.';
    } elseif ($perfil === 'tecnico') {
        $idTecnico = filter_var($datos['id_tecnico'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idTecnico === false) {
            $errores[] = 'Selecciona el técnico al que pertenece este usuario.';
        }
    }

    $errores = array_merge($errores, validar_clave_nueva((string) ($datos['clave'] ?? ''), (string) ($datos['confirmacion'] ?? ''), $usuario));
    if ($errores) {
        return $errores;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($idTecnico !== null) {
            // Bloquea al técnico para que no lo desactiven mientras se le crea el usuario.
            $stmt = $pdo->prepare('SELECT activo FROM tecnico WHERE id_tecnico = ? LOCK IN SHARE MODE');
            $stmt->execute([$idTecnico]);
            if ((string) $stmt->fetchColumn() !== '1') {
                $pdo->rollBack();
                return ['El técnico seleccionado no existe o está inactivo.'];
            }
        }
        $pdo->prepare('INSERT INTO usuario (usuario, password_hash, perfil, id_tecnico) VALUES (?, ?, ?, ?)')
            ->execute([$usuario, password_hash((string) $datos['clave'], PASSWORD_DEFAULT), $perfil, $idTecnico]);
        $idUsuario = (int) $pdo->lastInsertId();
        $pdo->commit();
        return [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && ($mensaje = mensaje_usuario_repetido($e, $usuario))) {
            return [$mensaje];
        }
        throw $e;
    }
}

/** El administrador asigna una contraseña nueva a otro usuario (por ejemplo, si la olvidó). */
function restablecer_clave(int $idUsuario, string $clave, string $confirmacion): array
{
    $u = obtener_usuario($idUsuario);
    if (!$u) {
        return ['El usuario no existe.'];
    }
    $errores = validar_clave_nueva($clave, $confirmacion, $u['usuario']);
    if ($errores) {
        return $errores;
    }
    db()->prepare('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?')
        ->execute([password_hash($clave, PASSWORD_DEFAULT), $idUsuario]);
    return [];
}

/** El usuario conectado cambia su propia contraseña. */
function cambiar_mi_clave(int $idUsuario, string $actual, string $clave, string $confirmacion): array
{
    $stmt = db()->prepare('SELECT usuario, password_hash FROM usuario WHERE id_usuario = ?');
    $stmt->execute([$idUsuario]);
    $fila = $stmt->fetch();
    if (!$fila) {
        return ['El usuario no existe.'];
    }
    if (!password_verify($actual, $fila['password_hash'])) {
        return ['La contraseña actual no es correcta.'];
    }
    $errores = validar_clave_nueva($clave, $confirmacion, $fila['usuario']);
    if (!$errores && $clave === $actual) {
        $errores[] = 'La contraseña nueva debe ser distinta de la actual.';
    }
    if ($errores) {
        return $errores;
    }
    db()->prepare('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?')
        ->execute([password_hash($clave, PASSWORD_DEFAULT), $idUsuario]);
    return [];
}

/**
 * Activa o desactiva un usuario. No se puede desactivar uno mismo ni al
 * último administrador activo. Un usuario desactivado pierde la sesión
 * en su siguiente clic (requiere_login() lo revisa en cada página).
 */
function cambiar_activo_usuario(int $idUsuario, bool $activo, int $idUsuarioActual): array
{
    if (!$activo && $idUsuario === $idUsuarioActual) {
        return ['No puedes desactivar tu propio usuario.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Bloquea a todos los administradores: dos administradores no pueden
        // desactivarse uno al otro al mismo tiempo y dejar el sistema sin ninguno.
        $admins = $pdo->query("SELECT id_usuario, activo FROM usuario WHERE perfil = 'administrador' FOR UPDATE")->fetchAll();
        $stmt = $pdo->prepare('SELECT usuario, perfil, activo FROM usuario WHERE id_usuario = ? FOR UPDATE');
        $stmt->execute([$idUsuario]);
        $u = $stmt->fetch();

        $errores = [];
        if (!$u) {
            $errores[] = 'El usuario no existe.';
        } elseif (!$activo && $u['perfil'] === 'administrador' && (int) $u['activo'] === 1) {
            $activos = count(array_filter($admins, fn($a) => (int) $a['activo'] === 1));
            if ($activos <= 1) {
                $errores[] = 'No se puede desactivar al último administrador activo.';
            }
        }

        if (!$errores) {
            $pdo->prepare('UPDATE usuario SET activo = ? WHERE id_usuario = ?')->execute([$activo ? 1 : 0, $idUsuario]);
        }
        $errores ? $pdo->rollBack() : $pdo->commit();
        return $errores;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
