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

## Módulos Específicos

### Login Temporário
- O módulo Login Temporário é FUNCIONAL mas NÃO VISÍVEL no admin
- Não deve aparecer no menu ou dashboard
- Permanece ativo internamente para funcionalidades do sistema

### Quiz Builder
- Integrado como módulo interno (não plugin standalone)
- Menu: `admin.php?page=alvobot-quiz-builder`
- Usa sistema de rewrite rules para URLs únicas (aquiz-e)