<?php
/**
 * Classe para gerenciar traduções de CTAs do módulo Pre Article
 *
 * @package Alvobot_Pre_Article
 */

declare(strict_types=1);

if (!class_exists('Alvobot_PreArticle_CTA_Translations')) {

    class Alvobot_PreArticle_CTA_Translations {
        
        /**
         * Traduções das CTAs para múltiplos idiomas
         * Baseado nas top 15 línguas de países com maiores PIBs e mais de 5 milhões de falantes
         */
        private static $cta_translations = [
            // Português (Brasil/Portugal)
            'pt' => [
                'Desejo Saber Mais Sobre o Assunto',
                'Desbloquear o Conteúdo Agora', 
                'Quero Ler o Artigo Completo!',
                'Continuar Lendo Este Conteúdo',
                'Ver o Artigo na Íntegra',
                'Acessar o Conteúdo Completo',
                'Não Quero Perder o Resto',
                'Mostrar o Artigo Inteiro',
                'Ler Mais Sobre Este Tema',
                'Explorar o Assunto Completo'
            ],
            // Inglês
            'en' => [
                'I Want to Learn More About This',
                'Unlock the Content Now',
                'I Want to Read the Complete Article!',
                'Continue Reading This Content',
                'View the Full Article',
                'Access the Complete Content',
                'I Don\'t Want to Miss the Rest',
                'Show the Entire Article',
                'Read More About This Topic',
                'Explore the Complete Subject'
            ],
            // Espanhol
            'es' => [
                'Quiero Saber Más Sobre Este Tema',
                'Desbloquear el Contenido Ahora',
                '¡Quiero Leer el Artículo Completo!',
                'Seguir Leyendo Este Contenido',
                'Ver el Artículo Completo',
                'Acceder al Contenido Completo',
                'No Quiero Perderme el Resto',
                'Mostrar el Artículo Entero',
                'Leer Más Sobre Este Tema',
                'Explorar el Tema Completo'
            ],
            // Italiano
            'it' => [
                'Voglio Sapere Di Più Su Questo',
                'Sblocca il Contenuto Ora',
                'Voglio Leggere l\'Articolo Completo!',
                'Continua a Leggere Questo Contenuto',
                'Visualizza l\'Articolo Completo',
                'Accedi al Contenuto Completo',
                'Non Voglio Perdere il Resto',
                'Mostra l\'Articolo Intero',
                'Leggi di Più su Questo Argomento',
                'Esplora l\'Argomento Completo'
            ],
            // Japonês
            'ja' => [
                'この件についてもっと知りたい',
                'コンテンツを今すぐ解除',
                '完全な記事を読みたい！',
                'このコンテンツを読み続ける',
                '記事全体を見る',
                '完全なコンテンツにアクセス',
                '残りを見逃したくない',
                '記事全体を表示',
                'このトピックについてもっと読む',
                '完全な主題を探る'
            ],
            // Alemão  
            'de' => [
                'Ich möchte mehr darüber erfahren',
                'Inhalte jetzt freischalten',
                'Ich möchte den vollständigen Artikel lesen!',
                'Diesen Inhalt weiterlesen',
                'Den vollständigen Artikel anzeigen',
                'Auf den vollständigen Inhalt zugreifen',
                'Ich möchte den Rest nicht verpassen',
                'Den ganzen Artikel zeigen',
                'Mehr über dieses Thema lesen',
                'Das vollständige Thema erkunden'
            ],
            // Francês
            'fr' => [
                'Je veux en savoir plus à ce sujet',
                'Débloquer le contenu maintenant',
                'Je veux lire l\'article complet !',
                'Continuer à lire ce contenu',
                'Voir l\'article complet',
                'Accéder au contenu complet',
                'Je ne veux pas manquer le reste',
                'Afficher l\'article entier',
                'Lire plus sur ce sujet',
                'Explorer le sujet complet'
            ],
            // Chinês (Simplificado)
            'zh' => [
                '我想了解更多关于这个的信息',
                '现在解锁内容',
                '我想阅读完整的文章！',
                '继续阅读此内容',
                '查看完整文章',
                '访问完整内容',
                '我不想错过其余部分',
                '显示整篇文章',
                '阅读更多关于此主题',
                '探索完整主题'
            ],
            // Hindi
            'hi' => [
                'मैं इस बारे में और जानना चाहता हूं',
                'अभी सामग्री अनलॉक करें',
                'मैं पूरा लेख पढ़ना चाहता हूं!',
                'यह सामग्री पढ़ना जारी रखें',
                'पूरा लेख देखें',
                'पूरी सामग्री तक पहुंचें',
                'मैं बाकी को मिस नहीं करना चाहता',
                'पूरा लेख दिखाएं',
                'इस विषय के बारे में और पढ़ें',
                'पूरे विषय का अन्वेषण करें'
            ],
            // Árabe
            'ar' => [
                'أريد أن أعرف المزيد عن هذا',
                'إلغاء قفل المحتوى الآن',
                'أريد قراءة المقال كاملاً!',
                'متابعة قراءة هذا المحتوى',
                'عرض المقال الكامل',
                'الوصول إلى المحتوى الكامل',
                'لا أريد تفويت الباقي',
                'إظهار المقال كاملاً',
                'قراءة المزيد عن هذا الموضوع',
                'استكشاف الموضوع الكامل'
            ],
            // Russo
            'ru' => [
                'Я хочу узнать больше об этом',
                'Разблокировать контент сейчас',
                'Я хочу прочитать полную статью!',
                'Продолжить чтение этого контента',
                'Посмотреть полную статью',
                'Получить доступ к полному контенту',
                'Я не хочу пропустить остальное',
                'Показать всю статью',
                'Читать больше на эту тему',
                'Изучить полную тему'
            ],
            // Coreano
            'ko' => [
                '이것에 대해 더 알고 싶습니다',
                '지금 콘텐츠 잠금 해제',
                '전체 기사를 읽고 싶습니다!',
                '이 콘텐츠를 계속 읽기',
                '전체 기사 보기',
                '완전한 콘텐츠에 액세스',
                '나머지를 놓치고 싶지 않습니다',
                '전체 기사 표시',
                '이 주제에 대해 더 읽기',
                '완전한 주제 탐구'
            ],
            // Turco
            'tr' => [
                'Bu konuda daha fazla öğrenmek istiyorum',
                'İçeriği şimdi kilidini aç',
                'Tam makaleyi okumak istiyorum!',
                'Bu içeriği okumaya devam et',
                'Tam makaleyi görüntüle',
                'Tam içeriğe erişim',
                'Geri kalanını kaçırmak istemiyorum',
                'Tüm makaleyi göster',
                'Bu konu hakkında daha fazla oku',
                'Tam konuyu keşfet'
            ],
            // Indonésio
            'id' => [
                'Saya ingin tahu lebih banyak tentang ini',
                'Buka kunci konten sekarang',
                'Saya ingin membaca artikel lengkap!',
                'Lanjutkan membaca konten ini',
                'Lihat artikel lengkap',
                'Akses konten lengkap',
                'Saya tidak ingin melewatkan sisanya',
                'Tampilkan seluruh artikel',
                'Baca lebih lanjut tentang topik ini',
                'Jelajahi topik lengkap'
            ],
            // Holandês
            'nl' => [
                'Ik wil meer weten over dit onderwerp',
                'Ontgrendel de inhoud nu',
                'Ik wil het volledige artikel lezen!',
                'Doorgaan met het lezen van deze inhoud',
                'Het volledige artikel bekijken',
                'Toegang tot de volledige inhoud',
                'Ik wil de rest niet missen',
                'Het hele artikel tonen',
                'Meer lezen over dit onderwerp',
                'Het volledige onderwerp verkennen'
            ],
            // Tailandês
            'th' => [
                'ฉันต้องการทราบข้อมูลเพิ่มเติมเกี่ยวกับเรื่องนี้',
                'ปลดล็อกเนื้อหาตอนนี้',
                'ฉันต้องการอ่านบทความฉบับเต็ม!',
                'อ่านเนื้อหานี้ต่อ',
                'ดูบทความฉบับเต็ม',
                'เข้าถึงเนื้อหาฉบับสมบูรณ์',
                'ฉันไม่อยากพลาดส่วนที่เหลือ',
                'แสดงบทความทั้งหมด',
                'อ่านเพิ่มเติมเกี่ยวกับหัวข้อนี้',
                'สำรวจหัวข้อฉบับสมบูรณ์'
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
            'ja' => '日本語',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'zh' => '中文',
            'hi' => 'हिन्दी',
            'ar' => 'العربية',
            'ru' => 'Русский',
            'ko' => '한국어',
            'tr' => 'Türkçe',
            'id' => 'Bahasa Indonesia',
            'nl' => 'Nederlands',
            'th' => 'ไทย'
        ];

        /**
         * Detecta o idioma atual do site
         */
        public static function get_current_language() {
            // Verifica se o Polylang oficial está ativo (evita conflito com AutoPoly)
            if (function_exists('pll_current_language') && !class_exists('Automatic_Polylang')) {
                $lang = pll_current_language();
                if ($lang) {
                    return $lang;
                }
            }

            // Fallback: verifica WPML
            if (defined('ICL_LANGUAGE_CODE')) {
                return ICL_LANGUAGE_CODE;
            }

            // Fallback: idioma do WordPress
            $locale = get_locale();
            $lang_code = substr($locale, 0, 2);
            
            // Verifica se temos traduções para este idioma
            if (isset(self::$cta_translations[$lang_code])) {
                return $lang_code;
            }

            // Fallback final: português
            return 'pt';
        }

        /**
         * Obtém as CTAs traduzidas para o idioma atual
         */
        public static function get_translated_ctas($language = null) {
            if (!$language) {
                $language = self::get_current_language();
            }

            // Verifica se existe tradução para o idioma
            if (isset(self::$cta_translations[$language])) {
                return self::$cta_translations[$language];
            }

            // Fallback: português
            return self::$cta_translations['pt'];
        }

        /**
         * Obtém um texto de CTA traduzido por índice
         */
        public static function get_translated_cta_by_index($index, $language = null) {
            $ctas = self::get_translated_ctas($language);
            
            // Garante que o índice existe
            if (isset($ctas[$index])) {
                return $ctas[$index];
            }

            // Fallback: primeiro CTA disponível
            return isset($ctas[0]) ? $ctas[0] : 'Ler Mais';
        }

        /**
         * Obtém todas as traduções disponíveis
         */
        public static function get_all_translations() {
            return self::$cta_translations;
        }

        /**
         * Obtém os idiomas suportados
         */
        public static function get_supported_languages() {
            return array_keys(self::$cta_translations);
        }

        /**
         * Obtém o nome nativo do idioma
         */
        public static function get_language_native_name($lang_code) {
            return isset(self::$language_names[$lang_code]) ? self::$language_names[$lang_code] : $lang_code;
        }

        /**
         * Traduz automaticamente um texto de CTA padrão se correspondência for encontrada
         */
        public static function translate_default_cta($cta_text, $target_language = null) {
            if (!$target_language) {
                $target_language = self::get_current_language();
            }

            // Se já está no idioma de destino, retorna como está
            if ($target_language === 'pt') {
                return $cta_text;
            }

            $default_ctas_pt = self::$cta_translations['pt'];
            $target_ctas = self::get_translated_ctas($target_language);

            // Procura o índice do texto em português
            $index = array_search($cta_text, $default_ctas_pt);
            
            if ($index !== false && isset($target_ctas[$index])) {
                return $target_ctas[$index];
            }

            // Se não encontrou correspondência exata, retorna o texto original
            return $cta_text;
        }

        /**
         * Verifica se um idioma é suportado
         */
        public static function is_language_supported($lang_code) {
            return isset(self::$cta_translations[$lang_code]);
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
                'polylang_active' => function_exists('pll_current_language') && !class_exists('Automatic_Polylang'),
                'autopoly_detected' => class_exists('Automatic_Polylang'),
                'wpml_active' => defined('ICL_LANGUAGE_CODE'),
                'site_locale' => get_locale(),
                'pll_function_exists' => function_exists('pll_current_language'),
                'PLL_function_exists' => function_exists('PLL')
            ];
        }
    }
}