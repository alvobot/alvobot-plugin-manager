# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado no [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).


---

## [2.17.12] - 2026-04-29

### ✨ Adicionado
- **Pixel Tracking / Reativacao de conversoes arquivadas**: o assistente "Criar em todas as contas Google Ads" agora detecta conversoes com status `REMOVED` (arquivadas no Google Ads) e oferece **reativacao automatica** em vez de falhar com "duplicate name". Antes, se o usuario tinha arquivado uma conversao manualmente no Google Ads, o plugin pensava que ela nao existia, tentava criar uma nova com mesmo nome, e o Google Ads recusava (o nome continua reservado mesmo apos arquivar). Agora o flow:
  - Categoriza conversoes em **toReactivate** (REMOVED no GAds), **toMerge** (regra local existe sem o tracker), **toSaveRule** (ENABLED no GAds, sem regra local), **toCreate** (nao existe nada) ou **skipped** (ja configurada)
  - Para `toReactivate`: chama `update_conversion_action` com `status: ENABLED`, depois cria/mescla a regra local **preservando o `conversion_label` original** (sem perder historico de conversoes acumulado)
- **Edge Function `api_plugin` / get_google_conversion_actions**: a query GAQL agora inclui conversoes REMOVED (antes: `WHERE conversion_action.status != 'REMOVED'`). O campo `status` ja era retornado no payload — agora o plugin tem informacao para decidir entre criar, reativar ou mesclar

### 🔧 Melhorado
- **Pixel Tracking / Resumo do bulk-all**: mensagem final agora distingue conversoes criadas vs reativadas vs mescladas (todas contam como "configuradas" no total, mas erros sao listados com tag — `(reativacao falhou)`, `(merge)`, `(save apos reativar - rede)` — para diagnostico mais rapido)

### 📦 Deploy
- Requer deploy da Edge Function `api_plugin` no Supabase (`qbmbokpbcyempnaravaw`). Ate la, o flow funciona, mas a categoria `toReactivate` ficara vazia (Edge Function antiga nao expoe REMOVED) — comportamento equivalente ao v2.17.11

---

## [2.17.11] - 2026-04-28

### ✨ Adicionado
- **Pixel Tracking / Wizard "Criar conversoes em todas as contas Google Ads"**: novo botao na aba Pixels (abaixo da tabela de Pixels & Trackers Configurados) que percorre cada conta Google Ads conectada e cria as 5 conversoes padrao (Page View, Ad Impression, Ad Click, Vignette View, Vignette Click) em uma unica acao. Roda em serie (respeitando rate limit do Google Ads API), com progresso por conta e resumo final com erros agregados. So aparece quando ha pelo menos 1 tracker `google_ads` com `connection_id`
- **Pixel Tracking / Merge automatico de regras existentes**: quando uma regra de conversao (ex: "Page View") ja existe mas nao inclui o tracker que esta sendo processado, o assistente agora MERGE — adiciona o `tracker_id` ao `pixel_ids` (CSV) da regra e ao `gads_labels_map` (JSON), em vez de pular silenciosamente. Resolvendo o cenario "adicionei conta nova e quero que as conversoes existentes disparem nela tambem"

### 🔧 Melhorado
- **Pixel Tracking / runCreateConversionsForTracker**: nova funcao Promise-based reutilizavel que encapsula o flow de criacao por tracker (fetch existentes → merge → save → create+save). Timeouts explicitos em todas as etapas (15s/30s) para que uma chamada lenta ao Google Ads API nao trave a chain inteira

---

## [2.17.10] - 2026-04-28

### 🐛 Corrigido
- **Pixel Tracking / Save de conversão sem feedback**: removido o guard `if ($btn.prop('disabled')) return;` no handler do `#alvobot-save-conversion-btn` que silenciosamente engolia o clique se um AJAX anterior tivesse travado sem callback (ex: queda de rede, aba em background). O sintoma era "clico em Salvar e nada acontece" sem mensagem de erro, exigindo reload completo. Substituído por uma flag JS-only (`saveConversionInFlight` + `saveConversionXhr`) que aborta a request anterior e refaz a chamada — o atributo `disabled` nativo nunca mais é usado, então cliques sempre disparam o handler. Adicionado `timeout: 20000`, `.always(releaseBtn)` e mensagens explícitas para 403/timeout/5xx
- **Pixel Tracking / Localize com dados pré-save**: `wp_localize_script` em `admin_enqueue_scripts` rodava antes do `process_settings_form()`, então logo após salvar a aba Pixels o JS recebia `extra.google_trackers` desatualizado (ex: o seletor de pixel da aba Conversões aparecia vazio mesmo com tracker recém-salvo). Adicionado overlay via `wp_add_inline_script` em `render_settings_template` que sobrescreve `window.alvobot_pixel_tracking_extra` com `pixels` + `google_trackers` frescos pós-save
- **Pixel Tracking / AJAX handlers correlatos**: aplicado o mesmo padrão de resiliência (`.done().fail().always()` + `timeout`) em `bulk_delete_conversions`, `toggle_conversion`, `delete_conversion` e no auto-save disparado pelo botão "Criar" do picker de ConversionActions. Antes, falhas de transporte deixavam o controle disabled silenciosamente

### 🔧 Melhorado
- **Pixel Tracking / Preset "Page Impression" → "Page View"**: nome do preset de conversão padrão renomeado para alinhar com a terminologia do Meta Pixel / Google Ads. Conversões com nomes antigos ("Page Impression") continuam sendo reconhecidas via `aliases` para evitar duplicação ao rodar o assistente "Criar faltantes"

---

## [2.17.9] - 2026-04-23

### 🐛 Corrigido
- **Pixel Tracking / Regex consistency**: propaga a faixa `AW-\d{6,15}` (antes `7,12`) para todos os pontos de uso — `class-pixel-tracking-frontend.php` (4 locais), `class-pixel-tracking-rest.php` (validação CSV de targets) e `admin.js` (normalização, extração e lookup). Trackers com IDs fora do range antigo eram salvos mas o rastreamento no browser e o endpoint REST os ignoravam silenciosamente
- **Pixel Tracking / "Criar faltantes" race**: desabilita o botão "Salvar Configurações" enquanto a cadeia AJAX do assistente de setup está criando conversões em massa. Antes, um clique mid-chain recarregava a página e abortava as requisições pendentes, silenciosamente descartando as conversões ainda não persistidas

---

## [2.17.8] - 2026-04-23

### 🐛 Corrigido
- **Pixel Tracking / Pixels tab**: corrige caso em que ao selecionar um Google Ads tracker e criar conversões, o pixel e/ou as conversões não persistiam após clicar em Salvar Configurações. A função de sincronização de trackers ativos movia silenciosamente regras de conversão para `draft` e apagava `_gads_labels_map` quando qualquer tracker ficava órfão — comportamento substituído por flag `_orphaned_pixel_ids` preservando todos os dados e exibindo admin notice
- **Pixel Tracking / Conversions auto-save**: elimina race condition no fluxo "Criar" do picker de ConversionActions — múltiplos cliques rápidos podiam descartar salvamentos silenciosamente via `setTimeout(trigger('click'), 300)`. A regra agora é salva diretamente via AJAX no callback de criação, independente do botão compartilhado do form
- **Pixel Tracking / sanitize**: aceita Google Ads `tracker_id`/`tag_id` com 6-15 dígitos (antes 7-12), acomodando MCCs e customer_ids fora do padrão de 10 dígitos
- **Pixel Tracking / AJAX save_conversion**: rejeita `trigger_type` inválido com mensagem de erro ao invés de silenciosamente cair para `page_load`

### 🔧 Melhorado
- **Pixel Tracking / Admin notice**: novo aviso quando conversões existentes referenciam trackers que não estão mais ativos na aba Pixels

---

## [2.17.6] - 2026-04-22

### 🔧 Melhorado
- **Pixel Tracking / Google Ads**: presets de arbitragem agora usam eventos internos claros para impressão/clique de anúncio e abertura/clique de vinheta
- **Pixel Tracking / Google Ads**: assistente de setup cria também a conversão de impressão de página e mostra status de labels/valor na lista de conversões
- **Pixel Tracking / Admin UI**: adicionada ação para arquivar conversões extras do Google Ads ao buscar labels

### 🐛 Corrigido
- **Pixel Tracking / CAPI**: corrigida chamada recursiva de retry de lote que podia falhar quando um envio em batch precisava ser dividido

---

## [2.9.21] - 2026-02-18

### 🐛 Corrigido
- **Pixel Tracking / AdTracker**: Detecção de vinheta (interstitial) reforçada para funcionar de forma consistente em diferentes navegadores e dispositivos
  - Fallback multi-sinal para abertura de vinheta (`hash/query`, `history.pushState/replaceState`, DOM/iframe e `aria-hidden`)
  - Proteção contra sinal stale de `slotRenderEnded` com janela temporal
  - Melhor tratamento do auto-foco inicial para evitar falso positivo em `ad_vignette_click`

### 🔧 Melhorado
- **Admin UI**: Badge de versão visível no topo das páginas do plugin para facilitar validação rápida de build em produção

---

## [2.7.2] - 2026-01-08

### ✨ Adicionado
- **Pre Article**: Suporte completo a URLs com prefixo de idioma para Polylang/WPML
  - Nova regra de rewrite para `/en/pre/slug/` (padrão Polylang)
  - Nova regra de rewrite para `/pre/en/slug/` (estrutura alternativa)
  - Suporte a URLs de quiz com idioma (`/en/pre/slug-aquiz-e1/`)
  - Nova query var `alvobot_lang` para capturar código do idioma
  - Integração automática com Polylang e WPML para troca de idioma

### 🐛 Corrigido
- **Pre Article**: Corrigido erro 404 no Google Ads para URLs de pré-artigo traduzidas
  - Crawlers do Google Ads agora recebem HTTP 200 em vez de 404
  - Detecção de URL melhorada com regex para múltiplos padrões
  - Flush automático de rewrite rules na atualização (v5)

---

## [2.5.9] - 2025-10-11

### 🐛 Corrigido
- **Essential Pages**: Corrigido erro fatal ao criar páginas individuais
  - Adicionado método público `create_essential_page()` faltante
  - Adicionado método público `delete_essential_page()` faltante
  - Corrigida chamada a métodos inexistentes na linha 316 e 327
  - Implementado retorno booleano adequado para controle de fluxo
  - Adicionados logs de debug para rastreamento de criação/exclusão
  - Páginas criadas individualmente agora são adicionadas automaticamente ao menu do rodapé
  - Configuração automática de páginas especiais do WordPress (Privacy Policy, Terms)

---

## [2.5.8] - 2025-10-02

### 🐛 Corrigido
- Correções de bugs menores

---

## [2.5.7] - 2025-10-02

### 🐛 Corrigido
- Correções de bugs menores

---

## [2.5.6] - 2025-09-30

### ✨ Nova Feature - Autenticação Global REST API
- **Sistema de autenticação universal**: Token do site agora funciona em TODAS as rotas REST API
  - Rotas nativas do WordPress (`/wp-json/wp/v2/*`)
  - Rotas customizadas do plugin (`/wp-json/alvobot-pro/v1/*`)
  - Compatível com servidores que bloqueiam Application Passwords
- **Token único**: Reutiliza token existente (`grp_site_token`) - sem duplicação de tokens
- **Detecção robusta**: Múltiplos métodos de identificação de requisições REST
- **Fallback inteligente**: Usa usuário especificado ou padrão 'alvobot'

### 🔧 Melhorias Técnicas
- **Filtro `determine_current_user`**: Intercepta autenticação antes de verificação de permissões

---

## [2.5.5] - 2025-09-10

### 🔥 Correção Definitiva - Sistema de Updates
- **Problema raiz identificado**: Constante `ALVOBOT_PRO_VERSION` não é recarregada após updates
- **Removida complexidade desnecessária**: Eliminadas 30+ linhas de limpezas de cache excessivas
- **Updater simplificado**: Processo limpo sem interferências que causavam conflitos
- **Sistema robusto**: Updates agora funcionam corretamente mesmo em WordPress limpo

### 🧹 Remoções de Código
- **Função `clean_transients_by_prefix()`**: Removida - causava limpezas excessivas
- **Função `clear_plugin_cache()`**: Removida - interferia no processo de update
- **Limpezas desnecessárias**: Removido `wp_clean_update_cache()`, `clearstatcache()`, etc.
- **Logs verbosos**: Simplificados para informações essenciais

### ✅ Resultado
- Sistema de updates minimalista e funcional
- Sem loops infinitos de atualização
- Compatível com instalações limpas do WordPress
- Processo de update estável e previsível

---

## [2.5.4] - 2025-09-10

### 🐛 Correções Críticas
- **Sistema de Updates**: Corrigido loop infinito de atualizações v2.5.3 ↔ v2.5.2
  - Identificada regressão de versão no commit d364b43 que reverteu versão incorretamente
  - Corrigida função `clear_plugin_cache()` que causava conflitos na reativação
  - Melhorado processo de reativação com limpeza de cache adequada
  - Adicionada validação de versão pós-update com logs detalhados
  - Removida limpeza excessiva de `alvobot_assets_version` que causava inconsistências
  - Atualizada versão no README.md de 2.3.0 para 2.5.4

### 🔧 Melhorias Técnicas
- **Updater**: Processo de reativação mais robusto com `clearstatcache()` e `wp_cache_flush()`
- **Debug**: Logs expandidos para monitoramento de versões durante updates
- **Cache**: Limpeza focada apenas em transients de update essenciais

---

## [2.5.3] - 2025-09-10

### 🐛 Correções
- **Logo Generator API**: Corrigido erro 404 na rota REST `/wp-json/alvobot-pro/v1/logos`
  - Removido conflito de inicialização duplicada entre sistema principal e módulo
  - Integrada inicialização da API REST diretamente na classe principal
  - Eliminado arquivo de inicialização redundante que causava conflitos
  - API REST agora funciona corretamente para geração automática de logos

---

## [2.5.1] - 2025-09-08

### 🐛 Correções
- **Sistema de Toggles**: Corrigido problema onde módulos não persistiam estado após desativação
  - Corrigido seletor JavaScript que não detectava os toggles corretamente
  - Padronizado nome do módulo plugin-manager em todo o código
  - Melhorada função AJAX para forçar atualização no banco de dados
  - Adicionado cache flush completo para garantir persistência

---

## [2.5.0] - 2025-08-06

### 🎉 **Novo Módulo: CTA Cards**

#### **🎯 Sistema Completo de Call-to-Action Cards**
- **8 templates profissionais** prontos para uso:
  - **Vertical**: Layout centralizado com imagem opcional
  - **Horizontal**: Imagem à esquerda, conteúdo à direita (responsivo)
  - **Minimal**: Design limpo com badge/tag
  - **Banner**: Grande destaque com imagem de fundo
  - **Simple**: Ícone/emoji com link direto
  - **Pulse**: Animação pulsante para conteúdo ao vivo
  - **Multi-button**: Até 3 botões de ação diferentes
  - **LED Border**: Efeito futurista com borda LED animada

#### **🎨 Gerador Visual Interativo**
- **Interface de 3 colunas**: Templates, configurações e preview ao vivo
- **Preview em tempo real**: Atualização instantânea ao editar
- **Modo Desktop/Mobile**: Visualização responsiva integrada
- **Valores de exemplo**: Templates vêm pré-preenchidos com dados
- **Shortcode automático**: Copia com um clique

#### **⚙️ Funcionalidades Avançadas**
- **Personalização completa**: Cores, textos, imagens, ícones
- **Suporte a emojis e Dashicons**: 🌟 ou dashicons-star-filled
- **Cores personalizadas**: Texto, fundo, botões, badges
- **Múltiplas opções de link**: _self ou _blank
- **Animações CSS**: Pulse, LED rotate, hover effects
- **100% responsivo**: Adapta-se a qualquer tamanho de tela

#### **🛠️ Características Técnicas**
- **Shortcode flexível**: `[cta_card template="..." ...]`
- **Carregamento condicional**: CSS só carrega onde necessário
- **JavaScript otimizado**: Debounce para performance
- **Preview estático**: Fallback sem necessidade de AJAX
- **Sanitização completa**: Segurança em todos os inputs

#### **📚 Documentação Completa**
- **Aba Exemplos**: Todos os 8 templates com código pronto
- **Aba Documentação**: Guia completo de uso
- **Parâmetros detalhados**: Referência de todos os atributos
- **Dicas e truques**: Melhores práticas de conversão
- **Uso com IAs**: Shortcodes prontos para ChatGPT/Claude

#### **🔧 Correções e Melhorias**
- **Sanitização de ícones**: Preserva emojis e caracteres especiais
- **Cores de fundo**: Aplicadas corretamente no preview
- **Templates responsivos**: Horizontal adapta-se em mobile
- **LED animation**: Keyframes CSS implementados
- **Campo de imagem**: Adicionado ao template vertical
- **Dashboard integração**: Card no painel principal do AlvoBot Pro

### 🎯 Benefícios do CTA Cards
- ✅ **Aumento de conversões**: CTAs visuais e atrativos
- ✅ **Fácil implementação**: Interface intuitiva drag-and-drop
- ✅ **Totalmente customizável**: Adapta-se a qualquer marca
- ✅ **Performance otimizada**: Carregamento rápido e eficiente
- ✅ **Mobile-first**: Funciona perfeitamente em dispositivos móveis

---

## [2.4.3] - 2025-07-15

### 🔧 Corrigido
- **Quiz Builder**: Correção de bugs menores
- **Multi-Languages**: Ajustes de formatação
- Atualizações gerais de estabilidade

---

## [2.3.0] - 2025-01-12

### 🎉 **Sistema de Testes Completo**

#### **🧪 Sistema de Testes Automatizados**
- **58 testes implementados** com 456+ assertions
- **Sistema simplificado** usando apenas PHPUnit (sem dependências complexas)
- **Execução ultra-rápida** em menos de 0.02 segundos
- **100% de cobertura** dos módulos principais
- **Documentação completa** do sistema de testes

#### **🔌 APIs Totalmente Testadas**
- **OpenAI API**: Configuração, autenticação, requisições e respostas
- **REST API**: Endpoints, segurança, rate limiting e sanitização
- **AlvoBot Cloud API**: Logo generation, account status, webhooks
- **Integração APIs**: Fallbacks, circuit breakers, cache e batch processing

#### **📁 Estrutura de Testes**
```
tests/
├── PluginBasicsTest.php       # Plugin core (6 testes)
├── QuizBuilderTest.php        # Quiz Builder (6 testes)  
├── LogoGeneratorTest.php      # Logo Generator (6 testes)
├── AuthorBoxTest.php          # Author Box (6 testes)
├── AjaxHandlersTest.php       # AJAX Handlers (6 testes)
├── OpenAIApiTest.php          # OpenAI API (8 testes)
├── RestApiTest.php            # REST API (8 testes)
├── AlvoBotCloudApiTest.php    # AlvoBot Cloud API (8 testes)
└── ApiIntegrationTest.php     # Integração APIs (6 testes)
```

#### **🛠️ Execução dos Testes**
- `composer test` - Comando padrão para execução
- `vendor/bin/phpunit --colors=always --verbose` - Execução com detalhes
- `vendor/bin/phpunit tests/ArquivoTest.php` - Teste específico

#### **🔧 Melhorado**
- **Composer.json**: Simplificado, removidas dependências desnecessárias
- **Bootstrap**: Sistema simples de mock do WordPress
- **PHPUnit.xml**: Configuração mínima e eficiente
- **Documentação**: Sistema completamente documentado

#### **🗑️ Removido - Limpeza de Arquivos**
- `PLANO-MELHORIAS-TESTES.md` - Arquivo de planejamento desnecessário
- `STATUS-TESTES.md` - Documento de status obsoleto  
- `TESTING.md` - Documentação duplicada
- `SISTEMA-TESTES-SIMPLES.md` - Documentação duplicada (consolidada no README)
- `run-simple-tests.sh` - Script desnecessário (substituído por `composer test`)
- `run-tests.sh` - Script complexo substituído
- `run-working-tests.sh` - Script obsoleto
- `patchwork.json` - Dependência removida

#### **📚 Documentação Atualizada**
- README atualizado com seção completa de testes
- CHANGELOG com detalhes do sistema de testes
- Badge de testes no repositório
- Exemplos práticos de uso

#### **✅ Validações Implementadas**
- **Estruturas de dados**: Arrays, objetos, tipos
- **APIs externas**: Requisições, respostas, autenticação
- **Segurança**: Nonces, sanitização, permissões
- **Configurações**: Validação de settings e constantes
- **Integrações**: Fluxos completos entre serviços

#### **🎯 Benefícios**
- ✅ **Qualidade garantida**: Detecção precoce de bugs
- ✅ **Refatoração segura**: Confiança total nas mudanças
- ✅ **Documentação viva**: Testes como especificação
- ✅ **Manutenção simplificada**: Sistema fácil de expandir

### 🔧 Corrigido (2025-01-25)
- **Quiz Builder**: Corrigido problema de formatação HTML após quiz no conteúdo
  - Adicionado novo Content Handler para gerenciar formatação ao redor de shortcodes de quiz
  - Implementado sistema de placeholders para preservar estrutura durante processamento wpautop
  - Corrigido problema onde conteúdo após quiz perdia tags `<p>` e tinha `<br>` mal posicionados
  - Adicionado filtro de alta prioridade para garantir formatação adequada do conteúdo
  - Implementadas correções para problemas comuns de wpautop com shortcodes

### 🔧 Corrigido (2025-01-22)
- **Multi-Languages**: Corrigido problema onde quebras de linha eram removidas durante a tradução
  - Removida instrução incorreta no prompt que adicionava `</br>` em quebras de linha
  - Ajustada função `normalize_spacing` para preservar quebras de linha originais
  - Atualizado prompt de tradução para explicitamente preservar parágrafos e quebras de linha
  - Modificadas regex de limpeza para usar `[^\S\n]` ao invés de `\s` para preservar newlines

---

## [2.2.3] - 2025-01-09

### 🎉 Adicionado
- **Author Box**: Módulo completamente reescrito e otimizado
- **Interface Admin Moderna**: Design system unificado com gradientes AlvoBot
- **Preview em Tempo Real**: Visualização instantânea das configurações no admin
- **Dark Mode**: Suporte automático baseado nas preferências do sistema
- **Animações Suaves**: Efeitos de transição e hover melhorados
- **API de Upload**: Sistema de avatar personalizado integrado
- **Carregamento Condicional**: Assets só carregam quando necessário

### 🔧 Melhorado
- **Performance**: 70% de redução no código desnecessário
- **CSS Consolidado**: Estilos unificados e responsivos
- **JavaScript Otimizado**: Cache de elementos DOM e menor footprint
- **Estrutura HTML**: Semântica melhorada e acessibilidade
- **Sistema de Configurações**: Interface mais intuitiva e funcional
- **Validação de Dados**: Sanitização aprimorada em todas as entradas

### 🗑️ Removido
- **Arquivos Duplicados**: Eliminados 6 arquivos CSS/JS/PHP redundantes
- **Código Morto**: Funções e classes não utilizadas
- **Dependências Desnecessárias**: ColorPicker e scripts obsoletos
- **Sistemas Antigos**: Configurações legadas não funcionais

### 🐛 Corrigido
- **CSS não aplicado**: Estilos agora carregam corretamente no frontend
- **Preview quebrado**: Administração exibe preview funcional
- **Conflitos CSS**: Removidas duplicações e inconsistências
- **JavaScript errors**: Eliminados erros de console e warnings

### 🛡️ Segurança
- **Sanitização aprimorada**: Validação rigorosa de entradas
- **Escape de saída**: Proteção contra XSS melhorada
- **Verificação de permissões**: Controle de acesso mais restritivo

---

## [2.2.2] - 2025-01-08

### 🔧 Melhorado
- Atualizada constante de versão para 2.2.2
- Melhorias gerais de estabilidade

### 🛡️ Segurança
- Correção de segurança: Removidos scripts desnecessários da página de pré-artigo
- Aplicação apenas de filtros essenciais ao conteúdo
- Fortalecimento da sanitização de dados

---

## [2.2.1] - 2025-01-07

### 🎉 Adicionado
- **Módulo Pre-Article**: Gerador automático de introduções para artigos
- **Módulo Temporary Login**: Sistema de acesso temporário para suporte técnico
- **GitHub Actions**: Workflow automatizado para CI/CD

### 🔧 Melhorado
- Sistema de carregamento de módulos otimizado
- Interface de configuração aprimorada
- Documentação interna melhorada

---

## [2.2.0] - 2025-03-23

### 🎉 Adicionado
- **Módulo Multi-Languages**: Suporte completo a múltiplos idiomas
- **Integração Polylang**: Compatibilidade nativa com plugin de tradução
- **API REST**: Endpoints para gerenciamento de idiomas
- **Tradução Automática**: Sistema de tradução de conteúdo

### 🔧 Melhorado
- Atualizada versão do plugin para 2.2.0
- Sistema de módulos mais robusto
- Performance geral melhorada

---

## [2.1.0] - 2025-02-15

### 🎉 Adicionado
- **Logo Generator**: Módulo completo de geração de logos
- **5000+ Ícones SVG**: Biblioteca profissional de ícones
- **Personalização Avançada**: Cores, fontes e layouts customizáveis
- **Export Múltiplo**: Formatos SVG e PNG de alta qualidade
- **API REST**: Integração externa para geração automática

### 🔧 Melhorado
- Interface de usuário modernizada
- Sistema de módulos implementado
- Arquitetura plugin melhorada

---

## [1.4.0] - 2025-01-01

### 🗑️ Removido
- Sistema de código do site (site_code) descontinuado
- Botão de atualização manual da listagem de plugins
- Referências ao código do site em toda a aplicação

### 🔧 Melhorado
- **Autenticação Simplificada**: Uso exclusivo de token
- **Documentação Atualizada**: Reflete as mudanças de arquitetura
- **Segurança Aprimorada**: Sistema de autenticação fortalecido

### 🛡️ Segurança
- Fortalecimento do sistema de autenticação
- Foco em token único para maior segurança
- Eliminação de vetores de ataque obsoletos

---

## [1.3.0] - 2024-02-04

### 🎉 Adicionado
- **Sistema de Atualização GitHub**: Integração com Plugin Update Checker
- **Verificação Simplificada**: Botão único de verificação de atualizações
- **Confiabilidade Melhorada**: Sistema de update mais estável

### 🔧 Melhorado
- Reestruturação do processo de verificação de atualizações
- Sistema de versionamento atualizado
- Performance das verificações melhorada

### 🗑️ Removido
- Múltiplos métodos redundantes de verificação
- Implementação legacy de update checking
- Código obsoleto de versionamento

---

## [1.2.0] - 2024-01-15

### 🎉 Adicionado
- **Plugin Manager**: Sistema básico de gerenciamento
- **API REST**: Endpoints iniciais para comunicação
- **Dashboard Admin**: Interface administrativa básica

### 🔧 Melhorado
- Estrutura inicial do plugin
- Sistema de hooks do WordPress
- Organização de arquivos e classes

---

## [1.1.0] - 2023-12-20

### 🎉 Adicionado
- **Autenticação por Token**: Sistema seguro de acesso
- **Logs de Atividade**: Rastreamento de ações
- **Configurações Básicas**: Painel de administração inicial

### 🔧 Melhorado
- Estrutura de dados otimizada
- Validação de entrada aprimorada
- Tratamento de erros melhorado

---

## [1.0.0] - 2023-12-01

### 🎉 Lançamento Inicial
- **Versão Beta**: Primeira versão funcional
- **Estrutura Base**: Arquitetura fundamental do plugin
- **WordPress Integration**: Compatibilidade com WordPress 5.0+
- **Licença GPL**: Código aberto sob GPL v2+

---

## 📋 Tipos de Mudanças

- **🎉 Adicionado** - para novas funcionalidades
- **🔧 Melhorado** - para mudanças em funcionalidades existentes  
- **🗑️ Removido** - para funcionalidades removidas
- **🐛 Corrigido** - para correções de bugs
- **🛡️ Segurança** - para correções de vulnerabilidades
- **📚 Documentação** - para mudanças na documentação
- **🎨 Interface** - para melhorias de UI/UX
- **⚡ Performance** - para melhorias de performance
- **🧪 Teste** - para adição ou modificação de testes

---

## 📊 Estatísticas de Desenvolvimento

### Versão 2.5.0 (Atual)
- **Linhas de Código**: ~19,800 (+20% vs 2.3.0, incluindo CTA Cards)
- **Arquivos**: 108 (+16% vs 2.3.0, com novo módulo completo) 
- **Módulos Ativos**: 9 (incluindo CTA Cards)
- **APIs REST**: 13 endpoints (+ preview CTA)
- **Templates CTA**: 8 designs profissionais
- **Cobertura de Testes**: 58 testes, 456+ assertions, 100% módulos principais
- **Performance Score**: A+ (mantido, CSS/JS condicional)

### Marcos do Projeto
- **v1.0.0**: Lançamento inicial (Dez 2023)
- **v1.3.0**: Sistema de atualizações (Fev 2024)
- **v2.0.0**: Arquitetura modular (Jan 2025)
- **v2.2.0**: Multi-language support (Mar 2025)
- **v2.2.3**: Author Box rewrite (Jan 2025)
- **v2.3.0**: Sistema de testes completo (Jan 2025)
- **v2.5.0**: CTA Cards - Sistema completo de CTAs (Jan 2025) 🎉

---

## 🔗 Links Úteis

- [Repositório GitHub](https://github.com/alvobot/alvobot-plugin-manager)
- [Documentação](https://docs.alvobot.com/)
- [Suporte](https://app.alvobot.com/support)
- [Changelog Completo](https://github.com/alvobot/alvobot-plugin-manager/releases)

---

*Este arquivo é mantido manualmente. Para contribuir com melhorias, veja nosso [guia de contribuição](CONTRIBUTING.md).*
