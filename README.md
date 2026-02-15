# AlvoBot Pro - Plugin Manager Suite

[![Version](https://img.shields.io/badge/version-2.5.9-blue.svg)](https://github.com/alvobot/alvobot-plugin-manager)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-brightgreen.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![Tests](https://img.shields.io/badge/tests-58%20passing-brightgreen.svg)]()

> Suite completa de ferramentas AlvoBot para WordPress incluindo gerador de logo, author box, gerenciamento de plugins e muito mais.

## 📋 Índice

- [Sobre](#-sobre)
- [Recursos](#-recursos)
- [Instalação](#-instalação)
- [Módulos](#-módulos)
- [API](#-api)
- [Configuração](#-configuração)
- [Testes](#-testes)
- [Screenshots](#-screenshots)
- [Changelog](#-changelog)
- [Contribuição](#-contribuição)
- [Suporte](#-suporte)
- [Licença](#-licença)

## 🎯 Sobre

O **AlvoBot Pro** é uma suite completa de ferramentas profissionais para WordPress, desenvolvida para potencializar seu site com funcionalidades avançadas de design, conteúdo e gerenciamento. Criado pela equipe AlvoBot, oferece uma experiência integrada e moderna.

### ✨ Principais Características

- 🎨 **Interface Moderna**: Design system unificado com gradientes AlvoBot
- 🚀 **Performance Otimizada**: Carregamento condicional e código limpo
- 📱 **Totalmente Responsivo**: Experiência perfeita em todos os dispositivos
- 🔧 **Modular**: Ative apenas os módulos que precisar
- 🛡️ **Seguro**: Validação rigorosa e sanitização de dados
- 🌍 **Multilíngue**: Suporte completo à internacionalização
- 🧪 **Testado**: 58 testes automatizados com 456+ assertions

## 🚀 Recursos

### 🎨 **Logo Generator**
- Geração automática de logos profissionais
- Biblioteca com 5000+ ícones SVG
- Personalização completa de cores e fontes
- Export em alta resolução (SVG, PNG)
- API REST para integração externa

### 👤 **Author Box**
- Design moderno e responsivo
- Avatar personalizado com upload
- Configuração flexível de exibição
- Dark mode automático
- Preview em tempo real no admin

### 🔧 **Plugin Manager**
- Monitoramento em tempo real
- Ativação/desativação remota
- Status detalhado do sistema
- API segura com autenticação

### 🌐 **Multi-Languages**
- Integração com Polylang
- Tradução automática de conteúdo
- Gerenciamento de idiomas
- API REST para automação

### 🔐 **Temporary Login**
- Links de acesso temporário
- Controle de expiração
- Logs de segurança
- Ideal para suporte técnico

### 📝 **Pre-Article Generator**
- Geração automática de introduções
- Templates personalizáveis
- Otimização para SEO
- Integração com editores

## 💻 Instalação

### Requisitos do Sistema

- **WordPress**: 5.0 ou superior
- **PHP**: 7.4 ou superior
- **MySQL**: 5.6 ou superior
- **Memória**: 128MB mínimo (256MB recomendado)

### Instalação Manual

1. **Download do Plugin**
   ```bash
   git clone https://github.com/alvobot/alvobot-plugin-manager.git
   ```

2. **Upload para WordPress**
   - Extraia o arquivo na pasta `/wp-content/plugins/`
   - Ou use o uploader do WordPress admin

3. **Ativação**
   - Acesse `Plugins > Plugins Instalados`
   - Ative o "AlvoBot Pro"

4. **Configuração Inicial**
   - Acesse `Configurações > AlvoBot Pro`
   - Configure seu token de API
   - Ative os módulos desejados

### Instalação via Composer

```bash
composer require alvobot/alvobot-plugin-manager
```

## 🧩 Módulos

### 🎨 Logo Generator

Gere logos profissionais em segundos com nossa biblioteca de ícones e ferramentas de personalização.

**Funcionalidades:**
- ✅ 5000+ ícones SVG profissionais
- ✅ Personalização completa de cores
- ✅ Múltiplas opções de fonte
- ✅ Export SVG e PNG
- ✅ API REST integrada

**Uso:**
```php
// Via shortcode
[alvobot_logo name="Minha Empresa" icon="computer" background="#CD9042"]

// Via PHP
echo do_shortcode('[alvobot_logo name="Minha Empresa"]');
```

### 👤 Author Box

Exiba informações do autor de forma elegante e profissional.

**Funcionalidades:**
- ✅ Design responsivo moderno
- ✅ Avatar personalizado
- ✅ Configuração por post/página
- ✅ Dark mode automático
- ✅ Preview em tempo real

**Configuração:**
1. Acesse `Configurações > Author Box`
2. Configure onde exibir (posts/páginas)
3. Personalize o título e descrição
4. Salve as alterações

### 🔧 Plugin Manager

Monitore e gerencie plugins remotamente via API segura.

**Funcionalidades:**
- ✅ Lista completa de plugins
- ✅ Ativação/desativação remota
- ✅ Status em tempo real
- ✅ Logs de atividade
- ✅ Autenticação por token

**Endpoints API:**
```
POST /wp-json/alvobot-pro/v1/plugins/commands
GET  /wp-json/alvobot-pro/v1/system/status
```

### 🌐 Multi-Languages

Gerencie conteúdo multilíngue com facilidade.

**Funcionalidades:**
- ✅ Integração Polylang
- ✅ Tradução automática
- ✅ API REST completa
- ✅ Gerenciamento de idiomas

### 🔐 Temporary Login

Crie links de acesso temporário para suporte técnico.

**Funcionalidades:**
- ✅ Links com expiração
- ✅ Controle de permissões
- ✅ Logs de segurança
- ✅ Ideal para suporte

### 📝 Pre-Article Generator

Gere introduções automáticas para seus artigos.

**Funcionalidades:**
- ✅ Templates personalizáveis
- ✅ Geração automática
- ✅ Otimização SEO
- ✅ Integração com editores

## 🔌 API

### Autenticação

Os endpoints usam estratégias diferentes:

```bash
# Plugin Manager / Logo Generator / Author Box
# Envie o token do site no header "token" ou no JSON (campo "token")
token: YOUR_SITE_TOKEN

# Multi-Languages
# Requer usuário autenticado com permissão edit_posts
# (sessão WordPress + nonce ou Basic Auth válido)
```

### Endpoints Principais

#### Plugin Manager
```bash
# Listar plugins
curl -X POST https://seusite.com/wp-json/alvobot-pro/v1/plugins/commands \
  -H "Content-Type: application/json" \
  -H "token: YOUR_SITE_TOKEN" \
  -d '{"command":"get_plugins"}'

# Ativar plugin
curl -X POST https://seusite.com/wp-json/alvobot-pro/v1/plugins/commands \
  -H "Content-Type: application/json" \
  -H "token: YOUR_SITE_TOKEN" \
  -d '{"command":"activate_plugin","plugin":"plugin-name/plugin.php"}'
```

#### Logo Generator
```bash
# Gerar logo
curl -X POST https://seusite.com/wp-json/alvobot-pro/v1/logos \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_SITE_TOKEN",
    "name": "Minha Empresa",
    "icon": "computer",
    "background_color": "#CD9042",
    "font_color": "#FFFFFF"
  }'
```

#### Author Box
```bash
# Atualizar autor
curl -X PUT https://seusite.com/wp-json/alvobot-pro/v1/authors/username \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_SITE_TOKEN",
    "display_name": "Novo Nome",
    "description": "Nova descrição",
    "avatar": "base64_image_data"
  }'
```

#### Multi-Languages (REST)
```bash
# Endpoint principal de tradução (autenticado)
POST /wp-json/alvobot-pro/v1/translate

# Endpoints administrativos (internos)
POST /wp-json/alvobot-pro/v1/admin/translate
GET  /wp-json/alvobot-pro/v1/admin/queue/status
POST /wp-json/alvobot-pro/v1/admin/queue/add
```

### Códigos de Resposta

| Código | Significado |
|--------|-------------|
| 200 | Sucesso |
| 400 | Requisição inválida |
| 401 | Token inválido/ausente |
| 403 | Permissões insuficientes |
| 404 | Recurso não encontrado |
| 500 | Erro interno |

## ⚙️ Configuração

### Configuração Inicial

1. **Token de API**
   ```php
   // Em wp-config.php
   define('ALVOBOT_API_TOKEN', 'seu-token-aqui');
   ```

2. **Configurações por Módulo**
   - Acesse `Configurações > AlvoBot Pro`
   - Configure cada módulo individualmente
   - Ative/desative conforme necessário

### Variáveis CSS Customizáveis

```css
:root {
  --alvobot-primary: #CD9042;
  --alvobot-primary-dark: #B8803A;
  --alvobot-white: #ffffff;
  --alvobot-gray-50: #f9f9f9;
  --alvobot-gray-100: #f6f7f7;
  /* ... mais variáveis */
}
```

### Hooks e Filtros

```php
// Customizar configurações do Author Box
add_filter('alvobot_author_box_settings', function($settings) {
    $settings['custom_field'] = 'valor';
    return $settings;
});

// Modificar HTML do Author Box
add_filter('alvobot_author_box_html', function($html, $author_id) {
    // Seu código personalizado
    return $html;
}, 10, 2);
```

## 🧪 Testes

O AlvoBot Pro inclui um sistema de testes automatizados completo e simples, garantindo qualidade e confiabilidade do código.

### ✅ **Sistema de Testes**

```bash
✅ 58 testes executados
✅ 456+ assertions validadas  
✅ 100% de sucesso
✅ Tempo de execução < 0.02s
✅ Zero dependências complexas
```

### **Módulos Testados**

- **Plugin Básico**: Constantes, arquivos, configurações
- **Quiz Builder**: Estrutura, validação, shortcodes
- **Logo Generator**: Requisições, cores, estilos
- **Author Box**: Dados, configurações, HTML
- **AJAX Handlers**: Segurança, nonces, sanitização
- **OpenAI API**: Configuração, autenticação, respostas
- **REST API**: Endpoints, rate limiting, validação
- **AlvoBot Cloud API**: Logo generation, account status
- **Integração de APIs**: Fallbacks, cache, batch processing

### **Como Executar os Testes**

```bash
# Instalação das dependências
composer install

# Execução dos testes
composer test

# Execução com cores e verbose
vendor/bin/phpunit --colors=always --verbose

# Execução de teste específico
vendor/bin/phpunit tests/PluginBasicsTest.php
```

### **Estrutura dos Testes**

```
tests/
├── bootstrap.php              # Configuração simples
├── PluginBasicsTest.php       # Testes básicos do plugin
├── QuizBuilderTest.php        # Testes do Quiz Builder
├── LogoGeneratorTest.php      # Testes do Logo Generator
├── AuthorBoxTest.php          # Testes do Author Box
├── AjaxHandlersTest.php       # Testes AJAX
├── OpenAIApiTest.php          # Testes OpenAI API
├── RestApiTest.php            # Testes REST API
├── AlvoBotCloudApiTest.php    # Testes AlvoBot Cloud API
└── ApiIntegrationTest.php     # Testes integração APIs
```

### **Benefícios dos Testes**

- ✅ **Detecção precoce de bugs** antes da produção
- ✅ **Refatoração segura** com confiança total
- ✅ **Validação automática** de todas as funcionalidades
- ✅ **Documentação viva** através dos testes
- ✅ **Qualidade consistente** em todas as releases

> 📖 **Documentação completa**: Veja o [CHANGELOG.md](CHANGELOG.md) para mais detalhes sobre os testes.

## 📸 Screenshots

### Interface Principal
![Dashboard Principal](assets/screenshots/dashboard.png)

### Logo Generator
![Logo Generator](assets/screenshots/logo-generator.png)

### Author Box
![Author Box](assets/screenshots/author-box.png)

### Plugin Manager
![Plugin Manager](assets/screenshots/plugin-manager.png)

## 📋 Changelog

Veja o arquivo [CHANGELOG.md](CHANGELOG.md) para histórico completo de versões.

### Versão Atual: 2.5.9

**🐛 Correções:**
- **Essential Pages**: Corrigido erro fatal ao criar páginas individuais
- Adicionados métodos públicos `create_essential_page()` e `delete_essential_page()`
- Implementado retorno booleano adequado para controle de fluxo
- Adicionados logs de debug para rastreamento de criação/exclusão
- Páginas criadas individualmente agora são adicionadas automaticamente ao menu do rodapé
- Configuração automática de páginas especiais do WordPress (Privacy Policy, Terms)

## 🤝 Contribuição

Contribuições são bem-vindas! Veja nosso [guia de contribuição](CONTRIBUTING.md).

### Como Contribuir

1. **Fork** o projeto
2. **Clone** seu fork
3. **Crie** uma branch para sua feature
4. **Commit** suas mudanças
5. **Push** para a branch
6. **Abra** um Pull Request

### Padrões de Código

- Siga o [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use comentários em português para documentação
- Mantenha compatibilidade com PHP 7.4+
- Teste em múltiplas versões do WordPress

### Reportar Bugs

Use nosso [sistema de issues](https://github.com/alvobot/alvobot-plugin-manager/issues) para reportar bugs.

**Template de Bug Report:**
```markdown
**Descrição:** Breve descrição do bug
**Versão:** 2.5.9
**WordPress:** 6.x
**PHP:** 8.x
**Passos para reproduzir:**
1. Primeiro passo
2. Segundo passo
3. Resultado inesperado

**Comportamento esperado:** O que deveria acontecer
**Screenshots:** Se aplicável
```

## 🆘 Suporte

### Documentação
- [Documentação Oficial](https://docs.alvobot.com/)
- [API Reference](https://docs.alvobot.com/api/)
- [Tutoriais](https://docs.alvobot.com/tutorials/)

### Comunidade
- [GitHub Issues](https://github.com/alvobot/alvobot-plugin-manager/issues)
- [Suporte AlvoBot](https://app.alvobot.com/support)
- [Discord Community](https://discord.gg/alvobot)

### Suporte Comercial
Para suporte prioritário e personalizado:
- 📧 Email: support@alvobot.com
- 🌐 Site: https://app.alvobot.com/
- 💬 Chat: Disponível 24/7 no painel AlvoBot

## 📄 Licença

Este projeto está licenciado sob a **GPL v2 ou posterior**.

```
Copyright (C) 2025 AlvoBot - Cris Franklin

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 👨‍💻 Autores

**AlvoBot Team**
- **Cris Franklin** - *Desenvolvedor Principal* - [@alvobot](https://github.com/alvobot)
- **Claude AI** - *Assistente de Desenvolvimento* - Otimizações e melhorias

---

## 🌟 Apoie o Projeto

Se este plugin foi útil para você, considere:

- ⭐ **Dar uma estrela** no GitHub
- 🐛 **Reportar bugs** e sugerir melhorias  
- 🤝 **Contribuir** com código
- 💬 **Compartilhar** com a comunidade
- ☕ **Apoiar** via [AlvoBot Pro](https://app.alvobot.com/)

---

<div align="center">

**Feito com ❤️ pela equipe [AlvoBot](https://alvobot.com/)**

[![AlvoBot](https://img.shields.io/badge/Powered%20by-AlvoBot-CD9042?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJMMTMuMDkgOC4yNkwyMCA5TDEzLjA5IDE1Ljc0TDEyIDIyTDEwLjkxIDE1Ljc0TDQgOUwxMC45MSA4LjI2TDEyIDJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K)](https://alvobot.com/)

</div>
