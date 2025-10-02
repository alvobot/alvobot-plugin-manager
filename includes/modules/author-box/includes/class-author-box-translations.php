<?php
/**
 * Classe para gerenciar traduções do módulo Author Box
 *
 * @package Alvobot_Author_Box
 */

declare(strict_types=1);

if (!class_exists('Alvobot_AuthorBox_Translations')) {

    class Alvobot_AuthorBox_Translations {

        /**
         * Traduções dos textos do Author Box para múltiplos idiomas
         */
        private static $translations = [
            // Português (Brasil/Portugal)
            'pt' => [
                'about_author' => 'Sobre o Autor',
                'display_on_posts' => 'Exibir em Posts',
                'display_on_pages' => 'Exibir em Páginas',
                'show_description' => 'Exibir a biografia do autor',
                'select_display_location' => 'Selecione onde o Author Box será exibido.',
                'title_description' => 'Título que será exibido acima do Author Box.',
                'author_box_settings' => 'Configurações do Author Box',
                'configure_options' => 'Configure as opções do Author Box abaixo.',
                'display_settings' => 'Configure as opções de exibição do Author Box em seus posts e páginas.',
                'avatar_settings' => 'Configurações do Avatar',
                'custom_avatar' => 'Avatar Personalizado',
                'select_image' => 'Selecionar Imagem',
                'remove_image' => 'Remover Imagem',
                'avatar_description' => 'Esta imagem será usada no Author Box em vez do seu Gravatar.',
                'personalize_info' => 'Personalize como suas informações de autor aparecem no Author Box.',
                'live_preview' => 'Preview em Tempo Real',
                'preview_author_box' => 'Preview do Author Box',
                'save_changes' => 'Salvar Alterações',
                'settings' => 'Configurações',
                'title' => 'Título',
                'display' => 'Exibição',
                'biography' => 'Biografia'
            ],
            // Inglês
            'en' => [
                'about_author' => 'About the Author',
                'display_on_posts' => 'Display on Posts',
                'display_on_pages' => 'Display on Pages',
                'show_description' => 'Show author biography',
                'select_display_location' => 'Select where the Author Box will be displayed.',
                'title_description' => 'Title that will be displayed above the Author Box.',
                'author_box_settings' => 'Author Box Settings',
                'configure_options' => 'Configure the Author Box options below.',
                'display_settings' => 'Configure the Author Box display options for your posts and pages.',
                'avatar_settings' => 'Avatar Settings',
                'custom_avatar' => 'Custom Avatar',
                'select_image' => 'Select Image',
                'remove_image' => 'Remove Image',
                'avatar_description' => 'This image will be used in the Author Box instead of your Gravatar.',
                'personalize_info' => 'Customize how your author information appears in the Author Box.',
                'live_preview' => 'Live Preview',
                'preview_author_box' => 'Author Box Preview',
                'save_changes' => 'Save Changes',
                'settings' => 'Settings',
                'title' => 'Title',
                'display' => 'Display',
                'biography' => 'Biography'
            ],
            // Espanhol
            'es' => [
                'about_author' => 'Sobre el Autor',
                'display_on_posts' => 'Mostrar en Entradas',
                'display_on_pages' => 'Mostrar en Páginas',
                'show_description' => 'Mostrar la biografía del autor',
                'select_display_location' => 'Seleccione dónde se mostrará la Caja de Autor.',
                'title_description' => 'Título que se mostrará encima de la Caja de Autor.',
                'author_box_settings' => 'Configuración de la Caja de Autor',
                'configure_options' => 'Configure las opciones de la Caja de Autor a continuación.',
                'display_settings' => 'Configure las opciones de visualización de la Caja de Autor en sus entradas y páginas.',
                'avatar_settings' => 'Configuración del Avatar',
                'custom_avatar' => 'Avatar Personalizado',
                'select_image' => 'Seleccionar Imagen',
                'remove_image' => 'Eliminar Imagen',
                'avatar_description' => 'Esta imagen se utilizará en la Caja de Autor en lugar de su Gravatar.',
                'personalize_info' => 'Personalice cómo aparece su información de autor en la Caja de Autor.',
                'live_preview' => 'Vista Previa en Vivo',
                'preview_author_box' => 'Vista Previa de la Caja de Autor',
                'save_changes' => 'Guardar Cambios',
                'settings' => 'Configuración',
                'title' => 'Título',
                'display' => 'Mostrar',
                'biography' => 'Biografía'
            ],
            // Italiano
            'it' => [
                'about_author' => 'Informazioni sull\'Autore',
                'display_on_posts' => 'Mostra sui Post',
                'display_on_pages' => 'Mostra sulle Pagine',
                'show_description' => 'Mostra la biografia dell\'autore',
                'select_display_location' => 'Seleziona dove verrà visualizzata la Box Autore.',
                'title_description' => 'Titolo che verrà visualizzato sopra la Box Autore.',
                'author_box_settings' => 'Impostazioni Box Autore',
                'configure_options' => 'Configura le opzioni della Box Autore qui sotto.',
                'display_settings' => 'Configura le opzioni di visualizzazione della Box Autore sui tuoi post e pagine.',
                'avatar_settings' => 'Impostazioni Avatar',
                'custom_avatar' => 'Avatar Personalizzato',
                'select_image' => 'Seleziona Immagine',
                'remove_image' => 'Rimuovi Immagine',
                'avatar_description' => 'Questa immagine verrà utilizzata nella Box Autore al posto del tuo Gravatar.',
                'personalize_info' => 'Personalizza come appaiono le tue informazioni di autore nella Box Autore.',
                'live_preview' => 'Anteprima Live',
                'preview_author_box' => 'Anteprima Box Autore',
                'save_changes' => 'Salva Modifiche',
                'settings' => 'Impostazioni',
                'title' => 'Titolo',
                'display' => 'Visualizzazione',
                'biography' => 'Biografia'
            ],
            // Francês
            'fr' => [
                'about_author' => 'À propos de l\'Auteur',
                'display_on_posts' => 'Afficher sur les Articles',
                'display_on_pages' => 'Afficher sur les Pages',
                'show_description' => 'Afficher la biographie de l\'auteur',
                'select_display_location' => 'Sélectionnez où la Boîte Auteur sera affichée.',
                'title_description' => 'Titre qui sera affiché au-dessus de la Boîte Auteur.',
                'author_box_settings' => 'Paramètres de la Boîte Auteur',
                'configure_options' => 'Configurez les options de la Boîte Auteur ci-dessous.',
                'display_settings' => 'Configurez les options d\'affichage de la Boîte Auteur sur vos articles et pages.',
                'avatar_settings' => 'Paramètres de l\'Avatar',
                'custom_avatar' => 'Avatar Personnalisé',
                'select_image' => 'Sélectionner une Image',
                'remove_image' => 'Supprimer l\'Image',
                'avatar_description' => 'Cette image sera utilisée dans la Boîte Auteur à la place de votre Gravatar.',
                'personalize_info' => 'Personnalisez l\'apparition de vos informations d\'auteur dans la Boîte Auteur.',
                'live_preview' => 'Aperçu en Direct',
                'preview_author_box' => 'Aperçu de la Boîte Auteur',
                'save_changes' => 'Enregistrer les Modifications',
                'settings' => 'Paramètres',
                'title' => 'Titre',
                'display' => 'Affichage',
                'biography' => 'Biographie'
            ],
            // Alemão
            'de' => [
                'about_author' => 'Über den Autor',
                'display_on_posts' => 'Auf Beiträgen anzeigen',
                'display_on_pages' => 'Auf Seiten anzeigen',
                'show_description' => 'Autor-Biografie anzeigen',
                'select_display_location' => 'Wählen Sie aus, wo die Autor-Box angezeigt werden soll.',
                'title_description' => 'Titel, der über der Autor-Box angezeigt wird.',
                'author_box_settings' => 'Autor-Box Einstellungen',
                'configure_options' => 'Konfigurieren Sie die Autor-Box Optionen unten.',
                'display_settings' => 'Konfigurieren Sie die Anzeigeoptionen der Autor-Box für Ihre Beiträge und Seiten.',
                'avatar_settings' => 'Avatar-Einstellungen',
                'custom_avatar' => 'Benutzerdefinierter Avatar',
                'select_image' => 'Bild auswählen',
                'remove_image' => 'Bild entfernen',
                'avatar_description' => 'Dieses Bild wird in der Autor-Box anstelle Ihres Gravatars verwendet.',
                'personalize_info' => 'Passen Sie an, wie Ihre Autorinformationen in der Autor-Box erscheinen.',
                'live_preview' => 'Live-Vorschau',
                'preview_author_box' => 'Autor-Box Vorschau',
                'save_changes' => 'Änderungen speichern',
                'settings' => 'Einstellungen',
                'title' => 'Titel',
                'display' => 'Anzeige',
                'biography' => 'Biografie'
            ]
        ];

        /**
         * Mapeia códigos de idioma para nomes nativos
         */
        private static $language_names = [
            'pt' => 'Português',
            'en' => 'English',
            'es' => 'Español',
            'it' => 'Italiano',
            'fr' => 'Français',
            'de' => 'Deutsch'
        ];

        /**
         * Detecta o idioma atual do site (usa a mesma lógica do Pre Article)
         */
        public static function get_current_language() {
            // Prioridade 1: Verifica idioma forçado por sessão
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $forced_lang = $_SESSION['alvobot_forced_language'] ?? null;
            if ($forced_lang && isset(self::$translations[$forced_lang])) {
                return $forced_lang;
            }

            // Prioridade 2: Verifica parâmetro forçado (para debug)
            if (isset($_GET['force_lang']) && isset(self::$translations[$_GET['force_lang']])) {
                return $_GET['force_lang'];
            }

            // Prioridade 3: Verifica URL para detectar idioma (padrão dominio.com/es/)
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('/^\/([a-z]{2})(\/|$)/', $request_uri, $matches)) {
                $url_lang = $matches[1];
                if (isset(self::$translations[$url_lang])) {
                    return $url_lang;
                }
            }

            // Prioridade 4: Verifica se o Polylang oficial está ativo
            if (function_exists('pll_current_language') && !class_exists('Automatic_Polylang')) {
                $lang = pll_current_language();
                if ($lang && isset(self::$translations[$lang])) {
                    return $lang;
                }
            }

            // Prioridade 5: Verifica WPML
            if (defined('ICL_LANGUAGE_CODE') && isset(self::$translations[ICL_LANGUAGE_CODE])) {
                return ICL_LANGUAGE_CODE;
            }

            // Prioridade 6: Idioma do WordPress baseado em locale
            $locale = get_locale();
            $lang_code = substr($locale, 0, 2);
            if (isset(self::$translations[$lang_code])) {
                return $lang_code;
            }

            // Prioridade 7: Detecta pelo domínio
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (preg_match('/\.([a-z]{2})$/', $host, $matches)) {
                $domain_lang = $matches[1];
                if (isset(self::$translations[$domain_lang])) {
                    return $domain_lang;
                }
            }

            // Prioridade 8: Detecta pelo Accept-Language do navegador
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $accept_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
                foreach ($accept_languages as $lang_str) {
                    $lang_code = substr(trim($lang_str), 0, 2);
                    if (isset(self::$translations[$lang_code])) {
                        return $lang_code;
                    }
                }
            }

            // Fallback final: português
            return 'pt';
        }

        /**
         * Obtém uma tradução específica
         */
        public static function get_translation($key, $language = null) {
            if (!$language) {
                $language = self::get_current_language();
            }

            // Verifica se existe tradução para o idioma e chave
            if (isset(self::$translations[$language][$key])) {
                return self::$translations[$language][$key];
            }

            // Fallback: português
            if (isset(self::$translations['pt'][$key])) {
                return self::$translations['pt'][$key];
            }

            // Fallback final: retorna a chave
            return $key;
        }

        /**
         * Obtém todas as traduções para um idioma
         */
        public static function get_all_translations($language = null) {
            if (!$language) {
                $language = self::get_current_language();
            }

            if (isset(self::$translations[$language])) {
                return self::$translations[$language];
            }

            // Fallback: português
            return self::$translations['pt'];
        }

        /**
         * Obtém os idiomas suportados
         */
        public static function get_supported_languages() {
            return array_keys(self::$translations);
        }

        /**
         * Obtém o nome nativo do idioma
         */
        public static function get_language_native_name($lang_code) {
            return isset(self::$language_names[$lang_code]) ? self::$language_names[$lang_code] : $lang_code;
        }

        /**
         * Verifica se um idioma é suportado
         */
        public static function is_language_supported($lang_code) {
            return isset(self::$translations[$lang_code]);
        }

        /**
         * Função de conveniência para traduzir - compatível com __()
         */
        public static function __($key, $language = null) {
            return self::get_translation($key, $language);
        }

        /**
         * Função de conveniência para traduzir e ecoar - compatível com _e()
         */
        public static function _e($key, $language = null) {
            echo self::get_translation($key, $language);
        }

        /**
         * Debug: retorna informações sobre o idioma detectado
         */
        public static function get_language_debug_info() {
            $current_lang = self::get_current_language();
            $supported_langs = self::get_supported_languages();

            return [
                'detected_language' => $current_lang,
                'language_name' => self::get_language_native_name($current_lang),
                'is_supported' => self::is_language_supported($current_lang),
                'supported_languages' => $supported_langs,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'http_host' => $_SERVER['HTTP_HOST'] ?? '',
                'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'site_locale' => get_locale(),
                'polylang_active' => function_exists('pll_current_language') && !class_exists('Automatic_Polylang'),
                'wpml_active' => defined('ICL_LANGUAGE_CODE')
            ];
        }
    }
}