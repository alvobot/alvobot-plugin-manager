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