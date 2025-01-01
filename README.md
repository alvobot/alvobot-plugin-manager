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

- **Geração de Token**: O plugin gera um token único (`grp_site_token`) para o site.

- **Criação do Usuário 'alvobot'**: Um usuário administrador chamado `alvobot` é criado ou atualizado.

- **Geração de Senha de Aplicativo**: Uma senha de aplicativo é gerada para o usuário `alvobot` com o nome `AlvoBot App Integration`.

- **Registro no Servidor Central**: O site é registrado no servidor central da AlvoBot, enviando informações como URL, versão do WordPress, plugins instalados e outros dados relevantes.

## Uso

O plugin funciona de forma automática após a instalação e ativação, não requerendo nenhuma configuração adicional por parte do usuário.

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
```

### Reset do Plugin

**Endpoint**: `/wp-json/grp/v1/reset`

**Método**: `POST`

**Descrição**: Permite resetar o plugin, gerando um novo token e recriando o usuário alvobot.

#### Parâmetros

- `token` (string, obrigatório): Token atual do site para autenticação.

#### Exemplo de Requisição

```json
POST /wp-json/grp/v1/reset
Content-Type: application/json

{
  "token": "TOKEN_ATUAL_DO_SITE"
}
```

## Hooks e Filtros

O plugin oferece diversos hooks e filtros para personalização:

- `grp_before_command_execution`: Executado antes de processar um comando remoto.
- `grp_after_command_execution`: Executado após processar um comando remoto.
- `grp_validate_api_response`: Permite validar e modificar respostas da API.

## Internacionalização

O plugin está preparado para internacionalização e pode ser traduzido para qualquer idioma. Os arquivos de tradução devem ser colocados no diretório `/languages`.

## Desinstalação

Ao desinstalar o plugin, as seguintes ações são realizadas:

- Remoção do token do site
- Remoção do usuário 'alvobot'
- Limpeza de todas as opções relacionadas ao plugin

## Requisitos do Sistema

- WordPress 5.8 ou superior
- PHP 7.4 ou superior
- Permissões de administrador para instalação e ativação

## Suporte

Para suporte, entre em contato através do [site oficial](https://alvobot.com/suporte) ou abra uma issue no repositório do GitHub.

## Licença

Este plugin está licenciado sob a GPL v2 ou posterior.

## Versão 1.4.0

- Adicionado suporte a internacionalização
- Melhorias na documentação
- Correções de bugs