<?php
/**
 * Multi-language Helper Class for Software Manager Plugin
 *
 * Provides translation functionality with lazy loading and fallback support.
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 * @package GLPI\Plugin\Softwaremanager
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Plugin Language Helper Class
 *
 * Provides translation functionality with support for multiple languages.
 * Translations are loaded on-demand and cached for performance.
 */
class PluginSoftwaremanagerLang {

    /** @var bool Whether translations have been loaded */
    private static $loaded = false;

    /** @var string Current language code */
    private static $current_lang = '';

    /** @var array Cached translations */
    private static $translations = [];

    /** @var array Default fallback translations (English) */
    private static $defaults = [
        'menu.softwarelist' => 'Software Inventory',
        'menu.scanhistory' => 'Scan History',
        'menu.whitelist' => 'Whitelist Management',
        'menu.blacklist' => 'Blacklist Management',
        'menu.import' => 'Import/Export',
        'menu.config' => 'Plugin Configuration',
        'menu.analytics' => 'Analytics Reports',
        'config.tab_cron' => 'Automated Actions',
        'config.tab_targets' => 'Report Targets',
        'config.tab_help' => 'Help',
        'scan.title' => 'Software Compliance Scan',
        'common.total' => 'Total',
        'common.approved' => 'Approved',
        'common.blacklisted' => 'Blacklisted',
        'common.unmanaged' => 'Unmanaged',
        'common.save' => 'Save',
        'common.cancel' => 'Cancel',
        'common.delete' => 'Delete',
        'error.not_found' => 'Record not found',
        'error.no_permission' => 'Permission denied',
        'success.saved' => 'Saved successfully',
    ];

    /**
     * Load translations for the current language
     *
     * @return void
     */
    public static function load(): void {
        if (self::$loaded) {
            return;
        }

        // Determine user language
        self::$current_lang = self::detectLanguage();

        // Load translation file if exists
        $lang_file = __DIR__ . '/../locales/' . self::$current_lang . '.php';
        if (file_exists($lang_file)) {
            include $lang_file;
            if (isset($GLOBALS['LANG']['softwaremanager'])) {
                self::$translations = $GLOBALS['LANG']['softwaremanager'];
            }
        }

        self::$loaded = true;
    }

    /**
     * Detect the current language from session or configuration
     *
     * @return string Language code (e.g., 'zh_CN', 'en_GB')
     */
    private static function detectLanguage(): string {
        // Try session first
        if (isset($_SESSION['glpilanguage'])) {
            $lang = $_SESSION['glpilanguage'];
            // Map common language codes
            $lang_map = [
                'zh_CN' => 'zh_CN',
                'zh_TW' => 'zh_CN',
                'en_GB' => 'en_GB',
                'en_US' => 'en_GB',
            ];
            return $lang_map[$lang] ?? 'en_GB';
        }

        // Fallback to browser language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if ($browser_lang === 'zh') {
                return 'zh_CN';
            }
        }

        // Default to English
        return 'en_GB';
    }

    /**
     * Get a translation string by key
     *
     * Supports dot notation for nested keys (e.g., 'menu.softwarelist')
     *
     * @param string $key Translation key (supports dot notation)
     * @param string $default Default value if key not found
     * @return string Translated string or default
     */
    public static function get(string $key, string $default = ''): string {
        self::load();

        // Convert dot notation to array access
        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Check defaults
                $value = self::$defaults;
                foreach ($keys as $dk) {
                    if (isset($value[$dk])) {
                        $value = $value[$dk];
                    } else {
                        return $default ?: $key;
                    }
                }
                return is_string($value) ? $value : ($default ?: $key);
            }
        }

        return is_string($value) ? $value : ($default ?: $key);
    }

    /**
     * Translate a string (alias for get)
     *
     * @param string $key Translation key
     * @param string $default Default value if key not found
     * @return string Translated string
     */
    public static function trans(string $key, string $default = ''): string {
        return self::get($key, $default);
    }

    /**
     * Translate with printf-style placeholders
     *
     * @param string $key Translation key
     * @param array $params Parameters to substitute
     * @param string $default Default value if key not found
     * @return string Translated and formatted string
     */
    public static function transFormatted(string $key, array $params = [], string $default = ''): string {
        $template = self::get($key, $default);
        if (empty($params)) {
            return $template;
        }
        return vsprintf($template, $params);
    }

    /**
     * Get the current language code
     *
     * @return string Current language code
     */
    public static function getCurrentLanguage(): string {
        self::load();
        return self::$current_lang;
    }

    /**
     * Check if a translation key exists
     *
     * @param string $key Translation key
     * @return bool True if key exists
     */
    public static function has(string $key): bool {
        self::load();
        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Set a translation value at runtime (for testing/overrides)
     *
     * @param string $key Translation key
     * @param string $value Translation value
     * @return void
     */
    public static function set(string $key, string $value): void {
        self::load();
        $keys = explode('.', $key);
        $current = &self::$translations;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    /**
     * Get all available translation keys
     *
     * @return array All translation keys
     */
    public static function getKeys(): array {
        self::load();
        return array_keys(self::flattenArray(self::$translations));
    }

    /**
     * Flatten nested array with dot notation
     *
     * @param array $array Nested array
     * @param string $prefix Key prefix
     * @return array Flattened array
     */
    private static function flattenArray(array $array, string $prefix = ''): array {
        $result = [];
        foreach ($array as $key => $value) {
            $new_key = $prefix ? $prefix . '.' . $key : $key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        return $result;
    }
}

/**
 * Convenience function for quick translation
 *
 * @param string $key Translation key
 * @param string $default Default value
 * @return string Translated string
 */
function __sm(string $key, string $default = ''): string {
    return PluginSoftwaremanagerLang::trans($key, $default);
}

/**
 * Convenience function for formatted translation
 *
 * @param string $key Translation key
 * @param array $params Parameters
 * @param string $default Default value
 * @return string Translated and formatted string
 */
function __smf(string $key, array $params = [], string $default = ''): string {
    return PluginSoftwaremanagerLang::transFormatted($key, $params, $default);
}
