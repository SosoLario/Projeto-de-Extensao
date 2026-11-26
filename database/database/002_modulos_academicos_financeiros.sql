-- Complementa o banco existente sem apagar alunos, professores, horários,
-- frequências ou notas. Execute uma vez após database/schema.sql.
USE classmanager;

ALTER TABLE gestores ADD COLUMN perfil ENUM('administrador','secretaria') NOT NULL DEFAULT 'administrador' AFTER senha_hash;
ALTER TABLE gestores ADD COLUMN status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo' AFTER perfil;
ALTER TABLE alunos ADD COLUMN responsavel_email VARCHAR(150) NULL AFTER responsavel_telefone;
ALTER TABLE professores ADD COLUMN idiomas VARCHAR(255) NOT NULL DEFAULT 'Inglês' AFTER especialidade;

CREATE TABLE IF NOT EXISTS cursos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  idioma VARCHAR(60) NOT NULL,
  nivel ENUM('Iniciante','Básico','Intermediário','Avançado') NOT NULL,
  duracao_meses SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  carga_horaria SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor mensal demonstrativo',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cursos_ativo (ativo), KEY idx_cursos_idioma_nivel (idioma,nivel)
) ENGINE=InnoDB;

INSERT INTO cursos (nome,idioma,nivel,duracao_meses,carga_horaria,valor)
SELECT 'Inglês Intermediário','Inglês','Intermediário',12,96,320.00
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nome='Inglês Intermediário' AND idioma='Inglês');
INSERT INTO cursos (nome,idioma,nivel,duracao_meses,carga_horaria,valor)
SELECT 'Inglês Avançado','Inglês','Avançado',12,96,350.00
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nome='Inglês Avançado' AND idioma='Inglês');
INSERT INTO cursos (nome,idioma,nivel,duracao_meses,carga_horaria,valor)
SELECT 'Espanhol Básico','Espanhol','Básico',10,80,280.00
WHERE NOT EXISTS (SELECT 1 FROM cursos WHERE nome='Espanhol Básico' AND idioma='Espanhol');

ALTER TABLE horarios
  ADD COLUMN curso_id INT UNSIGNED NULL AFTER professor_id,
  ADD COLUMN hora_fim TIME NULL AFTER hora_inicio,
  ADD COLUMN capacidade SMALLINT UNSIGNED NOT NULL DEFAULT 12 AFTER hora_fim,
  ADD COLUMN data_inicio DATE NULL AFTER capacidade,
  ADD COLUMN data_fim DATE NULL AFTER data_inicio,
  ADD COLUMN status ENUM('ativa','concluída','cancelada') NOT NULL DEFAULT 'ativa' AFTER capacidade;

UPDATE horarios SET hora_fim=ADDTIME(hora_inicio,'01:30:00') WHERE hora_fim IS NULL;
UPDATE horarios SET data_inicio=CURDATE() WHERE data_inicio IS NULL;
ALTER TABLE horarios MODIFY data_inicio DATE NOT NULL;
UPDATE horarios h JOIN cursos c ON c.nome='Inglês Avançado' AND c.idioma='Inglês'
  SET h.curso_id=c.id WHERE h.turma LIKE '%Avançado%' AND h.curso_id IS NULL;
UPDATE horarios h JOIN cursos c ON c.nome='Espanhol Básico' AND c.idioma='Espanhol'
  SET h.curso_id=c.id WHERE h.turma LIKE '%Espanhol%' AND h.curso_id IS NULL;
UPDATE horarios h JOIN cursos c ON c.nome='Inglês Intermediário' AND c.idioma='Inglês'
  SET h.curso_id=c.id WHERE h.curso_id IS NULL;
ALTER TABLE horarios MODIFY hora_fim TIME NOT NULL;
ALTER TABLE horarios ADD CONSTRAINT fk_horarios_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE horarios ADD KEY idx_horarios_professor_dia (professor_id,dia_semana,status);

CREATE TABLE IF NOT EXISTS matriculas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT UNSIGNED NOT NULL,
  horario_id INT UNSIGNED NOT NULL,
  data_matricula DATE NOT NULL,
  valor_mensalidade DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  situacao ENUM('ativa','concluída','cancelada','transferida') NOT NULL DEFAULT 'ativa',
  observacoes VARCHAR(500) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_matriculas_aluno (aluno_id,situacao), KEY idx_matriculas_horario (horario_id,situacao),
  CONSTRAINT fk_matriculas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matriculas_horario FOREIGN KEY (horario_id) REFERENCES horarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO matriculas (aluno_id,horario_id,data_matricula,valor_mensalidade,situacao)
SELECT a.id,a.horario_id,a.data_matricula,COALESCE(c.valor,0),IF(a.status='ativo','ativa','cancelada')
FROM alunos a JOIN horarios h ON h.id=a.horario_id LEFT JOIN cursos c ON c.id=h.curso_id
WHERE NOT EXISTS (SELECT 1 FROM matriculas m WHERE m.aluno_id=a.id AND m.horario_id=a.horario_id);

CREATE TABLE IF NOT EXISTS aulas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horario_id INT UNSIGNED NOT NULL,
  data_aula DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fim TIME NOT NULL,
  conteudo VARCHAR(1000) NOT NULL DEFAULT '',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_aula_horario_data (horario_id,data_aula),
  CONSTRAINT fk_aulas_horario FOREIGN KEY (horario_id) REFERENCES horarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO aulas (horario_id,data_aula,hora_inicio,hora_fim)
SELECT DISTINCT f.horario_id,f.data_aula,h.hora_inicio,h.hora_fim
FROM frequencias f JOIN horarios h ON h.id=f.horario_id
WHERE NOT EXISTS (SELECT 1 FROM aulas a WHERE a.horario_id=f.horario_id AND a.data_aula=f.data_aula);

ALTER TABLE frequencias ADD COLUMN aula_id INT UNSIGNED NULL AFTER id;
ALTER TABLE frequencias ADD COLUMN situacao ENUM('presente','falta','atrasado') NULL AFTER presente;
UPDATE frequencias SET situacao=IF(presente=1,'presente','falta') WHERE situacao IS NULL;
ALTER TABLE frequencias MODIFY situacao ENUM('presente','falta','atrasado') NOT NULL DEFAULT 'presente';
ALTER TABLE frequencias ADD CONSTRAINT fk_frequencias_aula FOREIGN KEY (aula_id) REFERENCES aulas(id) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE frequencias ADD UNIQUE KEY uq_frequencias_aula_aluno (aula_id,aluno_id);
UPDATE frequencias f JOIN aulas a ON a.horario_id=f.horario_id AND a.data_aula=f.data_aula SET f.aula_id=a.id;

CREATE TABLE IF NOT EXISTS mensalidades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matricula_id INT UNSIGNED NOT NULL,
  referencia CHAR(7) NOT NULL COMMENT 'Formato AAAA-MM',
  valor DECIMAL(10,2) NOT NULL,
  desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  multa DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  juros DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  vencimento DATE NOT NULL,
  situacao ENUM('pendente','pago','atrasado','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mensalidade_matricula_referencia (matricula_id,referencia),
  KEY idx_mensalidades_vencimento (vencimento,situacao),
  CONSTRAINT fk_mensalidades_matricula FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mensalidade_id INT UNSIGNED NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  data_pagamento DATETIME NOT NULL,
  forma_pagamento ENUM('dinheiro','pix','cartão','transferência','outro') NOT NULL DEFAULT 'outro',
  observacoes VARCHAR(255) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pagamentos_mensalidade_data (mensalidade_id,data_pagamento),
  CONSTRAINT fk_pagamentos_mensalidade FOREIGN KEY (mensalidade_id) REFERENCES mensalidades(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Cria uma mensalidade demonstrativa pendente para cada matrícula migrada.
INSERT INTO mensalidades (matricula_id,referencia,valor,vencimento,situacao)
SELECT m.id,DATE_FORMAT(CURDATE(),'%Y-%m'),m.valor_mensalidade,DATE_ADD(CURDATE(),INTERVAL 10 DAY),'pendente'
FROM matriculas m
WHERE m.situacao='ativa'
  AND NOT EXISTS (SELECT 1 FROM mensalidades f WHERE f.matricula_id=m.id AND f.referencia=DATE_FORMAT(CURDATE(),'%Y-%m'));

-- Os CPFs da primeira carga eram marcadores fictícios, sem dígitos verificadores.
UPDATE alunos SET cpf=NULL WHERE cpf IN ('000.000.000-01','000.000.000-02');
