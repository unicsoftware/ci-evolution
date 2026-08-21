-- Módulo WhatsApp - Evolution API
-- Script de Execução (Up Migration) - PostgreSQL

-- 1. Criação da tabela whatsapp_configs
CREATE TABLE whatsapp_configs (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    instance_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    profile_name VARCHAR(100) NULL,
    profile_picture TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'not_configured',
    connected BOOLEAN NOT NULL DEFAULT FALSE,
    last_connection TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

-- 2. Criação de índice composto para performance nas buscas por empresa e usuário (filtrando deletados soft delete)
CREATE INDEX idx_whatsapp_configs_empresa_usuario 
ON whatsapp_configs (empresa_id, usuario_id) 
WHERE deleted_at IS NULL;
