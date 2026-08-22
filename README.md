# WG Panel

Painel web para gerenciamento de VPNs WireGuard em roteadores Mikrotik RouterOS.  
Interface moderna e intuitiva para times de suporte gerenciarem interfaces e peers WireGuard sem precisar entender a sintaxe do RouterOS.

## Funcionalidades

### Gerenciamento WireGuard
- **Interfaces WireGuard** — Criar, editar, desabilitar e excluir interfaces no Mikrotik
- **Peers WireGuard** — CRUD completo com geração automática de chaves (private/public)
- **Config de cliente** — Geração automática de arquivos `.conf` para Linux e Windows
  - **Linux/macOS/Android:** Config padrão WireGuard
  - **Windows:** Config com rotas `netsh` PostUp/PostDown para roaming correto
- **Rotas adicionais** — Definir redes extras que o peer acessa via VPN (AllowedIPs + PostUp)
- **Download .conf** — Arquivo pronto para importar no app WireGuard

### Dashboard e Monitoramento
- **Métricas ao vivo** — Interfaces ativas, peers ativos, peers conectados
- **Peers conectados** — Lista de peers com handshake recente (< 3 min)
- **Gráfico de tráfego** — Chart.js com tráfego RX/TX por interface
- **Coleta automática** — Cron a cada 5 minutos registrando tráfego no banco
- **Atualização manual** — Botão para forçar coleta sob demanda
- **Página de tráfego** — Filtros por interface e período (1h a 1 ano)

### Integração Mikrotik
- **API REST** via HTTP Basic Auth
- **MikrotikClient** — Classe genérica com métodos GET, POST, PUT, PATCH, DELETE
- **Tratamento de erros** — Exceção customizada `MikrotikApiException`
- **Log de chamadas** — Todas as requisições registradas com tempo de resposta
- **SSL configurável** — Suporte a certificados self-signed em desenvolvimento

### Gestão de Usuários
- **Login/Logout** com sessões PHP e timeout configurável
- **CRUD de usuários** — Criar, listar, editar, excluir
- **Perfil** — Editar nome e trocar senha
- **Controle de acesso** — Middleware de autenticação em rotas protegidas

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.4 |
| Frontend | HTML, CSS, JavaScript puro |
| Banco | PostgreSQL 17 |
| Gráficos | Chart.js (CDN) |
| API | REST HTTP/HTTPS (Mikrotik RouterOS) |

## Instalação

### Instalação automática (recomendado)

```bash
git clone https://github.com/delanora/wgpanel.git
cd wgpanel
sudo bash install.sh
```

O script instala automaticamente: Git, PHP, PostgreSQL, configura o banco, cron e serviço systemd.

### Instalação manual

#### 1. Instalar dependências

```bash
sudo apt update
sudo apt install -y php php-cli php-pgsql php-curl postgresql postgresql-client git
```

#### 2. Clonar repositório

```bash
git clone https://github.com/delanora/wgpanel.git /var/www/wgpanel
cd /var/www/wgpanel
```

#### 3. Configurar banco de dados

```bash
# Criar usuário e banco
sudo -u postgres psql -c "CREATE USER admin WITH PASSWORD 'admin123' CREATEDB;"
sudo -u postgres psql -c "CREATE DATABASE mikrotik_manager OWNER admin;"

# Importar schema
sudo -u postgres psql -d mikrotik_manager -f database/init.sql
sudo -u postgres psql -d mikrotik_manager -f database/002_wireguard_tables.sql
sudo -u postgres psql -d mikrotik_manager -f database/003_wireguard_traffic_log.sql

# Conceder permissões
sudo -u postgres psql -d mikrotik_manager -c "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO admin;"
sudo -u postgres psql -d mikrotik_manager -c "GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO admin;"
```

#### 4. Configurar .env

```bash
cp .env.example .env
# Edite com suas configurações
```

#### 5. Configurar cron

```bash
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /var/www/wgpanel/src/cron/collect_traffic.php >> /tmp/wireguard_traffic.log 2>&1") | crontab -
```

#### 6. Iniciar servidor

```bash
cd /var/www/wgpanel/src
php -S 0.0.0.0:8080
```

### Acessar

- **URL:** http://localhost:8080
- **Login:** admin@example.com
- **Senha:** admin123

## Configuração Mikrotik

Configure no arquivo `.env`:

```env
MIKROTIK_API_URL=http://IP_DO_SEU_MIKROTIK
MIKROTIK_API_PORT=80
MIKROTIK_USER=seu_usuario
MIKROTIK_PASS=sua_senha
```

A aplicação usa a **API REST** do Mikrotik (porta 80 ou 443 por padrão).

## Estrutura do Projeto

```
wgpanel/
├── install.sh                          # Script de instalação
├── .env.example                        # Exemplo de configuração
├── database/
│   ├── init.sql                        # Schema: users
│   ├── 002_wireguard_tables.sql        # Schema: interfaces + peers
│   └── 003_wireguard_traffic_log.sql   # Schema: log de tráfego
└── src/
    ├── index.php                       # Front controller
    ├── cron/collect_traffic.php        # Script de coleta (cron)
    ├── config/
    │   ├── config.php                  # Configurações + .env loader
    │   ├── database.php                # Conexão PDO
    │   └── routes.php                  # Definição de rotas
    ├── src/
    │   ├── Router.php                  # Router simples
    │   ├── Service/MikrotikClient.php  # Cliente API Mikrotik
    │   ├── Exception/MikrotikApiException.php
    │   ├── Middleware/AuthMiddleware.php
    │   └── Controller/
    │       ├── AuthController.php
    │       ├── DashboardController.php
    │       ├── TrafficController.php
    │       ├── UserController.php
    │       ├── MikrotikController.php
    │       ├── WireguardInterfaceController.php
    │       └── WireguardPeerController.php
    ├── views/
    │   ├── layouts/header.php, footer.php
    │   ├── auth/login.php
    │   ├── dashboard/index.php
    │   ├── traffic/index.php
    │   ├── users/*.php
    │   ├── wireguard/*.php
    │   └── wireguard/peers/*.php
    └── assets/
        ├── css/style.css
        └── js/app.js
```

## Comandos Úteis

```bash
# Status do serviço
systemctl status wgpanel

# Reiniciar
systemctl restart wgpanel

# Parar
systemctl stop wgpanel

# Logs
journalctl -u wgpanel -f

# Acessar banco
sudo -u postgres psql -d mikrotik_manager

# Testar coleta de tráfego
php /var/www/wgpanel/src/cron/collect_traffic.php

# Testar API Mikrotik
php -r "
require_once 'src/config/config.php';
require_once 'src/src/Exception/MikrotikApiException.php';
require_once 'src/src/Service/MikrotikClient.php';
\$c = App\Service\MikrotikClient::fromEnv();
print_r(\$c->systemIdentity());
"
```

## Testes

O projeto possui uma suíte de testes automatizados com PHPUnit 11.

### Configurar banco de testes

```bash
# Criar banco mikrotik_manager_test (separado do banco de produção)
sudo -u postgres psql -c "CREATE DATABASE mikrotik_manager_test OWNER postgres;"
sudo -u postgres psql -d mikrotik_manager_test -c "GRANT ALL ON SCHEMA public TO postgres;"
sudo -u postgres psql -d mikrotik_manager_test -f database/init.sql
sudo -u postgres psql -d mikrotik_manager_test -f database/002_wireguard_tables.sql
sudo -u postgres psql -d mikrotik_manager_test -f database/003_wireguard_traffic_log.sql

# Dar ownership de todas as tabelas
sudo -u postgres psql -d mikrotik_manager_test -c "
  ALTER TABLE users OWNER TO postgres;
  ALTER TABLE sessions OWNER TO postgres;
  ALTER TABLE wireguard_interfaces OWNER TO postgres;
  ALTER TABLE wireguard_peers OWNER TO postgres;
  ALTER TABLE wireguard_traffic_log OWNER TO postgres;
  GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO postgres;
  GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO postgres;
"
```

### Rodar testes

```bash
# Instalar dependências de dev (composer + phpunit)
COMPOSER_ALLOW_SUPERUSER=1 composer install

# Rodar todos os testes
composer test

# Rodar apenas testes unitários
composer test:unit

# Rodar apenas testes de integração
composer test:integration

# Rodar com testdox (formato legível)
vendor/bin/phpunit --configuration phpunit.xml --testdox

# Rodar um teste específico
vendor/bin/phpunit --filter testSuccessfulCreation
```

### Estrutura de testes

```
tests/
├── bootstrap.php              # Autoloader + config de teste
├── setup_test_db.php          # Script para criar banco de teste
├── Unit/
│   ├── Service/
│   │   ├── MikrotikClientTest.php        # 13 testes: GET, POST, erros, timeout, proplist
│   │   └── MockTransport.php             # Mock HTTP para testes
│   └── Middleware/
│       └── AuthMiddlewareTest.php         # 6 testes: sessão, timeout, redirect
└── Integration/
    ├── IntegrationTestCase.php            # Base class com helpers de DB
    ├── WireguardInterfaceControllerTest.php  # 4 testes: criação, rollback, sugestões
    └── WireguardPeerControllerTest.php       # 4 testes: criação, rollback, IP, config
```

### Observações

- Os testes usam banco `mikrotik_manager_test` (não toca no banco de produção)
- MikrotikClient é testado via `MockTransport` (não depende de Mikrotik real)
- Controllers testados com `MockTransport` + banco real de teste
- `AuthMiddleware` testado via subclasse `TestableAuthMiddleware` (intercepta exit)

## Licença

MIT
