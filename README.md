# Mikrotik Manager

Aplicação web para gerenciamento de roteadores Mikrotik RouterOS.

## 🚀 Funcionalidades

- **Login/Logout** com sessões PHP
- **Dashboard** com estatísticas
- **Gerenciamento de Usuários** (CRUD completo)
- **Perfil do Usuário** (editar dados e trocar senha)
- **Integração Mikrotik** via API REST
  - Visualização de interfaces
  - Lista de clientes conectados
  - Logs do sistema
  - Execução de comandos

## 📋 Pré-requisitos

- Docker e Docker Compose
- Git

## 🛠️ Instalação

### 1. Clonar o repositório

```bash
git clone <repositorio>
cd mikrotik-manager
```

### 2. Criar arquivo .env

```bash
cp .env.example .env
```

Edite o arquivo `.env` com suas configurações.

### 3. Iniciar o PostgreSQL (Docker)

```bash
docker-compose up -d
```

### 4. Iniciar o servidor PHP local

```bash
cd src
php -S localhost:8080
```

### 5. Acessar a aplicação

- **URL:** http://localhost:8080
- **Login padrão:** admin@example.com / admin123

## 📁 Estrutura do Projeto

```
mikrotik-manager/
├── docker-compose.yml      # Configuração Docker
├── Dockerfile              # Build da imagem PHP
├── .env                    # Variáveis de ambiente
├── database/
│   └── init.sql            # Schema do banco
├── docker/
│   └── apache.conf         # Configuração Apache
└── src/                    # Código-fonte PHP
    ├── index.php           # Front controller
    ├── .htaccess           # Rewrite rules
    ├── config/
    │   ├── config.php      # Configurações gerais
    │   ├── database.php    # Conexão PDO
    │   └── routes.php      # Definição de rotas
    ├── src/
    │   ├── Router.php      # Router simples
    │   ├── Controller/
    │   │   ├── AuthController.php
    │   │   ├── DashboardController.php
    │   │   ├── UserController.php
    │   │   └── MikrotikController.php
    │   └── Middleware/
    │       └── AuthMiddleware.php
    ├── views/
    │   ├── layouts/
    │   │   ├── header.php
    │   │   └── footer.php
    │   ├── auth/
    │   │   └── login.php
    │   ├── dashboard/
    │   │   └── index.php
    │   ├── users/
    │   │   ├── index.php
    │   │   ├── create.php
    │   │   ├── edit.php
    │   │   └── profile.php
    │   ├── mikrotik/
    │   │   ├── index.php
    │   │   ├── interfaces.php
    │   │   ├── clients.php
    │   │   └── logs.php
    │   └── 404.php
    └── assets/
        ├── css/
        │   └── style.css
        └── js/
            └── app.js
```

## 🔧 Configuração Mikrotik

### API REST (recomendado)

1. No Mikrotik, habilite a API REST:
```
/tool/fetch url="http://..."
```

2. Configure as credenciais no arquivo `.env`:
```
MIKROTIK_USER=seu_usuario
MIKROTIK_PASS=sua_senha
```

### Portas da API

- **API REST:** http://45.4.112.13/rest/...
- **API original:** porta 8728 (TCP)
- **Winbox:** porta 8291 (TCP)

## 🔒 Segurança

- ⚠️ **Altere a senha do admin padrão** após o primeiro login
- ⚠️ **Configure HTTPS** em produção
- ⚠️ **Restrinja o acesso** ao Mikrotik para IPs confiáveis
- ⚠️ **Não commite** o arquivo `.env` no repositório

## 📝 Comandos Úteis

```bash
# Iniciar PostgreSQL em background
docker-compose up -d

# Ver logs do PostgreSQL
docker-compose logs -f

# Parar PostgreSQL
docker-compose down

# Acessar PostgreSQL via terminal
docker-compose exec db psql -U admin -d mikrotik_manager

# Iniciar servidor PHP local
cd src
php -S localhost:8080
```

## 🐛 Solução de Problemas

### Erro de conexão com banco
Verifique se o container do PostgreSQL está rodando:
```bash
docker-compose ps
```

### Erro de permissão
Ajuste permissões da pasta `src`:
```bash
chmod -R 755 src
```

### Erro na API Mikrotik
Verifique se o Mikrotik está acessível:
```bash
curl http://45.4.112.13/rest/system/identity
```

## 📄 Licença

MIT
