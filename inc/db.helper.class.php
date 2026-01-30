<?php
/**
 * Software Manager Plugin for GLPI
 * Database Compatibility Helper Class
 *
 * This class provides a unified database access layer that works across
 * GLPI 10.x and 11.x versions, automatically detecting and using the
 * appropriate API for each version.
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 * @package GLPI\Plugin\Softwaremanager
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Database Compatibility Helper Class
 *
 * Provides version-agnostic database access methods that automatically
 * adapt to the running GLPI version (10.x or 11.x).
 */
class PluginSoftwaremanagerDBHelper {

    /**
     * @var mixed Cached database connection instance
     */
    private static $db = null;

    /**
     * @var string Detected GLPI version
     */
    private static $glpi_version = null;

    /**
     * @var bool Whether GLPI is version 11.x or higher
     */
    private static $is_glpi_11 = null;

    /**
     * Get the database connection instance
     *
     * @return \DB|null Database connection object
     */
    public static function getDB() {
        if (self::$db === null) {
            // Try GLPI 11.x method first
            if (class_exists('DB') && method_exists('DB', 'getInstance')) {
                self::$db = DB::getInstance();
                self::$is_glpi_11 = true;
            } else {
                // Fall back to GLPI 10.x method
                global $DB;
                if (isset($DB)) {
                    self::$db = $DB;
                    self::$is_glpi_11 = false;
                }
            }

            // If still null, try to initialize DB connection
            if (self::$db === null && class_exists('DB')) {
                try {
                    self::$db = new DB();
                    self::$is_glpi_11 = true;
                } catch (Exception $e) {
                    error_log("PluginSoftwaremanagerDBHelper: Failed to create DB instance: " . $e->getMessage());
                }
            }
        }
        return self::$db;
    }

    /**
     * Check if current GLPI version is 11.x or higher
     *
     * @return bool True if GLPI 11.x+, false otherwise
     */
    public static function isGLPI11() {
        if (self::$is_glpi_11 === null) {
            self::getDB(); // Trigger version detection
        }
        return self::$is_glpi_11;
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table Table name
     * @return bool True if table exists
     */
    public static function tableExists($table) {
        $DB = self::getDB();

        // GLPI 11.x and some 10.x versions have tableExists method
        if (method_exists($DB, 'tableExists')) {
            return $DB->tableExists($table);
        }

        // Fallback: use raw query
        return self::legacyTableExists($table);
    }

    /**
     * Legacy table existence check using raw query
     *
     * @param string $table Table name
     * @return bool True if table exists
     */
    private static function legacyTableExists($table) {
        $DB = self::getDB();
        // GLPI 11.x: use doQuery() instead of query()
        if (method_exists($DB, 'doQuery')) {
            $result = $DB->doQuery("SHOW TABLES LIKE '" . $DB->escape($table) . "'");
        } else {
            $result = $DB->query("SHOW TABLES LIKE '" . $DB->escape($table) . "'");
        }
        return ($result && $DB->numrows($result) > 0);
    }

    /**
     * Execute a query and return result
     *
     * @param string $query SQL query
     * @return mixed Query result
     */
    public static function query($query) {
        $DB = self::getDB();
        // GLPI 11.x: use doQuery() instead of query()
        if (method_exists($DB, 'doQuery')) {
            return $DB->doQuery($query);
        }
        return $DB->query($query);
    }

    /**
     * Execute a query or die on error
     *
     * @param string $query SQL query
     * @param string $error Error message
     * @return mixed Query result
     */
    public static function queryOrDie($query, $error = '') {
        $DB = self::getDB();
        if (method_exists($DB, 'queryOrDie')) {
            return $DB->queryOrDie($query, $error);
        }
        // GLPI 11.x: use doQuery() instead of query()
        if (method_exists($DB, 'doQuery')) {
            $result = $DB->doQuery($query);
        } else {
            $result = $DB->query($query);
        }
        if (!$result) {
            die($error);
        }
        return $result;
    }

    /**
     * Get the last insert ID
     *
     * @return int Last insert ID
     */
    public static function insertId() {
        $DB = self::getDB();
        if (method_exists($DB, 'insertId')) {
            return $DB->insertId();
        }
        return mysqli_insert_id($DB->dbh);
    }

    /**
     * Fetch a row as associative array
     *
     * @param resource $result Query result
     * @return array|false Row data or false
     */
    public static function fetchAssoc($result) {
        $DB = self::getDB();
        if (method_exists($DB, 'fetchAssoc')) {
            return $DB->fetchAssoc($result);
        }
        return $DB->fetchAssoc($result); // Same in both versions
    }

    /**
     * Get number of rows in result
     *
     * @param resource $result Query result
     * @return int Number of rows
     */
    public static function numrows($result) {
        $DB = self::getDB();
        if (method_exists($DB, 'numrows')) {
            return $DB->numrows($result);
        }
        return mysqli_num_rows($result);
    }

    /**
     * Execute a database request using GLPI's query builder
     *
     * This method provides a unified interface for the $DB->request() method
     * which changed significantly between GLPI 10.x and 11.x.
     *
     * @param array|string $params Query parameters or raw SQL
     * @return mixed Query result
     */
    public static function request($params) {
        $DB = self::getDB();

        // GLPI 11.x uses $DB->request() with array syntax
        // GLPI 10.x also supports this but with some differences
        if (is_array($params)) {
            return $DB->request($params);
        }

        // Raw SQL query
        // GLPI 11.x: use doQuery() instead of query()
        if (method_exists($DB, 'doQuery')) {
            return $DB->doQuery($params);
        }
        return $DB->query($params);
    }

    /**
     * Escape a string value
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public static function escape($value) {
        $DB = self::getDB();
        if (method_exists($DB, 'escape')) {
            return $DB->escape($value);
        }
        return mysqli_real_escape_string($DB->dbh, $value);
    }

    /**
     * Insert data into a table
     *
     * @param string $table Table name
     * @param array $data Data to insert (column => value)
     * @return bool|resource Query result
     */
    public static function insert($table, $data) {
        $DB = self::getDB();

        if (method_exists($DB, 'insert') && is_array($data)) {
            // GLPI 11.x style
            return $DB->insert($table, $data);
        }

        // GLPI 10.x style - build query manually
        $columns = array_keys($data);
        $values = array_values($data);

        $escaped_columns = array_map([self::class, 'escape'], $columns);
        $escaped_values = array_map(function($val) use ($DB) {
            if (is_null($val)) {
                return 'NULL';
            }
            if (is_int($val) || is_bool($val)) {
                return (int)$val;
            }
            return "'" . self::escape($val) . "'";
        }, $values);

        $query = "INSERT INTO `$table` (`" . implode('`, `', $escaped_columns) . "`) " .
                 "VALUES (" . implode(', ', $escaped_values) . ")";

        return self::query($query);
    }

    /**
     * Update data in a table
     *
     * @param string $table Table name
     * @param array $data Data to update (column => value)
     * @param array $where WHERE conditions (column => value)
     * @return bool|resource Query result
     */
    public static function update($table, $data, $where) {
        $DB = self::getDB();

        if (method_exists($DB, 'update')) {
            // GLPI 11.x style
            return $DB->update($table, $data, $where);
        }

        // GLPI 10.x style - build query manually
        $set_parts = [];
        foreach ($data as $column => $value) {
            $escaped_column = self::escape($column);
            if (is_null($value)) {
                $set_parts[] = "`$escaped_column` = NULL";
            } elseif (is_int($value) || is_bool($value)) {
                $set_parts[] = "`$escaped_column` = " . (int)$value;
            } else {
                $escaped_value = self::escape($value);
                $set_parts[] = "`$escaped_column` = '$escaped_value'";
            }
        }

        $where_parts = [];
        foreach ($where as $column => $value) {
            $escaped_column = self::escape($column);
            if (is_null($value)) {
                $where_parts[] = "`$escaped_column` IS NULL";
            } elseif (is_int($value) || is_bool($value)) {
                $where_parts[] = "`$escaped_column` = " . (int)$value;
            } else {
                $escaped_value = self::escape($value);
                $where_parts[] = "`$escaped_column` = '$escaped_value'";
            }
        }

        $query = "UPDATE `$table` SET " . implode(', ', $set_parts) .
                 " WHERE " . implode(' AND ', $where_parts);

        return self::query($query);
    }

    /**
     * Delete data from a table
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (column => value)
     * @return bool|resource Query result
     */
    public static function delete($table, $where) {
        $DB = self::getDB();

        if (method_exists($DB, 'delete')) {
            // GLPI 11.x style
            return $DB->delete($table, $where);
        }

        // GLPI 10.x style - build query manually
        $where_parts = [];
        foreach ($where as $column => $value) {
            $escaped_column = self::escape($column);
            if (is_null($value)) {
                $where_parts[] = "`$escaped_column` IS NULL";
            } elseif (is_array($value)) {
                // Handle IN clause
                $escaped_values = array_map([self::class, 'escape'], $value);
                $where_parts[] = "`$escaped_column` IN ('" . implode("', '", $escaped_values) . "')";
            } elseif (is_int($value) || is_bool($value)) {
                $where_parts[] = "`$escaped_column` = " . (int)$value;
            } else {
                $escaped_value = self::escape($value);
                $where_parts[] = "`$escaped_column` = '$escaped_value'";
            }
        }

        $query = "DELETE FROM `$table` WHERE " . implode(' AND ', $where_parts);
        return self::query($query);
    }

    /**
     * Execute a raw SQL query (alias for query)
     *
     * @param string $query SQL query
     * @return mixed Query result
     */
    public static function raw($query) {
        return self::query($query);
    }

    /**
     * Get the current GLPI version
     *
     * @return string GLPI version
     */
    public static function getGLPIVersion() {
        if (self::$glpi_version === null) {
            if (defined('GLPI_VERSION')) {
                self::$glpi_version = GLPI_VERSION;
            } elseif (class_exists('Glpi\\Version')) {
                self::$glpi_version = \Glpi\Version::getVersion();
            } else {
                // Try to get from config
                global $CFG_GLPI;
                self::$glpi_version = isset($CFG_GLPI['version']) ? $CFG_GLPI['version'] : '0.0';
            }
        }
        return self::$glpi_version;
    }

    /**
     * Compare GLPI version with a given version
     *
     * @param string $version Version to compare
     * @param string $operator Comparison operator (default: '>=')
     * @return bool Comparison result
     */
    public static function compareGLPIVersion($version, $operator = '>=') {
        return version_compare(self::getGLPIVersion(), $version, $operator);
    }
}
