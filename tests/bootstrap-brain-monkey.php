<?php
/**
 * Brain Monkey bootstrap — no global WP function stubs.
 * Patchwork intercepts WP function calls dynamically.
 */

use Brain\Monkey;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Plugin constants only — Brain Monkey handles WP functions.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

$plugin_main = dirname(__DIR__) . '/alvobot-pro.php';
if (file_exists($plugin_main)) {
    $header = file_get_contents($plugin_main, false, null, 0, 1024);
    if (preg_match("/define\s*\(\s*['\"]ALVOBOT_PRO_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $header, $m)) {
        define('ALVOBOT_PRO_VERSION', $m[1]);
    }
}
if (!defined('ALVOBOT_PRO_VERSION')) {
    define('ALVOBOT_PRO_VERSION', '2.10.6');
}
define('ALVOBOT_PRO_PLUGIN_FILE', dirname(__DIR__) . '/alvobot-pro.php');
define('ALVOBOT_PRO_PLUGIN_DIR', dirname(__DIR__) . '/');
define('ALVOBOT_PRO_PLUGIN_URL', 'http://localhost/wp-content/plugins/alvobot-plugin-manager/');

// Minimal WP_Error stub — the real class is not available without a full WP install.
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;

        public function __construct(string $code = '', string $message = '', $data = '')
        {
            $this->code    = $code;
            $this->message = $message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

echo "Brain Monkey Test Suite — AlvoBot Pro\n";
echo "======================================\n\n";
