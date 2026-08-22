#!/bin/bash
# ============================================
# WG Panel - Script de Atualização
# Atualiza código e aplica migrations pendentes
# ============================================

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERRO]${NC} $1"; }

# ============================================
# Configurações
# ============================================
APP_DIR="/var/www/wgpanel"
DB_NAME="mikrotik_manager"
DB_USER="admin"

# ============================================
# Verificar se é root
# ============================================
if [ "$EUID" -ne 0 ]; then
    log_error "Execute como root: sudo bash update.sh"
    exit 1
fi

echo ""
echo -e "${BLUE}  ╔══════════════════════════════════════╗${NC}"
echo -e "${BLUE}  ║      WG Panel - Atualização          ║${NC}"
echo -e "${BLUE}  ╚══════════════════════════════════════╝${NC}"
echo ""

# ============================================
# 1. Backup do banco (segurança)
# ============================================
log_info "Criando backup do banco de dados..."
BACKUP_FILE="/tmp/wgpanel_backup_$(date +%Y%m%d_%H%M%S).sql"
sudo -u postgres pg_dump "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    log_success "Backup salvo em: $BACKUP_FILE"
else
    log_warn "Falha no backup, continuando..."
fi

# ============================================
# 2. Parar serviço
# ============================================
log_info "Parando serviço wgpanel..."
systemctl stop wgpanel 2>/dev/null || true
sleep 1
log_success "Serviço parado"

# ============================================
# 3. Atualizar código
# ============================================
log_info "Atualizando código do repositório..."
cd "$APP_DIR"
git fetch origin 2>/dev/null

# Verificar se há mudanças
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/master)

if [ "$LOCAL" = "$REMOTE" ]; then
    log_warn "Código já está atualizado (nenhuma mudança)"
else
    COMMITS=$(git rev-list HEAD..origin/master --count)
    log_info "Há $COMMITS commit(s) para baixar"
    git pull origin master
    log_success "Código atualizado"
fi

# ============================================
# 4. Aplicar migrations pendentes
# ============================================
log_info "Verificando migrations pendentes..."

# Listar todas as migrations na ordem correta
MIGRATIONS=(
    "database/init.sql"
    "database/002_wireguard_tables.sql"
    "database/003_wireguard_traffic_log.sql"
    "database/004_add_additional_routes.sql"
)

APPLIED=0
for MIGRATION in "${MIGRATIONS[@]}"; do
    if [ -f "$APP_DIR/$MIGRATION" ]; then
        log_info "Aplicando: $MIGRATION"
        sudo -u postgres psql -d "$DB_NAME" -f "$APP_DIR/$MIGRATION" 2>&1 | grep -E "(NOTICE|ERROR|DO$)" || true
        APPLIED=$((APPLIED + 1))
    fi
done

# Aplicar qualquer migration nova não listada acima
for FILE in $(ls "$APP_DIR/database/"*.sql 2>/dev/null | sort); do
    MIGRATION=$(basename "$FILE")
    ALREADY=false
    for LISTED in "${MIGRATIONS[@]}"; do
        if [ "$(basename "$LISTED")" = "$MIGRATION" ]; then
            ALREADY=true
            break
        fi
    done
    if [ "$ALREADY" = false ]; then
        log_info "Aplicando nova migration: $MIGRATION"
        sudo -u postgres psql -d "$DB_NAME" -f "$FILE" 2>&1 | grep -E "(NOTICE|ERROR|DO$)" || true
        APPLIED=$((APPLIED + 1))
    fi
done

log_success "$APPLIED migration(s) processada(s)"

# ============================================
# 5. Conceder permissões
# ============================================
log_info "Atualizando permissões do banco..."
sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;" 2>/dev/null
sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;" 2>/dev/null
log_success "Permissões atualizadas"

# ============================================
# 6. Configurar permissões de arquivos
# ============================================
log_info "Atualizando permissões dos arquivos..."
chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true
chmod -R 755 "$APP_DIR" 2>/dev/null || true
chmod -R 777 "$APP_DIR/src/assets" 2>/dev/null || true
log_success "Permissões atualizadas"

# ============================================
# 7. Atualizar crontab (se necessário)
# ============================================
if ! crontab -l 2>/dev/null | grep -q "collect_traffic.php"; then
    log_info "Configurando cron de coleta de tráfego..."
    CRON_LINE="*/5 * * * * /usr/bin/php $APP_DIR/src/cron/collect_traffic.php >> /tmp/wireguard_traffic.log 2>&1"
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    log_success "Cron configurado"
else
    log_warn "Cron já configurado"
fi

# ============================================
# 8. Reiniciar serviço
# ============================================
log_info "Reiniciando serviço wgpanel..."
systemctl daemon-reload
systemctl start wgpanel
sleep 2

if systemctl is-active --quiet wgpanel; then
    log_success "Serviço wgpanel rodando"
else
    log_error "Serviço wgpanel falhou ao iniciar!"
    log_error "Verifique os logs: journalctl -u wgpanel -n 20"
    exit 1
fi

# ============================================
# 9. Verificar aplicação
# ============================================
log_info "Verificando se a aplicação responde..."
sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080/" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "200" ]; then
    log_success "Aplicação respondendo (HTTP $HTTP_CODE)"
else
    log_warn "Aplicação retornou HTTP $HTTP_CODE (pode ser normal se redirecionar para login)"
fi

# ============================================
# Resumo
# ============================================
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          Atualização concluída!                  ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${BLUE}Commits:${NC}       $LOCAL → $REMOTE"
echo -e "  ${BLUE}Migrations:${NC}    $APPLIED processada(s)"
echo -e "  ${BLUE}Backup:${NC}        $BACKUP_FILE"
echo -e "  ${BLUE}Serviço:${NC}       $(systemctl is-active wgpanel)"
echo ""
echo -e "  ${YELLOW}Comandos úteis:${NC}"
echo -e "    logs:       journalctl -u wgpanel -f"
echo -e "    status:     systemctl status wgpanel"
echo -e "    reiniciar:  systemctl restart wgpanel"
echo ""
