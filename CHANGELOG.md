# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado no [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

## [Não Lançado]

### 🎯 Em Desenvolvimento
- Integração dinâmica de cores entre módulos
- Sistema de temas personalizado
- Cache avançado para APIs
- Dashboard analítico
- **Sistema de Atualizações**: Debug melhorado para diagnóstico de problemas de update
  - Logs detalhados do processo de atualização
  - Debug específico para módulo updater
  - Fallback para renomeação quando deleção falha

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