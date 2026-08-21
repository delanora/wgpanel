-- ============================================
-- WG Panel - Database Schema
-- ============================================

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

-- Tabela de Sessões (opcional, para controle de sessões)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INTEGER
);

-- Índices
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(active);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_last_activity ON sessions(last_activity);

-- ============================================
-- Dados Iniciais
-- ============================================

-- Usuário admin padrão (senha: admin123)
-- Em produção, ALTERE ESTA SENHA IMEDIATAMENTE!
INSERT INTO users (name, email, password, role, active) VALUES
('Administrador', 'admin@example.com', '$2y$10$YourHashedPasswordHere', 'admin', true)
ON CONFLICT (email) DO NOTHING;

-- Senha 'admin123' hasheada com password_hash() do PHP
-- Para gerar o hash correto, execute no PHP:
-- echo password_hash('admin123', PASSWORD_DEFAULT);
