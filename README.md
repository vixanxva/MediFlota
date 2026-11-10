# MediFlota

Sistema de información web para el control de ingresos, egresos y mantenimiento de los vehículos del taller de la Entidad de Salud **Vida Sinú** (Montería, Córdoba).

Proyecto académico — Sistemas de Información y Gestión, Universidad del Sinú (2026-2).
Equipo: Andrés Calderín, Sebastián Caraballo y Jeison Villanueva. Docente: Pabel López.

## Alcance

| Módulo | Requisitos |
|---|---|
| Usuarios (administrador / técnico) | RF01 |
| Vehículos | RF02 |
| Técnicos | RF03 |
| Ingresos y egresos | RF04, RF07, RF08 |
| Fallas y costos | RF05, RF06 |
| Consultas (disponibilidad e historial) | RF09, RF10 |

## Tecnologías

- PHP 8 + MySQL/MariaDB (XAMPP)
- HTML, CSS y Bootstrap 5
- PDO con consultas preparadas, `password_hash` para contraseñas

## Instalación local

1. Instalar [XAMPP](https://www.apachefriends.org/) e iniciar Apache y MySQL.
2. Clonar el repositorio dentro de `C:\xampp\htdocs\`:
   ```bash
   git clone https://github.com/vixanxva/MediFlota.git
   ```
3. En phpMyAdmin, importar `database/schema.sql` y luego `database/seed.sql`.
4. Abrir `http://localhost/MediFlota/`.

## Estructura de carpetas

```
MediFlota/
├── config/      # conexión a la base de datos
├── database/    # schema.sql y seed.sql
├── docs/        # pruebas funcionales y documentación
├── includes/    # header.php, footer.php, menu.php, sesión y funciones de ayuda
├── modules/     # una carpeta por módulo (panel, vehiculos, tecnicos, ingresos, historial)
├── assets/      # css/estilos.css, js, img/logo.svg
├── index.php    # login
├── inicio.php   # página de inicio tras el login
└── logout.php   # cerrar sesión
```

Si tu MySQL tiene contraseña u otra configuración, copia `config/config.local.example.php` como `config/config.local.php` y cambia ahí lo necesario (ese archivo no se sube a git).

## Cómo crear una página protegida

Cada página de `modules/<modulo>/` empieza así:

```php
<?php
require_once __DIR__ . '/../../includes/sesion.php';
requiere_perfil('administrador');              // solo administradores
// requiere_perfil('administrador', 'tecnico'); // ambos perfiles
// requiere_login();                            // cualquier usuario con sesión

// ...consultas y procesamiento del formulario (antes de imprimir HTML)...

$titulo = 'Vehículos';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Vehículos</h1>
<!-- contenido de la página -->
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- `header.php` pone Bootstrap, los estilos, la barra de navegación según el perfil y el mensaje guardado con `guardar_mensaje()`. `footer.php` cierra la página.
- Las redirecciones (`redirigir()`) van **antes** del `header.php`, porque después ya se envió HTML.
- **El menú no se edita:** ya tiene todos los módulos (`includes/menu.php`). La opción aparece en gris ("en construcción") hasta que exista `modules/<modulo>/index.php`; al crear ese archivo se activa sola.
- Cuando exista `modules/panel/index.php` (#8), `inicio.php` redirige allá y el panel pasa a ser la página principal.
- Formularios de una columna: envolverlos en `<div class="contenido-angosto">`. Los estilos propios van en `assets/css/estilos.css`.

Funciones disponibles (`includes/sesion.php` y `includes/funciones.php`):

| Función | Para qué |
|---|---|
| `db()` | Conexión PDO. Usar siempre consultas preparadas: `db()->prepare('... WHERE id = ?')`. |
| `usuario_actual()` | Datos del usuario conectado: `usuario`, `perfil`, `id_tecnico`, `nombre`. |
| `es_administrador()` | `true` si el perfil es administrador. |
| `e($texto)` | Escapa texto para imprimirlo en HTML. Usarla en todo lo que venga de la BD o de formularios. |
| `url('modules/vehiculos/')` | URL dentro del proyecto, sin importar la carpeta donde esté instalado. |
| `redirigir('inicio.php')` | Redirige y termina. |
| `csrf_campo()` / `verificar_csrf()` | Poner `<?= csrf_campo() ?>` en cada formulario POST y llamar `verificar_csrf()` al procesarlo. |
| `guardar_mensaje($texto, $tipo)` / `tomar_mensaje()` | Mensaje para mostrar en la siguiente página (ej. "Vehículo guardado"). |
| `mostrar_errores($errores)` | Lista de errores de validación en una alerta roja (no imprime nada si está vacía). |
| `etiqueta_estado_vehiculo($estado)` | Etiqueta de color del estado: verde disponible, amarillo en mantenimiento, rojo fuera de servicio. Textos en `ESTADOS_VEHICULO`. |
| `etiqueta_rtm($vencimiento)` / `estado_rtm($vencimiento)` | Fecha de la revisión técnico-mecánica con etiqueta «Vencida» o «Por vencer» (`DIAS_AVISO_RTM` = 30 días antes). |
| `TIPOS_VEHICULO` | `['ambulancia' => 'Ambulancia', 'otro' => 'Otro']`. |
| `formato_fecha()`, `formato_fecha_hora()`, `formato_numero()`, `formato_pesos()` | `09/11/2026`, `09/11/2026 2:20 p. m.`, `152.300`, `$ 1.450.000`. |

## Flujo de trabajo

- `main`: versión estable, la que se entrega e instala en el taller.
- `develop`: integración del trabajo del equipo. **Todas las ramas salen de aquí.**
- Por cada tarea, crear una rama desde `develop`: `feature/<numero-issue>-<nombre-corto>` (ej. `feature/6-crud-vehiculos`).
- Al terminar, abrir un Pull Request hacia `develop` y escribir `Closes #<numero>` en la descripción.
- Cuando `develop` esté estable, se fusiona a `main`.

### Reglas de revisión

- **Nadie sube cambios directamente a `develop` ni a `main`.** Todo entra por Pull Request.
- Cada integrante abre un PR con su tarea o comenta en el issue correspondiente.
- **Solo Jeison Villanueva (@vixanxva) aprueba los PR**, después de revisarlos. Sin su aprobación, GitHub no permite la fusión.
- Si se solicitan cambios, se corrigen en la misma rama y el PR se actualiza solo.

```bash
git checkout develop
git pull
git checkout -b feature/6-crud-vehiculos
# ...cambios...
git add .
git commit -m "Agrega CRUD de vehículos"
git push -u origin feature/6-crud-vehiculos
```

## Niveles de tareas

| Etiqueta | Tipo de tarea |
|---|---|
| `nivel 1` | Pruebas, ingreso de datos y documentación. No requiere programar. |
| `nivel 2` | Requiere código: formularios, CRUD y consultas. |
| `nivel 3` | Lógica principal del sistema y base de datos. |
