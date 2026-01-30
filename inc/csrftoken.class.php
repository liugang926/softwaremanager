<?php
/**
 * CSRF Token Helper for GLPI 11.x
 * Provides compatible CSRF token generation and validation
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginSoftwaremanagerCSRF {

    /**
     * Validate CSRF token for AJAX requests
     * GLPI 11.x compatible - doesn't throw exception
     *
     * @param array $data POST data containing _glpi_csrf_token
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($data) {
        if (!isset($data['_glpi_csrf_token'])) {
            return false;
        }

        $token = $data['_glpi_csrf_token'];

        // GLPI 11.x uses glpicsrftokens session key
        if (isset($_SESSION['glpicsrftokens'][$token])) {
            // Token found in session, it's valid
            // Remove it to prevent replay attacks (like GLPI does)
            unset($_SESSION['glpicsrftokens'][$token]);
            return true;
        }

        return false;
    }

    /**
     * Generate a new CSRF token
     * @return string The token
     */
    public static function generateToken() {
        if (method_exists('Session', 'getNewCSRFToken')) {
            return Session::getNewCSRFToken();
        }
        return bin2hex(random_bytes(32));
    }
}
?>
