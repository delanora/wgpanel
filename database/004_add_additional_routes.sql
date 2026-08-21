-- ============================================
-- Migration 004: Adicionar coluna additional_routes
-- Coluna ausente na migration original
-- ============================================

-- Adicionar coluna se não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'wireguard_peers' AND column_name = 'additional_routes'
    ) THEN
        ALTER TABLE wireguard_peers ADD COLUMN additional_routes TEXT NOT NULL DEFAULT '';
        RAISE NOTICE 'Coluna additional_routes adicionada com sucesso';
    ELSE
        RAISE NOTICE 'Coluna additional_routes já existe';
    END IF;
END $$;
