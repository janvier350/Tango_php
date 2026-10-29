-- =============================================================================
--  TANGO - Flujo de Caja / Caja Chica
--  Script 01: ESTRUCTURA (solo tablas, sin datos)
--  Motor: MySQL 8.x  ·  InnoDB  ·  utf8mb4 / utf8mb4_unicode_ci
--
--  Reconstruido desde el codigo PHP (MySQLi). El proyecto NO tiene migraciones
--  ni ORM: las tablas, columnas y tipos se infirieron de las consultas
--  (INSERT/SELECT/UPDATE) y de los CREATE TABLE que el propio codigo ejecuta
--  (auditoria, geo_ip). Ver README.md y las NOTAS al final de este archivo.
--
--  Las llaves foraneas NO estan declaradas en la base real (el proyecto no las
--  crea). Para el DER, ejecutar despues el script 02 (relaciones logicas).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `tango`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `tango`;

-- =============================================================================
--  MODULO 1: ORGANIZACION Y USUARIOS
-- =============================================================================

-- Roles del sistema (1=Administrador, 2=Recepcion, 3=CEO, 4=Administracion)
CREATE TABLE IF NOT EXISTS `CTR_ROL` (
  `ID_ROL` INT NOT NULL AUTO_INCREMENT,
  `ROL`    VARCHAR(60) NOT NULL,
  PRIMARY KEY (`ID_ROL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Oficinas = cajas. ES_INDEPENDIENTE: no suma al total del dashboard.
-- FORM_SIMPLE: usa el formulario simplificado (tipo Mensajeria).
CREATE TABLE IF NOT EXISTS `CTR_OFICINA` (
  `ID_OFICINA`       INT NOT NULL AUTO_INCREMENT,
  `OFICINA`          VARCHAR(100) NOT NULL,
  `ES_INDEPENDIENTE` TINYINT(1) NOT NULL DEFAULT 0,
  `FORM_SIMPLE`      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID_OFICINA`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios de la aplicacion.
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `nombres`        VARCHAR(150) NOT NULL,
  `usuario`        VARCHAR(60)  NOT NULL,
  `clave`          VARCHAR(255) NOT NULL,            -- password_hash (bcrypt) o texto plano legado
  `ID_ROL`         INT NULL,
  `ID_CTR_OFICINA` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
  KEY `idx_usuarios_rol` (`ID_ROL`),
  KEY `idx_usuarios_oficina` (`ID_CTR_OFICINA`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MODULO 2: CATALOGOS (Informacion Financiera, proyectos, beneficiarios, etc.)
-- =============================================================================

-- Categoria de Informacion Financiera (ej. GTO.OFIC, REPOSICION, ...).
CREATE TABLE IF NOT EXISTS `cat_reposiciones` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120) NOT NULL,
  `informacion` TEXT NULL,                           -- glosario (descripcion)
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle/concepto de Informacion Financiera (REPOSICION), bajo una categoria.
CREATE TABLE IF NOT EXISTS `CAT_REPOSICION` (
  `ID_REPOSICION`        INT NOT NULL AUTO_INCREMENT,
  `REPOSICION`           VARCHAR(150) NOT NULL,
  `ID_CAT_REPOCICIONES`  INT NULL,                   -- (sic) -> cat_reposiciones.id
  `informacion`          TEXT NULL,                  -- glosario (descripcion)
  PRIMARY KEY (`ID_REPOSICION`),
  KEY `idx_catreposicion_cat` (`ID_CAT_REPOCICIONES`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proyectos.
CREATE TABLE IF NOT EXISTS `PROYECTOS` (
  `ID_PROYECTO` INT NOT NULL AUTO_INCREMENT,
  `PROYECTO`    VARCHAR(200) NOT NULL,
  PRIMARY KEY (`ID_PROYECTO`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beneficiarios / intermediarios. TIPO: 'I' intermediario, '0' beneficiario.
CREATE TABLE IF NOT EXISTS `BENEFICIARIO` (
  `ID_BENEFICIARIO` INT NOT NULL AUTO_INCREMENT,
  `RAZON_SOCIAL`    VARCHAR(255) NOT NULL,
  `ESTADO`          CHAR(1) NOT NULL DEFAULT 'A',
  `ID_USUARIO_CREA` INT NULL,
  `FECHA_CREA`      DATE NULL,
  `TIPO`            CHAR(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID_BENEFICIARIO`),
  KEY `idx_beneficiario_tipo` (`TIPO`),
  KEY `idx_beneficiario_usuario` (`ID_USUARIO_CREA`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo de empresas.
CREATE TABLE IF NOT EXISTS `cat_empresas` (
  `id`     INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo de bancos / medios de pago.
CREATE TABLE IF NOT EXISTS `cat_bancos` (
  `id`     INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MODULO 3: MOVIMIENTOS Y CAJA
-- =============================================================================

-- Movimientos de caja (ingresos/gastos). Tabla central.
--  ESTADO: 'A' activo, 'I' anulado.  inf_fin -> CAT_REPOSICION.ID_REPOSICION.
--  ID_PROYECTO: 0 cuando no aplica (por eso NO se declara FK en la base real).
CREATE TABLE IF NOT EXISTS `movimientos` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `fecha`               DATE NULL,
  `empresa`             VARCHAR(150) NULL,
  `concepto`            TEXT NULL,
  `intermediario`       VARCHAR(255) NULL,           -- "Beneficiario" en el formulario
  `intermediario2`      VARCHAR(255) NULL,           -- "Intermediario" en el formulario
  `importe_recibido`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `importe_entregado`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vale_ref`            VARCHAR(60) NULL,
  `doc_soporte`         VARCHAR(100) NULL,
  `inf_fin`             INT NULL,                     -- -> CAT_REPOSICION.ID_REPOSICION
  `periodo`             VARCHAR(10) NULL,             -- 'YYYY-M'
  `banco`              VARCHAR(100) NULL,
  `cheque_num`          VARCHAR(60) NULL,
  `ID_USUARIO`          INT NULL,                     -- quien registra -> usuarios.id
  `FECHA_REGISTRO_I_E`  DATETIME NULL,
  `ID_PROYECTO`         INT NOT NULL DEFAULT 0,       -- -> PROYECTOS.ID_PROYECTO (0 = ninguno)
  `ID_OFICINA`          INT NULL,                     -- caja -> CTR_OFICINA.ID_OFICINA
  `ESTADO`              CHAR(1) NOT NULL DEFAULT 'A',
  `ID_USUARIO_REVISA`   INT NULL,                     -- quien aprueba -> usuarios.id
  `FECHA_REVISION`      DATETIME NULL,
  -- --- Columnas LEGADO (aun referenciadas por index.php y vales.php) ---------
  `tipo`                VARCHAR(20) NULL,             -- legado: 'Ingreso'/'Egreso'
  `categoria`           VARCHAR(100) NULL,            -- legado
  `monto`               DECIMAL(12,2) NULL,           -- legado
  `observaciones`       TEXT NULL,                    -- legado
  PRIMARY KEY (`id`),
  KEY `idx_mov_oficina` (`ID_OFICINA`),
  KEY `idx_mov_usuario` (`ID_USUARIO`),
  KEY `idx_mov_revisa` (`ID_USUARIO_REVISA`),
  KEY `idx_mov_proyecto` (`ID_PROYECTO`),
  KEY `idx_mov_inffin` (`inf_fin`),
  KEY `idx_mov_fecha` (`fecha`),
  KEY `idx_mov_estado` (`ESTADO`),
  CONSTRAINT `chk_mov_estado` CHECK (`ESTADO` IN ('A','I'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arqueos de caja.
CREATE TABLE IF NOT EXISTS `arqueos` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `ID_OFICINA`          INT NULL,
  `ID_USUARIO`          INT NULL,
  `total_efectivo_real` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `saldo_sistema`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `diferencia`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `observaciones`       TEXT NULL,
  `fecha`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arqueo_oficina` (`ID_OFICINA`),
  KEY `idx_arqueo_usuario` (`ID_USUARIO`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MODULO 4: GLOSARIO, AUDITORIA Y UTILIDADES
-- =============================================================================

-- Tokens de acceso temporal al glosario (enlaces con vencimiento).
CREATE TABLE IF NOT EXISTS `glosario_tokens` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `token`        VARCHAR(32) NOT NULL,
  `descripcion`  VARCHAR(255) NULL,
  `fecha_expira` DATETIME NOT NULL,
  `creado_por`   INT NULL,                            -- -> usuarios.id
  `activo`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_glosario_token` (`token`),
  KEY `idx_glosario_creado_por` (`creado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitacora de auditoria (creada automaticamente por audit.php).
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id`          BIGINT NOT NULL AUTO_INCREMENT,
  `fecha_hora`  DATETIME NOT NULL,
  `id_usuario`  INT NULL,                             -- -> usuarios.id (historico)
  `usuario`     VARCHAR(120) NULL,
  `rol`         VARCHAR(60) NULL,
  `oficina`     VARCHAR(120) NULL,
  `modulo`      VARCHAR(60) NOT NULL,
  `accion`      VARCHAR(40) NOT NULL,
  `id_registro` VARCHAR(60) NULL,
  `detalle`     TEXT NULL,
  `ip`          VARCHAR(60) NULL,
  `user_agent`  VARCHAR(400) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha_hora`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_modulo` (`modulo`),
  KEY `idx_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache de geolocalizacion por IP (creada automaticamente por audit.php).
CREATE TABLE IF NOT EXISTS `geo_ip` (
  `ip`     VARCHAR(60) NOT NULL,
  `ciudad` VARCHAR(120) NULL,
  `pais`   VARCHAR(120) NULL,
  `ts`     DATETIME NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MODULO 5: LEGADO / EN REVISION
--  Tabla usada solo por vales.php, que no esta enlazada en ningun menu.
--  Se incluye para que el DER refleje la base real; verificar si sigue en uso.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `vales_caja` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `fecha`       DATE NULL,
  `entregado_a` VARCHAR(255) NULL,
  `concepto`    VARCHAR(500) NULL,
  `monto`       DECIMAL(12,2) NULL,
  `estado`      VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  LLAVES FORANEAS DECLARADAS EN EL PROYECTO
--  Ninguna: la base real NO declara llaves foraneas (el codigo PHP no las crea
--  y usa valores centinela como ID_PROYECTO = 0). Las relaciones para el DER
--  estan en el script 02 (relaciones logicas), de donde se puede omitir
--  cualquiera que no se desee dibujar.
-- =============================================================================

-- =============================================================================
--  NOTAS Y SUPUESTOS
--  - Todas las tablas tienen PRIMARY KEY (ninguna quedo sin PK).
--  - Tipos inferidos del uso (no hay DDL de origen): montos DECIMAL(12,2),
--    fechas sin hora DATE, con hora DATETIME, textos largos TEXT. Longitudes de
--    VARCHAR estimadas; ajustar si la base real difiere.
--  - UNIQUE en usuarios.usuario y glosario_tokens.token: el codigo valida
--    unicidad antes de insertar. Otros catalogos (empresas, bancos, proyectos,
--    beneficiarios) tambien se validan por app; se dejaron como indice simple
--    para no romper si la base real tuviera duplicados.
--  - CHECK chk_mov_estado (ESTADO IN 'A','I'): inferido del uso, no del DDL.
--  - movimientos conserva columnas legado (tipo, categoria, monto,
--    observaciones) porque index.php (SALDO CONTABLE del Sistema) y vales.php
--    aun las consultan.
--  - EXCLUIDAS por ser de OTRO proyecto (TodoList), no de TANGO: ADM_USUARIO y
--    ADM_ROL (solo las usa class/checkLogin.php, que no se incluye en ninguna
--    pagina de TANGO). Ver README.md.
-- =============================================================================
