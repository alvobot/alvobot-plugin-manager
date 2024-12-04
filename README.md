# AlvoBot Plugin

Bem-vindo ao **AlvoBot Plugin**! Este plugin para WordPress permite a gestão remota de plugins com a autorização dos clientes. Abaixo você encontrará uma documentação completa sobre como instalar, configurar e utilizar o plugin, incluindo detalhes sobre as rotas da API disponíveis.

## Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso](#uso)
- [Rotas da API](#rotas-da-api)
  - [Registrar Site](#registrar-site)
  - [Comandos Remotos](#comandos-remotos)
  - [Reset do Plugin](#reset-do-plugin)
- [Hooks e Filtros](#hooks-e-filtros)
- [Internacionalização](#internacionalização)
- [Desinstalação](#desinstalação)
- [Requisitos do Sistema](#requisitos-do-sistema)
- [Suporte](#suporte)
- [Licença](#licença)

## Instalação

1. **Download**: Baixe o pacote do plugin a partir do [repositório oficial](https://alvobot.com/alvobot-plugin) ou obtenha o arquivo `alvobot-plugin.zip`.

2. **Upload**: No painel administrativo do WordPress, navegue até `Plugins` > `Adicionar Novo` > `Enviar Plugin`.

3. **Instalação**: Selecione o arquivo `alvobot-plugin.zip` e clique em `Instalar Agora`.

4. **Ativação**: Após a instalação, clique em `Ativar Plugin`.

## Configuração

Após a ativação, o plugin realizará as seguintes ações automaticamente:

- **Geração de Token e Código do Site**: O plugin gera um token único (`grp_site_token`) e um código de verificação de 6 caracteres (`grp_site_code`) para o site.

- **Criação do Usuário 'alvobot'**: Um usuário administrador chamado `alvobot` é criado ou atualizado.

- **Geração de Senha de Aplicativo**: Uma senha de aplicativo é gerada para o usuário `alvobot` com o nome `AlvoBot App Integration`.

- **Registro no Servidor Central**: O site é registrado no servidor central da AlvoBot, enviando informações como URL, versão do WordPress, plugins instalados e outros dados relevantes.

## Uso

### Atualização Manual

- Na lista de plugins do WordPress, você verá um link `Atualizar` associado ao AlvoBot Plugin. Clique neste link para forçar uma atualização manual dos dados enviados ao servidor central.

### Verificação de Atualizações

- Também há um link `Verificar Atualizações` que, quando clicado, força uma verificação imediata por novas versões do plugin.

### Código de Verificação

- O código de verificação (`grp_site_code`) é exibido na lista de plugins. Este código pode ser utilizado para fins de autenticação ou suporte.

## Rotas da API

O AlvoBot Plugin expõe algumas rotas REST API para permitir a comunicação com o servidor central.

### Registrar Site

**Endpoint**: Automático na ativação do plugin.

O registro do site é feito automaticamente durante a ativação do plugin. Os dados enviados incluem:

- `action`: 'register_site'
- `site_url`: URL do site
- `token`: Token único do site
- `wp_version`: Versão do WordPress
- `plugins`: Lista de plugins instalados
- `site_code`: Código de verificação do site
- `app_password`: Senha de aplicativo gerada (apenas na ativação)

### Comandos Remotos

**Endpoint**: `/wp-json/grp/v1/command`

**Método**: `POST`

**Descrição**: Recebe comandos do servidor central para gerenciar plugins remotamente.

#### Parâmetros

- `token` (string, obrigatório): Token único do site para autenticação.
- `command` (string, obrigatório): Comando a ser executado. Valores possíveis:
  - `install_plugin`: Instala e ativa um plugin.
  - `activate_plugin`: Ativa um plugin existente.
- `plugin_slug` (string, opcional): Slug do plugin no repositório do WordPress.
- `plugin_url` (string, opcional): URL para download do plugin (em formato .zip).

#### Exemplo de Requisição

```json
POST /wp-json/grp/v1/command
Content-Type: application/json

{
  "token": "SEU_TOKEN_UNICO",
  "command": "install_plugin",
  "plugin_slug": "contact-form-7"
}