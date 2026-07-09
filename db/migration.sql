-- ============================================================
-- Migration: Plugin Formularios Dinâmicos
-- Cria as tabelas necessárias para o plugin
-- Execute no banco de dados do Mapas Culturais:
--   psql -d mapasculturais -f src/plugins/FormularioDinamico/db/migration.sql
-- ============================================================

-- Tabela: definição do formulário
CREATE TABLE IF NOT EXISTS formulario_dinamico (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    entidade VARCHAR(20) NOT NULL CHECK (entidade IN ('agent', 'space', 'event', 'opportunity')),
    ativo BOOLEAN DEFAULT true,
    criado_por INTEGER REFERENCES agent(id),
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);

-- Índices únicos parciais: apenas 1 formulário ativo por entidade (agent, space, event)
CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_agent
ON formulario_dinamico (entidade) WHERE entidade = 'agent' AND ativo = true;

CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_space
ON formulario_dinamico (entidade) WHERE entidade = 'space' AND ativo = true;

CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_event
ON formulario_dinamico (entidade) WHERE entidade = 'event' AND ativo = true;

-- Tabela: campos do formulário
CREATE TABLE IF NOT EXISTS formulario_dinamico_campo (
    id SERIAL PRIMARY KEY,
    formulario_id INTEGER NOT NULL REFERENCES formulario_dinamico(id) ON DELETE CASCADE,
    slug VARCHAR(100) NOT NULL,
    rotulo VARCHAR(255) NOT NULL,
    placeholder VARCHAR(255),
    tipo VARCHAR(30) NOT NULL,
    opcoes JSONB,
    obrigatorio BOOLEAN DEFAULT false,
    ordem INTEGER DEFAULT 0,
    coluna_span INTEGER DEFAULT 12,
    editavel BOOLEAN DEFAULT true,
    UNIQUE(formulario_id, slug)
);

CREATE INDEX IF NOT EXISTS idx_fdc_ordem ON formulario_dinamico_campo (formulario_id, ordem);

-- Tabela: vinculação formulário-oportunidade
CREATE TABLE IF NOT EXISTS formulario_dinamico_oportunidade (
    id SERIAL PRIMARY KEY,
    formulario_id INTEGER NOT NULL REFERENCES formulario_dinamico(id) ON DELETE CASCADE,
    oportunidade_id INTEGER NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
    UNIQUE(formulario_id, oportunidade_id)
);
