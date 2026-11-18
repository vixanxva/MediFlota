# Pruebas funcionales e integración (issue #11)

**Versión probada:** rama `develop`, commit `452eeea` (incluye los issues #1 al #9).
**Fecha:** 09/11/2026.
**Entorno:** XAMPP en Windows 11 (PHP 8.0.30, MariaDB 10.4.32, Apache), `http://localhost/MediFlota/`, con la base recién importada (`schema.sql` + `seed.sql`).
**Resultado:** los 8 casos mínimos pasan (45 de 45 verificaciones). Se encontró **un hallazgo** que bloquea la instalación ([ver Hallazgos](#hallazgos)).

## Cómo se probó

Los casos se ejecutaron como **un solo recorrido**, para probar también la integración entre módulos:

1. Se registra un vehículo nuevo (PRU123) y un técnico nuevo.
2. El vehículo entra al taller y se le asigna el técnico.
3. Se registran el diagnóstico y los repuestos, y se registra el egreso.
4. Se confirma que el panel y el historial muestran lo registrado.

Cada paso se hizo con peticiones HTTP a las mismas páginas que usa el navegador (formularios con su token CSRF). Después se comprobó el resultado en la pantalla y en la base de datos.

Para repetirlas **a mano**, sigue los pasos de cada caso con los usuarios de prueba (`admin` y `tecnico`, contraseña `mediflota123`) y marca el resultado en la última columna.

## Casos

### 1. Login correcto, contraseña incorrecta y acceso sin sesión

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 1.1 | Sin iniciar sesión, abrir `/modules/panel/` | Redirige al login | ✅ | ☐ |
| 1.2 | Entrar con `admin` y una contraseña incorrecta | «Usuario o contraseña incorrectos.» (no dice cuál de los dos falló) | ✅ | ☐ |
| 1.3 | Entrar con `admin` / `mediflota123` | Entra y llega al panel de disponibilidad | ✅ | ☐ |

### 2. Técnico intentando entrar a una página de administrador

Con el usuario `tecnico`:

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 2.1 | Abrir `/modules/vehiculos/` y `/modules/vehiculos/formulario.php` | «Acceso denegado» (403) | ✅ | ☐ |
| 2.2 | Abrir `/modules/tecnicos/` | «Acceso denegado» (403) | ✅ | ☐ |
| 2.3 | Abrir `/modules/ingresos/nuevo.php` | «Acceso denegado» (403) | ✅ | ☐ |
| 2.4 | Abrir `/modules/ingresos/detalle.php?id=8` (ingreso de otro técnico) | «Acceso denegado» (403) | ✅ | ☐ |
| 2.5 | Abrir el panel | Sí entra | ✅ | ☐ |

### 3. Registrar, editar y desactivar vehículo; placa repetida

Con el usuario `admin`, en **Vehículos**:

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 3.1 | Registrar placa `pru-123`, ambulancia, Nissan NV350 2024, 5000 km, disponible | Se guarda como **PRU123**, disponible | ✅ | ☐ |
| 3.2 | Editar PRU123: modelo «NV350 Urvan 2024» y 5200 km | Se guardan los cambios | ✅ | ☐ |
| 3.3 | Registrar otro vehículo con placa `PRU 123` | «Ya existe un vehículo con la placa PRU123» | ✅ | ☐ |
| 3.4 | Desactivar PRU123 | Sale de «Activos» y no aparece al registrar un ingreso | ✅ | ☐ |
| 3.5 | En «Inactivos», activarlo de nuevo | Vuelve a «Activos» | ✅ | ☐ |

### 4. Registrar y editar técnico; documento repetido

Con el usuario `admin`, en **Técnicos**:

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 4.1 | Registrar «Andrés Felipe Soto Ruiz», documento `1.067.333.222`, teléfono 3112223344, Mecánica general | Se guarda con el documento **1067333222** | ✅ | ☐ |
| 4.2 | Editar su teléfono a 3009998877 | Se guarda el cambio | ✅ | ☐ |
| 4.3 | Registrar otra persona con el documento `1067333222` | «Ya existe un técnico con el documento 1067333222» | ✅ | ☐ |

### 5. Ingreso → el vehículo pasa a «en mantenimiento»

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 5.1 | En **Ingresos y egresos → Registrar ingreso**: PRU123, hoy, 5350 km, «Ruido en la suspensión delantera» | Se abre el detalle del ingreso | ✅ | ☐ |
| 5.2 | Ver PRU123 en Vehículos o en el panel | Estado **En mantenimiento**, kilometraje 5.350 | ✅ | ☐ |
| 5.3 | Ver los contadores del panel | Un disponible menos; en mantenimiento pasa de 2 a 3 | ✅ | ☐ |
| 5.4 | Intentar registrar otro ingreso de PRU123 | «… ya está en el taller. Registra primero su egreso.» | ✅ | ☐ |

### 6. Repuestos → el costo total es correcto

En el detalle del ingreso de PRU123:

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 6.1 | Diagnóstico «Bujes de la tijera delantera gastados.», correctivo, técnico Andrés Felipe Soto Ruiz | Se guarda | ✅ | ☐ |
| 6.2 | Agregar «Buje de tijera», cantidad 4, $ 45.500 | Subtotal **$ 182.000** | ✅ | ☐ |
| 6.3 | Agregar «Terminal de dirección», cantidad 1, $ 120.000 | — | ✅ | ☐ |
| 6.4 | Agregar un repuesto equivocado ($ 99.999) y quitarlo | Desaparece de la lista | ✅ | ☐ |
| 6.5 | Ver el costo total | **$ 302.000** (182.000 + 120.000), igual a la suma en la base de datos | ✅ | ☐ |

### 7. Egreso → el vehículo pasa a «disponible»

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 7.1 | Registrar el egreso con «Cambio de bujes y terminal.» | Vuelve a la lista con mensaje de éxito | ✅ | ☐ |
| 7.2 | Ver PRU123 | Estado **Disponible**; el ingreso queda cerrado | ✅ | ☐ |
| 7.3 | Ver los contadores del panel | Vuelven a los valores de antes del ingreso | ✅ | ☐ |
| 7.4 | Revisión en la base de datos | Todo vehículo «en mantenimiento» tiene exactamente un ingreso abierto, y ningún otro tiene | ✅ | — |

### 8. Historial y costo acumulado coinciden con lo registrado

| # | Pasos | Resultado esperado | Obtenido | A mano |
|---|---|---|---|---|
| 8.1 | En **Historial**, elegir PRU123 | 1 ingreso, con el diagnóstico, «Correctivo» y el técnico registrados | ✅ | ☐ |
| 8.2 | Ver el costo acumulado | **$ 302.000** | ✅ | ☐ |
| 8.3 | Ver el último mantenimiento | Fecha de hoy, 5.350 km | ✅ | ☐ |
| 8.4 | Elegir MFA102 (datos de prueba) | Acumulado **$ 1.946.000**, igual a la suma de sus repuestos en la base de datos | ✅ | ☐ |

## Pruebas por módulo

Además del recorrido, cada módulo se probó por separado al fusionarse, incluidos los casos borde y la seguridad:
- datos inválidos;
- HTML en los textos;
- formularios sin token CSRF;
- acciones simultáneas.

Todas pasan sobre `452eeea`.

| Módulo (issue) | Verificaciones |
|---|---|
| Login y sesión (#2) | 25 |
| Ingresos y egresos (#3): lógica y pantallas | 40 + 37 |
| Fallas y costos (#4): lógica y pantallas | 49 + 52 |
| Plantilla y menú (#5) | 36 |
| Vehículos (#6): lógica y pantallas | 70 + 50 |
| Técnicos (#7): lógica y pantallas | 59 + 45 |
| Panel (#8) | 31 |
| Historial (#9) | 37 |
| **Total** | **531** |

En los registros de Apache y PHP no quedó ningún aviso, *warning* ni error del sistema durante las pruebas.

## Hallazgos

| # | Hallazgo | Gravedad | Estado |
|---|---|---|---|
| H1 | **No hay forma de crear usuarios ni cambiar contraseñas sin `seed.sql`.** El #14 instala solo con `schema.sql`, así que la tabla `usuario` queda vacía y nadie puede iniciar sesión. Tampoco existe una pantalla para crear usuarios, restablecer contraseñas o cambiar la propia. | Alta: bloquea el #14 | Issue #27; resuelto en el PR #26 |

**Pasos para reproducir H1:**
1. Importar solo `database/schema.sql` en una base vacía.
2. Abrir `http://localhost/MediFlota/` e intentar entrar.
3. No existe ningún usuario y no hay forma de crear el primero desde el sistema.

## Firma

| Probó | Fecha | Resultado |
|---|---|---|
| | | |
