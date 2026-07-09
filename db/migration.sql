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
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    ativo BOOLEAN DEFAULT true,
    criado_por INTEGER REFERENCES agent(id),
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);

-- Índices únicos parciais: apenas 1 formulário publicado por entidade (agent, space, event)
CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_agent
ON formulario_dinamico (entidade) WHERE entidade = 'agent' AND status = 'published';

CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_space
ON formulario_dinamico (entidade) WHERE entidade = 'space' AND status = 'published';

CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_event
ON formulario_dinamico (entidade) WHERE entidade = 'event' AND status = 'published';

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
    grupo_id INTEGER DEFAULT 0,
    grupo_titulo VARCHAR(255) DEFAULT '',
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

-- Migração: adiciona colunas novas em tabelas existentes
ALTER TABLE formulario_dinamico ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'draft';
ALTER TABLE formulario_dinamico_campo ADD COLUMN IF NOT EXISTS grupo_id INTEGER DEFAULT 0;
ALTER TABLE formulario_dinamico_campo ADD COLUMN IF NOT EXISTS grupo_titulo VARCHAR(255) DEFAULT '';

-- Remove os índices antigos (baseados em ativo) se existirem
DROP INDEX IF EXISTS idx_fd_ativo_agent;
DROP INDEX IF EXISTS idx_fd_ativo_space;
DROP INDEX IF EXISTS idx_fd_ativo_event;

-- Migra dados existentes
UPDATE formulario_dinamico SET status = 'published' WHERE status IS NULL OR status = '';
