<?php
/**
 * Software Manager Plugin - Logger
 *
 * Provides a unified logging interface for the plugin with support for
 * multiple log levels and structured context data.
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 * @package GLPI\Plugin\Softwaremanager
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Plugin Logger Class
 *
 * Provides unified logging with multiple severity levels and context support.
 * Logs are written to GLPI's log files using the logInFile function when available.
 */
class PluginSoftwaremanagerLogger {

    /** @var int Debug level for detailed development information */
    const DEBUG = 1;

    /** @var int Info level for general informational messages */
    const INFO = 2;

    /** @var int Warning level for potentially harmful situations */
    const WARNING = 3;

    /** @var int Error level for error events that might still allow the application to continue */
    const ERROR = 4;

    /** @var string Log file name for this plugin */
    const LOG_FILE = 'softwaremanager';

    /** @var int Minimum log level (can be configured) */
    private static $min_level = self::INFO;

    /** @var bool Whether logging is enabled */
    private static $enabled = true;

    /**
     * Set the minimum log level
     *
     * @param int $level One of the class level constants
     */
    public static function setMinLevel(int $level): void {
        self::$min_level = $level;
    }

    /**
     * Enable or disable logging
     *
     * @param bool $enabled True to enable, false to disable
     */
    public static function setEnabled(bool $enabled): void {
        self::$enabled = $enabled;
    }

    /**
     * Check if logging is enabled for a given level
     *
     * @param int $level Log level to check
     * @return bool True if logging is enabled for this level
     */
    public static function isEnabled(int $level): bool {
        return self::$enabled && $level >= self::$min_level;
    }

    /**
     * Log a message with specified level and context
     *
     * @param int $level Log level (use class constants)
     * @param string $message Log message
     * @param array $context Additional context data (optional)
     * @return void
     */
    public static function log(int $level, string $message, array $context = []): void {
        if (!self::isEnabled($level)) {
            return;
        }

        $level_names = [
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::WARNING => 'WARNING',
            self::ERROR => 'ERROR'
        ];

        $level_name = $level_names[$level] ?? 'UNKNOWN';

        // Build log message with timestamp and context
        $log_parts = [
            date('Y-m-d H:i:s'),
            '[' . $level_name . ']',
            'softwaremanager',
            $message
        ];

        $log_message = implode(' ', $log_parts);

        // Add context if provided
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Write to GLPI log
        self::writeLog($log_message);
    }

    /**
     * Write log message to appropriate log destination
     *
     * @param string $message Formatted log message
     * @return void
     */
    private static function writeLog(string $message): void {
        // Try GLPI's logInFile function first
        if (function_exists('logInFile')) {
            logInFile(self::LOG_FILE, $message . "\n");
        } elseif (function_exists('error_log')) {
            // Fallback to PHP's error_log
            error_log('[softwaremanager] ' . $message);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context data (optional)
     * @return void
     */
    public static function debug(string $message, array $context = []): void {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array $context Additional context data (optional)
     * @return void
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context data (optional)
     * @return void
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context data (optional)
     * @return void
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log an exception with stack trace
     *
     * @param Throwable $exception Exception to log
     * @param string $message Additional message (optional)
     * @return void
     */
    public static function exception(Throwable $exception, string $message = ''): void {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $log_message = $message ?: 'Exception occurred';
        self::error($log_message, $context);
    }

    /**
     * Log database query information
     *
     * @param string $query SQL query (truncated if too long)
     * @param array $params Query parameters (optional)
     * @param float $duration Query execution time in seconds (optional)
     * @return void
     */
    public static function query(string $query, array $params = [], float $duration = null): void {
        $context = [
            'query' => strlen($query) > 500 ? substr($query, 0, 500) . '...' : $query
        ];

        if (!empty($params)) {
            $context['params'] = $params;
        }

        if ($duration !== null) {
            $context['duration'] = round($duration * 1000, 2) . 'ms';
        }

        self::debug('Database query', $context);
    }

    /**
     * Log API or external service call
     *
     * @param string $service Service name
     * @param string $action Action performed
     * @param array $context Additional context (optional)
     * @return void
     */
    public static function apiCall(string $service, string $action, array $context = []): void {
        $context['service'] = $service;
        $context['action'] = $action;
        self::info('API call', $context);
    }

    /**
     * Log cron task execution
     *
     * @param string $task Task name
     * @param bool $success Whether the task succeeded
     * @param array $context Additional context (optional)
     * @return void
     */
    public static function cronTask(string $task, bool $success, array $context = []): void {
        $context['task'] = $task;
        $context['success'] = $success ? 'true' : 'false';

        if ($success) {
            self::info('Cron task completed', $context);
        } else {
            self::error('Cron task failed', $context);
        }
    }
}
