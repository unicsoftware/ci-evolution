

# Implementação — Gestão de Conexões WhatsApp / Evolution API

## Contexto do projeto

Estou desenvolvendo um SaaS multi-tenant em **CodeIgniter**, utilizando a **Evolution API** para conexão e gerenciamento de WhatsApp.

A integração com a Evolution API **já existe e está funcionando** no sistema.

Não quero reconstruir a integração do zero.

Quero melhorar principalmente:

1. UX da tela de conexões WhatsApp.
2. Controle da quantidade de conexões permitidas pelo plano contratado pelo tenant.
3. Geração automática do nome técnico da instância Evolution.
4. Eliminar a possibilidade de o usuário criar nomes inconsistentes para as instâncias.
5. Melhorar o fluxo de conexão via QR Code.
6. Criar exportação da configuração da conexão em JSON.
7. Criar uma estrutura preparada para integração futura/imediata com outro ERP.
8. Manter o padrão visual do **Nice-Admin**.

Referência visual/template:

[https://github.com/hacktheme/Nice-Admin](https://github.com/hacktheme/Nice-Admin)

---

# REGRA PRINCIPAL

Antes de alterar qualquer arquivo:

1. Analise toda a estrutura atual do projeto.
2. Identifique como o sistema atualmente:

   * cadastra tenants;
   * identifica o tenant logado;
   * identifica o plano contratado;
   * determina a quantidade máxima de WhatsApps;
   * cria uma instância na Evolution API;
   * salva a instância no banco;
   * gera/exibe o QR Code;
   * verifica o status da conexão;
   * desconecta/reconecta uma instância.
3. Identifique Controller, Model, View, JavaScript, rotas e tabelas envolvidos.
4. Não crie uma segunda implementação paralela se já existir uma implementação funcional.
5. Reaproveite o máximo possível do código existente.
6. Preserve todas as funcionalidades atuais.
7. Faça alterações incrementais e compatíveis com a arquitetura atual.

Não substitua a integração existente com Evolution API sem necessidade.

---

# OBJETIVO DA NOVA UX

A tela deve ser apresentada ao usuário como:

**Conexões WhatsApp**

O usuário não deve precisar conhecer conceitos técnicos da Evolution API.

Evitar apresentar ao usuário final termos como:

* Instance Name
* Evolution Instance
* API Key
* Endpoint
* Webhook
* Server URL

Esses dados podem existir internamente, mas devem ficar ocultos da UX normal.

O usuário deve enxergar principalmente:

* Nome da conexão
* Número do WhatsApp
* Status
* Data/hora da conexão
* Ações disponíveis

---

# 1. LIMITE DE CONEXÕES POR PLANO

O tenant possui um plano contratado.

O plano determina a quantidade máxima de conexões WhatsApp.

Exemplo:

Plano:

```text
WhatsApps permitidos: 5
```

Tenant:

```text
Conexões utilizadas: 3
Disponíveis: 2
```

A tela deve apresentar claramente:

```text
Conexões WhatsApp

3 / 5 conexões utilizadas

[barra de progresso]

2 conexões disponíveis
```

O botão:

```text
+ Conectar WhatsApp
```

deve ficar disponível enquanto:

```text
connections_count < plan_limit
```

Quando atingir o limite:

```text
5 / 5 conexões utilizadas

Você atingiu o limite de conexões WhatsApp do seu plano.

[ Fazer upgrade ]
```

Se não existir atualmente uma funcionalidade de upgrade, apenas apresentar uma mensagem apropriada e não criar uma funcionalidade de pagamento nova.

---

# REGRA DE SEGURANÇA

O limite NÃO deve ser controlado somente no frontend.

O backend deve obrigatoriamente validar o limite antes de criar uma nova conexão.

Exemplo lógico:

```php
if ($connections_count >= $plan_limit) {
    return error;
}
```

Mesmo que alguém tente chamar diretamente o endpoint AJAX/API, não poderá ultrapassar o limite contratado.

Também considerar condições de concorrência para evitar que duas requisições simultâneas criem conexões acima do limite.

---

# 2. NOME DA INSTÂNCIA EVOLUTION

O usuário NÃO deve digitar o nome técnico da instância Evolution.

Não criar um campo:

```text
Nome da instância
```

para o usuário.

O sistema deve gerar automaticamente.

Sugestão de padrão:

```text
t{tenant_id}_wa_{connection_number}
```

Exemplos:

```text
t42_wa_01
t42_wa_02
t42_wa_03
```

Ou adaptar ao padrão existente do projeto caso exista uma convenção melhor.

Requisitos:

* somente lowercase;
* sem espaços;
* sem acentos;
* sem caracteres especiais;
* determinístico;
* único;
* compatível com Evolution API;
* não depender de texto digitado pelo usuário.

O nome técnico deve ser armazenado no banco.

Exemplo:

```text
tenant_id = 42
connection_number = 3

instance_name = t42_wa_03
```

---

# 3. NOME AMIGÁVEL DA CONEXÃO

Separar:

### Nome amigável

O usuário pode informar:

```text
WhatsApp Comercial
```

### Nome técnico

Gerado automaticamente:

```text
t42_wa_01
```

Nunca utilizar o nome amigável diretamente como nome da instância Evolution.

Exemplo:

```text
name = WhatsApp Comercial

instance_name = t42_wa_01
```

Se o usuário não informar nome amigável, gerar automaticamente:

```text
WhatsApp 01
WhatsApp 02
WhatsApp 03
```

---

# 4. BANCO DE DADOS

Primeiro verificar a estrutura atual.

Se já existir uma tabela de conexões WhatsApp, adaptar a tabela existente.

Não criar tabela duplicada sem necessidade.

A estrutura deverá suportar, no mínimo:

```text
id
tenant_id
name
instance_name
phone_number
status
external_id
created_at
updated_at
```

Se já existirem campos equivalentes, reutilizá-los.

Também preservar os campos técnicos existentes necessários para a Evolution API.

---

# 5. IDENTIFICAÇÃO DA CONEXÃO

Cada conexão deve possuir:

```text
id
tenant_id
```

E, se possível:

```text
external_id
```

O `external_id` será utilizado para integração com ERP.

Não utilizar somente o ID interno do banco para integrações externas.

Se o projeto já possui UUID/ULID ou outro identificador externo padrão, reutilizar o padrão existente.

---

# 6. TELA PRINCIPAL

Criar/reestruturar a tela:

```text
Conexões WhatsApp
```

Utilizar o padrão visual do:

**Nice-Admin**

Referência:

[https://github.com/hacktheme/Nice-Admin](https://github.com/hacktheme/Nice-Admin)

Utilizar componentes compatíveis com o template já presente no projeto.

Não criar um design completamente diferente do restante do sistema.

---

# 7. HEADER DA PÁGINA

Exibir:

```text
WhatsApp

Gerencie os números WhatsApp conectados à sua conta.
```

E um card/resumo:

```text
CONEXÕES

3 / 5

████████████░░░░░░

2 disponíveis
```

Botão:

```text
+ Conectar WhatsApp
```

---

# 8. CARDS DAS CONEXÕES

Preferencialmente apresentar as conexões em cards responsivos.

Cada card deve apresentar:

```text
🟢 CONECTADO

WhatsApp Comercial

+55 11 99999-9999

Conectado agora

[ Gerenciar ]
[ Exportar ]
```

Estados possíveis:

```text
🟢 CONECTADO
🟡 AGUARDANDO CONEXÃO
🔴 DESCONECTADO
⚪ INATIVO
```

Utilizar badges/labels do padrão Nice-Admin/Bootstrap existente.

Não utilizar emojis se isso entrar em conflito com o design atual do projeto; nesse caso utilizar ícones já existentes no template.

---

# 9. BOTÃO CONECTAR WHATSAPP

Ao clicar:

```text
+ Conectar WhatsApp
```

abrir modal ou página seguindo o padrão atual do projeto.

Mostrar:

```text
Conectar WhatsApp

Nome da conexão

[ WhatsApp Comercial ]

Esse será apenas o nome de identificação da conexão.
O nome técnico da instância será gerado automaticamente.

[ Cancelar ] [ Gerar conexão ]
```

Não solicitar:

```text
Instance Name
API Key
Server URL
```

---

# 10. CRIAÇÃO DA INSTÂNCIA

Ao confirmar:

1. Backend valida tenant.
2. Backend identifica plano.
3. Backend calcula limite.
4. Backend verifica quantidade atual.
5. Backend gera `instance_name`.
6. Backend cria a instância na Evolution API usando a integração já existente.
7. Backend salva a conexão.
8. Frontend recebe os dados necessários.
9. Exibir QR Code utilizando a implementação existente.
10. Aguardar conexão.

Não duplicar código de integração com Evolution API se já existir.

---

# 11. FLUXO DO QR CODE

Depois da criação:

```text
Conectando WhatsApp

WhatsApp Comercial

[ QR CODE ]

Abra o WhatsApp no celular:

Configurações
→ Aparelhos conectados
→ Conectar aparelho

Aguardando conexão...
```

O status deve ser atualizado usando o mecanismo já existente no projeto.

Quando conectado:

```text
🟢 WhatsApp conectado!

Número:
+55 11 99999-9999

[ Continuar ]
```

Atualizar o card automaticamente.

---

# 12. STATUS

O sistema deve manter o status sincronizado com a Evolution API usando a lógica já existente.

Não criar polling duplicado se já existir.

Se já existir AJAX polling, websocket, webhook ou outra estratégia, reutilizar.

Mapear os estados técnicos da Evolution para estados amigáveis da interface.

Exemplo:

```text
open       → CONECTADO
connecting → AGUARDANDO CONEXÃO
close      → DESCONECTADO
```

Adaptar aos estados realmente utilizados pela versão da Evolution API existente no projeto.

---

# 13. EXPORTAÇÃO JSON

Adicionar no card:

```text
[ Exportar ]
```

Ao clicar, disponibilizar:

```text
Exportar conexão

[ Baixar JSON ]
[ Copiar JSON ]
```

O arquivo deverá ter nome semelhante a:

```text
whatsapp-t42-wa-01.json
```

ou:

```text
whatsapp-comercial.json
```

Conteúdo sugerido:

```json
{
    "integration": "whatsapp",
    "provider": "evolution",
    "connection_id": 18,
    "external_id": "wh_01HXYZ",
    "tenant_id": 42,
    "name": "WhatsApp Comercial",
    "instance_name": "t42_wa_01",
    "phone": "5511999999999",
    "status": "connected"
}
```

Adaptar os campos ao modelo real do sistema.

---

# 14. SEGURANÇA DA EXPORTAÇÃO

ATENÇÃO:

Não exportar automaticamente:

```text
Evolution API Key
senha
credencial mestre
tokens administrativos
segredos internos
```

Não colocar credenciais sensíveis no JSON apenas porque existem no banco.

Se o ERP precisar autenticar na conexão, criar/usar um mecanismo de token de integração específico.

Nunca expor uma API Key administrativa da Evolution para o usuário final.

---

# 15. COPIAR JSON

O botão:

```text
Copiar JSON
```

deve copiar para o clipboard exatamente o JSON da integração.

Mostrar feedback:

```text
JSON copiado com sucesso.
```

Utilizar o sistema de notificações/toast já existente no projeto.

Não criar uma biblioteca adicional sem necessidade.

---

# 16. PREPARAÇÃO PARA INTEGRAÇÃO COM ERP

A arquitetura deve deixar preparada uma integração futura.

Cada conexão deve possuir uma identificação externa estável:

```text
external_id
```

Exemplo:

```text
wh_01JXYZ123
```

O ERP poderá armazenar:

```text
tenant_external_id
connection_external_id
instance_name
phone
integration_token
```

Não fazer o ERP depender diretamente do ID auto_increment interno.

---

# 17. BOTÃO "ENVIAR PARA ERP"

Verificar se já existe alguma integração com ERP no projeto.

Se existir, integrar com ela.

Se não existir, NÃO criar uma integração completa fictícia.

Nesse caso, deixar preparada a estrutura para posteriormente implementar:

```text
[ Exportar JSON ]
[ Copiar JSON ]
```

e, se for simples dentro da arquitetura existente:

```text
[ Enviar para ERP ]
```

Mas não inventar endpoints externos que não existem.

---

# 18. GERENCIAMENTO DA CONEXÃO

O botão:

```text
Gerenciar
```

deve permitir ações como:

```text
Visualizar conexão
Reconectar
Desconectar
Renomear
Exportar
```

Somente apresentar ações que já sejam suportadas pela integração existente.

---

# 19. EXCLUSÃO

Se existir exclusão de conexão, garantir que:

1. A instância seja tratada corretamente na Evolution API.
2. O registro seja atualizado/removido conforme a regra atual do sistema.
3. O limite disponível seja recalculado.
4. O tenant possa criar uma nova conexão depois.

Não permitir que uma conexão excluída continue contando para o limite, salvo se a regra comercial do sistema exigir isso.

---

# 20. VALIDAÇÕES

Implementar validações no backend e frontend.

Nome amigável:

* obrigatório ou usar nome automático;
* tamanho máximo compatível com banco;
* limpar espaços desnecessários.

Instância:

* gerada pelo backend;
* nunca confiada ao frontend;
* lowercase;
* única.

Tenant:

* conexão sempre vinculada ao tenant autenticado;
* nunca aceitar `tenant_id` arbitrário enviado pelo frontend.

---

# 21. MULTI-TENANCY / SEGURANÇA

Esse ponto é crítico.

Nenhum tenant pode:

* visualizar conexões de outro tenant;
* exportar conexão de outro tenant;
* editar conexão de outro tenant;
* excluir conexão de outro tenant;
* consultar status de conexão de outro tenant;
* utilizar `connection_id` de outro tenant para acessar dados.

Sempre validar:

```text
connection.tenant_id == authenticated_tenant_id
```

Não confiar somente no ID enviado pelo frontend.

---

# 22. FRONTEND

Utilizar:

* Bootstrap existente;
* componentes Nice-Admin;
* ícones já presentes;
* modais existentes;
* cards;
* badges;
* alerts;
* progress bar;
* buttons;
* toast/notification já existente.

Evitar adicionar frameworks novos.

Evitar dependências desnecessárias.

Manter compatibilidade com a versão atual do projeto.

---

# 23. RESPONSIVIDADE

A tela precisa funcionar bem em:

* desktop;
* notebook;
* tablet;
* celular.

Os cards devem quebrar corretamente.

O modal de QR Code deve funcionar em telas pequenas.

---

# 24. UX DE ERROS

Se a Evolution API retornar erro:

Mostrar mensagem amigável.

Exemplo:

```text
Não foi possível criar a conexão WhatsApp.

Verifique a disponibilidade da integração e tente novamente.
```

Não mostrar stack trace, SQL error ou credenciais.

Registrar detalhes técnicos no log do sistema.

---

# 25. LOGS

Adicionar logs úteis para:

```text
criação da conexão
criação da instância
erro Evolution API
conexão realizada
desconexão
exportação
```

Nunca registrar:

```text
API Keys
tokens secretos
senhas
credenciais
QR Code completo
```

---

# 26. COMPATIBILIDADE

Antes de alterar:

* identificar versão do CodeIgniter;
* identificar versão do PHP;
* identificar estrutura de rotas;
* identificar ORM/query builder utilizado;
* identificar sistema de autenticação;
* identificar estrutura multi-tenant;
* identificar versão/contrato da Evolution API;
* identificar tabelas existentes.

Não assumir CodeIgniter 3 ou 4 sem verificar.

Não assumir estrutura de banco.

Não substituir tecnologias existentes sem necessidade.

---

# 27. MIGRATION

Se forem necessários novos campos:

Criar migration seguindo o padrão já utilizado no projeto.

Não alterar banco manualmente sem necessidade.

Exemplo de campos possíveis:

```text
name
instance_name
external_id
status
phone_number
```

Mas somente criar aquilo que realmente não existir.

---

# 28. REGRA DE NOME AUTOMÁTICO

Implementar uma função centralizada, por exemplo:

```php
generateInstanceName($tenantId, $connectionNumber)
```

Resultado:

```text
t42_wa_01
t42_wa_02
t42_wa_03
```

Não espalhar essa lógica em vários Controllers.

O backend deve ser a única autoridade para geração.

---

# 29. NÃO QUEBRAR FUNCIONALIDADES EXISTENTES

Antes de finalizar, verificar:

* conexão existente continua funcionando;
* QR Code continua funcionando;
* reconexão continua funcionando;
* desconexão continua funcionando;
* status continua funcionando;
* tenants antigos continuam funcionando;
* conexões antigas continuam funcionando;
* planos existentes continuam funcionando.

Se houver dados antigos sem `instance_name` ou `external_id`, criar estratégia de compatibilidade/migração.

Não apagar dados existentes.

---

# 30. TESTES

Criar/verificar pelo menos estes cenários:

### Cenário 1

Tenant possui:

```text
5 permitidas
0 utilizadas
```

Deve conseguir criar.

### Cenário 2

Tenant possui:

```text
5 permitidas
4 utilizadas
```

Deve conseguir criar somente mais uma.

### Cenário 3

Tenant possui:

```text
5 permitidas
5 utilizadas
```

Não deve conseguir criar.

### Cenário 4

Duas requisições simultâneas tentando criar a última conexão.

Não permitir ultrapassar o limite.

### Cenário 5

Tenant A tentando acessar conexão do Tenant B.

Deve retornar acesso negado/não encontrado.

### Cenário 6

Criar três conexões.

Deve gerar:

```text
tX_wa_01
tX_wa_02
tX_wa_03
```

### Cenário 7

Exportar conexão.

JSON deve ser válido e não conter segredos.

### Cenário 8

Conectar WhatsApp pelo QR Code.

Status deve mudar corretamente para conectado.

---

# 31. ENTREGÁVEIS

Ao finalizar, apresentar:

## 1. Arquivos alterados

Listar:

```text
Controller:
...

Model:
...

View:
...

JavaScript:
...

Migration:
...

Routes:
...
```

## 2. Banco

Explicar:

```text
Tabela:
Campo:
Tipo:
Motivo:
```

## 3. Evolution API

Explicar quais chamadas existentes foram reutilizadas ou modificadas.

## 4. Fluxo

Explicar:

```text
Tenant
↓
Plano
↓
Limite
↓
Nova conexão
↓
Nome automático
↓
Evolution API
↓
QR Code
↓
WhatsApp conectado
↓
Exportação JSON
```

## 5. Segurança

Explicar como foi garantido:

* isolamento entre tenants;
* limite de conexões;
* proteção de credenciais;
* validação no backend.

## 6. Testes

Listar os testes realizados e respectivos resultados.

---

# REGRAS FINAIS

1. NÃO reconstruir a integração Evolution API se ela já funciona.
2. NÃO permitir que o usuário informe o nome técnico da instância.
3. NÃO confiar no limite enviado pelo frontend.
4. NÃO permitir que o frontend defina livremente `tenant_id`.
5. NÃO expor API Keys sensíveis no JSON.
6. NÃO criar endpoints fictícios para ERP.
7. NÃO adicionar frameworks desnecessários.
8. NÃO alterar funcionalidades que não tenham relação com essa tarefa.
9. NÃO criar uma segunda arquitetura paralela.
10. Reutilizar o padrão visual do Nice-Admin.
11. Priorizar código simples, organizado e compatível com o projeto atual.
12. Antes de codificar, faça uma análise da arquitetura existente e identifique os arquivos responsáveis pela conexão atual.

## IMPORTANTE

Comece a tarefa fazendo uma **análise do projeto existente**.

Primeiro mostre:

```text
1. Como a conexão Evolution funciona atualmente.
2. Quais Controllers estão envolvidos.
3. Quais Models estão envolvidos.
4. Quais Views estão envolvidas.
5. Quais tabelas estão envolvidas.
6. Como o tenant e o plano são identificados.
7. Como o limite atual de WhatsApps é determinado.
8. Como o QR Code é gerado.
9. Como o status da conexão é atualizado.
10. Qual é o menor conjunto de alterações necessário.
```

Somente depois dessa análise implemente a solução.

O objetivo é **evoluir a implementação atual**, não substituí-la.
