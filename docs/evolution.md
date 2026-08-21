# Módulo de Integração WhatsApp - Evolution API

Este módulo implementa a integração entre a aplicação **CodeIgniter 4** e a **Evolution API**, permitindo que cada usuário do sistema conecte sua própria conta do WhatsApp por meio de leitura de QR Code.

---

## Estrutura de Arquivos

```text
app/
├── Config/
│   ├── Routes.php              # Rotas do grupo /whatsapp
│   └── Services.php            # Service 'evolution' injetado via Dependency Injection
├── Controllers/
│   └── Whatsapp.php            # Controller AJAX principal
├── Database/
│   └── Migrations/
│       └── *_CreateWhatsappConfigsTable.php # Migration da tabela whatsapp_configs
├── Libraries/
│   └── Evolution.php           # Library de comunicação REST (CURLRequest)
├── Models/
│   └── WhatsappModel.php       # Model de persistência com Soft Deletes
└── Views/
    └── whatsapp/
        └── index.php           # Interface Dashboard Premium (Bootstrap 5)

public/
└── assets/
    ├── css/
    │   └── whatsapp.css        # Estilos customizados, glassmorphism e glows
    └── js/
        └── whatsapp.js         # Lógica de chamadas AJAX, CSRF e Polling

docs/
├── evolution.md                # Esta documentação
├── db_up.sql                   # Script de criação do banco de dados (PostgreSQL)
└── db_down.sql                 # Script de rollback do banco de dados
```

---

## Configuração Global da API (`.env`)

A URL do servidor e a API Key **global** são definidas no arquivo `.env` do projeto. Elas são carregadas automaticamente pelo CodeIgniter e **nunca são expostas ao browser**.

```ini
# .env
evolution.apiUrl = 'http://evolution.eabc.com.br'
evolution.apiKey = 'SUA_API_KEY_GLOBAL_AQUI'
```

> **Importante:** Essas credenciais são globais para toda a aplicação. O usuário só precisa informar o **nome da instância** que deseja criar/vincular.

---

## Banco de Dados

### Tabela: `whatsapp_configs`

A tabela armazena a configuração e o estado de conexão de cada instância por empresa e usuário. A coluna `api_url` e `api_key` são preenchidas automaticamente pelo servidor via `.env` — não pelo usuário.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | SERIAL PK | Identificador único |
| `empresa_id` | INT | ID da empresa (da sessão) |
| `usuario_id` | INT | ID do usuário (da sessão) |
| `api_url` | VARCHAR(255) | URL da API (preenchida via `.env`) |
| `api_key` | VARCHAR(255) | API Key global (preenchida via `.env`) |
| `instance_name` | VARCHAR(100) | Nome da instância informado pelo usuário |
| `phone` | VARCHAR(30) | Número de telefone conectado |
| `profile_name` | VARCHAR(100) | Nome do perfil do WhatsApp |
| `profile_picture` | TEXT | URL da foto do perfil |
| `status` | VARCHAR(50) | Status local: `not_configured`, `configured`, `waiting_qr`, `connected`, `disconnected` |
| `connected` | BOOLEAN | Flag de conexão ativa |
| `last_connection` | TIMESTAMP | Data/hora da última conexão bem-sucedida |
| `created_at` | TIMESTAMP | Criado em (automático) |
| `updated_at` | TIMESTAMP | Atualizado em (automático) |
| `deleted_at` | TIMESTAMP | Soft delete |

### Script DDL (ver `db_up.sql`)

```sql
CREATE TABLE whatsapp_configs (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    instance_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    profile_name VARCHAR(100) NULL,
    profile_picture TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'not_configured',
    connected BOOLEAN NOT NULL DEFAULT FALSE,
    last_connection TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_whatsapp_configs_empresa_usuario
ON whatsapp_configs (empresa_id, usuario_id)
WHERE deleted_at IS NULL;
```

---

## Rotas Mapeadas

O grupo de rotas `/whatsapp` foi registrado em `app/Config/Routes.php`:

| Método HTTP | Rota | Ação Controller | Descrição |
|---|---|---|---|
| **GET** | `/whatsapp` | `Whatsapp::index` | Carrega o dashboard principal |
| **POST** | `/whatsapp/save` | `Whatsapp::save` | Salva o nome da instância (api_url e api_key vêm do `.env`) |
| **POST** | `/whatsapp/test` | `Whatsapp::test` | Testa health check do servidor usando credenciais do `.env` |
| **POST** | `/whatsapp/create` | `Whatsapp::create` | Verifica instâncias existentes e cria se necessário |
| **GET** | `/whatsapp/status` | `Whatsapp::status` | Obtém o estado da conexão da instância |
| **GET** | `/whatsapp/qrcode` | `Whatsapp::qrcode` | Gera/retorna o QR Code em base64 |
| **POST** | `/whatsapp/restart` | `Whatsapp::restart` | Reinicia a instância na Evolution API |
| **POST** | `/whatsapp/logout` | `Whatsapp::disconnect` | Desconecta a sessão atual do WhatsApp |
| **POST** | `/whatsapp/send` | `Whatsapp::send` | Envia uma mensagem de texto de teste |
| **DELETE** | `/whatsapp/delete` | `Whatsapp::delete` | Exclui a instância na API e aplica soft delete local |

---

## Fluxo de Pareamento (Atualizado)

```
Usuário                       Sistema (CI4)                    Evolution API
   │                              │                                  │
   │── Informa nome da instância ─▶│                                  │
   │                              │── Salva config (api_url/key do .env) ──▶ DB
   │                              │                                  │
   │── Clica "Testar Servidor" ──▶│── GET /  (health check) ────────▶│
   │◀─ "Servidor online!" ────────│◀─ {status:200, version:...} ─────│
   │                              │                                  │
   │── Clica "Criar Instância" ──▶│── GET /instance/fetchInstances ─▶│
   │                              │◀─ [ lista de instâncias ] ───────│
   │                              │                                  │
   │                              │ [se instância JÁ EXISTE]         │
   │                              │── GET /instance/connectionState ─▶│
   │                              │◀─ {state: "open"|"close"} ───────│
   │                              │                                  │
   │                              │  [se open → sinc. perfil]        │
   │                              │── GET /instance/fetchInstances ─▶│
   │◀─ Painel conectado ──────────│   Sync DB + retorna config       │
   │                              │                                  │
   │                              │ [se instância NÃO EXISTE]        │
   │                              │── POST /instance/create ─────────▶│
   │◀─ Exibe QR Code ─────────────│◀─ {base64 QR} ───────────────────│
   │                              │                                  │
   │── Escaneia QR com celular ──▶│ polling a cada 5s                │
   │                              │── GET /instance/connectionState ─▶│
   │                              │◀─ {state: "open"} ───────────────│
   │◀─ Badge: Conectado! ─────────│── Sync perfil no DB              │
```

### Passos detalhados:

1. **Nome da Instância**: O usuário informa apenas o nome da instância no formulário. A URL e API Key do servidor são lidas do `.env`.
2. **Testar Servidor**: Chama o endpoint raiz `/` da Evolution API para verificar se o servidor está online (não requer instância ativa).
3. **Criar / Vincular Instância**: Antes de criar, verifica se uma instância com o mesmo nome já existe na API:
   - **Existe e está conectada (`open`)**: sincroniza o perfil (nome, telefone, foto) e exibe o painel de conectado diretamente.
   - **Existe mas desconectada**: exibe o QR Code para o usuário escanear e iniciar o polling.
   - **Não existe**: cria a instância via `/instance/create` e exibe o QR Code.
4. **Polling de Status**: A cada 5 segundos o JS consulta `/whatsapp/status`, que verifica o `connectionState` na API.
5. **Conexão Detectada**: Quando o estado retorna `open`, o polling para, o QR Code some, o perfil é sincronizado e o dashboard conectado é exibido.

---

## Endpoints da Evolution API Utilizados

| Endpoint | Método | Descrição |
|---|---|---|
| `/` | GET | Health check do servidor |
| `/instance/fetchInstances` | GET | Lista todas as instâncias existentes |
| `/instance/create` | POST | Cria uma nova instância |
| `/instance/connect/{instance}` | GET | Obtém o QR Code para pareamento |
| `/instance/connectionState/{instance}` | GET | Estado da conexão (`open`, `close`, `connecting`) |
| `/instance/restart/{instance}` | POST | Reinicia a instância |
| `/instance/logout/{instance}` | POST | Encerra a sessão do WhatsApp |
| `/instance/delete/{instance}` | DELETE | Remove a instância do servidor |
| `/message/sendText/{instance}` | POST | Envia mensagem de texto |

---

## Variáveis de Status

| Valor | Badge | Descrição |
|---|---|---|
| `not_configured` | ⚫ Cinza | Nenhuma configuração salva |
| `configured` | 🔵 Azul | Instância salva mas não criada na API |
| `waiting_qr` | 🟡 Amarelo | Instância criada, aguardando scan do QR Code |
| `connected` | 🟢 Verde | WhatsApp conectado e ativo |
| `disconnected` | 🔴 Vermelho | Sessão encerrada |
