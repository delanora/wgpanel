# Mikrotik Manager

## O que é

Aplicação web para gerenciamento de roteadores **Mikrotik RouterOS**, feita em PHP puro com frontend HTML/CSS/JS.

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.4 (servidor built-in) |
| Frontend | HTML, CSS, JavaScript puro |
| Banco | PostgreSQL 17 (instalado localmente via apt) |
| API | REST HTTP/HTTPS com o Mikrotik |

---

## Estrutura do projeto

```
/var/www/wgpanel/
├── database/
│   └── init.sql              # Schema do banco (tabela users)
├── .env                      # Configurações (API Mikrotik, DB)
└── src/
    ├── index.php             # Front controller
    ├── config/               # Config, Database, Routes
    ├── src/
    │   ├── Router.php        # Router simples
    │   ├── Controller/       # Auth, Dashboard, Users, Mikrotik
    │   └── Middleware/       # Autenticação (sessões)
    ├── views/                # Templates PHP
    │   ├── auth/             # Login
    │   ├── dashboard/        # Dashboard principal
    │   ├── users/            # CRUD + Perfil
    │   └── mikrotik/         # Interfaces, Clientes, Logs, Comandos
    └── assets/               # CSS + JS
```

---

## Funcionalidades implementadas

| Tela | O que faz |
|------|-----------|
| **Login/Logout** | Autenticação com sessão PHP + timeout de 1h |
| **Dashboard** | Painel com total de usuários, status, horário |
| **CRUD Usuários** | Criar, listar, editar, excluir usuários do sistema |
| **Perfil** | Editar nome e trocar senha do usuário logado |
| **Mikrotik - Conexão** | Verifica status da API REST do roteador |
| **Mikrotik - Interfaces** | Lista todas as interfaces (ethernet, wireless, etc) |
| **Mikrotik - Clientes** | Lista clientes conectados via Hotspot |
| **Mikrotik - Logs** | Exibe logs do roteador com níveis (error, warning, info) |
| **Mikrotik - Comandos** | Executa comandos permitidos via API REST |

---

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Middleware de autenticação bloqueia rotas protegidas
- Comandos Mikrotik com whitelist (só permite comandos pré-definidos)
- Sessão expira após 1 hora de inatividade

---

## Infraestrutura

- **PostgreSQL** instalado via `apt` (sem Docker)
- **Banco** `mikrotik_manager` com tabela `users`
- **Usuário admin** padrão: `admin@example.com` / `admin123`
- **Servidor PHP** rodando com `php -S 0.0.0.0:8080`

---

## Comandos úteis

```bash
# Iniciar servidor PHP
cd /var/www/wgpanel/src && php -S 0.0.0.0:8080

# Parar servidor PHP
kill $(pgrep -f "php -S 0.0.0.0:8080")

# Acessar PostgreSQL
sudo -u postgres psql -d mikrotik_manager

# Verificar status do PostgreSQL
pg_isready
```

---

## API Mikrotik configurada

- **Endpoint:** `http://45.4.112.13`
- **Protocolo:** REST API (HTTP/HTTPS)
- **Porta padrão:** 8728
