-- Adiciona foto de perfil sem alterar os dados existentes.
-- Execute uma única vez após schema.sql e 002_modulos_academicos_financeiros.sql.
USE classmanager;

ALTER TABLE alunos ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER telefone;
ALTER TABLE professores ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER telefone;
