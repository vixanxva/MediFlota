-- =============================================================
-- MediFlota - Datos de prueba
-- Importar DESPUÉS de database/schema.sql.
--
-- Todos los datos son ficticios (placas, nombres, documentos y
-- teléfonos inventados). Las fechas son relativas al día de la
-- importación (CURDATE), así la RTM vencida / por vencer y los
-- ingresos abiertos siguen siendo coherentes en cualquier fecha.
--
-- Se puede ejecutar varias veces: vacía las tablas antes de insertar.
-- =============================================================

SET NAMES utf8mb4;
USE mediflota;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE repuesto;
TRUNCATE TABLE ingreso;
TRUNCATE TABLE usuario;
TRUNCATE TABLE vehiculo;
TRUNCATE TABLE tecnico;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Técnicos
-- -------------------------------------------------------------
INSERT INTO tecnico (id_tecnico, nombre, documento, telefono, especialidad, activo) VALUES
  (1, 'Carlos Andrés Petro Hoyos',     '1067894512', '3104567821', 'Mecánica general y motor diésel', 1),
  (2, 'Luis Fernando Doria Martínez',  '1003456789', '3015678934', 'Electricidad automotriz',         1),
  (3, 'María José Negrete Arrieta',    '1066123987', '3207894561', 'Frenos, suspensión y dirección',  1);

-- -------------------------------------------------------------
-- Usuarios
-- ⚠️ CREDENCIALES DE PRUEBA: ambos usuarios tienen la contraseña
--    "mediflota123". Deben cambiarse en producción.
--    admin   -> perfil administrador
--    tecnico -> perfil técnico, enlazado a Carlos Andrés Petro Hoyos
-- -------------------------------------------------------------
INSERT INTO usuario (id_usuario, usuario, password_hash, perfil, id_tecnico, activo) VALUES
  (1, 'admin',   '$2y$10$/2zuq2mzYpX/KNSNGRDC9ed5EE9u.k0ZBHgVfwOsqQzKUzCwepdSi', 'administrador', NULL, 1),
  (2, 'tecnico', '$2y$10$Q6fZZfVHCszDPd9p2JoLzew7cj0nrpY1K9MRGiMje/K03nx6yARMG', 'tecnico',       1,    1);

-- -------------------------------------------------------------
-- Vehículos (4 ambulancias, 2 de otro tipo)
--   disponible:       MFA101, MFB201, MFB202
--   en_mantenimiento: MFA103, MFA104  (cada uno con 1 ingreso abierto)
--   fuera_servicio:   MFA102          (RTM vencida)
--   RTM por vencer (< 30 días): MFA103
-- -------------------------------------------------------------
INSERT INTO vehiculo (id_vehiculo, placa, tipo, marca, modelo, kilometraje, vencimiento_rtm, estado, activo) VALUES
  (1, 'MFA101', 'ambulancia', 'Mercedes-Benz', 'Sprinter 515 2021',  98450, CURDATE() + INTERVAL 120 DAY, 'disponible',       1),
  (2, 'MFA102', 'ambulancia', 'Renault',       'Master 2022',        64200, CURDATE() - INTERVAL 20 DAY,  'fuera_servicio',   1),
  (3, 'MFA103', 'ambulancia', 'Toyota',        'Hiace 2019',        152300, CURDATE() + INTERVAL 15 DAY,  'en_mantenimiento', 1),
  (4, 'MFA104', 'ambulancia', 'Ford',          'Transit 2023',       41870, CURDATE() + INTERVAL 200 DAY, 'en_mantenimiento', 1),
  (5, 'MFB201', 'otro',       'Toyota',        'Hilux 2020',        110500, CURDATE() + INTERVAL 90 DAY,  'disponible',       1),
  (6, 'MFB202', 'otro',       'Yamaha',        'XTZ 150 2022',       23400, CURDATE() + INTERVAL 300 DAY, 'disponible',       1);

-- -------------------------------------------------------------
-- Ingresos (10: 8 cerrados y 2 abiertos)
-- Abierto = fecha_hora_egreso NULL.
-- -------------------------------------------------------------
INSERT INTO ingreso (id_ingreso, id_vehiculo, fecha_hora_ingreso, kilometraje_ingreso, falla_reportada,
                     diagnostico, tipo_mantenimiento, id_tecnico, fecha_hora_egreso, trabajo_realizado) VALUES
  -- MFA101
  (1, 1, TIMESTAMP(CURDATE() - INTERVAL 150 DAY, '07:30:00'), 85100,
     'Mantenimiento programado de los 85.000 km.',
     'Aceite degradado y filtros saturados según plan de mantenimiento.', 'preventivo', 1,
     TIMESTAMP(CURDATE() - INTERVAL 150 DAY, '13:45:00'),
     'Cambio de aceite de motor, filtro de aceite y filtro de aire. Revisión de niveles.'),
  (2, 1, TIMESTAMP(CURDATE() - INTERVAL 40 DAY, '09:10:00'), 95020,
     'No encienden la sirena ni las luces de emergencia.',
     'Relé del circuito de emergencia quemado y fusible principal abierto.', 'correctivo', 2,
     TIMESTAMP(CURDATE() - INTERVAL 39 DAY, '11:00:00'),
     'Reemplazo de relés y fusibles del circuito de emergencia. Prueba de sirena y balizas.'),

  -- MFA102
  (3, 2, TIMESTAMP(CURDATE() - INTERVAL 200 DAY, '08:00:00'), 52300,
     'Revisión preventiva de frenos.',
     'Pastillas delanteras al 20 % de vida útil; líquido de frenos contaminado.', 'preventivo', 3,
     TIMESTAMP(CURDATE() - INTERVAL 200 DAY, '16:20:00'),
     'Cambio de pastillas delanteras y purga del sistema con líquido DOT 4.'),
  (4, 2, TIMESTAMP(CURDATE() - INTERVAL 60 DAY, '10:15:00'), 63950,
     'Ruido al cambiar de marcha y el embrague patina en subida.',
     'Disco y prensa de embrague desgastados.', 'correctivo', 1,
     TIMESTAMP(CURDATE() - INTERVAL 55 DAY, '17:00:00'),
     'Cambio de kit de embrague completo y aceite de transmisión.'),

  -- MFA103 (el último queda abierto)
  (5, 3, TIMESTAMP(CURDATE() - INTERVAL 90 DAY, '07:45:00'), 147800,
     'Mantenimiento programado de los 147.000 km.',
     'Aceite y filtro de combustible al final de su intervalo.', 'preventivo', 1,
     TIMESTAMP(CURDATE() - INTERVAL 90 DAY, '15:30:00'),
     'Cambio de aceite de motor y filtro de combustible. Revisión de correas.'),
  (6, 3, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '14:20:00'), 152300,
     'Recalentamiento del motor durante un traslado.',
     'Bomba de agua con fuga y termostato pegado en posición cerrada.', 'correctivo', 1,
     NULL, NULL),

  -- MFA104 (el último queda abierto)
  (7, 4, TIMESTAMP(CURDATE() - INTERVAL 100 DAY, '08:30:00'), 35000,
     'Mantenimiento programado de los 35.000 km.',
     'Servicio de rutina según manual del fabricante.', 'preventivo', 3,
     TIMESTAMP(CURDATE() - INTERVAL 100 DAY, '12:10:00'),
     'Cambio de aceite sintético y filtro de aceite. Rotación de llantas.'),
  (8, 4, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:05:00'), 41870,
     'El aire acondicionado del habitáculo del paciente no enfría.',
     'Fuga en la manguera de alta presión del A/A; sistema sin carga de gas.', 'correctivo', 2,
     NULL, NULL),

  -- MFB201
  (9, 5, TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '07:00:00'), 109900,
     'Mantenimiento programado de los 110.000 km.',
     'Servicio de rutina; filtro de aire saturado por polvo.', 'preventivo', 3,
     TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '11:40:00'),
     'Cambio de aceite de motor y filtro de aire. Revisión de suspensión.'),

  -- MFB202
  (10, 6, TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '09:30:00'), 23100,
     'La cadena hace ruido y salta en aceleración.',
     'Kit de arrastre (cadena, piñón y corona) desgastado.', 'correctivo', 3,
     TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '12:00:00'),
     'Cambio del kit de arrastre, tensado y lubricación de la cadena.');

-- -------------------------------------------------------------
-- Repuestos (precios en pesos colombianos, COP)
-- -------------------------------------------------------------
INSERT INTO repuesto (id_ingreso, descripcion, cantidad, costo_unitario) VALUES
  (1,  'Aceite de motor 15W-40 (galón)',               3,   68000.00),
  (1,  'Filtro de aceite',                             1,   42000.00),
  (1,  'Filtro de aire',                               1,   95000.00),

  (2,  'Relé 12 V 40 A',                               2,   18500.00),
  (2,  'Fusibles surtidos (paquete)',                  1,   12000.00),

  (3,  'Pastillas de freno delanteras (juego)',        1,  285000.00),
  (3,  'Líquido de frenos DOT 4 (500 ml)',             2,   38000.00),

  (4,  'Kit de embrague (disco, prensa y balinera)',   1, 1450000.00),
  (4,  'Aceite de transmisión 75W-90 (litro)',         3,   45000.00),

  (5,  'Aceite de motor 15W-40 (galón)',               4,   68000.00),
  (5,  'Filtro de combustible',                        1,   78000.00),

  (6,  'Bomba de agua',                                1,  520000.00),
  (6,  'Termostato',                                   1,  115000.00),
  (6,  'Refrigerante (galón)',                         2,   52000.00),

  (7,  'Aceite sintético 5W-30 (litro)',               6,   48000.00),
  (7,  'Filtro de aceite',                             1,   55000.00),

  (8,  'Gas refrigerante R-134a (lata)',               2,   45000.00),
  (8,  'Manguera de alta presión A/A',                 1,  230000.00),

  (9,  'Aceite de motor 15W-40 (galón)',               2,   68000.00),
  (9,  'Filtro de aire',                               1,   89000.00),

  (10, 'Kit de arrastre (cadena, piñón y corona)',     1,  185000.00),
  (10, 'Lubricante para cadena (aerosol)',             1,   28000.00);
