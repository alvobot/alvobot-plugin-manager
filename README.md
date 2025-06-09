# AlvoBot Plugin Manager

Plugin WordPress para gerenciamento de plugins e funcionalidades do AlvoBot Pro. Este plugin permite a gestão remota de plugins e recursos com a autorização dos clientes.

## Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Requisitos do Sistema](#requisitos-do-sistema)
- [Módulos](#módulos)
  - [Plugin Manager](#1-plugin-manager)
  - [Author Box](#2-author-box)
  - [Logo Generator](#3-logo-generator)
  - [Pre-Article](#4-pre-article)
  - [Multi-Languages](#5-multi-languages)
  - [Temporary Login](#6-temporary-login)
- [Desinstalação](#desinstalação)
- [Suporte](#suporte)
- [Licença](#licença)

## Requisitos do Sistema

- WordPress 5.8 ou superior
- PHP 7.4 ou superior
- Permissões de administrador para instalação e ativação
- Conexão com internet para comunicação com o servidor central

## Instalação

1. **Download**: Baixe o pacote do plugin a partir do [repositório oficial](https://alvobot.com/alvobot-plugin) ou obtenha o arquivo `alvobot-plugin.zip`.

2. **Upload**: No painel administrativo do WordPress, navegue até `Plugins` > `Adicionar Novo` > `Enviar Plugin`.

3. **Instalação**: Selecione o arquivo `alvobot-plugin.zip` e clique em `Instalar Agora`.

4. **Ativação**: Após a instalação, clique em `Ativar Plugin`.

## Configuração

Após a ativação, o plugin realizará as seguintes ações automaticamente:

- **Geração de Token**: O plugin gera um token único (`grp_site_token`) para o site.
- **Criação do Usuário 'alvobot'**: Um usuário administrador chamado `alvobot` é criado ou atualizado.
- **Geração de Senha de Aplicativo**: Uma senha de aplicativo é gerada para o usuário `alvobot` com o nome `AlvoBot App Integration`.
- **Registro no Servidor Central**: O site é registrado no servidor central da AlvoBot.

### Dados de Registro

Durante o registro, o plugin envia:
- URL do site
- Token único
- Versão do WordPress
- Lista de plugins instalados
- Senha de aplicativo (apenas na ativação inicial)

## Módulos

### 1. Plugin Manager

Módulo central responsável pelo gerenciamento remoto de plugins.

#### API Endpoints

**Comando Remoto**
- `POST /wp-json/grp/v1/command`
  - Gerencia plugins remotamente
  - Requer autenticação via token
  - Comandos suportados:
    - `install_plugin`: Instala e ativa um plugin
    - `activate_plugin`: Ativa um plugin existente
    - `deactivate_plugin`: Desativa um plugin
    - `update_plugin`: Atualiza um plugin existente
    - `reset`: Reseta o plugin para estado inicial

**Exemplo de Requisição para Instalação de Plugin:**
```json
POST /wp-json/grp/v1/command
{
    "token": "seu-token-aqui",
    "command": "install_plugin",
    "plugin_slug": "contact-form-7",
    "plugin_url": "https://downloads.wordpress.org/plugin/contact-form-7.latest-stable.zip"
}
```

**Exemplo de Resposta:**
```json
{
    "success": true,
    "message": "Plugin instalado com sucesso",
    "data": {
        "plugin": "contact-form-7/wp-contact-form-7.php",
        "status": "active"
    }
}
```

### 2. Author Box

Módulo para gerenciamento de informações dos autores do blog.

#### API Endpoints

- `PUT /wp-json/alvobot-pro/v1/authors/{username}`
  - Atualiza informações do autor
  - Requer autenticação via token
  - Parâmetros:
    - `token` (string, obrigatório)
    - `username` (string, obrigatório)
    - `display_name` (string, opcional)
    - `description` (string, opcional)
    - `author_image` (string, opcional)

**Exemplo de Atualização de Autor:**
```json
PUT /wp-json/alvobot-pro/v1/authors/autor123
{
    "token": "seu-token-aqui",
    "display_name": "Nome do Autor",
    "description": "Biografia do autor",
    "author_image": "https://exemplo.com/imagem.jpg"
}
```

### 3. Logo Generator

Módulo para geração automática de logos para o site.

#### API Endpoints

- `POST /wp-json/alvobot-pro/v1/logos`
  - Gera um novo logo
  - Requer autenticação via token
  - Suporta personalização de cores e fontes

**Exemplo de Geração de Logo:**
```json
POST /wp-json/alvobot-pro/v1/logos
{
    "token": "seu-token-aqui",
    "blog_name": "Nome do Site",
    "font_color": "#000000",
    "background_color": "#ffffff",
    "font_choice": "Roboto"
}
```

### 4. Pre-Article

Módulo para gerenciamento de pré-artigos e CTAs.

#### API Endpoints

- `GET /wp-json/alvobot-pre-article/v1/pre-articles`
  - Lista todas as URLs dos pré-artigos
  - Requer autenticação
  - Retorna schema com lista de URLs

- `GET /wp-json/alvobot-pre-article/v1/posts/{post_id}/ctas`
  - Obtém CTAs de um post específico
  - Requer autenticação
  - Parâmetros:
    - `post_id` (integer, obrigatório): ID do post

**Exemplo de Requisição de CTAs:**
```json
GET /wp-json/alvobot-pre-article/v1/posts/123/ctas
Headers: {
    "Authorization": "Bearer seu-token-aqui"
}
```

### 5. Multi-Languages

Módulo para suporte a múltiplos idiomas no site.

#### Funcionalidades

- Gerenciamento de traduções para posts e páginas
- Suporte para múltiplos idiomas no site
- Interface simplificada para tradução de conteúdo
- Compatibilidade com principais plugins de SEO

#### API Endpoints

- `GET /wp-json/alvobot-multi-languages/v1/languages`
  - Lista todos os idiomas configurados
  - Requer autenticação via token
  - Retorna schema com lista de idiomas disponíveis

- `POST /wp-json/alvobot-multi-languages/v1/translations`
  - Cria ou atualiza traduções para um post/página
  - Requer autenticação via token
  - Parâmetros:
    - `post_id` (integer, obrigatório): ID do post original
    - `language` (string, obrigatório): Código do idioma (ex: 'en', 'es', 'fr')
    - `title` (string, obrigatório): Título traduzido
    - `content` (string, obrigatório): Conteúdo traduzido
    - `excerpt` (string, opcional): Resumo traduzido

**Exemplo de Criação de Tradução:**
```json
POST /wp-json/alvobot-multi-languages/v1/translations
{
    "token": "seu-token-aqui",
    "post_id": 123,
    "language": "en",
    "title": "Translated Title",
    "content": "Translated content goes here...",
    "excerpt": "Brief description in English"
}
```

### 6. Temporary Login

Módulo para criação de links de login temporário para acesso seguro ao painel administrativo.

#### Funcionalidades

- Geração de links de login temporário com expiração configurável
- Controle de usuários simultâneos ativos
- Remoção automática de usuários expirados
- Interface administrativa intuitiva
- API externa para integração com sistemas de suporte

#### API Endpoints

**Gerar Link de Login Temporário**
- `POST /wp-json/alvobot-pro/v1/temporary-login/generate`
  - Gera um novo link de login temporário
  - Requer autenticação via token
  - Parâmetros:
    - `token` (string, obrigatório): Token de autenticação do site
    - `expiration_hours` (integer, opcional): Horas até expiração (padrão: 24, máximo: 168)
    - `description` (string, opcional): Descrição do acesso temporário

**Exemplo de Geração de Login Temporário:**
```json
POST /wp-json/alvobot-pro/v1/temporary-login/generate
{
    "token": "seu-token-aqui",
    "expiration_hours": 48,
    "description": "Suporte técnico - Issue #123"
}
```

**Exemplo de Resposta:**
```json
{
    "success": true,
    "data": {
        "login_url": "https://seusite.com/?temp-login-token=abc123&tl-site=xyz789",
        "expires_at": "2024-01-17 14:30:00",
        "expires_in_hours": 48,
        "description": "Suporte técnico - Issue #123",
        "user_id": 456
    },
    "message": "Link de login temporário gerado com sucesso."
}
```

#### Endpoints Internos

- `GET /wp-json/alvobot-pro/v1/temporary-login/status`
  - Obtém status dos logins temporários ativos
  - Requer permissões administrativas

- `POST /wp-json/alvobot-pro/v1/temporary-login/create`
  - Cria usuário temporário (interface administrativa)
  - Requer permissões administrativas

- `POST /wp-json/alvobot-pro/v1/temporary-login/revoke`
  - Revoga todos os logins temporários
  - Requer permissões administrativas

#### Configurações

O módulo permite configurar:
- Dias padrão para expiração (1-30 dias)
- Número máximo de usuários simultâneos (1-10)
- Remoção automática de usuários expirados
- Email para notificações

#### Segurança

- Links expiram automaticamente após o tempo configurado
- Usuários temporários têm permissões de administrador limitadas
- Tokens únicos por usuário temporário
- Validação dupla com token do site
- Logs de acesso para auditoria

## Hooks e Filtros

O plugin oferece hooks e filtros para personalização:

- `grp_before_command_execution`: Antes de processar comando remoto
- `grp_after_command_execution`: Após processar comando remoto
- `grp_validate_api_response`: Validação de respostas da API

## Desinstalação

Ao desinstalar, o plugin:
- Remove o token do site
- Remove o usuário 'alvobot'
- Limpa todas as opções relacionadas

## Suporte

Para suporte:
- Site oficial: [AlvoBot](https://alvobot.ai)
- Email: suporte@alvobot.ai
- Documentação: [Docs](https://docs.alvobot.ai)

## Licença

Este software é proprietário e seu uso é restrito aos termos de licença do AlvoBot Pro.

## Changelog

### Versão 1.4.0
- Adicionado suporte a internacionalização
- Melhorias na documentação
- Correções de bugs