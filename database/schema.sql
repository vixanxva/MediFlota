-- =============================================================
-- MediFlota - Esquema de base de datos
-- Control de ingresos, egresos y mantenimiento de vehículos
-- del taller de Vida Sinú.
--
-- Motor: InnoDB | Cotejamiento: utf8mb4_unicode_ci
-- Normalizado hasta 3FN: el costo total de un ingreso NO se
-- guarda; se calcula como SUM(cantidad * costo_unitario).
--
-- Se puede ejecutar varias veces: borra y vuelve a crear las tablas.
-- Después de este archivo, importar database/seed.sql.
-- =============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS mediflota
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mediflota;

-- Borrar en orden inverso a las dependencias
DROP TABLE IF EXISTS repuesto;
DROP TABLE IF EXISTS ingreso;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS vehiculo;
DROP TABLE IF EXISTS tecnico;

-- -------------------------------------------------------------
-- tecnico: personal del taller que atiende los ingresos
-- -------------------------------------------------------------
CREATE TABLE tecnico (
  id_tecnico    INT          NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100) NOT NULL,
  documento     VARCHAR(20)  NOT NULL,
  telefono      VARCHAR(20)  NULL,
  especialidad  VARCHAR(60)  NOT NULL,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tecnico),
  UNIQUE KEY uq_tecnico_documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- usuario: cuentas de acceso al sistema
-- Un usuario con perfil 'tecnico' debe estar enlazado a un técnico.
-- -------------------------------------------------------------
CREATE TABLE usuario (
  id_usuario     INT          NOT NULL AUTO_INCREMENT,
  usuario        VARCHAR(50)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  perfil         ENUM('administrador','tecnico') NOT NULL,
  id_tecnico     INT          NULL,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_usuario (usuario),
  UNIQUE KEY uq_usuario_id_tecnico (id_tecnico),
  CONSTRAINT fk_usuario_tecnico
    FOREIGN KEY (id_tecnico) REFERENCES tecnico (id_tecnico),
  CONSTRAINT chk_usuario_perfil_tecnico
    CHECK (perfil <> 'tecnico' OR id_tecnico IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- vehiculo: ambulancias y demás vehículos de la entidad
-- 'activo' permite desactivar un vehículo sin borrar su historial.
-- -------------------------------------------------------------
CREATE TABLE vehiculo (
  id_vehiculo      INT         NOT NULL AUTO_INCREMENT,
  placa            VARCHAR(10) NOT NULL,
  tipo             ENUM('ambulancia','otro') NOT NULL,
  marca            VARCHAR(50) NOT NULL,
  modelo           VARCHAR(50) NOT NULL,
  kilometraje      INT         NOT NULL DEFAULT 0,
  vencimiento_rtm  DATE        NOT NULL,
  estado           ENUM('disponible','en_mantenimiento','fuera_servicio')
                               NOT NULL DEFAULT 'disponible',
  activo           TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id_vehiculo),
  UNIQUE KEY uq_vehiculo_placa (placa),
  KEY idx_vehiculo_estado (estado),
  CONSTRAINT chk_vehiculo_kilometraje CHECK (kilometraje >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- ingreso: entrada de un vehículo al taller y su egreso
-- Si fecha_hora_egreso es NULL, el ingreso sigue abierto.
-- -------------------------------------------------------------
CREATE TABLE ingreso (
  id_ingreso           INT      NOT NULL AUTO_INCREMENT,
  id_vehiculo          INT      NOT NULL,
  fecha_hora_ingreso   DATETIME NOT NULL,
  kilometraje_ingreso  INT      NOT NULL,
  falla_reportada      TEXT     NOT NULL,
  diagnostico          TEXT     NULL,
  tipo_mantenimiento   ENUM('preventivo','correctivo') NULL,
  id_tecnico           INT      NULL,
  fecha_hora_egreso    DATETIME NULL,
  trabajo_realizado    TEXT     NULL,
  PRIMARY KEY (id_ingreso),
  KEY idx_ingreso_vehiculo (id_vehiculo),
  KEY idx_ingreso_tecnico (id_tecnico),
  CONSTRAINT fk_ingreso_vehiculo
    FOREIGN KEY (id_vehiculo) REFERENCES vehiculo (id_vehiculo),
  CONSTRAINT fk_ingreso_tecnico
    FOREIGN KEY (id_tecnico) REFERENCES tecnico (id_tecnico),
  CONSTRAINT chk_ingreso_kilometraje CHECK (kilometraje_ingreso >= 0),
  CONSTRAINT chk_ingreso_fechas
    CHECK (fecha_hora_egreso IS NULL OR fecha_hora_egreso >= fecha_hora_ingreso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- repuesto: repuestos e insumos usados en un ingreso
-- Al borrar un ingreso se borran sus repuestos.
-- -------------------------------------------------------------
CREATE TABLE repuesto (
  id_repuesto     INT           NOT NULL AUTO_INCREMENT,
  id_ingreso      INT           NOT NULL,
  descripcion     VARCHAR(150)  NOT NULL,
  cantidad        INT           NOT NULL,
  costo_unitario  DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id_repuesto),
  KEY idx_repuesto_ingreso (id_ingreso),
  CONSTRAINT fk_repuesto_ingreso
    FOREIGN KEY (id_ingreso) REFERENCES ingreso (id_ingreso)
    ON DELETE CASCADE,
  CONSTRAINT chk_repuesto_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_repuesto_costo CHECK (costo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
