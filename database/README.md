# Base de datos `mediflota`

MySQL/MariaDB · motor InnoDB · cotejamiento `utf8mb4_unicode_ci` · normalizada hasta 3FN.

## Instalación

Importar en este orden (phpMyAdmin → Importar, o por consola):

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database/seed.sql
```

Los dos archivos se pueden ejecutar varias veces: `schema.sql` borra y vuelve a crear las tablas, y `seed.sql` las vacía antes de insertar.

### Credenciales de prueba

| Usuario | Contraseña | Perfil |
|---|---|---|
| `admin` | `mediflota123` | administrador |
| `tecnico` | `mediflota123` | técnico (enlazado a Carlos Andrés Petro Hoyos) |

> ⚠️ Son solo para desarrollo. En el taller **no se importa `seed.sql`**. Con la tabla `usuario` vacía, el primer administrador se crea en `instalar.php`, y los demás usuarios en el módulo Usuarios (ver el README principal).

## Relaciones

```
tecnico 1 ──── 0..1 usuario        (un usuario técnico se enlaza a un técnico)
tecnico 1 ──── 0..N ingreso        (técnico asignado al ingreso)
vehiculo 1 ─── 0..N ingreso        (historial del vehículo)
ingreso 1 ──── 0..N repuesto       (se borran en cascada con el ingreso)
```

## Reglas de negocio

- **Costo total:** no se guarda. Se calcula con `SUM(cantidad * costo_unitario)` sobre `repuesto`.
- **Ingreso abierto:** `fecha_hora_egreso IS NULL`. Al registrar el egreso se llena esa fecha y `trabajo_realizado`.
- **Estado del vehículo:** un vehículo `en_mantenimiento` tiene exactamente un ingreso abierto; los demás estados no tienen ninguno. La aplicación debe mantener esta regla al registrar ingresos y egresos.
- **Kilometraje:** al registrar un ingreso, `vehiculo.kilometraje` se actualiza con `kilometraje_ingreso`, así nunca es menor que el del último ingreso.
- **Borrado lógico:** técnicos, usuarios y vehículos se desactivan con `activo = 0` en lugar de borrarse, para conservar el historial.

## Diccionario de datos

Convenciones: `TINYINT(1)` se usa como booleano (1 = sí, 0 = no). PK = llave primaria, FK = llave foránea, UQ = valor único.

### `tecnico`

Personal del taller que atiende los ingresos.

| Campo | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id_tecnico` | INT AUTO_INCREMENT | No | PK. |
| `nombre` | VARCHAR(100) | No | Nombre completo. |
| `documento` | VARCHAR(20) | No | UQ. Número de documento de identidad. |
| `telefono` | VARCHAR(20) | Sí | Teléfono de contacto. |
| `especialidad` | VARCHAR(60) | No | Área de especialidad (mecánica, electricidad, frenos…). |
| `activo` | TINYINT(1) | No | 1 = activo, 0 = desactivado. Por defecto 1. |

### `usuario`

Cuentas de acceso al sistema.

| Campo | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id_usuario` | INT AUTO_INCREMENT | No | PK. |
| `usuario` | VARCHAR(50) | No | UQ. Nombre de inicio de sesión. |
| `password_hash` | VARCHAR(255) | No | Hash generado con `password_hash()` de PHP. Nunca la contraseña en texto plano. |
| `perfil` | ENUM('administrador','tecnico') | No | Rol del usuario. |
| `id_tecnico` | INT | Sí | FK → `tecnico.id_tecnico`, UQ. Obligatorio si `perfil = 'tecnico'` (restricción CHECK). |
| `activo` | TINYINT(1) | No | 1 = puede iniciar sesión, 0 = bloqueado. Por defecto 1. |

### `vehiculo`

Ambulancias y demás vehículos de la entidad.

| Campo | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id_vehiculo` | INT AUTO_INCREMENT | No | PK. |
| `placa` | VARCHAR(10) | No | UQ. Placa del vehículo (ej. ABC123). |
| `tipo` | ENUM('ambulancia','otro') | No | Tipo de vehículo. |
| `marca` | VARCHAR(50) | No | Marca del fabricante. |
| `modelo` | VARCHAR(50) | No | Línea y año (ej. Sprinter 515 2021). |
| `kilometraje` | INT | No | Kilometraje actual (≥ 0). Por defecto 0. |
| `vencimiento_rtm` | DATE | No | Fecha de vencimiento de la revisión técnico-mecánica. |
| `estado` | ENUM('disponible','en_mantenimiento','fuera_servicio') | No | Estado operativo. Por defecto `disponible`. Indexado. |
| `activo` | TINYINT(1) | No | 1 = activo, 0 = desactivado sin borrar su historial. Por defecto 1. |

### `ingreso`

Entrada de un vehículo al taller y su posterior egreso.

| Campo | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id_ingreso` | INT AUTO_INCREMENT | No | PK. |
| `id_vehiculo` | INT | No | FK → `vehiculo.id_vehiculo`. Indexado. |
| `fecha_hora_ingreso` | DATETIME | No | Fecha y hora de entrada al taller. |
| `kilometraje_ingreso` | INT | No | Kilometraje al ingresar (≥ 0). |
| `falla_reportada` | TEXT | No | Falla o motivo informado al ingresar. |
| `diagnostico` | TEXT | Sí | Diagnóstico del técnico. |
| `tipo_mantenimiento` | ENUM('preventivo','correctivo') | Sí | Se define al diagnosticar. |
| `id_tecnico` | INT | Sí | FK → `tecnico.id_tecnico`. Técnico asignado. Indexado. |
| `fecha_hora_egreso` | DATETIME | Sí | Fecha y hora de salida. NULL = ingreso abierto. No puede ser anterior al ingreso (CHECK). |
| `trabajo_realizado` | TEXT | Sí | Descripción del trabajo hecho, se llena al dar egreso. |

### `repuesto`

Repuestos e insumos usados en un ingreso.

| Campo | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id_repuesto` | INT AUTO_INCREMENT | No | PK. |
| `id_ingreso` | INT | No | FK → `ingreso.id_ingreso`, `ON DELETE CASCADE`. Indexado. |
| `descripcion` | VARCHAR(150) | No | Nombre del repuesto o insumo. |
| `cantidad` | INT | No | Unidades usadas (> 0). |
| `costo_unitario` | DECIMAL(12,2) | No | Precio por unidad en pesos colombianos (≥ 0). |

## Consultas útiles

Costo total por ingreso:

```sql
SELECT i.id_ingreso, SUM(r.cantidad * r.costo_unitario) AS costo_total
FROM ingreso i
LEFT JOIN repuesto r ON r.id_ingreso = i.id_ingreso
GROUP BY i.id_ingreso;
```

Ingresos abiertos:

```sql
SELECT v.placa, i.fecha_hora_ingreso, i.falla_reportada
FROM ingreso i
JOIN vehiculo v ON v.id_vehiculo = i.id_vehiculo
WHERE i.fecha_hora_egreso IS NULL;
```
