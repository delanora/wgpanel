#!/bin/bash
# ============================================
# WG Panel - Script de Instalação
# Instalação completa em Debian/Ubuntu
# ============================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_banner() {
    echo -e "${BLUE}"
    echo "  ╔══════════════════════════════════════╗"
    echo "  ║         WG Panel Installer           ║"
    echo "  ║   Mikrotik WireGuard Management      ║"
    echo "  ╚══════════════════════════════════════╝"
    echo -e "${NC}"
}

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERRO]${NC} $1"; }

# ============================================
# Verificar se é root
# ============================================
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "Execute como root: sudo bash install.sh"
        exit 1
    fi
}

# ============================================
# Configurações editáveis
# ============================================
APP_DIR="/var/www/wgpanel"
APP_PORT=8080
DB_NAME="mikrotik_manager"
DB_USER="admin"
DB_PASS="CHANGED"
ADMIN_EMAIL="admin@example.com"
ADMIN_PASS="CHANGED"

# ============================================
# Etapa 1: Atualizar sistema
# ============================================
update_system() {
    log_info "Atualizando sistema..."
    apt-get update -qq
    apt-get upgrade -y -qq
    log_success "Sistema atualizado"
}

# ============================================
# Etapa 2: Instalar dependências
# ============================================
install_deps() {
    log_info "Instalando dependências..."
    
    apt-get install -y -qq \
        git \
        curl \
        wget \
        lsb-release \
        apt-transport-https \
        ca-certificates \
        gnupg \
        unzip
    
    # PHP
    log_info "Instalando PHP 8.2+..."
    apt-get install -y -qq \
        php \
        php-cli \
        php-pgsql \
        php-curl \
        php-mbstring \
        php-json \
        php-xml \
        php-zip
    
    # PostgreSQL
    log_info "Instalando PostgreSQL..."
    apt-get install -y -qq \
        postgresql \
        postgresql-client
    
    log_success "Dependências instaladas"
}

# ============================================
# Etapa 3: Clonar repositório
# ============================================
clone_repo() {
    log_info "Clonando repositório..."
    
    if [ -d "$APP_DIR" ]; then
        log_warn "Diretório $APP_DIR já existe"
        read -p "Deseja sobrescrever? (s/N): " overwrite
        if [ "$overwrite" = "s" ] || [ "$overwrite" = "S" ]; then
            rm -rf "$APP_DIR"
        else
            log_info "Mantendo diretório existente"
            return
        fi
    fi
    
    git clone https://github.com/delanora/wgpanel.git "$APP_DIR"
    log_success "Repositório clonado em $APP_DIR"
}

# ============================================
# Etapa 4: Configurar PostgreSQL
# ============================================
setup_database() {
    log_info "Configurando PostgreSQL..."
    
    # Iniciar serviço
    systemctl start postgresql
    systemctl enable postgresql
    
    # Criar usuário e banco
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS' CREATEDB;" 2>/dev/null || true
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;" 2>/dev/null || true
    
    # Importar schema
    log_info "Criando tabelas..."
    sudo -u postgres psql -d "$DB_NAME" -f "$APP_DIR/database/init.sql" 2>/dev/null || true
    sudo -u postgres psql -d "$DB_NAME" -f "$APP_DIR/database/002_wireguard_tables.sql" 2>/dev/null || true
    sudo -u postgres psql -d "$DB_NAME" -f "$APP_DIR/database/003_wireguard_traffic_log.sql" 2>/dev/null || true
    
    # Conceder permissões
    sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;" 2>/dev/null || true
    sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;" 2>/dev/null || true
    sudo -u postgres psql -d "$DB_NAME" -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;" 2>/dev/null || true
    sudo -u postgres psql -d "$DB_NAME" -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;" 2>/dev/null || true
    
    # Gerar hash da senha do admin
    ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    sudo -u postgres psql -d "$DB_NAME" -c "UPDATE users SET password = '$ADMIN_HASH' WHERE email = '$ADMIN_EMAIL';" 2>/dev/null || true
    
    log_success "Banco de dados configurado"
}

# ============================================
# Etapa 5: Configurar .env
# ============================================
setup_env() {
    log_info "Configurando variáveis de ambiente..."
    
    if [ -f "$APP_DIR/.env" ]; then
        cp "$APP_DIR/.env" "$APP_DIR/.env.backup"
    fi
    
    cat > "$APP_DIR/.env" << EOF
# Banco de Dados PostgreSQL
DB_HOST=localhost
DB_PORT=5432
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

# Mikrotik Router API
MIKROTIK_API_URL=http://YOUR_MIKROTIK_IP
MIKROTIK_API_PORT=80

# Mikrotik Credenciais
MIKROTIK_USER=
MIKROTIK_PASS=

# Mikrotik Client
MIKROTIK_TIMEOUT=10
MIKROTIK_VERIFY_SSL=false
MIKROTIK_LOG_ENABLED=true
MIKROTIK_LOG_FILE=/tmp/mikrotik_api.log
EOF
    
    log_success "Arquivo .env configurado"
}

# ============================================
# Etapa 6: Configurar permissões
# ============================================
setup_permissions() {
    log_info "Configurando permissões..."
    
    chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true
    chmod -R 755 "$APP_DIR" 2>/dev/null || true
    chmod -R 777 "$APP_DIR/src/assets" 2>/dev/null || true
    
    log_success "Permissões configuradas"
}

# ============================================
# Etapa 7: Configurar cron
# ============================================
setup_cron() {
    log_info "Configurando cron de coleta de tráfego..."
    
    CRON_LINE="*/5 * * * * /usr/bin/php $APP_DIR/src/cron/collect_traffic.php >> /tmp/wireguard_traffic.log 2>&1"
    
    # Verificar se já existe
    if ! crontab -l 2>/dev/null | grep -q "collect_traffic.php"; then
        (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
        log_success "Cron configurado (a cada 5 minutos)"
    else
        log_warn "Cron já configurado"
    fi
}

# ============================================
# Etapa 8: Criar service systemd
# ============================================
setup_service() {
    log_info "Configurando serviço systemd..."
    
    cat > /etc/systemd/system/wgpanel.service << EOF
[Unit]
Description=WG Panel - Mikrotik WireGuard Manager
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR/src
ExecStart=/usr/bin/php -S 0.0.0.0:$APP_PORT -t $APP_DIR/src
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable wgpanel
    systemctl start wgpanel
    
    log_success "Serviço wgpanel configurado e iniciado"
}

# ============================================
# Etapa 9: Verificar instalação
# ============================================
verify_installation() {
    log_info "Verificando instalação..."
    
    # Verificar PHP
    if php -v > /dev/null 2>&1; then
        log_success "PHP: $(php -r 'echo PHP_VERSION;')"
    else
        log_error "PHP não encontrado"
    fi
    
    # Verificar PostgreSQL
    if pg_isready > /dev/null 2>&1; then
        log_success "PostgreSQL: rodando"
    else
        log_error "PostgreSQL não está rodando"
    fi
    
    # Verificar banco
    if sudo -u postgres psql -d "$DB_NAME" -c "SELECT 1" > /dev/null 2>&1; then
        log_success "Banco $DB_NAME: acessível"
    else
        log_error "Banco $DB_NAME: inacessível"
    fi
    
    # Verificar serviço
    if systemctl is-active --quiet wgpanel; then
        log_success "Serviço wgpanel: rodando"
    else
        log_warn "Serviço wgpanel: parado"
    fi
    
    # Verificar porta
    if ss -tlnp | grep -q ":$APP_PORT"; then
        log_success "Porta $APP_PORT: aberta"
    else
        log_warn "Porta $APP_PORT: não detectada"
    fi
}

# ============================================
# Exibir informações finais
# ============================================
print_summary() {
    LOCAL_IP=$(hostname -I | awk '{print $1}')
    
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║        Instalação concluída com sucesso!         ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BLUE}URL:${NC}            http://${LOCAL_IP}:${APP_PORT}"
    echo -e "  ${BLUE}Login:${NC}          $ADMIN_EMAIL"
    echo -e "  ${BLUE}Senha:${NC}          $ADMIN_PASS"
    echo ""
    echo -e "  ${YELLOW}Arquivos:${NC}"
    echo -e "    Aplicação:  $APP_DIR"
    echo -e "    Config:     $APP_DIR/.env"
    echo -e "    Logs:       /tmp/wireguard_traffic.log"
    echo ""
    echo -e "  ${YELLOW}Comandos úteis:${NC}"
    echo -e "    status:     systemctl status wgpanel"
    echo -e "    reiniciar:  systemctl restart wgpanel"
    echo -e "    parar:      systemctl stop wgpanel"
    echo -e "    logs:       journalctl -u wgpanel -f"
    echo ""
    echo -e "  ${RED}⚠  Altere a senha do admin após o primeiro login!${NC}"
    echo ""
}

# ============================================
# Menu principal
# ============================================
main() {
    print_banner
    check_root
    
    echo -e "${YELLOW}Este script irá instalar:${NC}"
    echo "  - Git"
    echo "  - PHP 8.2+ com extensões"
    echo "  - PostgreSQL 15+"
    echo "  - WG Panel (aplicação)"
    echo "  - Cron de coleta de tráfego"
    echo "  - Serviço systemd"
    echo ""
    read -p "Continuar? (S/n): " confirm
    
    if [ "$confirm" = "n" ] || [ "$confirm" = "N" ]; then
        echo "Instalação cancelada."
        exit 0
    fi
    
    echo ""
    update_system
    install_deps
    clone_repo
    setup_database
    setup_env
    setup_permissions
    setup_cron
    setup_service
    verify_installation
    print_summary
}

# Executar
main "$@"
