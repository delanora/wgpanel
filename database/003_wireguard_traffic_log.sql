-- ============================================
-- Migration 003: WireGuard Traffic Log
-- ============================================

CREATE TABLE IF NOT EXISTS wireguard_traffic_log (
    id SERIAL PRIMARY KEY,
    peer_id INTEGER NOT NULL REFERENCES wireguard_peers(id) ON DELETE CASCADE,
    rx BIGINT NOT NULL DEFAULT 0,
    tx BIGINT NOT NULL DEFAULT 0,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_wtl_peer_id ON wireguard_traffic_log(peer_id);
CREATE INDEX idx_wtl_logged_at ON wireguard_traffic_log(logged_at);
