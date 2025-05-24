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

## Core Plugin Functionality

This section describes the foundational architecture and core operations of the AlvoBot Pro plugin, which serves as a comprehensive suite of tools for WordPress enhancement and provides capabilities for remote site management.

### Key Files and Their Roles

The plugin's functionality is primarily organized across three main files:

*   **`alvobot-pro.php`**: This is the main plugin bootstrap file. It is responsible for:
    *   Defining essential constants (like version, paths, and URLs).
    *   Loading the main plugin class (`AlvoBotPro`).
    *   Including necessary dependencies.
    *   Registering activation and deactivation hooks.

*   **`includes/class-alvobot-pro.php`**: This file contains the `AlvoBotPro` class, which is the central orchestrator of the plugin. Its responsibilities include:
    *   Loading and initializing active modules (e.g., Plugin Manager, Author Box).
    *   Creating and managing the plugin's administration menus and settings pages.
    *   Enqueuing necessary CSS stylesheets and JavaScript files for the admin interface.
    *   Handling plugin activation and deactivation logic, delegating to specific module activation/deactivation methods.

*   **`includes/class-alvobot-pro-ajax.php`**: This file houses the `AlvoBotPro_Ajax` class, dedicated to handling all AJAX (Asynchronous JavaScript and XML) requests. This facilitates dynamic interactions within the WordPress admin area without requiring page reloads. Key AJAX actions handled by this class include:
    *   Toggling the active status of individual modules.
    *   Managing site registration with the central AlvoBot server.
    *   Saving plugin settings.
    *   Performing a plugin reset to default configurations.
    *   Recording plugin activity for logging purposes.

### Plugin Lifecycle

The AlvoBot Pro plugin follows a standard WordPress plugin lifecycle, with specific actions performed during activation, deactivation, and initialization:

*   **Activation (`alvobot_pro_activate` function & `AlvoBotPro::activate` method):**
    When the plugin is activated, the following key actions occur:
    *   Default options for all available modules are saved to the WordPress database. This ensures that each module has a baseline configuration.
    *   The `activate()` method of each registered module is called, allowing modules to perform their specific setup tasks (e.g., creating custom database tables, setting default options).
    *   The plugin's version (`ALVOBOT_PRO_VERSION`) is stored or updated in the WordPress options table.

*   **Deactivation (`alvobot_pro_deactivate` function & `AlvoBotPro::deactivate` method):**
    Upon deactivation, the plugin executes:
    *   The `deactivate()` method of each registered module is called. This allows modules to perform any necessary cleanup, such as removing temporary data or settings if appropriate (though core data like generated content is typically preserved).

*   **Initialization (`alvobot_pro_init` function & `AlvoBotPro::init` method):**
    On every WordPress page load where the plugin is active (particularly in the admin area), the `init` process takes place:
    *   The `AlvoBotPro` class sets up the administrative menus and pages, making the plugin's settings and module interfaces accessible.
    *   Frontend and backend assets (CSS, JavaScript) are loaded as needed for the plugin's functionality and user interface.

### Module Management

AlvoBot Pro is designed with a modular architecture, allowing functionalities to be enabled or disabled as needed.

*   **Definition and Loading:** Modules are typically defined as separate classes (often within the `modules/` directory). The main `AlvoBotPro` class is responsible for scanning for available modules and loading the ones that are marked as active.
*   **`alvobot_pro_active_modules` WordPress Option:** The status of which modules are currently active is stored in a WordPress option named `alvobot_pro_active_modules`. This option usually holds an array of module identifiers.
*   **Toggling Modules:** Users can enable or disable modules through the plugin's settings interface. This action is typically handled by an AJAX request processed by the `toggle_module` method in the `AlvoBotPro_Ajax` class, which updates the `alvobot_pro_active_modules` option.

### User and Token Management

For remote management and API interactions, specific user and token mechanisms are established:

*   **`alvobot` WordPress User:** While primarily initialized and managed by the "Plugin Manager" module during its activation, the creation of an `alvobot` WordPress user with administrator privileges is a core concept. This user is utilized for authenticated actions performed via the AlvoBot central server, ensuring secure remote operations.
*   **`grp_site_token` WordPress Option:** A unique site token, stored as `grp_site_token` in the WordPress options table, is generated upon plugin activation (typically by the Plugin Manager module). This token serves as a secret key to authenticate API requests between the WordPress site and the AlvoBot central server, ensuring that commands are only accepted from authorized sources.

### Key Constants

The plugin defines several constants in `alvobot-pro.php` for easy access to important values and paths:

*   **`ALVOBOT_PRO_VERSION`**: Specifies the current version of the AlvoBot Pro plugin.
*   **`ALVOBOT_PRO_PATH`**: The absolute server path to the plugin's main directory.
*   **`ALVOBOT_PRO_PLUGIN_DIR`**: (Often the same as `ALVOBOT_PRO_PATH`) The directory path of the plugin.
*   **`ALVOBOT_PRO_PLUGIN_URL`**: The base URL for the plugin's directory, used for accessing assets like CSS and JavaScript.
*   **`ALVOBOT_PRO_PLUGIN_FILE`**: The full path to the main plugin file (`alvobot-pro.php`).
*   **`GRP_SERVER_URL`**: The URL of the central AlvoBot server. This is crucial for remote operations like plugin management and site registration.
*   **`ALVOBOT_PLUGIN_UPDATE_URL`**: The URL used by the plugin to check for new versions and manage updates.

## Módulos

### 1. Plugin Manager Module

The Plugin Manager Module is a core component of AlvoBot Pro, designed to facilitate the remote administration of plugins on the WordPress site from a central AlvoBot server. This module is fundamental for the remote management capabilities of AlvoBot Pro and is always active.

#### Role of the Module

The primary role of this module is to establish a secure and reliable communication channel between the WordPress site and the AlvoBot central server (`GRP_SERVER_URL`). This allows authorized remote commands to manage (install, activate, deactivate, delete, list) plugins, and to reset the plugin's configuration if needed.

#### Activation and Setup

Upon activation of the AlvoBot Pro plugin (or when this module is specifically re-activated via its settings page), the Plugin Manager performs several critical setup actions:

1.  **`alvobot` User Creation:** It ensures an administrator user named `alvobot` exists. If not, it creates this user with the email `alvobot@alvobot.com.br`. This user is used for operations initiated by the AlvoBot central server.
2.  **Application Password Generation:** A unique application password is generated for the `alvobot` user. The name of this password is "AlvoBot Plugin Manager". This password is used for authenticating the site during the registration process with the central server. Existing application passwords for this user are typically removed before a new one is created.
3.  **Site Registration (`register_site` method):** The module registers the WordPress site with the AlvoBot central server. This involves sending a `POST` request to the `GRP_SERVER_URL` with the following data:
    *   `action`: 'register_site'
    *   `site_url`: The URL of the WordPress site (from `get_site_url()`).
    *   `token`: The unique site token (`grp_site_token` option).
    *   `wp_version`: The current WordPress version (from `get_bloginfo('version')`).
    *   `plugins`: An array of installed plugin slugs/main files.
    *   `app_password`: The newly generated application password for the `alvobot` user.
    This registration is crucial for enabling remote management.

#### REST API Endpoint

The Plugin Manager module exposes a REST API endpoint to receive commands from the AlvoBot central server.

*   **Endpoint URL:** `POST /wp-json/alvobot-pro/v1/plugins/commands`
    *   Note: The base path `/wp-json/` is standard for WordPress REST API endpoints. The namespace `alvobot-pro/v1` and the specific route `/plugins/commands` are defined within the module.
*   **Authentication:** Requests to this endpoint must be authenticated. The `verify_token` method checks for a valid `grp_site_token`. This token can be provided either in the request header (e.g., `Token: your-site-token`) or as part of the JSON body (e.g., `"token": "your-site-token"`). If the token is missing or invalid, a `401 Unauthorized` error is returned.

#### API Commands

The `handle_command` method processes various commands sent to the API endpoint. All commands expect a JSON payload.

*   **`install_plugin`**: Installs and activates a new plugin.
    *   Parameters:
        *   `plugin_slug` (string, optional): The slug of the plugin from the WordPress.org repository (e.g., "contact-form-7").
        *   `plugin_url` (string, optional): The direct URL to a `.zip` file for installing a plugin not on WordPress.org.
    *   One of `plugin_slug` or `plugin_url` must be provided. The module uses `Plugin_Upgrader` for installation and `activate_plugin()` for activation.

*   **`activate_plugin`**: Activates an already installed plugin.
    *   Parameter:
        *   `plugin` (string, required): The plugin's main file path (e.g., `contact-form-7/wp-contact-form-7.php`).

*   **`deactivate_plugin`**: Deactivates an active plugin.
    *   Parameter:
        *   `plugin` (string, required): The plugin's main file path.

*   **`delete_plugin`**: Deactivates and then deletes a plugin from the site.
    *   Parameter:
        *   `plugin` (string, required): The plugin's main file path.

*   **`get_plugins`**: Retrieves a list of all installed plugins.
    *   The response includes details for each plugin: file path, name, version, description, author, and current activation status (active/inactive).

*   **`reset`**: Performs a comprehensive reset of the Plugin Manager and related AlvoBot Pro settings. This includes:
    *   Deleting the existing `grp_site_token`, `grp_site_code`, `grp_registered`, and `grp_app_password` options.
    *   Deleting the `alvobot` WordPress user.
    *   Creating a new `alvobot` user.
    *   Generating a new application password for the new `alvobot` user.
    *   Generating a new `grp_site_token`.
    *   Re-registering the site with the AlvoBot central server using the new credentials.
    *   The response includes the new token, application password, and new user details.

#### Settings Page

The Plugin Manager module has a settings page accessible within the WordPress admin area, rendered by the `render_settings_page` method.

*   **Template File:** `includes/modules/plugin-manager/templates/plugin-manager-settings.php`
*   **Information Displayed:**
    *   Status of the `alvobot` user (whether it exists and has admin rights).
    *   The current `grp_site_token`.
*   **Available Actions:**
    *   **"Activate Plugin Manager":** This button allows manual triggering of the module's `activate()` method. This is useful if the initial automatic activation failed or if a reset is needed for the user and application password setup.
    *   **"Retry Registration":** This button allows the site administrator to manually re-trigger the site registration process. It generates a new application password for the existing `alvobot` user and calls the `register_site()` method to attempt registration with the central server again. Success or error notices are displayed.

### 2. Author Box Module

The Author Box Module enhances your WordPress posts and pages by displaying a configurable box with information about the author. It also allows for remote updating of author details via a REST API.

#### Purpose of the Module

This module serves two main purposes:
1.  **Display Author Information:** It automatically appends a visually distinct "Author Box" to the content of single posts and/or pages, showcasing details about the post's author.
2.  **Remote Author Management:** It provides an API endpoint allowing the AlvoBot central server to update author-specific information, including their display name, biography, and custom avatar.

#### Key Functionalities & Configuration

The module's behavior and appearance are managed through WordPress options and user profile settings.

*   **Display Logic:**
    *   The author box is appended to the main content of posts and pages using the `the_content` filter with a high priority (999) to ensure it appears after most other content modifications.
    *   It only displays on singular views (e.g., single posts, individual pages).

*   **Content Elements:**
    The author box typically includes:
    *   **Customizable Title:** A title for the author box section (e.g., "Sobre o Autor").
    *   **Author Avatar:**
        *   Displays a custom avatar if one is uploaded by the user via their profile page (stored in the `ab_custom_avatar_id` user meta).
        *   If no custom avatar is set, it falls back to the user's Gravatar.
        *   The size of the avatar is configurable in the settings.
    *   **Author Name:** The author's display name, which links to their author archive page.
    *   **Author Description:** The biographical information of the author (from their user profile), if enabled in settings.

*   **Settings (`AlvoBotPro_AuthorBox` class):**
    *   Module settings are stored in a single WordPress option named `alvobot_pro_author_box`.
    *   The settings page for this module is accessible in the WordPress admin area, rendered by the `render_settings_page` method within the `AlvoBotPro_AuthorBox` class. This page includes a live preview of the author box.
    *   **Configurable options include:**
        *   `title_text`: The text to be displayed as the title of the author box.
        *   `display_on_posts`: A checkbox to enable or disable the author box display on single posts. (Default: Enabled)
        *   `display_on_pages`: A checkbox to enable or disable the author box display on pages. (Default: Disabled)
        *   `show_description`: A checkbox to show or hide the author's biographical description. (Default: Enabled)
        *   `avatar_size`: The size (width and height in pixels) for the author's avatar. (Default: 96px)

*   **User Profile Additions:**
    *   **Custom Avatar Upload:** On the user profile edit page (`profile.php`, `user-edit.php`), users can upload a custom avatar image. This functionality uses the WordPress media uploader and is handled by `js/custom-avatar.js`. The ID of the uploaded attachment is stored in the `ab_custom_avatar_id` user meta field.
    *   **Social Media Fields:** The `add_social_fields` method is present in the code, intended to add social media link fields to user profiles. However, in the current implementation, the loop that generates these fields is empty (`foreach (array() as $network => $label)`), so no social media fields are actually added to the user profile page by this module.

#### REST API Endpoint (`AlvoBotPro_AuthorBox_API` class)

The module provides a REST API endpoint for updating author information remotely.

*   **Endpoint:** `PUT /wp-json/alvobot-pro/v1/authors/(?P<username>[\w-]+)`
    *   `(P<username>[\w-]+)`: This is a placeholder for the WordPress username of the author to be updated.
*   **Method:** `PUT`
*   **Authentication:**
    *   Requires a `token` parameter in the JSON body of the request.
    *   This token is validated against the `grp_site_token` stored in WordPress options. An invalid or missing token will result in a `401 Unauthorized` error.
*   **Parameters (JSON body):**
    *   `token` (string, required): The site's authentication token (`grp_site_token`).
    *   `username` (string, required in URL): The username of the author to update.
    *   `display_name` (string, optional): If provided, updates the user's display name (`wp_update_user`).
    *   `description` (string, optional): If provided, updates the user's biographical information (user meta field `description`).
    *   `author_image` (string, optional):
        *   A Base64 encoded image string (e.g., `data:image/jpeg;base64,...`).
        *   If provided, the image is decoded, uploaded to the WordPress media library, and set as the user's custom avatar by storing the attachment ID in the `ab_custom_avatar_id` user meta field.

#### Assets

The module enqueues the following CSS and JavaScript files:

*   **Public-facing (Frontend):**
    *   `includes/modules/author-box/css/public.css`: Styles for the author box displayed on posts and pages. Loaded only on singular views.
*   **Admin Area (Backend):**
    *   `includes/modules/author-box/css/admin.css`: Specific styles for the Author Box settings page and user profile additions.
    *   `includes/modules/author-box/js/custom-avatar.js`: JavaScript for handling the custom avatar upload functionality on user profile pages. Loaded on `profile.php` and `user-edit.php`.
    *   `includes/modules/author-box/js/admin.js`: JavaScript for potential admin interactions on the Author Box settings page, including WordPress color picker integration (though color picker fields are not explicitly defined in the provided settings fields).

### 3. Logo Generator Module

The Logo Generator Module provides a user-friendly interface within the WordPress admin area and a REST API endpoint for creating custom SVG logos and favicons for the site.

#### Purpose of the Module

This module enables users to:
1.  **Design Custom Logos:** Interactively generate SVG logos by choosing icons, fonts, colors, and text.
2.  **Create Favicons:** Generate square SVG favicons based on a selected icon and colors.
3.  **Apply Branding:** Save generated logos and favicons to the Media Library and set them as the active site logo and site icon.
4.  **Remote Generation:** Allow the AlvoBot central server to generate and apply logos/favicons via a REST API.

#### Key Functionalities (Admin Interface - `AlvoBotPro_LogoGenerator` class)

The primary user interface for the Logo Generator is an admin page.

*   **Location:** The module adds an admin page accessible at `wp-admin/admin.php?page=alvobot-pro-logo`.
*   **Inputs for Logo Generation:**
    *   **Blog Name:** Text to be used in the logo (defaults to the site's current name).
    *   **Font Color:** Hexadecimal color code for the text and icon (default: `#000000`).
    *   **Background Color:** Hexadecimal color code for the logo's background (default: `#FFFFFF`).
    *   **Icon Selection:** Users can choose an icon from a grid.
        *   Icons are SVG files stored in `includes/modules/logo-generator/assets/icons/`.
        *   The interface provides a searchable and categorized grid of available icons. Icon metadata (category, keywords) can be provided via correspondingly named `.json` files in the same directory.
    *   **Font Choice:** A dropdown list of pre-selected Google Fonts (e.g., Montserrat, Playfair Display, Roboto).
*   **SVG Generation:**
    *   **`generate_logo()`:** This PHP method takes the user's inputs (blog name, font color, background color, selected icon path, font choice) and constructs an SVG string. It embeds the chosen icon (with colors adjusted to `font_color`), places the blog name as text using the selected font and color, all against the specified background color. The layout aims for a standard logo format (icon on the left, text on the right).
    *   **`generate_square_svg()`:** Used for favicons, this method takes an icon path, font color, and background color to generate a simple square SVG with the chosen icon centered and colored, on the specified background.
*   **Previews:**
    *   The admin page displays real-time previews of both the logo and the favicon as the user adjusts the input parameters. These previews are updated via AJAX requests.
*   **Saving & Applying:**
    *   **`save_logo_as_attachment()`:** This method takes the generated SVG content (for either logo or favicon) and a title, then saves it as a new SVG file in the WordPress Media Library. It returns an array with the attachment ID, URL, and file path.
    *   **Set as Site Logo:** An option (checkbox, default checked) on the admin page allows the generated logo to be automatically set as the site's official logo using `set_theme_mod('custom_logo', $attachment_id)`.
    *   **Set as Site Favicon:** An option (checkbox, default checked) allows the generated favicon to be automatically set as the site icon using `update_option('site_icon', $attachment_id)`.
*   **AJAX Endpoints (handled by `AlvoBotPro_LogoGenerator`):**
    All AJAX actions are secured with a WordPress nonce (`logo_generator_nonce`).
    *   `wp_ajax_generate_logo`: Triggered by changes in input fields or the "Gerar Logo" button. Calls `generate_logo()` and returns the SVG string for preview or before saving.
    *   `wp_ajax_save_logo`: Triggered by submitting the main form. Calls `generate_logo()`, then `save_logo_as_attachment()`. If the "set as logo" option is checked, it updates the `custom_logo` theme mod. If the "set as favicon" option is checked, it also triggers the favicon generation and saving process.
    *   `wp_ajax_generate_favicon`: Triggered by relevant input changes for favicon preview. Calls `generate_square_svg()` and returns the SVG string.
    *   `wp_ajax_save_favicon`: Can be called (though `ajax_save_logo` often handles it) to save the favicon via `generate_square_svg()` and `save_logo_as_attachment()`, then updates the `site_icon` option.
*   **SVG Uploads:** The module enables the upload of SVG files to the WordPress Media Library by adding `'svg' => 'image/svg+xml'` to the list of allowed MIME types via the `upload_mimes` filter. It also includes a fix for SVG display in the media library (`fix_svg_display`).

#### REST API Endpoint (`AlvoBotPro_LogoGenerator_API` class)

The module exposes a REST API endpoint for remote logo and favicon generation.

*   **Endpoint:** `POST /wp-json/alvobot-pro/v1/logos`
*   **Authentication:**
    *   Requires a `token` parameter in the JSON body of the request.
    *   This token is validated against the `grp_site_token` stored in WordPress options. An invalid or missing token results in a `401 Unauthorized` error.
*   **Parameters (JSON body):**
    *   `token` (string, required): The site's authentication token.
    *   `blog_name` (string, required): The text to be used for the logo.
    *   `font_color` (string, optional): Hex color for font and icon. Default: `#000000`.
    *   `background_color` (string, optional): Hex color for the background. Default: `#FFFFFF`.
    *   `font_choice` (string, optional): Font identifier (e.g., 'montserrat'). Default: 'montserrat'.
    *   `icon_choice` (string, optional): The filename of the icon (e.g., `arrow-left.svg`). If not provided, invalid, or not found in `assets/icons/`, a random icon from the available set is chosen.
    *   `save_to_media` (boolean, optional): If true, saves the generated logo to the Media Library. Default: `false`.
    *   `apply_to_site` (boolean, optional): If true, saves the logo to Media Library (if not already implied by `save_to_media`) and sets it as the site logo. Default: `false`.
    *   `generate_favicon` (boolean, optional): If true, generates a favicon using the same icon and colors, saves it to Media Library, and applies it as the site icon. Default: `false`.
*   **Response:** A JSON object containing:
    *   `success` (boolean): Indicates if the operation was successful.
    *   `logo_svg` (string): The generated SVG content for the logo.
    *   `media` (object, optional): If saved, contains `id` and `url` of the logo in Media Library.
    *   `favicon` (object, optional): If generated, contains `id` and `url` of the favicon.
    *   `message` (string): A success or error message.
    *   `favicon_error` (string, optional): If favicon generation failed.

#### Assets

*   **Icon SVGs:** A collection of SVG icons is stored in `includes/modules/logo-generator/assets/icons/`. These are used for generating the visual part of the logo.
*   **Admin CSS:** `includes/modules/logo-generator/assets/css/logo-generator.css` styles the admin interface of the module.
*   **Admin JavaScript:** `includes/modules/logo-generator/assets/js/logo-generator.js` handles the interactive elements of the admin page, including AJAX calls for previews and saving, color pickers, and dynamic updates.
*   **Google Fonts:** The module enqueues a selection of Google Fonts specifically for its admin page to be used in logo generation and font selection previews.

### 4. Pre-Article Module

The Pre-Article Module is designed to create intermediary "teaser" pages for WordPress posts. These pages are displayed to users before they access the full content of a post, typically used to present calls-to-action (CTAs), summaries, or advertisements.

#### Purpose of the Module

*   **Intermediary Content Pages:** Generates a unique "pre-article" page for each selected post.
*   **URL Structure:** These pre-article pages are accessible via a URL structure like `https://yourdomain.com/pre/{post_slug}/`, where `{post_slug}` is the slug of the original post.
*   **User Engagement & Monetization:** Aims to increase user engagement through CTAs or provide an additional space for monetization (e.g., ads) before the main content.

#### Key Functionalities & Configuration (`Alvobot_Pre_Article` class)

*   **Rewrite Rules & Template Loading:**
    *   The module registers custom WordPress rewrite rules via `add_rewrite_rule` to intercept URLs matching the `pre/([^/]+)/?$` pattern.
    *   When a URL matches this pattern, the module sets a query variable `alvobot_pre_article=1`.
    *   It then uses the `template_include` filter to load a custom PHP template located at `includes/modules/alvobot-pre-article/includes/templates/template-pre-article.php` for these pages. This template is responsible for displaying the pre-article content, including the post title, featured image, an excerpt, CTAs, and an optional ad block.

*   **Meta Box (Post Editor):**
    *   A meta box titled "Configuração do Pré-Artigo" is added to the post editor screen for 'post' post types.
    *   **Per-Post Options:**
        *   **Enable/Disable:** A checkbox (`_alvobot_use_custom`) allows users to enable or disable the pre-article page for the specific post.
        *   **Number of CTAs:** Users can specify the number of CTAs (`_alvobot_num_ctas`) to display for that post's pre-article page (1-10).
        *   **Custom CTAs:** For each CTA, users can set custom button text and hex color codes (`_alvobot_ctas` stores an array of this data). These per-post CTA settings override the global defaults.
        *   **Pre-Article URL Display:** The meta box shows the generated pre-article URL for the post.

*   **Global Settings Page:**
    *   **Access:**
        *   If AlvoBot Pro is active, these settings are integrated into the main AlvoBot Pro admin menu.
        *   If used as a standalone plugin (Free version context), it creates its own admin menu: "Alvobot" -> "Alvobot Pré-artigo" (`admin.php?page=alvobot-pre-artigo`).
    *   **Options Storage:** Global settings are stored in the `alvobot_pre_artigo_options` WordPress option.
    *   **Configurable Global Options:**
        *   **Default Number of CTAs:** (`num_ctas`) Sets the default quantity of CTA buttons to display if not overridden by per-post settings.
        *   **Default CTA Texts & Colors:** (`button_text_{n}`, `button_color_{n}`) Default text and color for each global CTA button. The module provides a list of pre-defined default texts like "Desejo Saber Mais Sobre o Assunto".
        *   **AdSense/HTML Content:** (`adsense_content`) Allows administrators to input HTML or AdSense code for an ad block that will be displayed on the pre-article template.
        *   **Footer Text:** (`footer_text`) Customizable text for the footer of the pre-article page. Supports the placeholder `{NOME DO SITE}` which is replaced with the site's actual name.

*   **CTA Logic:**
    *   If a post has custom CTA settings enabled and defined in its meta box, those CTAs (text and color) are used for its pre-article page.
    *   If custom CTAs are not enabled for a post, the pre-article page will use the global default CTAs defined in the module's settings page.

*   **Post List Integration:**
    *   The module adds a "Ver Pré-Artigo" (View Pre-Article) link to the actions available for each post in the WordPress posts list table (WP Admin -> Posts). This link opens the pre-article page in a new tab.

*   **AlvoBot Pro Version Integration:**
    *   If the AlvoBot Pro plugin is active, the Pre-Article module registers itself with the main Pro plugin using the `alvobot_pro_modules` filter. This typically integrates its settings page into the AlvoBot Pro dashboard.

#### Custom GitHub Updater (`Alvobot_Plugin_Updater` class)

*   The `alvobot-pre-article.php` file includes a built-in `Alvobot_Plugin_Updater` class. This class is designed to manage updates for the Pre-Article module by fetching new versions directly from the `alvobot/alvobot-pre-article` GitHub repository.
*   **Key Updater Features:**
    *   Checks the latest release tag on GitHub.
    *   Compares it with the currently installed version (`ALVOBOT_PRE_ARTICLE_VERSION`).
    *   If a new version is available, it displays an update notification in the WordPress plugins list.
    *   Provides plugin information (changelog, version, etc.) for the update pop-up by querying the GitHub API.
    *   Includes a "Verificar Atualizações" (Check for Updates) link in the plugin's action links, allowing for manual update checks.

#### REST API Endpoints

The main `README.md` for AlvoBot Pro lists the following REST API endpoints associated with the Pre-Article module.

*   `GET /wp-json/alvobot-pre-article/v1/pre-articles`
    *   **Purpose (as per main README):** Lists all URLs of the pre-articles.
    *   **Authentication (as per main README):** Requires authentication.
*   `GET /wp-json/alvobot-pre-article/v1/posts/{post_id}/ctas`
    *   **Purpose (as per main README):** Obtains CTAs for a specific post.
    *   **Authentication (as per main README):** Requires authentication.
    *   **Parameters (as per main README):** `post_id` (integer, obrigatório): ID of the post.

**Note on API Implementation and Namespace:**
*   The PHP files analyzed for this module documentation (`alvobot-pre-article.php` and `includes/class-alvobot-pre-article.php`) do not contain the direct implementation (route registration, callback functions) for these specific REST API endpoints. These are likely defined elsewhere within the broader AlvoBot Pro plugin or an auxiliary API-specific file for this module that was not part of this analysis.
*   **Recommendation for Consistency:** It is recommended that these endpoints, when fully implemented or reviewed, utilize the standardized AlvoBot Pro namespace `alvobot-pro/v1` instead of `alvobot-pre-article/v1` to maintain consistency across all plugin modules. For example: `GET /wp-json/alvobot-pro/v1/pre-articles`.

#### Assets

*   **Frontend CSS:**
    *   `includes/modules/alvobot-pre-article/assets/css/style.css`: Contains styles for the pre-article template page displayed to site visitors.
*   **Admin CSS & JS:**
    *   `includes/modules/alvobot-pre-article/assets/css/admin.css`: Styles for the meta box in the post editor and the global settings page.
    *   `includes/modules/alvobot-pre-article/assets/js/admin.js`: JavaScript for enhancing the admin interface, such as handling the dynamic addition/removal of CTA fields in the meta box and initializing WordPress color pickers.

### 5. Multi-Languages Module

The Multi-Languages Module integrates with the Polylang plugin to provide comprehensive multilingual content management capabilities. It allows for creating, updating, and managing translations for posts, pages, categories, and slugs, primarily through an extensive REST API.

#### Purpose of the Module

*   **Multilingual Content Management:** Enables the site to have content in multiple languages, targeting a broader audience.
*   **Translation Management:** Facilitates the creation and organization of translations for various content types, including posts, pages, categories, and slugs.
*   **API-Driven Operations:** Offers a rich set of REST API endpoints for programmatic control over translations, suitable for integration with external systems or advanced workflows.

**Crucial Dependency:** This module **requires the Polylang plugin** to be installed and active on the WordPress site. Most of its functionalities rely on Polylang's core features for language management and content translation relationships.

#### Key Functionalities & REST API Endpoints (`AlvoBotPro_MultiLanguages` class)

The module's operations are primarily exposed via the `alvobot-pro/v1` REST API namespace. Most write operations (POST, PUT, DELETE) require the user to have `edit_posts` capability.

*   **General Language & Translation Management:**
    *   `GET /wp-json/alvobot-pro/v1/languages`: Lists all languages configured in Polylang, including their codes, names, locales, and default status.
    *   `GET /wp-json/alvobot-pro/v1/language-url?post_id=<ID>&language_code=<lc>`: Retrieves the permalink of a specific post (`post_id`) in the specified language (`language_code`).
    *   `GET /wp-json/alvobot-pro/v1/translation-stats`: Provides statistics about the translated content on the site, broken down by post types and taxonomies for each configured language.

*   **Post Translations:**
    *   `POST /wp-json/alvobot-pro/v1/translate`: Creates a new translation for an existing post.
        *   **Key Parameters:** `post_id` (ID of the original post), `language_code` (target language), `title`, `content`, `excerpt` (optional), `slug` (optional), `date` (optional), `categories` (array of category IDs for the translation), `featured_media` (ID of the featured image), `meta_input` (array of custom fields).
    *   `PUT /wp-json/alvobot-pro/v1/translate`: Updates an existing post translation. Parameters are similar to the POST request, targeting the translated post.
    *   `DELETE /wp-json/alvobot-pro/v1/translate`: Deletes a specific post translation. Requires `post_id` (original post) and `language_code` of the translation to be deleted. The original post cannot be deleted via this endpoint.
    *   `GET /wp-json/alvobot-pro/v1/translations`: Lists posts and their available translations. Supports pagination (`per_page`, `page`), filtering by `post_type`, and `hide_empty` (to exclude posts with no translations).
    *   `GET /wp-json/alvobot-pro/v1/translations/check?post_id=<ID>&language_code=<lc>`: Checks if a translation exists for a given post in a specific language. Returns `true` or `false` and the ID of the translated post if it exists.
    *   `GET /wp-json/alvobot-pro/v1/translations/missing`: Lists posts that are missing translations in one or more configured languages. Supports pagination and `post_type` filtering.
    *   `PUT /wp-json/alvobot-pro/v1/change-post-language`: **This endpoint allows changing the Polylang-assigned language of an existing post.**
        *   **Parameters:**
            *   `post_id` (integer, required): The ID of the post whose language needs to be changed.
            *   `language_code` (string, required): The new language code to assign to the post.
            *   `update_translations` (boolean, optional, default: `true`): If true, updates the Polylang translation group to reflect the change.
        *   **Purpose:** This is particularly useful for correcting the language assignment of a post or reorganizing translation relationships.

*   **Category Translations (Taxonomy: `category`):**
    *   `POST /wp-json/alvobot-pro/v1/translate/category`: Creates a new translation for a category.
        *   **Parameters:** `category_id` (original category ID), `language_code` (target language), `name` (translated name), `description` (optional), `slug` (optional).
    *   `PUT /wp-json/alvobot-pro/v1/translate/category`: Updates an existing category translation.
    *   `DELETE /wp-json/alvobot-pro/v1/translate/category`: Deletes a category translation.
    *   `GET /wp-json/alvobot-pro/v1/translations/categories`: Lists categories and their translations. Supports pagination.

*   **Slug Translations:**
    *   `GET /wp-json/alvobot-pro/v1/slugs`: Lists posts along with their original and translated slugs for each language. Supports pagination and `post_type` filtering.
    *   `POST /wp-json/alvobot-pro/v1/translate/slug`: Creates or updates the translated slug for a specific post in a given language.
        *   **Parameters:** `post_id`, `language_code`, `slug`.
    *   `DELETE /wp-json/alvobot-pro/v1/translate/slug`: Resets a translated slug for a post in a specific language, causing Polylang to regenerate it based on the translated title.

*   **Taxonomy Management (General):**
    *   `GET /wp-json/alvobot-pro/v1/taxonomies`: Lists all taxonomies that are configured as translatable in Polylang.
    *   `GET /wp-json/alvobot-pro/v1/taxonomy/terms?taxonomy=<tax_slug>`: Lists all terms for a given taxonomy, along with their translations. Supports pagination.
    *   `GET /wp-json/alvobot-pro/v1/taxonomy/untranslated?taxonomy=<tax_slug>`: Lists terms in a specified taxonomy that are missing translations in one or more languages. Supports pagination.

*   **Synchronization:**
    *   `POST /wp-json/alvobot-pro/v1/sync-translations`: Allows defining or updating the translation relationships for a group of posts.
        *   **Parameter:** `translations` (array): An associative array where keys are language codes and values are the corresponding post IDs that should belong to the same translation group (e.g., `{"en": 12, "es": 34}`).

*   **REST API Field Extension (`pll_post_translations`):**
    *   The module extends the WordPress REST API responses for posts and pages by adding a `pll_post_translations` field.
    *   This field directly shows an object with language codes as keys and corresponding translated post IDs as values (e.g., `{"en": 1, "es": 15, "pt": 22}`).
    *   It also allows updating these translation relationships by sending data to this field in `PUT` or `POST` requests to the standard post/page endpoints.

#### Admin Settings Page

*   **Access:** The module adds a settings page accessible via the AlvoBot Pro admin menu (AlvoBot Pro -> Multi Languages).
*   **Content:** The primary purpose of this page (template: `includes/modules/multi-languages/templates/multi-languages-settings.php`) is to check and inform the administrator if the Polylang plugin is active. If Polylang is not active, a notice is displayed, as the Multi-Languages module's functionality is dependent on it.
*   An API documentation sub-page (`alvobot-pro-multi-languages-api-docs`) is also registered, intended to display documentation for the module's REST API endpoints, rendered by `templates/multi-languages-api-docs.php`.

#### Logging

*   The module implements a logging mechanism to record its operations.
*   Actions such as creating, updating, or deleting translations, and synchronization events are logged with a timestamp, action type, status (success/error), a message, and relevant details.
*   Logs are stored in a WordPress option named `alvobot_multi_languages_logs`.
*   The log is limited to a maximum of `100` entries to prevent excessive database usage.

### 6. Essential Pages Module

The Essential Pages Module facilitates the creation and management of crucial legal and informational pages for the website, such as "Termos de Uso" (Terms of Use), "Política de Privacidade" (Privacy Policy), and "Contato" (Contact). It also includes a simple contact form functionality accessible via a shortcode.

#### Purpose of the Module

*   **Standardized Page Creation:** Quickly generate essential legal pages with pre-defined templates.
*   **Centralized Management:** Provides an admin interface to view the status of these pages and perform actions like creating, viewing, editing, or deleting them.
*   **Contact Form:** Offers a basic contact form via the `[alvobot_contact_form]` shortcode to allow site visitors to send messages to the site administrator.

#### Key Functionalities (`AlvoBotPro_EssentialPages` class)

*   **Page Management (Admin Interface):**
    *   **Access:** The module's settings and management interface is located under the main AlvoBot Pro admin menu (typically `alvobot-pro_page_alvobot-pro-essential-pages`).
    *   **Page Status:** The interface displays the current status (e.g., "Publicado" - Published, "Não Criado" - Not Created) for each of the essential pages defined:
        *   `terms`: Termos de Uso (Terms of Use)
        *   `privacy`: Política de Privacidade (Privacy Policy)
        *   `contact`: Contato (Contact)
    *   **Individual Page Actions:** For each page, the following actions are available depending on its status:
        *   **Create:** If the page doesn't exist, this action creates it using the corresponding template.
        *   **View:** Opens the published page on the frontend.
        *   **Edit:** Opens the WordPress page editor for that page.
        *   **Delete:** Deletes the page. A confirmation dialog is shown.
    *   **Global Actions:**
        *   **"Criar/Recriar Todas" (Create/Recreate All):** Creates any missing essential pages or recreates all of them (after confirmation). If recreating, existing essential pages with the defined slugs are deleted first.
        *   **"Excluir Todas" (Delete All):** Deletes all defined essential pages (after confirmation).
    *   **Security:** All actions within the admin interface are secured using WordPress nonces (`alvobot_essential_pages_nonce`).

*   **Page Content Generation:**
    *   **Templates:** Pages are generated using content from template files located in the `includes/modules/essential-pages/templates/` directory (e.g., `terms.php`, `privacy.php`). The `contact.php` template primarily contains the `[alvobot_contact_form]` shortcode.
    *   **Placeholder Replacement:** The content from these templates undergoes a processing step where placeholders are replaced with actual site and company data. This is handled by the `process_content()` method.
    *   **Key Placeholders:**
        *   **Site Information:** `[site_name]`, `[site_url]`, `[current_year]`, `[terms_date]` (current date for terms), `[privacy_date]` (current date for privacy policy).
        *   **Company Information:** Placeholders like `[company_name]`, `[company_legal_name]`, `[company_document]`, `[company_address]`, `[company_full_address]`, `[company_phone]`, `[contact_email]`, `[support_email]`, `[facebook_url]`, `[instagram_url]`, etc.
    *   **Company Information Source:** The `get_company_info()` method provides the data for company-related placeholders. It uses default values (like site name for company name, admin email for contact email) and can be customized by populating the `alvobot_company_info` WordPress option. The UI for managing this global `alvobot_company_info` option is not part of this specific Essential Pages module but would typically reside in the main AlvoBot Pro settings.

*   **WordPress Integration:**
    *   **Privacy Policy Assignment:** When the "Política de Privacidade" page is created by this module, its ID is automatically set as the default WordPress privacy policy page using `update_option('wp_page_for_privacy', $page_id)`.
    *   **Terms of Use Option:** When the "Termos de Uso" page is created, its ID is saved to a custom WordPress option `wp_page_for_terms`.

*   **Contact Form Shortcode:**
    *   **Shortcode:** `[alvobot_contact_form]`
    *   **Functionality:** When this shortcode is placed on a page (e.g., the "Contato" page created by this module), it renders a contact form.
    *   **Form Fields:** Name (required), Email (required), Phone, Subject (required), Message (required).
    *   **Processing:** Upon submission, the form data is sanitized, and an email is sent to the site administrator's email address (obtained via `get_option('admin_email')`). Feedback messages (success or error) are displayed to the user.
    *   **Security:** The form submission is protected by a WordPress nonce (`contact_form_nonce`).
    *   **Styling:** Basic inline CSS is provided with the shortcode output for form layout and appearance.

#### REST API

No specific REST API endpoints for direct management or content generation of essential pages were identified within the analyzed files for this module. All management functionalities are handled through its WordPress admin settings page.

#### Assets

*   **Admin CSS:**
    *   `includes/modules/essential-pages/css/admin.css`: Provides styling for the module's settings page within the WordPress admin area.
*   **Contact Form Inline CSS:**
    *   The `[alvobot_contact_form]` shortcode includes inline `<style>` tags to provide basic styling for the contact form fields, labels, button, and feedback messages.

## Plugin Update Mechanism

AlvoBot Pro includes a custom mechanism to manage updates, ensuring users can receive the latest versions directly.

### Update Source

The plugin fetches new versions directly from a public GitHub repository using the `AlvoBotPro_Updater` class.

*   **Primary GitHub Repository:** `alvobot/alvobot-plugin-manager`
    *   The updater class queries the GitHub API (`https://api.github.com/repos/alvobot/alvobot-plugin-manager/releases/latest`) to find the latest release tag and download URL (zipball).
*   **`ALVOBOT_PLUGIN_UPDATE_URL` Constant:** The main plugin file (`alvobot-pro.php`) also defines a constant `ALVOBOT_PLUGIN_UPDATE_URL` (pointing to `https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/api_plugin`). However, the `AlvoBotPro_Updater` class, as analyzed, relies exclusively on the GitHub repository for fetching update information. The role of this Supabase URL in the update process is not evident from the updater class itself and might be part of a different update mechanism or a fallback not implemented in this specific class.

### Core Functionality (`AlvoBotPro_Updater` class)

The update process is integrated into the standard WordPress update interface:

*   **Automatic Update Checks:**
    *   The updater hooks into the `pre_set_site_transient_update_plugins` filter.
    *   During this check, it queries the specified GitHub repository for the latest release.
    *   If a newer version (tag) is found compared to the currently installed `ALVOBOT_PRO_VERSION`, it injects the update information into the WordPress transient. This makes the update appear on the "Plugins" page and the "Dashboard -> Updates" page like a regular WordPress.org plugin update.

*   **Plugin Information Display:**
    *   The updater uses the `plugins_api` filter to customize the "View version details" popup.
    *   When a user clicks this link, information is fetched from the GitHub repository's latest release, including the version number, author, last updated date, and the changelog (from the release body).

*   **Post-Update Handling:**
    *   After WordPress downloads and installs the update package (the zipball from GitHub), the `upgrader_post_install` hook is used.
    *   The `after_install` method in the updater class ensures the updated plugin files are correctly moved to the plugin's directory (`wp-content/plugins/alvobot-pro/`).
    *   If the plugin was active before the update, it is reactivated.
    *   Finally, it clears WordPress's plugin update caches to reflect the new version.

*   **Manual Update Check:**
    *   **"Verificar Atualizações" Link:** A "Verificar Atualizações" (Check for Updates) link is added to the AlvoBot Pro plugin's entry on the WordPress plugins page.
    *   **AJAX Handler:** Clicking this link triggers an AJAX request to the `wp_ajax_alvobotpro_manual_check_update` action.
        *   This action, handled by `handle_manual_check`, clears the `update_plugins` site transient and forces WordPress to check for updates again (which will include re-querying the GitHub repository via the `check_update` method).
        *   The page is then reloaded to show any new updates.

### Technical Notes

*   **Public Repository Access:** The update mechanism fetches release information and download URLs from a public GitHub repository. It does not use any authentication tokens for this process.
*   **Version Comparison:** The currently installed plugin version, defined by the `ALVOBOT_PRO_VERSION` constant in `alvobot-pro.php`, is compared against the version tag of the latest release on GitHub to determine if an update is available.

## Assets Structure

The AlvoBot Pro plugin organizes its static assets primarily within a main `assets/` directory at the plugin root, as well as within individual modules.

*   **Main `assets/` Directory:**
    *   `assets/css/`: Contains general CSS files for the plugin's administration interface, such as `alvobot-pro-admin.css`.
    *   `assets/js/`: Contains JavaScript files for general admin functionalities, like `alvobot-pro-admin.js` and `token-visibility.js`.
    *   `assets/images/`: Stores images used across the plugin, such as `icon-alvobot-app.svg`.
    *   `assets/templates/`: Includes PHP template files for parts of the admin UI, like `dashboard.php`.

*   **Module-Specific Assets:**
    Many individual modules (e.g., Author Box, Logo Generator, Pre-Article, Essential Pages) also contain their own `assets/` subdirectories. These typically include:
    *   Module-specific CSS files for styling their frontend output or admin settings pages (e.g., `author-box/css/public.css`, `logo-generator/assets/css/logo-generator.css`).
    *   Module-specific JavaScript files for frontend interactivity or admin page enhancements (e.g., `author-box/js/custom-avatar.js`, `logo-generator/assets/js/logo-generator.js`).
    *   Sometimes, images or icon sets specific to a module's functionality (e.g., the SVG icons in `logo-generator/assets/icons/`).

These assets are used to style the plugin's backend and frontend components, provide JavaScript-driven interactivity, and supply necessary images and icons for various features.

## Internationalization

AlvoBot Pro is prepared for translation into multiple languages, following WordPress localization standards.

*   **`languages/` Directory:** Located at the plugin root, this directory contains the necessary files for translation.
    *   **`alvobot-pro.pot`:** (Assumed standard name) This is the main Portable Object Template (POT) file, which serves as the master template for creating new translations. It includes all translatable strings from the plugin.
    *   **`.po` and `.mo` Files:** The directory contains translation files for specific locales, such as:
        *   `alvobot-pro-pt_BR.po`: The Portable Object (PO) file for Brazilian Portuguese, which is human-readable and used by translators.
        *   `alvobot-pro-pt_BR.mo`: The Machine Object (MO) file for Brazilian Portuguese, which is compiled from the `.po` file and used by WordPress to load translations at runtime.
        *   The presence of `alvobot-pro-pt_BR.l10n.php` suggests usage of PHP-based localization files, potentially for performance or specific contexts.

*   **Text Domains:**
    *   The primary text domain used throughout the plugin is `alvobot-pro`.
    *   Some modules, particularly those that can also function more independently or were developed as separate entities (like the Pre-Article module), might use their own text domain (e.g., `alvobot-pre-artigo`).
*   **Localization Functions:** The plugin's code uses standard WordPress localization functions such as `__()`, `_e()`, `esc_html__()`, `sprintf()`, etc., to ensure that text strings are translatable.

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
- Site oficial: [AlvoBot](https://alvobot.com)
- Email: suporte@alvobot.com
- Documentação: [Docs](https://docs.alvobot.com)

## Licença

Este software é proprietário e seu uso é restrito aos termos de licença do AlvoBot Pro.

## Changelog

### Versão 1.4.0
- Adicionado suporte a internacionalização
- Melhorias na documentação
- Correções de bugs