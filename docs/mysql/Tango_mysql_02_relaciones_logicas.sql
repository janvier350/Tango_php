-- =============================================================================
--  TANGO - Script 02: RELACIONES LOGICAS (OPCIONAL)
--
--  La base real NO declara estas llaves foraneas; se deducen por nombre y uso
--  en el codigo. Sirven para que MySQL Workbench dibuje las relaciones del DER.
--
--  Cada relacion es un ALTER TABLE independiente: puede omitirse cualquiera.
--  Ejecutar DESPUES del script 01 y sobre el esquema VACIO (sin datos), porque
--  algunos valores centinela reales (ej. movimientos.ID_PROYECTO = 0) no
--  cumplirian la FK si hubiera datos.
-- =============================================================================

USE `tango`;

-- usuarios -> rol / oficina ---------------------------------------------------
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol`
  FOREIGN KEY (`ID_ROL`) REFERENCES `CTR_ROL` (`ID_ROL`);

ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_oficina`
  FOREIGN KEY (`ID_CTR_OFICINA`) REFERENCES `CTR_OFICINA` (`ID_OFICINA`);

-- CAT_REPOSICION -> cat_reposiciones (categoria) ------------------------------
ALTER TABLE `CAT_REPOSICION`
  ADD CONSTRAINT `fk_catreposicion_categoria`
  FOREIGN KEY (`ID_CAT_REPOCICIONES`) REFERENCES `cat_reposiciones` (`id`);

-- movimientos -> oficina / usuario registra / usuario revisa / inf.fin / proy -
ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_oficina`
  FOREIGN KEY (`ID_OFICINA`) REFERENCES `CTR_OFICINA` (`ID_OFICINA`);

ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_usuario`
  FOREIGN KEY (`ID_USUARIO`) REFERENCES `usuarios` (`id`);

ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_usuario_revisa`
  FOREIGN KEY (`ID_USUARIO_REVISA`) REFERENCES `usuarios` (`id`);

ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_inffin`
  FOREIGN KEY (`inf_fin`) REFERENCES `CAT_REPOSICION` (`ID_REPOSICION`);

-- Nota: ID_PROYECTO usa 0 como "sin proyecto"; con datos reales esta FK fallaria.
ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_proyecto`
  FOREIGN KEY (`ID_PROYECTO`) REFERENCES `PROYECTOS` (`ID_PROYECTO`);

-- arqueos -> oficina / usuario ------------------------------------------------
ALTER TABLE `arqueos`
  ADD CONSTRAINT `fk_arqueo_oficina`
  FOREIGN KEY (`ID_OFICINA`) REFERENCES `CTR_OFICINA` (`ID_OFICINA`);

ALTER TABLE `arqueos`
  ADD CONSTRAINT `fk_arqueo_usuario`
  FOREIGN KEY (`ID_USUARIO`) REFERENCES `usuarios` (`id`);

-- beneficiario -> usuario que lo creo ----------------------------------------
ALTER TABLE `BENEFICIARIO`
  ADD CONSTRAINT `fk_beneficiario_usuario`
  FOREIGN KEY (`ID_USUARIO_CREA`) REFERENCES `usuarios` (`id`);

-- glosario_tokens -> usuario que lo creo -------------------------------------
ALTER TABLE `glosario_tokens`
  ADD CONSTRAINT `fk_glosario_creado_por`
  FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`);

-- auditoria -> usuario (historico; relacion logica para el DER) ---------------
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_usuario`
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);
