# Diretrizes de Desenvolvimento - AlvoBot Plugin Manager

## Informações do Ambiente de Desenvolvimento

### WordPress Local
- **URL**: https://plugin-developers/wp-admin/
- **Path**: `/Users/erickheslan/Local Sites/plugin-developer/app/public/`
- **Tipo**: Ambiente de desenvolvimento Local by Flywheel

### Versões Instaladas
- **WP-CLI**: 2.11.0
- **PHP**: 8.4.4 (/Users/erickheslan/Library/Application Support/Local/lightning-services/php-8.4.4+2/bin/darwin-arm64/bin/php)
- **MySQL**: 8.0.35 for macos13 on arm64 (MySQL Community Server - GPL)
- **Composer**: 2.8.6

### Estrutura do Plugin
- **Plugin Path**: `/Users/erickheslan/Local Sites/plugin-developer/app/public/wp-content/plugins/alvobot-plugin-manager/`
- **Admin URL**: https://plugin-developers/wp-admin/admin.php?page=alvobot-pro
- **Fila de Traduções**: https://plugin-developers/wp-admin/admin.php?page=alvobot-pro-multi-languages&tab=queue

### Sistema de Logs
- **Método**: `AlvoBotPro::debug_log('module-id', 'mensagem')`
- **Localização**: Logs aparecem nos logs de erro do WordPress quando WP_DEBUG está ativo

### Comandos Úteis
```bash
# Conectar ao ambiente Local
/Users/erickheslan/Library/Application\ Support/Local/ssh-entry/rWkqg9Zbx.sh

# Navegar até o plugin
cd /Users/erickheslan/Local Sites/plugin-developer/app/public/wp-content/plugins/alvobot-plugin-manager/

# WP-CLI commands
wp plugin list
wp option get alvobot_openai_settings
wp transient list

# Verificar tabela da fila
wp db query "SELECT * FROM wp_alvobot_translation_queue ORDER BY created_at DESC LIMIT 10;"

# Limpar transients/locks órfãos
wp transient delete --all
wp db query "DELETE FROM wp_options WHERE option_name LIKE '_transient_alvobot_translation_lock_%';"
```

### Problemas Conhecidos e Soluções

#### ✅ Sistema de Avisos de Conexão - IMPLEMENTADO (2025-01-20)
- **Funcionalidade**: Sistema completo de detecção e notificação de falhas na conexão
- **Detecção Automática**:
  - Falha na criação de Application Password (plugins de segurança bloqueando)
  - Falha no registro com servidor central (conexão/firewall)
  - Falha na criação do usuário alvobot
  - Detecção de códigos HTTP específicos (404, 401, 400, etc.)
- **Avisos Persistentes**:
  - Aparecem em todas as páginas admin até ser resolvido
  - Mensagens específicas por tipo de erro e código HTTP
  - Instruções claras do que fazer para cada cenário
  - Botão "Tentar Novamente" para retry manual
- **Cenários Detectados**:
  - **404 - Projeto não encontrado**: Avisa que domínio não está cadastrado no painel AlvoBot
  - **401 - Autenticação falhou**: App Password inválido ou bloqueado
  - **400 - Dados inválidos**: Problema com dados enviados
  - **App Password Failed**: Plugins de segurança bloqueando (Wordfence, iThemes, etc.)
- **Armazenamento**: Status salvo em `alvobot_connection_status` (wp_options)
- **Estrutura do Status**:
  ```php
  array(
      'status' => 'error' | 'connected',
      'error_type' => 'app_password_failed' | 'registration_failed' | 'user_creation_failed',
      'timestamp' => time(),
      'http_status' => 404,  // Código HTTP (opcional)
      'message' => 'Mensagem de erro',
      'server_response' => 'Resposta completa do servidor'
  )
  ```
- **Comandos Úteis**:
  ```bash
  # Verificar status da conexão
  wp option get alvobot_connection_status

  # Limpar status de erro
  wp option delete alvobot_connection_status

  # Forçar retry
  wp eval 'do_action("admin_init");' --url=admin.php?page=alvobot-pro&retry_connection=1

  # Testar bloqueio de App Password (Wordfence)
  wp plugin install wordfence --activate
  # Ativar: Wordfence → All Options → "Disable Application Passwords"
  ```
- **Status**: Sistema completo de monitoramento de conexão funcionando com detecção granular de erros

#### ✅ Sistema de Fila de Traduções - RESOLVIDO
- **Problema**: Itens não apareciam na página da fila mesmo sendo adicionados
- **Causa**: Inconsistência de nonces entre interface (`alvobot_translation_nonce`) e backend (`alvobot_nonce`)
- **Solução**: Padronizado para `alvobot_nonce` em todo o sistema
- **Status**: Funcional - fila aceita múltiplos idiomas e exibe corretamente

#### ✅ Handlers AJAX Duplicados - RESOLVIDO  
- **Problema**: Conflito entre handlers da Translation_Queue e Ajax_Controller
- **Solução**: Centralizados no Ajax_Controller, removidos da Translation_Queue
- **Status**: Sistema AJAX unificado e funcional

#### ✅ JavaScript Duplo Evento - RESOLVIDO
- **Problema**: Contagem incorreta de idiomas (mostrava 3 quando selecionado 2)
- **Causa**: Dois handlers para clique em idiomas, causando duplicação
- **Solução**: Unificado para um handler único no checkbox change
- **Status**: Contagem correta funcionando

#### ✅ AG Grid Warnings - CONHECIDO
- **Avisos**: `new Grid()` e `setRowData()` depreciados
- **Status**: Funcionais, mas podem ser atualizados futuramente para `createGrid()` e `updateGridOptions()`

#### ✅ Sistema de Logs Detalhados - IMPLEMENTADO
- **Interface Simplificada**: Removido ícone de engrenagem duplicado, consolidado no olho
- **Logs com Payloads**: Headers, corpo, cURL e respostas completas das requisições OpenAI
- **Modal de Detalhes**: Sistema de drill-down por fases (básico, idiomas, timeline, etc.)
- **Rate Limit Avançado**: Backoff exponencial e detecção automática de limites da API
- **Status**: Sistema completo de debugging para traduções funcionando

#### ✅ Quiz Builder Shortcode - RESOLVIDO
- **Problema**: Shortcode esperava atributo `quiz_data` mas conteúdo estava entre tags [quiz]...[/quiz]
- **Causa**: Conflito após tentativa de correção por outra IA
- **Solução**: Removido requisito de `quiz_data`, agora usa corretamente conteúdo entre tags
- **Limpeza**: Removidos métodos duplicados (render_quiz, get_next_content_chunk)
- **Status**: Quiz renderizando corretamente com JSON entre tags do shortcode

#### ✅ Sistema de Proteção de Cores no Quiz - IMPLEMENTADO
- **Funcionalidade**: Proteção contra texto transparente/invisível
- **Proteção**: 
  - Texto NUNCA pode ser transparente (força para preto automaticamente)
  - Para fundos transparentes: força texto claro (luminância > 0.5) para preto
  - Aplica-se tanto no frontend (PHP) quanto no admin (JavaScript)
- **Casos Cobertos**:
  - `color: transparent` → `#000000`
  - `background: transparent` + texto claro → força texto para `#000000`
  - Texto vazio ou undefined → `#000000`
- **Logs de Debug**: 
  - Warning quando texto transparente é detectado
  - Warning quando texto muito claro em fundo transparente
  - Warning quando contraste < 4.5:1 (WCAG AA)
- **Status**: Corrigido para detectar e prevenir texto laranja claro em fundo transparente

#### ✅ Rate Limit Management - IMPLEMENTADO  
- **Backoff Exponencial**: Retry automático com delays crescentes (2s, 4s, 8s, etc.)
- **Detecção API**: Lê headers x-ratelimit-* para ajuste dinâmico de limites
- **Logs Estruturados**: Rate limit info gravado em cada request/response
- **Status**: Sistema robusto para lidar com limites da OpenAI funcionando

#### ✅ Formatação HTML Após Quiz - RESOLVIDO (2025-01-25)
- **Problema**: Conteúdo após quiz ficava com HTML incorreto (faltando tags `<p>`, `<br>` mal posicionados)
- **Causa**: Conflito entre processamento do shortcode quiz e filtro wpautop do WordPress
- **Solução**: 
  - Criado Content Handler específico para gerenciar formatação ao redor de shortcodes
  - Sistema de placeholders temporários durante processamento wpautop
  - Filtros de alta prioridade para preservar estrutura HTML
  - Correções específicas para problemas comuns de wpautop com shortcodes
- **Status**: Conteúdo após quiz agora mantém formatação HTML correta

#### ✅ Preservação de Quebras de Linha - RESOLVIDO (2025-01-22)
- **Problema**: Quebras de linha eram removidas durante a tradução, juntando parágrafos
- **Causa**: Prompt instruía incorretamente a adicionar `</br>` em quebras de linha + normalização agressiva
- **Solução**: 
  - Atualizado prompt para preservar quebras de linha exatamente como no original
  - Modificada função `normalize_spacing` para usar `[^\S\n]` preservando newlines
  - Ajustadas todas as regex de limpeza para não remover quebras de linha
- **Status**: Traduções agora preservam formatação original de parágrafos

### Estado Atual dos Módulos
- **Multi-Languages**: ✅ Totalmente funcional com fila de traduções e sistema avançado de logs
- **Author Box**: ✅ Funcional  
- **Essential Pages**: ✅ Funcional
- **Logo Generator**: ✅ Funcional
- **Plugin Manager**: ✅ Funcional
- **Pre Article**: ✅ Funcional
- **Quiz Builder**: ✅ Funcional (corrigido - usa conteúdo entre tags [quiz]...[/quiz], não atributo quiz_data)
- **Temporary Login**: ✅ Funcional (oculto no admin)

## Workflow de Desenvolvimento

### ⚠️ REGRA OBRIGATÓRIA - Release Management:
- **SEMPRE** que fizer um commit, deve criar um novo release
- Incrementar a versão seguindo semantic versioning (MAJOR.MINOR.PATCH)
- Commits de bug fixes: incrementar PATCH (ex: 2.4.0 → 2.4.1)
- Commits de features: incrementar MINOR (ex: 2.4.1 → 2.5.0)
- Commits breaking changes: incrementar MAJOR (ex: 2.5.0 → 3.0.0)
- Sempre atualizar tanto o header do plugin quanto a constante ALVOBOT_PRO_VERSION
- Usar `gh release create` com changelog detalhado

## Princípios de Código

### 0. Obrigatório - Princípios Orientadores para a IA:
- Sempre atualizar a memória com todos os aprendizados, deixando esse documento de memória o mais completo possível
- Segurança em Primeiro Lugar: Todo input deve ser validado e sanitizado. Todo output deve ser escapado. Todas as ações que alteram estado devem ser protegidas por nonces. Credenciais nunca devem ser expostas.
- Performance é Essencial: Evitar consultas em loop. Não utilizar chamadas síncronas bloqueantes. Implementar caching eficaz. Otimizar o carregamento de assets.
- Conformidade com WordPress: Seguir rigorosamente os padrões de código do WordPress (PSR-4, PHPDoc). Utilizar as APIs nativas (Hooks, Transients, DB) da forma correta. Garantir a internacionalização (I18N) completa.
- Estabilidade e Robustez: Implementar tratamento de erros detalhado. Eliminar race conditions. Garantir que a lógica de negócios seja à prova de falhas.

### 1. Modularidade e Reutilização
- Sempre verificar se uma função já existe antes de criar uma nova
- Reutilizar código existente sempre que possível
- Criar funções modulares que possam ser aproveitadas em diferentes partes do sistema
- Evitar duplicação de código entre módulos

### 2. Comentários
- NÃO adicionar comentários desnecessários
- Código deve ser autoexplicativo através de nomes claros de variáveis e funções
- Comentários apenas quando absolutamente necessário para explicar lógica complexa
- Remover comentários óbvios como "Constructor", "Main function", etc.
- Manter comentários de cabeçalho, documentação de API e lógica de negócio complexa

### 3. Estrutura do Projeto
- Cada módulo deve ser independente e reutilizável
- Classes base e interfaces devem ser usadas para funcionalidades compartilhadas
- Seguir o padrão de nomenclatura existente no projeto

### 4. Boas Práticas
- Verificar duplicação antes de implementar novas funcionalidades
- Manter consistência com o código existente
- Priorizar clareza e simplicidade sobre complexidade

## Padrões de Integração de Módulos

### Estrutura de Arquivos Obrigatória
Cada módulo deve seguir esta estrutura:
```
modules/nome-do-modulo/
├── class-nome-do-modulo.php    # Arquivo principal (OBRIGATÓRIO)
├── assets/                      # Recursos do módulo
│   ├── css/
│   └── js/
├── includes/                    # Classes auxiliares
└── templates/                   # Templates PHP
```

### Classe Principal do Módulo
```php
class AlvoBotPro_NomeDoModulo {
    private $module_slug = 'nome-do-modulo';
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        AlvoBotPro::debug_log($this->module_slug, 'Initializing module');
        // Inicialização do módulo
    }
}
```

### Registro do Módulo
1. Adicionar em `class-alvobot-pro.php`:
   - Array `$module_files` com caminho do arquivo principal
   - Array `$default_modules` com status padrão (true/false)
   - Array `$module_classes` com mapeamento classe => id
   - Array `$module_names` no dashboard

2. NÃO criar menus duplicados - use apenas um dos padrões:
   - Submenu via módulo: `add_submenu_page('alvobot-pro', ...)`
   - Submenu via plugin principal (evitar duplicação)

### Padrões de Interface Admin

#### Estrutura HTML Obrigatória
```php
<div class="alvobot-admin-wrap">
    <div class="alvobot-admin-container">
        <div class="alvobot-admin-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Descrição do módulo', 'alvobot-pro'); ?></p>
        </div>
        
        <div class="alvobot-card">
            <div class="alvobot-card-content">
                <!-- Conteúdo -->
            </div>
        </div>
    </div>
</div>
```

#### Carregamento de Assets
```php
public function enqueue_admin_assets($hook) {
    // Verificar se está na página correta
    if (strpos($hook, 'alvobot-') === false) {
        return;
    }
    
    // Carregar CSS unificado (OBRIGATÓRIO)
    wp_enqueue_style(
        'alvobot-pro-styles',
        ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
        array(),
        ALVOBOT_PRO_VERSION
    );
    
    // Carregar JS do módulo se existir
    $module_js = plugin_dir_path(__FILE__) . 'assets/js/admin.js';
    if (file_exists($module_js)) {
        wp_enqueue_script(
            'alvobot-module-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            ALVOBOT_PRO_VERSION,
            true
        );
    }
}
```

### Problemas Comuns de Integração
- **Botões não funcionam**: Verificar se JavaScript está sendo carregado corretamente
- **Hook de página errado**: Usar `strpos($hook, 'nome-do-modulo')` em vez de hook específico
- **Assets não carregam**: Verificar caminhos e se arquivos existem antes de enqueue
- **JavaScript null reference**: Sempre verificar se elementos DOM existem antes de usar
- **IDs ausentes**: Verificar se elementos HTML têm os IDs corretos que o JS procura

### Segurança JavaScript
```javascript
// SEMPRE verificar se elementos existem
const element = document.getElementById('my-element');
if (!element) {
    console.warn('Element not found');
    return;
}

// Verificar antes de adicionar event listeners
const button = container.querySelector('#my-button');
if (button) {
    button.addEventListener('click', handler);
}
```

### Sistema de Logs
- ✅ SISTEMA PADRONIZADO: Todos os logs agora seguem o padrão `AlvoBotPro::debug_log('module-id', 'mensagem')`
- ✅ LOGS CONDICIONAIS: Logs só aparecem se o módulo estiver ativo E debug habilitado
- ✅ TODOS OS ARQUIVOS CORRIGIDOS: Removido uso direto de `error_log()` de todos os módulos
- Sempre usar: `AlvoBotPro::debug_log('module-id', 'mensagem')`
- NUNCA usar `error_log()` diretamente
- Primeiro parâmetro é sempre o ID do módulo
- A função debug_log verifica automaticamente:
  - Se WP_DEBUG está ativo
  - Se o debug está habilitado para o módulo específico
  - Se o módulo está ativo (exceto core/plugin-manager)
- Módulos corrigidos: multi-languages, quiz-builder, pre-article, essential-pages, plugin-manager, logo-generator, ajax, updater

### Text Domain
- Sempre usar `'alvobot-pro'` como text domain
- NUNCA criar text domains específicos por módulo

### Constantes
- Usar constantes globais do plugin: `ALVOBOT_PRO_VERSION`, `ALVOBOT_PRO_PLUGIN_URL`, etc.
- Evitar criar constantes específicas do módulo quando possível

## Design System — Padrão Obrigatório

O plugin usa um design system unificado definido em `assets/css/styles.css`, alinhado com o App (`alvobot-app`). **Todo CSS admin deve usar variáveis CSS — nunca hardcode de cores, tamanhos ou espaçamentos.**

### Paleta de Cores

| Token | Valor | Uso |
|-------|-------|-----|
| `--alvobot-primary` | `#fbbf24` (amarelo) | Botões primários, destaques, ícones ativos |
| `--alvobot-primary-dark` | `#d4970a` | Hover de primários |
| `--alvobot-primary-light` | `#FFFBEC` | Backgrounds de seleção/destaque |
| `--alvobot-secondary` | `#0E100D` | Texto em botão secundário |
| `--alvobot-accent` | `#269AFF` (azul) | Links, elementos de ação |
| `--alvobot-success` | `#12B76A` | Status positivo |
| `--alvobot-error` | `#F63D68` | Erros, ações destrutivas |
| `--alvobot-warning` | `#FDB022` | Alertas |
| `--alvobot-info` | `#269AFF` | Informativos |

**Neutros (Zinc scale):**

| Token | Valor | Uso |
|-------|-------|-----|
| `--alvobot-white` | `#ffffff` | Fundo de cards |
| `--alvobot-gray-50` | `#FCFCFD` | Fundo de headers/footers internos |
| `--alvobot-gray-100` | `#F9FAFB` | Backgrounds sutis |
| `--alvobot-gray-200` | `#F2F4F7` | **Apenas backgrounds** (nunca para borders) |
| `--alvobot-gray-300` | `#E4E4E7` | **Todas as borders** (padrão obrigatório) |
| `--alvobot-gray-400` | `#D4D4D8` | Placeholders, ícones inativos |
| `--alvobot-gray-500` | `#A1A1AA` | Borders de elementos interativos (toggles, estados ativos) |
| `--alvobot-gray-600` | `#6B7280` | Texto secundário |
| `--alvobot-gray-700` | `#52525B` | Labels de formulário |
| `--alvobot-gray-800` | `#344054` | Texto de ênfase |
| `--alvobot-gray-900` | `#18181B` | Títulos, texto principal |

### Borders — Regra Obrigatória

**Todas as borders estruturais usam `var(--alvobot-gray-300)`:**

```css
/* Cards, containers, inputs, dividers — sempre gray-300 */
border: 1px solid var(--alvobot-gray-300);

/* NUNCA usar: */
border: 1px solid var(--alvobot-gray-200);  /* ❌ */
border: 1px solid #e5e5e5;                   /* ❌ */
border: 1px solid #dcdcde;                   /* ❌ */
```

Exceção: `gray-500` para borders de elementos interativos (toggles, switches, estados ativos).

### Tipografia

```
Família: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif
```

| Token | Valor | Uso |
|-------|-------|-----|
| `--alvobot-font-size-xs` | `12px` | Labels pequenos, badges |
| `--alvobot-font-size-sm` | `13px` | Texto secundário, botões |
| `--alvobot-font-size-base` | `14px` | Texto padrão |
| `--alvobot-font-size-lg` | `16px` | Subtítulos |
| `--alvobot-font-size-xl` | `18px` | Títulos de seção |
| `--alvobot-font-size-2xl` | `20px` | Títulos de card |
| `--alvobot-font-size-3xl` | `24px` | Título da página |

### Espaçamento

| Token | Valor | Uso |
|-------|-------|-----|
| `--alvobot-space-xs` | `4px` | Gaps internos mínimos |
| `--alvobot-space-sm` | `8px` | Padding de badges, gaps entre elementos |
| `--alvobot-space-md` | `12px` | Padding de inputs, gap de grids |
| `--alvobot-space-lg` | `16px` | Padding de cards, gaps de seção |
| `--alvobot-space-xl` | `20px` | Padding de containers |
| `--alvobot-space-2xl` | `24px` | Gap entre cards |
| `--alvobot-space-3xl` | `32px` | Margem entre seções |

### Border Radius

| Token | Valor | Uso |
|-------|-------|-----|
| `--alvobot-radius-sm` | `4px` | Inputs, badges |
| `--alvobot-radius-md` | `8px` | Botões, dropdowns |
| `--alvobot-radius-lg` | `12px` | Cards, containers |
| `--alvobot-radius-xl` | `16px` | Modais |
| `--alvobot-radius-full` | `9999px` | Pills, avatares |

### Sombras

| Token | Uso |
|-------|-----|
| `--alvobot-shadow-sm` | Cards padrão |
| `--alvobot-shadow-md` | Cards hover, dropdowns |
| `--alvobot-shadow-lg` | Modais, popovers |
| `--alvobot-shadow-xl` | Overlays |

### Componentes Padrão

**Card:**
```css
.alvobot-card {
    background: var(--alvobot-white);
    border: 1px solid var(--alvobot-gray-300);
    border-radius: var(--alvobot-radius-lg);
    padding: var(--alvobot-space-2xl);
}
```

**Input:**
```css
.alvobot-input {
    height: 40px;
    padding: 0 var(--alvobot-space-md);
    border: 1px solid var(--alvobot-gray-300);
    border-radius: var(--alvobot-radius-md);
    font-size: var(--alvobot-font-size-base);
}
```

**Botão primário:**
```css
.alvobot-btn-primary {
    background: var(--alvobot-primary);
    color: var(--alvobot-secondary);
    border: none;
    border-radius: var(--alvobot-radius-md);
    padding: var(--alvobot-space-sm) var(--alvobot-space-lg);
    font-weight: 600;
}
```

### Ícones

O plugin usa **Lucide Icons** (SVG inline via JS). Nunca usar Dashicons do WordPress nos módulos admin.

```php
// No PHP: usar data-lucide attribute
<i data-lucide="icon-name" class="alvobot-icon"></i>

// No JS: inicializar com lucide.createIcons()
```

### CSS de Módulos

Cada módulo pode ter CSS próprio em `modules/<nome>/assets/css/`. As regras:

1. **Sempre importar ou depender de** `assets/css/styles.css` (carregado globalmente)
2. **Sempre usar variáveis CSS** do design system
3. **Nunca hardcodar cores** — usar `var(--alvobot-gray-300)`, não `#e5e5e5`
4. **Borders sempre `gray-300`** — padronizado em todo o plugin
5. **Frontend CSS (public-facing)** pode usar hardcoded se variáveis CSS não estiverem disponíveis

### Color Picker (WordPress Iris)

O plugin usa o color picker nativo do WordPress com overrides mínimos:
- Popover posicionado com backdrop
- Hex input e botão "Limpar" estilizados para match do design system
- Border-radius no iris-picker box
- Internos do Iris mantêm estilo padrão do WordPress (não sobrescrever)

Seletores de override precisam de 3+ níveis para vencer a especificidade do WP core:
```css
.alvobot-admin-wrap .wp-picker-container input[type="text"].wp-color-picker { ... }
```

## Módulos Específicos

### Login Temporário
- O módulo Login Temporário é FUNCIONAL mas NÃO VISÍVEL no admin
- Não deve aparecer no menu ou dashboard
- Permanece ativo internamente para funcionalidades do sistema

### Quiz Builder
- Integrado como módulo interno (não plugin standalone)
- Menu: `admin.php?page=alvobot-quiz-builder`
- Usa sistema de rewrite rules para URLs únicas (aquiz-e)