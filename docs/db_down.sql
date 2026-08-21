-- Módulo WhatsApp - Evolution API
-- Script de Rollback (Down Migration) - PostgreSQL

-- 1. Remoção de índices
DROP INDEX IF EXISTS idx_whatsapp_configs_empresa_usuario;

-- 2. Remoção da tabela
DROP TABLE IF EXISTS whatsapp_configs CASCADE;
