# Feature Specification: Pixel Tracking Module

**Feature Branch**: `001-pixel-tracking-module`
**Created**: 2026-02-16
**Status**: Draft
**Reference Implementation**: `examples/pixel-x-app/` (sempre consultar como referencia)
**Input**: Criar um modulo de tracking (Meta Pixel + Conversion API) dentro do alvobot-plugin-manager, inspirado no pixel-x-app. Focado em blogs (sem e-commerce). Armazenamento local no WordPress (sem Supabase para eventos/leads). Opcao de conectar pixel via conta Facebook do AlvoBot ou inserir manualmente.

## Problem Statement

Os clientes AlvoBot que rodam campanhas no Facebook Ads para blogs precisam de tracking preciso de conversoes para otimizar campanhas. As solucoes atuais (pixel-x-app, plugins de terceiros) sao externas ao ecossistema e criam dependencia de licencas separadas.

O AlvoBot precisa de um modulo de tracking nativo que:
1. Injete o Meta Pixel no frontend do site WordPress
2. Capture leads automaticamente de formularios
3. Envie eventos server-side via Facebook Conversion API (deduplicados)
4. Armazene dados de eventos/leads localmente no WordPress (como o Pixel X faz)
5. Permita conectar o pixel de duas formas: via conta Facebook ja vinculada no AlvoBot, ou inserindo Pixel ID + Token manualmente
6. Siga o padrao de modulos do alvobot-plugin-manager

**Contexto do blog**: Os sites que usarao este modulo sao blogs de conteudo. Os eventos relevantes sao PageView, ViewContent (artigo lido), Lead (formulario submetido), CompleteRegistration, Contact, Search, e CustomEvent. Nao ha carrinho de compras, checkout, nem produtos com preco.

## Design Decisions

### Armazenamento: WordPress Local (como Pixel X)
Eventos e leads sao armazenados como Custom Post Types no WordPress local, exatamente como o Pixel X faz (`pxa_event`, `pxa_lead`). NAO enviar eventos/leads ao Supabase. O Supabase e usado APENAS para buscar pixels ja conectados da conta Facebook do usuario (leitura, nao escrita).

### Geolocalizacao: Mesmo servico do Pixel X
Utilizar o mesmo stack de geolocalizacao do Pixel X:
- IP/edge signals: first-party WordPress request metadata, with Cloudflare headers used only when present
- Primary geo: server-side provider configured outside the plugin source (no committed API keys)
- Fallback geo: optional public provider with timeout and no fatal error when unavailable
- Cache: localStorage por IP (chave `pxa_geo_location_{ip}`)

### Configuracao de Pixel: Dual Mode
O admin escolhe entre:
- **Modo AlvoBot**: Busca pixels da conta Facebook ja conectada no alvobot-app (via tabela `meta_pixels` no Supabase). O plugin consulta a Edge Function `api_plugin` para obter a lista de pixels ativos da conexao do usuario. O token da conexao e recebido somente no backend admin, transformado em referencia temporaria (`token_ref`) e salvo localmente de forma criptografada para uso runtime da Conversion API.
- **Modo Manual**: Admin insere Pixel ID e Conversion API Token diretamente, como o Pixel X faz hoje.

### Escopo de Eventos: Blog (sem e-commerce)
Nao ha produtos, precos, carrinho, checkout, ou integracao com plataformas de pagamento. Eventos relevantes para blog sao focados em engajamento e captura de leads.

## Clarifications

### Session 2026-02-16

- Q: Does the module need to handle cookie/tracking consent, or is consent assumed to be managed externally? → A: Consent managed externally; module checks a consent signal (cookie or JS variable) before firing tracking. No built-in consent banner.
- Q: Which WordPress roles should be excluded from tracking (FR-017)? → A: Configurable in settings — admin chooses which roles to exclude. Default: all roles tracked (no exclusions).
- Q: Which scheduling mechanism for server-side event dispatch (FR-024)? → A: Action Scheduler (bundled), same as Pixel X. Recurring action every 5min, single-actions per batch, REST endpoint for external trigger as backup.
- Q: Should events/leads use Custom Post Types or custom DB tables? → A: CPTs (same as Pixel X), with configurable max volume cap. When limit is reached, oldest records are purged first. Dual retention: time-based (days) + volume-based (max records).
- Q: How should browser-captured events be persisted to WordPress for server-side dispatch? → A: AJAX/REST call per event from browser JS to WordPress PHP (same as Pixel X). Each event fires an async POST to create a CPT record with status `pending`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar Pixel via Conta AlvoBot (Priority: P1)

O admin acessa o modulo "Pixel Tracking" e escolhe "Conectar via AlvoBot". O sistema consulta a conta Facebook ja vinculada no alvobot-app (usando o mesmo padrao que ja usa para creditos: `wp_remote_post` para a Edge Function `api_plugin` com `site_url` + `alvobot_site_token`) e exibe os pixels disponiveis. O admin seleciona um pixel e o tracking comeca automaticamente.

**Padrao de comunicacao (identico a creditos):**
- Endpoint: `ALVOBOT_SERVER_URL` (`api_plugin` Edge Function)
- Auth: `site_url` + `alvobot_site_token` (32 chars, ja existe em `wp_options`)
- Action: `get_meta_pixels` (nova action na Edge Function)
- Cache: `get_transient('alvobot_pixel_tracking_pixels')` com TTL de 15 minutos
- A Edge Function consulta `meta_pixels` JOIN `connections` WHERE `is_active = true` AND `connections.is_active = true` AND `connections.needs_reconnect = false`

**Why this priority**: Experiencia mais fluida para usuarios que ja tem conta AlvoBot com Facebook conectado. Elimina atrito de configuracao.

**Independent Test**: Com uma conta Facebook ja conectada no alvobot-app (que tem pixels na tabela `meta_pixels`), acessar o modulo, selecionar um pixel, e verificar via Facebook Pixel Helper que o PageView esta disparando.

**Acceptance Scenarios**:

1. **Given** o site esta registrado no AlvoBot (projeto vinculado no Supabase) e tem uma conexao Meta ativa com pixels, **When** o admin acessa o modulo e escolhe "Conectar via AlvoBot", **Then** o sistema exibe uma lista dos pixels disponiveis (pixel_name + pixel_id) lidos via Edge Function `api_plugin` com action `get_meta_pixels`
2. **Given** o admin selecionou um pixel da lista, **When** salva a configuracao, **Then** o `pixel_id`, `connection_id`, nome publico e uma referencia/versao criptografada do token sao armazenados localmente em `wp_options`, sem expor `access_token` no HTML admin ou frontend, e o tracking comeca
3. **Given** o site NAO esta registrado no AlvoBot (`alvobot_site_token` vazio), **When** o admin tenta "Conectar via AlvoBot", **Then** uma mensagem informa que o site precisa estar vinculado ao AlvoBot e oferece o modo manual como alternativa
4. **Given** a conexao Meta no AlvoBot nao tem pixels ativos, **When** o admin acessa a lista, **Then** uma mensagem informa "Nenhum pixel encontrado" e sugere verificar a conexao no alvobot-app
5. **Given** o token da conexao Meta expira (`needs_reconnect = true`), **When** o cron de envio tenta usar o token, **Then** o sistema marca o pixel como "token expirado" e exibe aviso no admin para reconectar via AlvoBot

---

### User Story 2 - Configurar Pixel Manualmente (Priority: P1)

O admin escolhe "Inserir manualmente" e digita o Meta Pixel ID e Conversion API Token. Funciona identicamente ao Pixel X — nao depende do AlvoBot estar conectado.

**Why this priority**: Alternativa essencial para usuarios que nao usam o alvobot-app ou preferem configuracao direta. Tambem e o fallback quando a conexao AlvoBot nao esta disponivel.

**Independent Test**: Inserir Pixel ID e Token manualmente, salvar, verificar que PageView dispara no Facebook Pixel Helper.

**Acceptance Scenarios**:

1. **Given** o admin escolheu modo manual, **When** insere um Pixel ID valido (numerico, 15-16 digitos) e um Conversion API Token, **Then** a configuracao e salva e o tracking comeca
2. **Given** o admin inseriu um Pixel ID invalido (formato incorreto), **When** tenta salvar, **Then** uma mensagem de erro inline e exibida e a configuracao nao e salva
3. **Given** o admin configurou o Conversion API Token, **When** salva, **Then** o token e armazenado de forma segura em `wp_options` e NAO aparece em texto plano no HTML do site
4. **Given** o modulo esta desativado no dashboard AlvoBot, **When** um visitante acessa o site, **Then** nenhum script de tracking e injetado
5. **Given** o admin configurou a exclusao do role "administrator" nas configuracoes do modulo (FR-017), **When** um usuario logado como admin navega pelo site, **Then** o tracking NAO e disparado (pois o role esta na lista de exclusao configurada)

---

### User Story 3 - Tracking Automatico de Eventos no Browser (Priority: P1)

O sistema injeta um script JavaScript (inspirado no `pxa-base.js` do Pixel X) que captura automaticamente dados do visitante (fbp, fbc, UTMs, referrer, geolocation) e dispara eventos configurados no browser via `fbq()`. Os eventos sao disparados de acordo com triggers configurados.

**Why this priority**: O tracking browser-side e a funcionalidade core. E o que o usuario espera imediatamente apos configurar o pixel.

**Independent Test**: Configurar um evento "Lead" com trigger "form submit" em uma pagina especifica, submeter o formulario, verificar no Facebook Events Manager que o evento Lead foi recebido com fbp, fbc, email hasheado.

**Acceptance Scenarios**:

1. **Given** o pixel esta configurado (qualquer modo), **When** um visitante acessa uma pagina, **Then** o script captura automaticamente: `fbp` (gerado ou lido do cookie `_fbp`), `fbc` (extraido do parametro `fbclid` na URL ou cookie `_fbc`), todos os UTM parameters, `referrer`, `page_url`, `page_title`, e `user_agent`
2. **Given** o pixel esta configurado, **When** o visitante acessa uma pagina, **Then** o sistema captura identificadores first-party, UTMs e contexto de edge quando disponivel; Cloudflare e provedores de geo sao opcionais e nunca bloqueiam o tracking em hospedagens comuns como Hostinger, HostGator ou servidores sem CDN
3. **Given** uma conversao do tipo "Lead" esta configurada com trigger "form_submit" para a pagina `/contato`, **When** o visitante submete o formulario, **Then** o evento `Lead` e disparado via `fbq('track', 'Lead', {...})` com `event_id` unico (UUID)
4. **Given** uma conversao do tipo "ViewContent" com trigger "page_load" esta configurada para posts do blog, **When** um visitante acessa um artigo, **Then** `fbq('track', 'ViewContent', {content_name: 'titulo do post'})` e disparado automaticamente
5. **Given** uma conversao com trigger "scroll" a 80% esta configurada, **When** o visitante scrolla 80% da pagina, **Then** o evento e disparado uma unica vez (sem duplicata por re-scroll)
6. **Given** uma conversao com trigger "click" no seletor CSS `.cta-button`, **When** o visitante clica, **Then** o evento e disparado com os dados configurados
7. **Given** uma conversao com trigger "page_time" de 30 segundos, **When** o visitante permanece 30s na pagina, **Then** o evento e disparado uma unica vez

---

### User Story 4 - Captura Automatica de Leads (Priority: P2)

O script frontend detecta formularios na pagina, intercepta o submit, e captura dados de lead (nome, email, telefone) para enriquecer eventos subsequentes. Leads sao armazenados como Custom Post Type no WordPress (como `pxa_lead` no Pixel X).

**Why this priority**: A captura de leads enriquece os dados enviados a Conversion API (PII hasheado melhora a taxa de match do Facebook).

**Independent Test**: Acessar uma pagina com formulario de contato, preencher nome/email/telefone, submeter, verificar que o lead aparece no admin WordPress como post type `alvobot_pixel_lead` com fbp/fbc/UTMs associados.

**Acceptance Scenarios**:

1. **Given** a captura automatica de leads esta ativa, **When** um visitante submete qualquer formulario com campo de email, **Then** o sistema captura `email`, `nome` (se disponivel), `phone` (se disponivel), e associa com `fbp`, `fbc`, UTMs, IP, user agent, e geolocation
2. **Given** um lead ja foi capturado nesta sessao (mesmo `fbp`), **When** o visitante submete outro formulario, **Then** o lead existente e atualizado (upsert por email ou fbp) em vez de criar duplicata
3. **Given** a captura esta ativa, **When** o formulario nao contem campo de email, **Then** nenhum lead e criado (email e obrigatorio)
4. **Given** um lead foi capturado, **When** armazenado no WordPress, **Then** PII (email, phone, name) sao armazenados com valores SHA-256 em campos separados (para uso na Conversion API), alem do valor original
5. **Given** o site usa formularios de plugins terceiros (Contact Form 7, Elementor Forms, WPForms), **When** o visitante submete, **Then** a captura funciona via deteccao generica de formularios no DOM (como o Pixel X faz em `send_lead_data()`)
6. **Given** um lead foi capturado, **When** consultado no admin, **Then** o registro contem: email, nome, telefone, IP, fbp, fbc, UTMs (first_* e last_*), geolocation, device, timestamps

---

### User Story 5 - Envio Server-Side via Conversion API (Priority: P2)

Eventos capturados sao enviados ao Facebook Conversion API via server-side (PHP cron), garantindo tracking mesmo com bloqueadores de anuncios. O `event_id` e o mesmo do browser para deduplicacao. Eventos sao armazenados como Custom Post Type no WordPress (como `pxa_event` no Pixel X) com status `pending` → `sent` → `error`.

**Why this priority**: A Conversion API garante tracking confiavel. Bloqueadores de anuncios (30-40% dos usuarios) eliminam tracking browser-side.

**Independent Test**: Disparar um evento com ad blocker ativo, verificar no Facebook Events Manager que o evento foi recebido via server-side, sem duplicata quando o bloqueador esta desativado.

**Acceptance Scenarios**:

1. **Given** um evento foi disparado no browser com `event_id = "abc-123"`, **When** o PHP cron de envio executa, **Then** o mesmo `event_id` e enviado ao `POST https://graph.facebook.com/{ALVOBOT_PIXEL_FB_API_VERSION}/{PIXEL_ID}/events` com `action_source: "website"`
2. **Given** multiplos eventos com status `pending` existem, **When** o cron executa (a cada 5 minutos, como Pixel X), **Then** eventos sao enviados em batches de ate 250 por request
3. **Given** o envio de um batch falha, **When** o sistema retenta, **Then** o batch e dividido em sub-batches menores (como Pixel X faz em `PXA_send_event_to_server()`); eventos que continuam falhando sao marcados com `status = 'error'`
4. **Given** um evento contem dados de lead, **When** enviado a Conversion API, **Then** o `user_data` contem PII hasheado com SHA-256: `em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`, `fbc`, `fbp`, `client_ip_address`, `client_user_agent`, `external_id`
5. **Given** o Conversion API Token esta configurado em modo teste, **When** eventos sao enviados, **Then** o `test_event_code` e incluido no payload
6. **Given** um evento foi enviado com sucesso, **When** consultado no admin, **Then** o post type tem `status = 'sent'` e contem o `response_payload` da API do Facebook em post meta (para auditoria, como Pixel X faz)
7. **Given** o pixel foi configurado via modo AlvoBot, **When** o cron envia eventos, **Then** o token criptografado localmente e descriptografado apenas no runtime de envio da Conversion API

---

### User Story 6 - Configurar Conversoes no Admin (Priority: P2)

O administrador cria "conversoes" — regras que definem qual evento disparar, em quais paginas, com qual trigger. Armazenadas como Custom Post Type no WordPress (como `pxa_conversion` no Pixel X).

**Why this priority**: Sem conversoes configuraveis, o sistema so dispara PageView. O valor real esta em poder configurar Lead no formulario de contato, ViewContent nos artigos, etc.

**Independent Test**: Criar uma conversao "Lead" com trigger "form_submit" para a pagina `/contato`, submeter o formulario, verificar que o evento Lead chega ao Facebook.

**Acceptance Scenarios**:

1. **Given** o admin acessa a secao "Conversoes" do modulo, **When** clica em "Nova Conversao", **Then** um formulario e exibido com: nome da conversao, tipo de evento (dropdown), tipo de trigger, seletor CSS (para click/form_submit/view_element), paginas alvo, e campos opcionais de content_name
2. **Given** o admin selecionou evento "Lead" e trigger "form_submit", **When** salva, **Then** a conversao aparece na lista com status ativo e pode ser editada ou desativada
3. **Given** existem 3 conversoes ativas para a mesma pagina, **When** um visitante acessa, **Then** todas sao avaliadas e os respectivos eventos disparados conforme seus triggers
4. **Given** uma conversao esta configurada para "todas as paginas", **When** o admin muda para "paginas especificas", **Then** um seletor de paginas/posts do WordPress e exibido

**Tipos de evento suportados (foco blog):**
- PageView, Lead, ViewContent, CompleteRegistration, Contact, Search, Subscribe, StartTrial, SubmitApplication, Schedule, CustomEvent

**Tipos de trigger suportados:**
- `page_load` — Dispara ao carregar a pagina
- `page_time` — Dispara apos N segundos na pagina
- `form_submit` — Dispara ao submeter formulario (seletor CSS)
- `click` — Dispara ao clicar em elemento (seletor CSS)
- `scroll` — Dispara ao atingir N% de scroll
- `view_element` — Dispara quando elemento entra no viewport (seletor CSS)

---

### User Story 7 - Modo Teste (Priority: P3)

O administrador ativa o "Modo Teste" que envia eventos com `test_event_code` para o Facebook Events Manager, como o Pixel X faz.

**Why this priority**: Essencial para setup inicial e debugging, mas nao bloqueia o uso em producao.

**Independent Test**: Ativar modo teste, disparar eventos, verificar no Facebook Events Manager na aba "Test Events".

**Acceptance Scenarios**:

1. **Given** o modo teste esta ativo, **When** eventos sao enviados a Conversion API, **Then** o campo `test_event_code` e incluido no payload
2. **Given** o modo teste esta ativo, **When** o admin acessa a pagina de configuracao, **Then** um banner amarelo indica "Modo Teste Ativo"
3. **Given** o admin desativa o modo teste, **When** eventos sao enviados, **Then** o `test_event_code` nao e incluido

---

### User Story 8 - Limpeza Automatica de Dados (Priority: P3)

O sistema limpa automaticamente eventos e leads antigos para evitar que o banco WordPress cresca indefinidamente (como Pixel X faz em `PXA_models_cleaner`).

**Why this priority**: Sem limpeza, o banco de dados do WordPress cresce indefinidamente. Necessario para sites com alto trafego.

**Independent Test**: Configurar retencao para 7 dias, verificar que eventos mais antigos sao deletados pelo cron.

**Acceptance Scenarios**:

1. **Given** a limpeza automatica esta configurada para 7 dias, **When** o cron diario executa, **Then** eventos e leads com mais de 7 dias sao deletados
2. **Given** o admin configura retencao de 30 dias, **When** salva, **Then** o valor e atualizado e o proximo cron usara o novo periodo
3. **Given** a limpeza esta desativada, **When** o cron executa, **Then** nenhum dado e deletado

---

### User Story 9 - REST API para Interacao com o AlvoBot App (Priority: P2)

O modulo expoe endpoints REST API completos no namespace `alvobot-pro/v1/pixel-tracking/*` para que o alvobot-app possa interagir remotamente com o modulo — configurar pixels, gerenciar conversoes, consultar status de eventos/leads, e ativar/desativar tracking. Usa o mesmo padrao de autenticacao dos demais endpoints do plugin (Basic Auth com `alvobot_site_token`).

**Why this priority**: Sem API REST, o alvobot-app nao consegue gerenciar o pixel tracking remotamente. Os endpoints permitem que futuras features do app (dashboard, configuracao centralizada) interajam com o modulo.

**Independent Test**: Usando `curl` ou Postman com Basic Auth (`alvobot:{site_token}`), chamar `GET /wp-json/alvobot-pro/v1/pixel-tracking/settings` e receber a configuracao atual do modulo em JSON.

**Acceptance Scenarios**:

1. **Given** o alvobot-app faz uma chamada autenticada a `GET /pixel-tracking/settings`, **When** a autenticacao e valida, **Then** retorna a configuracao completa do modulo (pixels, modo teste, retencao, etc.)
2. **Given** o alvobot-app envia `POST /pixel-tracking/settings` com novos dados de pixel, **When** os dados sao validos, **Then** a configuracao e atualizada e o tracking reflete as mudancas
3. **Given** o alvobot-app chama `GET /pixel-tracking/conversions`, **When** autenticado, **Then** retorna a lista de conversoes configuradas com todos os seus campos
4. **Given** o alvobot-app envia `POST /pixel-tracking/conversions` com uma nova conversao, **When** os dados sao validos, **Then** a conversao e criada e fica ativa
5. **Given** o alvobot-app chama `GET /pixel-tracking/stats`, **When** autenticado, **Then** retorna contadores: total de eventos (pending/sent/error), total de leads, ultimo envio a Conversion API
6. **Given** uma chamada sem autenticacao valida, **When** qualquer endpoint e acessado, **Then** retorna 401 Unauthorized

**Endpoints REST API completos:**

```
Namespace: alvobot-pro/v1/pixel-tracking

CONFIGURACAO:
GET    /settings                    → Retorna configuracao completa do modulo
POST   /settings                    → Atualiza configuracao (pixels, modo teste, retencao)
GET    /settings/pixels             → Lista pixels configurados (com source: alvobot|manual)
POST   /settings/pixels             → Adiciona pixel (manual: pixel_id + token; alvobot: pixel_id da conexao)
DELETE /settings/pixels/{pixel_id}  → Remove pixel

CONVERSOES:
GET    /conversions                 → Lista todas as conversoes
GET    /conversions/{id}            → Detalhe de uma conversao
POST   /conversions                 → Cria nova conversao
PUT    /conversions/{id}            → Atualiza conversao
DELETE /conversions/{id}            → Deleta conversao

EVENTOS:
GET    /events                      → Lista eventos (filtros: status, event_name, date_from, date_to, limit, offset)
GET    /events/{event_id}           → Detalhe de um evento (com payload/response da API)
GET    /events/stats                → Contadores agregados: total, pending, sent, error, por dia

LEADS:
GET    /leads                       → Lista leads (filtros: date_from, date_to, search, limit, offset)
GET    /leads/{lead_id}             → Detalhe de um lead (com historico UTMs)
GET    /leads/stats                 → Contadores agregados: total, por dia, por fonte

STATUS:
GET    /status                      → Status geral: modulo ativo, pixels configurados, ultimo envio CAPI,
                                      eventos pendentes, saude do cron, token valido (AlvoBot mode)

ACOES:
POST   /actions/send-pending        → Forca envio imediato de eventos pendentes a Conversion API
POST   /actions/cleanup             → Forca limpeza de dados antigos
POST   /actions/refresh-pixels      → Forca refresh da lista de pixels do AlvoBot (limpa transient)
POST   /actions/test-pixel          → Envia um evento de teste ao Facebook para validar configuracao
```

**Autenticacao**: Mesma do plugin — Basic Auth com `username:alvobot_site_token` (via `verify_token()` ou `authenticate_rest_api_request()` ja existentes em `class-alvobot-pro.php`).

**Seguranca e paginacao REST**:
- Endpoints de listagem (`/events`, `/leads`, `/conversions`) usam `limit` default 20, maximo 100, e `offset` sanitizado.
- Filtros (`date_from`, `date_to`, `event_name`, `status`, `search`) devem ser sanitizados e validados com parsing estrito de datas antes de entrar em `WP_Query` ou SQL preparado do WordPress.
- Chamadas autenticadas do app devem respeitar rate limit de 100 req/min por token, retornando HTTP 429 com `Retry-After` quando excedido.
- Headers CORS so devem ser emitidos para origens configuradas do AlvoBot App; origem arbitraria nao e permitida.

**Formato de resposta padrao:**
```json
{
  "success": true,
  "data": { ... },
  "meta": { "total": 150, "page": 1, "per_page": 20 }
}
```

---

### Edge Cases

- O que acontece quando o Pixel ID e removido enquanto ha eventos pendentes? → Eventos pendentes sao descartados e logados como "pixel_removed"
- Como o sistema lida com JavaScript desabilitado? → Nenhum tracking ocorre (Meta Pixel requer JS)
- Site com cache agressivo (WP Super Cache, Cloudflare)? → Script de tracking com dados dinamicos inline (nonce/timestamp), como Pixel X faz
- Paginas protegidas por senha/login? → Tracking so ocorre se usuario nao e admin logado
- Facebook Conversion API retorna rate limit (429)? → Backoff exponencial: delay inicial 60s, multiplicador 2x, max 1h, max 5 tentativas; token invalido (190/463/467) vira erro permanente e notifica admin
- Impacto em Core Web Vitals? → Script com `defer`/`async`; nenhuma chamada sincrona; lazy load do SDK
- Token da conexao AlvoBot expira? → Banner de aviso no admin; cron para fora de enviar; admin pode trocar para modo manual
- Geo provider esta indisponivel? → Falha e logada sem fatal; endpoints e chaves de provedores devem ser configuraveis, sem API keys commitadas; IP pode ser anonimizado antes de consulta conforme politica de privacidade do site
- Dois pixels configurados (um via AlvoBot, um manual)? → Ambos recebem todos os eventos (como Pixel X com multiplos pixels)
- Modulo desativado no dashboard com eventos pendentes? → Eventos permanecem no banco, cron verifica flag `module active` e pula execucao. Eventos NAO sao perdidos — serao enviados quando o modulo for reativado.
- Consentimento revogado durante a sessao? → Tracking para imediatamente. O modulo deve remover cookies proprios de tracking (`_fbp`, `_fbc`, `_alvo_*`) quando conseguir detectar a revogacao; se a remocao for delegada ao plugin de consentimento, isso deve ficar claro na UI/docs. Pedidos de exclusao devem poder purgar eventos/leads pendentes vinculados ao visitante.

## Requirements *(mandatory)*

### Functional Requirements

**Modulo WordPress:**
- **FR-001**: O modulo DEVE seguir o padrao de registro do alvobot-plugin-manager (`$module_files`, `$default_modules`, `$module_classes`, `$module_names` em `class-alvobot-pro.php`)
- **FR-002**: O modulo DEVE suportar dois modos de configuracao de pixel: "Conectar via AlvoBot" (busca pixels da conexao Meta do usuario) e "Inserir manualmente" (Pixel ID + Token)
- **FR-003**: O modulo DEVE permitir configurar um ou mais pixels (multiplos pixels simultaneos, maximo 5 pixels, cada um recebendo todos os eventos)
- **FR-004**: O modulo DEVE armazenar configuracoes em `alvobot_pro_pixel-tracking_settings` via `wp_options`

**Modo AlvoBot (conexao via Supabase — somente leitura):**
- **FR-005**: No modo AlvoBot, o modulo DEVE consultar a Edge Function `api_plugin` para obter a lista de pixels da conexao Meta do usuario (tabela `meta_pixels` do Supabase, filtrada por `is_active = true`)
- **FR-006**: No modo AlvoBot, o modulo DEVE usar o token da conexao Meta como token da Conversion API sem expor o `access_token` em HTML, JSON admin persistente, frontend ou payloads de debug; localmente, o token deve ficar criptografado com AES-256-GCM e chave derivada dos salts do WordPress (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY` e salts equivalentes), acessivel apenas pelo runtime de envio. Tokens nunca podem ser logados em plaintext.
- **FR-007**: O modulo DEVE detectar quando o site NAO esta registrado no AlvoBot e oferecer apenas o modo manual

**Tracking Browser-Side:**
- **FR-035**: O modulo DEVE verificar um sinal de consentimento antes de injetar scripts de tracking ou setar cookies. Sinais suportados (verificados nesta ordem): (1) cookie `alvobot_tracking_consent=true`, (2) variavel JS `window.alvobot_tracking_consent === true`, (3) CookieYes: `window.CookieYes.getConsent('analytics') === 'yes'`, (4) Complianz: `window.cmplz_get_consent('statistics') === 'allow'`. Se nenhum sinal positivo for encontrado, nenhum tracking ocorre. O modulo NAO inclui banner de consentimento proprio — isso e responsabilidade de um plugin externo. O nome do cookie e configuravel nas settings.
- **FR-008**: O modulo DEVE injetar o Meta Pixel SDK (`fbevents.js`) em todas as paginas publicas quando ativo e o consentimento do visitante foi dado (FR-035)
- **FR-009**: O modulo DEVE gerar e persistir `fbp` no formato `fb.1.{timestamp}.{random}` quando o cookie `_fbp` nao existe (como `pxa-base.js`)
- **FR-010**: O modulo DEVE capturar `fbc` do parametro `fbclid` na URL e persisti-lo no cookie `_fbc`
- **FR-011**: O modulo DEVE capturar todos os UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_term`, `utm_content`) e parametros custom (`src`, `sck`)
- **FR-012**: O modulo DEVE capturar contexto de origem/edge de forma opcional: Cloudflare headers quando existirem, request metadata first-party sempre que possivel, e provedores de geo configuraveis sem chave hardcoded. Ausencia de Cloudflare ou falha de geo nao pode gerar erro fatal nem impedir PageView/conversoes.
- **FR-013**: O modulo DEVE suportar 6 tipos de trigger: `page_load`, `page_time`, `form_submit`, `click`, `scroll`, `view_element`
- **FR-014**: O modulo DEVE suportar eventos Meta focados em blog: PageView, Lead, ViewContent, CompleteRegistration, Contact, Search, Subscribe, StartTrial, SubmitApplication, Schedule, CustomEvent
- **FR-015**: O modulo DEVE gerar um `event_id` UUID unico por evento para deduplicacao browser/server
- **FR-036**: Apos disparar cada evento via `fbq()`, o script JS DEVE enviar o evento via AJAX (WP REST API ou `admin-ajax.php`) ao PHP para criar um registro CPT `alvobot_pixel_event` com status `pending`, garantindo que todo evento browser tenha copia server-side para envio via Conversion API (mesmo padrao do Pixel X)
- **FR-016**: O modulo DEVE carregar scripts de forma assincrona (`defer`/`async`) para nao impactar Core Web Vitals
- **FR-017**: O modulo DEVE oferecer uma configuracao para selecionar quais roles WordPress sao excluidos do tracking. Padrao: nenhum role excluido (todos os usuarios sao rastreados). O admin pode selecionar roles a excluir (administrator, editor, author, contributor, subscriber) nas configuracoes do modulo.
- **FR-018**: O modulo DEVE excluir bots conhecidos (googlebot, bingbot, etc.) do tracking

**Captura de Leads:**
- **FR-019**: O modulo DEVE capturar dados de lead (email, nome, telefone) automaticamente de formularios submetidos (como `send_lead_data()` do Pixel X)
- **FR-020**: Leads DEVEM ser armazenados como Custom Post Type no WordPress (`alvobot_pixel_lead`), com upsert por email ou fbp
- **FR-021**: Leads DEVEM conter valores SHA-256 em campos separados (email_hash, phone_hash, name_hash) para uso na Conversion API. Antes do hashing, email deve ser trim + lowercase; telefone deve manter somente digitos/codigo de pais; nomes devem ser trim + lowercase + colapso de espacos e remocao de caracteres especiais conforme normalizacao esperada pela Meta CAPI.

**Conversion API Server-Side:**
- **FR-022**: O modulo DEVE enviar eventos ao Facebook Conversion API (`POST graph.facebook.com/{ALVOBOT_PIXEL_FB_API_VERSION}/{PIXEL_ID}/events`) com PII normalizado e hasheado em SHA-256
- **FR-023**: Eventos DEVEM ser armazenados como Custom Post Type no WordPress (`alvobot_pixel_event`) com status: `pending` → `sent` → `error`
- **FR-024**: O modulo DEVE usar um unico job recorrente do Action Scheduler (`alvobot_pixel_send_events`) a cada 5 minutos. Cada execucao deve adquirir lock transiente (`DISPATCH_LOCK_TTL = 300s`) para evitar sobreposicao, processar ate 250 eventos pendentes, atualizar status e liberar o lock em sucesso/erro. O endpoint REST `/actions/send-pending` funciona como trigger de recuperacao/monitoramento.
- **FR-025**: O modulo DEVE implementar retry: batch falho e dividido em sub-batches menores (como Pixel X), com backoff conforme FR-039
- **FR-026**: O modulo DEVE armazenar o payload enviado e a resposta da API em post meta para auditoria

**Conversoes:**
- **FR-027**: Conversoes DEVEM ser armazenadas como Custom Post Type no WordPress (`alvobot_pixel_conversion`)
- **FR-028**: Cada conversao define: event_type, trigger_type, target_pages, css_selector, content_name (opcional)

**Seguranca e Qualidade:**
- **FR-029**: O modulo DEVE usar `AlvoBotPro::debug_log('pixel-tracking', ...)` para todos os logs
- **FR-030**: O modulo DEVE proteger acoes de admin com nonces WordPress
- **FR-031**: O modulo DEVE sanitizar todos os inputs e escapar todos os outputs
- **FR-032**: O modulo DEVE validar formato do Pixel ID (numerico, 15-16 digitos) antes de salvar
- **FR-033**: O modulo DEVE suportar modo teste com `test_event_code`

**Limpeza:**
- **FR-034**: O modulo DEVE limpar automaticamente eventos e leads antigos com duas estrategias combinadas: (1) retencao por tempo (configuravel, padrao 7 dias) e (2) volume maximo configuravel (ex: max 50.000 eventos, max 10.000 leads). Quando qualquer limite e atingido, os registros mais antigos sao purgados primeiro. Ambos os limites sao configuraveis pelo admin.
- **FR-037**: A versao da Facebook Graph API DEVE ser configuravel por constante `ALVOBOT_PIXEL_FB_API_VERSION`, default `v24.0`, sem alterar codigo de envio em cada deprecacao.
- **FR-038**: Tokens CAPI DEVEM usar AES-256-GCM com chave derivada dos salts WordPress e nunca aparecer em logs, debug payloads, HTML ou respostas REST admin.
- **FR-039**: Retry CAPI DEVE usar backoff exponencial (60s inicial, 2x, max 1h, max 5 tentativas); rate limit 429 segue backoff; tokens invalidos 190/463/467 viram erro permanente com notificacao admin; demais 4xx param apos 3 tentativas.
- **FR-040**: APIs REST autenticadas DEVEM aplicar rate limit de 100 req/min por token e retornar 429 + `Retry-After`.
- **FR-041**: List endpoints DEVEM limitar paginacao a default 20 e max 100 registros por request.
- **FR-042**: Query params REST DEVEM ser sanitizados e validados antes de `WP_Query`/SQL; SQL direto deve usar `$wpdb->prepare()`.
- **FR-043**: CORS DEVE permitir apenas origens configuradas do AlvoBot App.

### Key Entities (armazenadas localmente no WordPress)

- **Pixel Config**: Configuracao de pixel. Campos: `pixel_id`, `has_api_token`, `token_ref` transitorio durante save admin, `encrypted_api_token`/token criptografado localmente, `source` (alvobot|manual), `connection_id` (se AlvoBot), `test_mode`, `test_code`. Armazenado em `wp_options` sem token em texto plano no HTML. Um site pode ter multiplos pixels.
- **Conversion Rule** (CPT `alvobot_pixel_conversion`): Regra de disparo. Campos: `event_type`, `trigger_type`, `trigger_value` (segundos/percentual), `display_on` (all|specific|path), `page_ids[]`, `page_paths`, `css_selector`, `content_name`.
- **Pixel Event** (CPT `alvobot_pixel_event`): Evento capturado. Campos: `event_id` (UUID), `event_name`, `event_time`, `page_url`, `page_title`, `lead_id`, `user_data` (serialized), `custom_data` (serialized), `status` (pending|sent|error), `fb_request_payload`, `fb_response_payload`, `pixel_ids` (quais pixels recebem). Status workflow: pending → sent (sucesso) ou error (falha).
- **Pixel Lead** (CPT `alvobot_pixel_lead`): Lead capturado. Campos: `lead_id` (UUID), `email`, `email_hash`, `phone`, `phone_hash`, `name`, `name_hash`, `fbp`, `fbc`, `ip`, `user_agent`, `geo_city`, `geo_state`, `geo_country`, `geo_country_code`, `geo_zipcode`, `geo_currency`, `geo_timezone`, `first_utm_source`, `first_utm_medium`, `first_utm_campaign`, `last_utm_source`, `last_utm_medium`, `last_utm_campaign`, `src`, `sck`, `first_seen`, `last_seen`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin consegue configurar pixel (qualquer modo) e ver PageView disparando no Facebook Pixel Helper em menos de 3 minutos
- **SC-002**: Modo AlvoBot: admin consegue selecionar pixel da lista sem copiar/colar nenhum ID ou token
- **SC-003**: Eventos server-side (Conversion API) tem taxa de match > 50% no Facebook Events Manager
- **SC-004**: O script de tracking nao adiciona mais que 100ms ao Largest Contentful Paint (LCP)
- **SC-005**: 100% dos eventos disparados no browser tambem sao enviados via Conversion API (com mesmo event_id)
- **SC-006**: O modulo funciona com os 5 plugins de formulario mais populares (CF7, WPForms, Elementor Forms, Gravity Forms, Ninja Forms)
- **SC-007**: Eventos continuam sendo registrados (via Conversion API) mesmo com ad blockers ativos
- **SC-008**: O envio batched ao Facebook nao gera rate limit em sites com ate 10.000 pageviews/dia

## Out of Scope

- **Armazenamento no Supabase** — eventos e leads ficam APENAS no WordPress local (Supabase e usado somente para ler pixels da conexao Meta, nao para escrita)
- **Produtos / E-commerce** — sem carrinho, checkout, precos, AddToCart, Purchase, InitiateCheckout, AddPaymentInfo
- **Integracoes com plataformas de pagamento** (Hotmart, Eduzz, Kiwify) — nao aplicavel para blogs
- **Google Ads / Google Analytics tracking** — sera um modulo separado
- **Link tracking / URL builder** — sera uma feature separada
- **Dashboard de eventos no alvobot-app** — sera uma feature separada
- **Pixel de retargeting / audiencias personalizadas** — fora do escopo
- **A/B testing de pixels** — nao necessario
- **Webhooks de saida** — nao necessario para blogs
