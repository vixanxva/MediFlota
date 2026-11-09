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
├── includes/    # header, footer, sesión y control de acceso
├── modules/     # una carpeta por módulo (vehiculos, tecnicos, ingresos, ...)
├── assets/      # css, js, imágenes
└── index.php    # login
```

## Flujo de trabajo

- `main`: versión estable, la que se entrega e instala en el taller.
- `develop`: integración del trabajo del equipo. **Todas las ramas salen de aquí.**
- Por cada tarea, crear una rama desde `develop`: `feature/<numero-issue>-<nombre-corto>` (ej. `feature/6-crud-vehiculos`).
- Al terminar, abrir un Pull Request hacia `develop` y escribir `Closes #<numero>` en la descripción.
- Cuando `develop` esté estable, se fusiona a `main`.

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
