<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_MultiLanguages_Autoloader')) {
    return;
}

/**
 * Autoloader para o módulo Multi Languages
 * 
 * Carrega automaticamente classes e interfaces conforme necessário
 */
class AlvoBotPro_MultiLanguages_Autoloader {
    
    /** @var string Diretório base do módulo */
    private static $base_dir;
    
    /** @var array Mapeamento de classes para arquivos */
    private static $class_map = array();
    
    /** @var bool Se o autoloader foi registrado */
    private static $registered = false;
    
    /**
     * Inicializa o autoloader
     */
    public static function init() {
        if (self::$registered) {
            return;
        }
        
        self::$base_dir = dirname(__FILE__);
        self::build_class_map();
        self::register();
        
    }
    
    /**
     * Registra o autoloader
     */
    private static function register() {
        spl_autoload_register(array(__CLASS__, 'load_class'));
        self::$registered = true;
    }
    
    /**
     * Constrói mapeamento de classes
     */
    private static function build_class_map() {
        self::$class_map = array(
            // Interface essencial
            'AlvoBotPro_Translation_Provider_Interface' => 'interfaces/interface-translation-provider.php',
            
            // Validators
            'AlvoBotPro_Language_Validator' => 'validators/class-language-validator.php',
            
            // Controllers
            'AlvoBotPro_MultiLanguages_Ajax_Controller' => 'controllers/class-ajax-controller.php',
            'AlvoBotPro_MultiLanguages_Admin_Controller' => 'controllers/class-admin-controller.php',
            
            // Services
            'AlvoBotPro_Translation_Service' => 'services/class-translation-service.php',
            'AlvoBotPro_Rest_Api_Service' => 'services/class-rest-api-service.php',
            
            // Core Classes
            'AlvoBotPro_Translation_Engine' => 'includes/class-translation-engine.php',
            'AlvoBotPro_Translation_Queue' => 'includes/class-translation-queue.php',
            'AlvoBotPro_OpenAI_Translation_Provider' => 'includes/class-translation-providers.php',
        );
    }
    
    /**
     * Carrega uma classe
     * 
     * @param string $class_name Nome da classe
     */
    public static function load_class($class_name) {
        // Verifica se é uma classe do nosso módulo
        if (strpos($class_name, 'AlvoBotPro_') !== 0) {
            return;
        }
        
        // Verifica se temos mapeamento para esta classe
        if (!isset(self::$class_map[$class_name])) {
            return;
        }
        
        $file_path = self::$base_dir . '/' . self::$class_map[$class_name];
        
        // Carrega o arquivo se existe
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
        }
    }
    
    /**
     * Adiciona uma classe ao mapeamento
     * 
     * @param string $class_name Nome da classe
     * @param string $file_path Caminho relativo do arquivo
     */
    public static function add_class($class_name, $file_path) {
        self::$class_map[$class_name] = $file_path;
    }
    
    /**
     * Remove uma classe do mapeamento
     * 
     * @param string $class_name Nome da classe
     */
    public static function remove_class($class_name) {
        if (isset(self::$class_map[$class_name])) {
            unset(self::$class_map[$class_name]);
        }
    }
    
    /**
     * Retorna todas as classes mapeadas
     * 
     * @return array Mapeamento de classes
     */
    public static function get_class_map() {
        return self::$class_map;
    }
    
    /**
     * Verifica se uma classe está mapeada
     * 
     * @param string $class_name Nome da classe
     * @return bool True se mapeada
     */
    public static function is_class_mapped($class_name) {
        return isset(self::$class_map[$class_name]);
    }
    
    /**
     * Carrega todas as interfaces primeiro
     */
    public static function load_interfaces() {
        $interfaces = array(
            'AlvoBotPro_Translation_Provider_Interface'
        );
        
        foreach ($interfaces as $interface) {
            if (self::is_class_mapped($interface)) {
                self::load_class($interface);
            }
        }
    }
    
    /**
     * Carrega todas as classes essenciais
     */
    public static function load_core_classes() {
        $core_classes = array(
            'AlvoBotPro_Language_Validator',
            'AlvoBotPro_MultiLanguages_Ajax_Controller',
            'AlvoBotPro_MultiLanguages_Admin_Controller',
            'AlvoBotPro_Translation_Service',
            'AlvoBotPro_Rest_Api_Service'
        );
        
        foreach ($core_classes as $class) {
            if (self::is_class_mapped($class)) {
                self::load_class($class);
            }
        }
    }
    
    /**
     * Valida integridade do mapeamento
     * 
     * @return array Array com arquivos faltantes
     */
    public static function validate_class_map() {
        $missing_files = array();
        
        foreach (self::$class_map as $class_name => $file_path) {
            $full_path = self::$base_dir . '/' . $file_path;
            
            if (!file_exists($full_path)) {
                $missing_files[] = array(
                    'class' => $class_name,
                    'file' => $file_path,
                    'full_path' => $full_path
                );
            }
        }
        
        if (!empty($missing_files)) {
            AlvoBotPro::debug_log('multi-languages', 'Arquivos faltantes no autoloader: ' . count($missing_files));
        }
        
        return $missing_files;
    }
    
    /**
     * Remove autoloader (cleanup)
     */
    public static function unregister() {
        if (self::$registered) {
            spl_autoload_unregister(array(__CLASS__, 'load_class'));
            self::$registered = false;
            AlvoBotPro::debug_log('multi-languages', 'Autoloader removido');
        }
    }
}