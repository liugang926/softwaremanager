<?php
/**
 * Software Manager Plugin for GLPI
 * Software Inventory Management Class
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginSoftwaremanagerSoftwareInventory extends CommonDBTM {

    /**
     * Get software inventory data
     *
     * @param int    $start         Start offset for pagination
     * @param int    $limit         Number of items to fetch
     * @param string $search        Search term
     * @param int    $manufacturer  Manufacturer filter
     * @param string $status        Status filter (whitelist/blacklist/unmanaged/all)
     * @param string $sort          Sort field
     * @param string $order         Sort order (ASC/DESC)
     *
     * @return array Software inventory data
     */
    static function getSoftwareInventory($start = 0, $limit = 50, $search = '', $manufacturer = 0, $status = 'all', $sort = 'name', $order = 'ASC') {
        global $DB;

        $start = intval($start);
        $limit = intval($limit);
        $search = Html::cleanInputText($search);
        $manufacturer = intval($manufacturer);

        // Validate sort and order parameters
        $valid_sorts = ['name', 'manufacturer', 'computer_count', 'date_creation'];
        $sort = in_array($sort, $valid_sorts) ? $sort : 'name';
        $order = (strtoupper($order) === 'DESC') ? 'DESC' : 'ASC';

        // Build SQL query - Group by software name to combine versions
        // 支持精确匹配和部分匹配（根据 exact_match 字段）
        // 使用 CASE WHEN 在 SELECT 中计算状态，而不是复杂的 JOIN
        $sql = "SELECT
                MIN(s.id) as software_id,
                s.name as software_name,
                GROUP_CONCAT(DISTINCT COALESCE(sv.name, '') ORDER BY sv.name SEPARATOR ', ') as version,
                COALESCE(m.name, '') as manufacturer,
                COUNT(DISTINCT isv.items_id) as computer_count,
                CASE
                    WHEN (
                        SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                        WHERE w.is_active = 1
                        AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                    ) > 0 AND (
                        SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                        WHERE b.is_active = 1
                        AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                    ) > 0 THEN 'both'
                    WHEN (
                        SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                        WHERE w.is_active = 1
                        AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                    ) > 0 THEN 'whitelist'
                    WHEN (
                        SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                        WHERE b.is_active = 1
                        AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                    ) > 0 THEN 'blacklist'
                    ELSE 'unmanaged'
                END as status,
                CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                    WHERE w.is_active = 1
                    AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                ) > 0 THEN 1 ELSE 0 END as is_whitelisted,
                CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                    WHERE b.is_active = 1
                    AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                ) > 0 THEN 1 ELSE 0 END as is_blacklisted
            FROM glpi_softwares s
            LEFT JOIN glpi_manufacturers m ON (m.id = s.manufacturers_id)
            LEFT JOIN glpi_softwareversions sv ON (sv.softwares_id = s.id)
            LEFT JOIN glpi_items_softwareversions isv ON (
                isv.softwareversions_id = sv.id
                AND isv.itemtype = 'Computer'
                AND isv.is_deleted = 0
            )
            LEFT JOIN glpi_computers c ON (
                c.id = isv.items_id
                AND c.is_deleted = 0
                AND c.is_template = 0
            )
            WHERE s.is_deleted = 0";

            // Add search condition if provided
            if (!empty($search)) {
                $search_escaped = $DB->escape($search);
                $sql .= " AND (s.name LIKE '%$search_escaped%' OR m.name LIKE '%$search_escaped%')";
            }

            // Add manufacturer filter if provided
            if ($manufacturer > 0) {
                $sql .= " AND s.manufacturers_id = " . intval($manufacturer);
            }

            // Add status filter if provided - 支持精确匹配和部分匹配
            if ($status !== 'all') {
                switch ($status) {
                    case 'whitelist':
                        $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w WHERE w.is_active = 1 AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%'))))";
                        break;
                    case 'blacklist':
                        $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b WHERE b.is_active = 1 AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%'))))";
                        break;
                    case 'unmanaged':
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w WHERE w.is_active = 1 AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%'))))";
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b WHERE b.is_active = 1 AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%'))))";
                        break;
                }
            }

            $sql .= " GROUP BY s.name, m.name";

            // Add sorting
            switch ($sort) {
                case 'manufacturer':
                    $sql .= " ORDER BY m.name $order, s.name ASC";
                    break;
                case 'computer_count':
                    $sql .= " ORDER BY computer_count $order, s.name ASC";
                    break;
                case 'date_creation':
                    $sql .= " ORDER BY s.date_creation $order, s.name ASC";
                    break;
                default:
                    $sql .= " ORDER BY s.name $order";
                    break;
            }

            // Add limit for pagination
            if ($limit > 0) {
                $sql .= " LIMIT " . intval($start) . ", " . intval($limit);
            }

            $software_list = [];

            // Use doQuery for GLPI 11.x compatibility with raw SQL
            $result = $DB->doQuery($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $software_list[] = $row;
                }
            }

            return $software_list;
    }

    /**
     * Get total count of software inventory
     *
     * @param string $search        Search term
     * @param int    $manufacturer  Manufacturer filter
     * @param string $status        Status filter (whitelist/blacklist/unmanaged/all)
     *
     * @return int Total count
     */
    static function getSoftwareInventoryCount($search = '', $manufacturer = 0, $status = 'all') {
        global $DB;

        $search = Html::cleanInputText($search);
        $manufacturer = intval($manufacturer);

            $sql = "SELECT COUNT(DISTINCT s.name) as total
                   FROM glpi_softwares s
                   LEFT JOIN glpi_manufacturers m ON (m.id = s.manufacturers_id)
                   WHERE s.is_deleted = 0";

            // Add search condition if provided
            if (!empty($search)) {
                $search_escaped = $DB->escape($search);
                $sql .= " AND (s.name LIKE '%$search_escaped%' OR m.name LIKE '%$search_escaped%')";
            }

            // Add manufacturer filter if provided
            if ($manufacturer > 0) {
                $sql .= " AND s.manufacturers_id = " . intval($manufacturer);
            }

            // Add status filter if provided - 支持精确匹配和部分匹配
            if ($status !== 'all') {
                switch ($status) {
                    case 'whitelist':
                    $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w2 WHERE w2.is_active = 1 AND ((w2.exact_match = 1 AND w2.name = s.name) OR (w2.exact_match = 0 AND s.name LIKE CONCAT('%', w2.name, '%'))))";
                        break;
                    case 'blacklist':
                    $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b2 WHERE b2.is_active = 1 AND ((b2.exact_match = 1 AND b2.name = s.name) OR (b2.exact_match = 0 AND s.name LIKE CONCAT('%', b2.name, '%'))))";
                        break;
                    case 'unmanaged':
                    $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w2 WHERE w2.is_active = 1 AND ((w2.exact_match = 1 AND w2.name = s.name) OR (w2.exact_match = 0 AND s.name LIKE CONCAT('%', w2.name, '%'))))";
                    $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b2 WHERE b2.is_active = 1 AND ((b2.exact_match = 1 AND b2.name = s.name) OR (b2.exact_match = 0 AND s.name LIKE CONCAT('%', b2.name, '%'))))";
                        break;
                }
            }

            // Use doQuery for GLPI 11.x compatibility with raw SQL
            $result = $DB->doQuery($sql);
            if ($result && ($row = $result->fetch_assoc())) {
                return intval($row['total']);
            }

            return 0;
    }

    /**
     * Get detailed software information including associated computers
     *
     * @param int $software_id Software ID
     *
     * @return array|false Software details with computers list
     */
    static function getSoftwareDetails($software_id) {
        global $DB;

        try {
            if ($software_id <= 0) {
                Toolbox::logInFile('plugin_softwaremanager_debug', 'Invalid software_id: ' . $software_id);
                return false;
            }

            // Get software information using direct SQL query
            $software_sql = "SELECT s.id, s.name, m.name as manufacturer
                           FROM glpi_softwares s
                           LEFT JOIN glpi_manufacturers m ON s.manufacturers_id = m.id
                           WHERE s.id = " . intval($software_id) . " AND s.is_deleted = 0";

            Toolbox::logInFile('plugin_softwaremanager_debug', 'Software SQL Query: ' . $software_sql);

            // Use doQuery for GLPI 11.x compatibility
            $software_result = $DB->doQuery($software_sql);
            if (!$software_result) {
                return false;
            }

            // Handle both mysqli_result and mysqli_stmt
            if (get_class($software_result) === 'mysqli_result') {
                // Direct result, can fetch directly
                $row = $software_result->fetch_assoc();
            } else {
                // mysqli_stmt, need to execute and get result
                $software_result->execute();
                $result = $software_result->get_result();
                $row = $result->fetch_assoc();
            }

            if ($row === null) {
                return false;
            }

            $software_info = $row;
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Software found: ' . $software_info['name']);

            // Complete working query - including date_install field from glpi_items_softwareversions
            // GLPI 11.x: groups are linked via glpi_groups_items table
            $computer_sql = "SELECT DISTINCT
                c.id as computer_id,
                c.name as computer_name,
                c.serial,
                c.otherserial,
                c.date_mod as computer_last_update,
                c.date_creation as computer_creation_date,
                u.id as user_id,
                u.name as username,
                u.firstname,
                u.realname,
                g.id as group_id,
                g.name as group_name,
                l.name as location_name,
                sv.name as version_name,
                sv.id as version_id,
                st.name as computer_status,
                isv.date_install as installation_date
            FROM glpi_softwareversions sv
            LEFT JOIN glpi_items_softwareversions isv ON (isv.softwareversions_id = sv.id AND isv.itemtype = 'Computer' AND isv.is_deleted = 0)
            LEFT JOIN glpi_computers c ON (c.id = isv.items_id AND c.is_deleted = 0 AND c.is_template = 0)
            LEFT JOIN glpi_users u ON u.id = c.users_id
            LEFT JOIN glpi_groups_items gi ON (gi.items_id = c.id AND gi.itemtype = 'Computer')
            LEFT JOIN glpi_groups g ON g.id = gi.groups_id
            LEFT JOIN glpi_locations l ON l.id = c.locations_id
            LEFT JOIN glpi_states st ON st.id = c.states_id
            WHERE sv.softwares_id = " . intval($software_id) . "
            AND c.id IS NOT NULL
            ORDER BY c.name ASC";

            $computers = [];

            // Debug log the query
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Computer SQL Query: ' . $computer_sql);

            // Use doQuery for GLPI 11.x compatibility
            $computer_result = $DB->doQuery($computer_sql);
            if ($computer_result) {
                // Handle both mysqli_result and mysqli_stmt
                if (get_class($computer_result) === 'mysqli_result') {
                    // Direct result, can fetch directly
                    while ($row = $computer_result->fetch_assoc()) {
                        if ($row['computer_id']) {
                            $computers[] = [
                                'id' => $row['computer_id'],
                                'name' => $row['computer_name'],
                                'serial' => $row['serial'],
                                'asset_tag' => $row['otherserial'],
                                'version' => $row['version_name'],
                                'version_id' => $row['version_id'],
                                'installation_date' => $row['installation_date'],
                                'installation_last_update' => null,
                                'computer_last_update' => $row['computer_last_update'],
                                'computer_creation_date' => $row['computer_creation_date'],
                                'computer_status' => $row['computer_status'],
                                'user' => [
                                    'id' => $row['user_id'],
                                    'username' => $row['username'],
                                    'firstname' => $row['firstname'],
                                    'realname' => $row['realname'],
                                    'display_name' => trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? '')) ?: ($row['username'] ?? '')
                                ],
                                'group' => [
                                    'id' => $row['group_id'],
                                    'name' => $row['group_name']
                                ],
                                'location' => $row['location_name']
                            ];
                        }
                    }
                } else {
                    // mysqli_stmt, need to execute and get result
                    $computer_result->execute();
                    $result = $computer_result->get_result();
                    while ($row = $result->fetch_assoc()) {
                        if ($row['computer_id']) {
                            $computers[] = [
                                'id' => $row['computer_id'],
                                'name' => $row['computer_name'],
                                'serial' => $row['serial'],
                                'asset_tag' => $row['otherserial'],
                                'version' => $row['version_name'],
                                'version_id' => $row['version_id'],
                                'installation_date' => $row['installation_date'],
                                'installation_last_update' => null,
                                'computer_last_update' => $row['computer_last_update'],
                                'computer_creation_date' => $row['computer_creation_date'],
                                'computer_status' => $row['computer_status'],
                                'user' => [
                                    'id' => $row['user_id'],
                                    'username' => $row['username'],
                                    'firstname' => $row['firstname'],
                                    'realname' => $row['realname'],
                                    'display_name' => trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? '')) ?: ($row['username'] ?? '')
                                ],
                                'group' => [
                                    'id' => $row['group_id'],
                                    'name' => $row['group_name']
                                ],
                                'location' => $row['location_name']
                            ];
                        }
                    }
                }
            }

            Toolbox::logInFile('plugin_softwaremanager_debug', 'Query returned ' . count($computers) . ' results');

            return [
                'software' => $software_info,
                'computers' => $computers
            ];

        } catch (Exception $e) {
            Toolbox::logInFile('plugin_softwaremanager', 'Error in getSoftwareDetails: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if software is in whitelist or blacklist
     * 支持精确匹配和部分匹配（根据 exact_match 字段）
     *
     * @param string $software_name Software name
     *
     * @return string Status: 'whitelist', 'blacklist', or 'unregistered'
     */
    static function getSoftwareListStatus($software_name) {
        global $DB;

        if (empty($software_name)) {
            return 'unregistered';
        }

        try {
            // 安全转义软件名称
            $safe_name = addslashes($software_name);

            // Check whitelist first - 支持精确匹配和部分匹配
            // 精确匹配: name = 'software_name'
            // 部分匹配: name LIKE '%software_name%' (规则名称在软件名称中)
            $whitelist_table = PluginSoftwaremanagerSoftwareWhitelist::getTable();
            $sql_whitelist = "SELECT * FROM $whitelist_table
                              WHERE is_active = 1
                              AND ((exact_match = 1 AND name = '$safe_name')
                                   OR (exact_match = 0 AND '$safe_name' LIKE CONCAT('%', name, '%')))
                              ORDER BY priority DESC, id ASC
                              LIMIT 1";
            $whitelist_result = $DB->doQuery($sql_whitelist);

            if ($whitelist_result && ($whitelist_result->fetch_assoc())) {
                return 'whitelist';
            }

        // Check blacklist - 支持精确匹配和部分匹配
            $blacklist_table = PluginSoftwaremanagerSoftwareBlacklist::getTable();
            $sql_blacklist = "SELECT * FROM $blacklist_table
                              WHERE is_active = 1
                              AND ((exact_match = 1 AND name = '$safe_name')
                                   OR (exact_match = 0 AND '$safe_name' LIKE CONCAT('%', name, '%')))
                              ORDER BY priority DESC, id ASC
                              LIMIT 1";
            $blacklist_result = $DB->doQuery($sql_blacklist);

            if ($blacklist_result && ($blacklist_result->fetch_assoc())) {
                return 'blacklist';
            }

        } catch (Exception $e) {
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Error checking software list status: ' . $e->getMessage());
        }

        return 'unregistered';
    }



    /**
     * Get dashboard statistics
     *
     * @return array Statistics data
     */
    static function getDashboardStats() {
        global $DB;

        $stats = [
            'total' => 0,
            'whitelist' => 0,
            'blacklist' => 0,
            'both' => 0,
            'unmanaged' => 0
        ];

        // Get total count
        $sql_total = "SELECT COUNT(DISTINCT s.id) as total
                         FROM glpi_softwares s
                     WHERE s.is_deleted = 0";

        $result = $DB->doQuery($sql_total);
        if ($result && ($row = $result->fetch_assoc())) {
            $stats['total'] = intval($row['total']);
        }

            // Get detailed counts using the same logic as the main query - 支持精确匹配和部分匹配
        $sql_counts = "SELECT
                COUNT(DISTINCT CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                    WHERE w.is_active = 1
                    AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                ) > 0 THEN s.id END) as whitelist_count,
                COUNT(DISTINCT CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                    WHERE b.is_active = 1
                    AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                ) > 0 THEN s.id END) as blacklist_count,
                COUNT(DISTINCT CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                    WHERE w.is_active = 1
                    AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                ) > 0 AND (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                    WHERE b.is_active = 1
                    AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                ) > 0 THEN s.id END) as both_count,
                COUNT(DISTINCT CASE WHEN (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w
                    WHERE w.is_active = 1
                    AND ((w.exact_match = 1 AND w.name = s.name) OR (w.exact_match = 0 AND s.name LIKE CONCAT('%', w.name, '%')))
                ) = 0 AND (
                    SELECT COUNT(*) FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b
                    WHERE b.is_active = 1
                    AND ((b.exact_match = 1 AND b.name = s.name) OR (b.exact_match = 0 AND s.name LIKE CONCAT('%', b.name, '%')))
                ) = 0 THEN s.id END) as unmanaged_count
            FROM glpi_softwares s
            WHERE s.is_deleted = 0";

        $result = $DB->doQuery($sql_counts);
        if ($result && ($row = $result->fetch_assoc())) {
            $stats['whitelist'] = intval($row['whitelist_count']);
            $stats['blacklist'] = intval($row['blacklist_count']);
            $stats['both'] = intval($row['both_count']);
            $stats['unmanaged'] = intval($row['unmanaged_count']);
        }

        return $stats;
    }

    /**
     * Get available manufacturers for dropdown
     *
     * @return array Manufacturers list
     */
    static function getManufacturers() {
        global $DB;

        $manufacturers = [];

        $sql = "SELECT DISTINCT m.id, m.name
                FROM glpi_manufacturers m
                INNER JOIN glpi_softwares s ON s.manufacturers_id = m.id
                WHERE s.is_deleted = 0 AND m.name IS NOT NULL AND m.name != ''
                ORDER BY m.name ASC";

        $result = $DB->doQuery($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $manufacturers[] = $row;
            }
        }

        return $manufacturers;
    }

    /**
     * Import software data from CSV
     *
     * @param string $file_path Path to CSV file
     * @param array  $options   Import options
     *
     * @return array Import results
     */
    static function importFromCSV($file_path, $options = []) {
        $results = [
            'success' => false,
            'imported' => 0,
            'errors' => 0,
            'messages' => []
        ];

        if (!file_exists($file_path)) {
            $results['messages'][] = __('File not found', 'softwaremanager');
            return $results;
        }

        try {
            $handle = fopen($file_path, 'r');
            if (!$handle) {
                $results['messages'][] = __('Cannot read file', 'softwaremanager');
                return $results;
            }

            $line_number = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $line_number++;

                // Skip header row
                if ($line_number === 1 && isset($options['skip_header']) && $options['skip_header']) {
                    continue;
                }

                // Process CSV data here
                // Implementation depends on CSV format

                $results['imported']++;
            }

            fclose($handle);
            $results['success'] = true;

        } catch (Exception $e) {
            $results['messages'][] = $e->getMessage();
            $results['errors']++;
        }

        return $results;
    }
}
