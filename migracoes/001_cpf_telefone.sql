-- Migração 001: CPF e telefone opcionais na conta do usuário (tela "Criar conta").
--
-- Para quem JÁ tinha importado o database.sql antes desta mudança e quer manter os dados:
-- phpMyAdmin > banco "fiscalpulse" > aba SQL > colar e executar. É seguro rodar mais de uma vez
-- (IF NOT EXISTS) e não apaga nada. Quem importar o database.sql novo já tem as colunas.
USE fiscalpulse;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS cpf VARCHAR(30) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS telefone VARCHAR(30) NULL AFTER cpf;
