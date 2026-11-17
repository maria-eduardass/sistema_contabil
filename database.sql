CREATE DATABASE IF NOT EXISTS fiscalpulse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fiscalpulse;

DROP TABLE IF EXISTS avaliacoes;
DROP TABLE IF EXISTS obrigacoes;
DROP TABLE IF EXISTS tipos_obrigacao;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS empresas;

CREATE TABLE empresas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  razao_social VARCHAR(150) NOT NULL,
  cnpj CHAR(14) NOT NULL,
  regime_tributario ENUM('mei','simples','presumido','real') NOT NULL,
  email_contato VARCHAR(150) NOT NULL,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_empresas_cnpj (cnpj),
  CONSTRAINT ck_empresas_cnpj CHECK (cnpj REGEXP '^[0-9]{14}$')
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT UNSIGNED NULL,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  cpf VARCHAR(30) NULL,                 -- opcional, texto livre (projeto escolar: sem validação de dígitos)
  telefone VARCHAR(30) NULL,            -- opcional, texto livre
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('contador','cliente') NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email),
  KEY ix_usuarios_empresa (empresa_id),
  CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT ck_usuarios_perfil CHECK (
    (perfil = 'contador' AND empresa_id IS NULL) OR (perfil = 'cliente' AND empresa_id IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE tipos_obrigacao (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sigla VARCHAR(20) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  periodicidade ENUM('mensal','trimestral','anual') NOT NULL,
  UNIQUE KEY uq_tipos_sigla (sigla)
) ENGINE=InnoDB;

CREATE TABLE obrigacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT UNSIGNED NOT NULL,
  tipo_obrigacao_id INT UNSIGNED NOT NULL,
  competencia DATE NOT NULL,
  data_vencimento DATE NOT NULL,
  valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('pendente','pago','atrasado') NOT NULL DEFAULT 'pendente',
  data_pagamento DATE NULL,
  observacao VARCHAR(255) NULL,
  criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_obrigacao (empresa_id, tipo_obrigacao_id, competencia),
  KEY ix_obrig_empresa_status (empresa_id, status),
  KEY ix_obrig_venc_status (data_vencimento, status),
  CONSTRAINT fk_obrig_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT fk_obrig_tipo FOREIGN KEY (tipo_obrigacao_id) REFERENCES tipos_obrigacao(id),
  CONSTRAINT ck_obrig_valor CHECK (valor >= 0),
  CONSTRAINT ck_obrig_pagto CHECK (
    (status = 'pago' AND data_pagamento IS NOT NULL) OR (status <> 'pago' AND data_pagamento IS NULL))
) ENGINE=InnoDB;

CREATE TABLE avaliacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  nota TINYINT UNSIGNED NOT NULL,
  comentario VARCHAR(500) NULL,
  criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_aval_usuario (usuario_id),
  CONSTRAINT fk_aval_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT ck_aval_nota CHECK (nota BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ===== SEED =====
INSERT INTO empresas (razao_social, cnpj, regime_tributario, email_contato) VALUES
 ('Padaria Pão Quente ME',  '11222333000181', 'simples',   'contato@paoquente.test'),
 ('Tech Soluções Ltda',     '11444777000161', 'presumido', 'fin@techsolucoes.test'),
 ('Clínica Bem Viver S/S',  '00000000000191', 'real',      'adm@bemviver.test');

INSERT INTO usuarios (empresa_id, nome, email, senha_hash, perfil) VALUES
 (NULL, 'Carla Contadora', 'contador@fiscalpulse.test', '$2y$10$Ez1H78Zqeg9rhKjzOUaWzOCHjE5NrC.1tYdwdX4nK1UUWMFontpMm', 'contador'),
 (1,    'João da Padaria', 'cliente1@fiscalpulse.test', '$2y$10$5XY4d33cmjPfhUzscIS/.u0Hh0cFHnt.qeuxEPNMJYcll008W8CHO', 'cliente'),
 (2,    'Ana da Tech',     'cliente2@fiscalpulse.test', '$2y$10$5XY4d33cmjPfhUzscIS/.u0Hh0cFHnt.qeuxEPNMJYcll008W8CHO', 'cliente');

INSERT INTO tipos_obrigacao (sigla, nome, periodicidade) VALUES
 ('DAS','Documento de Arrecadação do Simples','mensal'),
 ('DCTF','Declaração de Débitos e Créditos Tributários Federais','mensal'),
 ('EFD-CONTRIB','EFD-Contribuições','mensal'),
 ('FGTS','Guia do FGTS','mensal'),
 ('DEFIS','Declaração de Informações Socioeconômicas e Fiscais','anual');

-- Datas relativas a CURDATE(): a demo sempre mostra riscos variados.
INSERT INTO obrigacoes (empresa_id, tipo_obrigacao_id, competencia, data_vencimento, valor, status, data_pagamento) VALUES
 (1,1, DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH,'%Y-%m-01'), CURDATE() - INTERVAL 20 DAY, 1850.00,'atrasado',NULL),
 (1,4, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m-01'), CURDATE() - INTERVAL 5 DAY,   620.50,'atrasado',NULL),
 (1,2, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                    CURDATE() + INTERVAL 3 DAY,     0.00,'pendente',NULL),
 (2,3, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                    CURDATE() + INTERVAL 2 DAY,  3200.00,'pendente',NULL),
 (2,2, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m-01'), CURDATE() - INTERVAL 10 DAY,    0.00,'pago', CURDATE() - INTERVAL 12 DAY),
 (3,4, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                    CURDATE() + INTERVAL 25 DAY,  980.00,'pendente',NULL),
 (3,1, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m-01'), CURDATE() - INTERVAL 8 DAY,  1500.00,'pago', CURDATE() - INTERVAL 9 DAY);

INSERT INTO avaliacoes (usuario_id, nota, comentario) VALUES (2, 5, 'Fácil de entender o risco.');
