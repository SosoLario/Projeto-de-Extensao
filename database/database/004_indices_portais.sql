-- Índices para as consultas de frequência e notas dos portais de aluno e professor.
-- Execute uma única vez após database/003_fotos_perfil.sql.
USE classmanager;

ALTER TABLE frequencias
  ADD INDEX idx_frequencias_aluno_data (aluno_id, data_aula),
  ADD INDEX idx_frequencias_horario_data (horario_id, data_aula);

ALTER TABLE notas
  ADD INDEX idx_notas_aluno_criado (aluno_id, criado_em),
  ADD INDEX idx_notas_horario_criado (horario_id, criado_em);
