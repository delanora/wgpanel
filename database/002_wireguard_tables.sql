-- ============================================
-- Migration 002: Tabelas WireGuard
-- ============================================

-- Tabela de Interfaces WireGuard
CREATE TABLE IF NOT EXISTS wireguard_interfaces (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    listen_port INTEGER NOT NULL,
    network_cidr VARCHAR(18) NOT NULL,
    server_ip VARCHAR(45) NOT NULL,
    public_key VARCHAR(44) NOT NULL,
    client_name VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Peers WireGuard
CREATE TABLE IF NOT EXISTS wireguard_peers (
    id SERIAL PRIMARY KEY,
    interface_id INTEGER NOT NULL REFERENCES wireguard_interfaces(id) ON DELETE CASCADE,
    peer_name VARCHAR(255) NOT NULL,
    public_key VARCHAR(44) NOT NULL,
    private_key VARCHAR(44) NOT NULL DEFAULT '',
    allowed_address VARCHAR(18) NOT NULL,
    endpoint_port INTEGER,
    contact_name VARCHAR(255) NOT NULL DEFAULT '',
    contact_email VARCHAR(255) NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices
CREATE INDEX idx_wg_interfaces_name ON wireguard_interfaces(name);
CREATE INDEX idx_wg_interfaces_status ON wireguard_interfaces(status);
CREATE INDEX idx_wg_peers_interface_id ON wireguard_peers(interface_id);
CREATE INDEX idx_wg_peers_status ON wireguard_peers(status);
CREATE INDEX idx_wg_peers_contact_email ON wireguard_peers(contact_email);
