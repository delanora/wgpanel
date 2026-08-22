-- ============================================
-- Migration 005: Tabela de log de latência (ping)
-- ============================================

CREATE TABLE IF NOT EXISTS latency_log (
    id SERIAL PRIMARY KEY,
    target VARCHAR(255) NOT NULL,
    target_label VARCHAR(255) NOT NULL,
    rtt_avg_ms NUMERIC(10,2),
    packet_loss_pct NUMERIC(5,2) NOT NULL DEFAULT 0,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices para buscas rápidas
CREATE INDEX IF NOT EXISTS idx_latency_log_target ON latency_log(target);
CREATE INDEX IF NOT EXISTS idx_latency_log_checked_at ON latency_log(checked_at);
CREATE INDEX IF NOT EXISTS idx_latency_log_target_checked ON latency_log(target, checked_at DESC);
