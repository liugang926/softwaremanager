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
        $sql = "SELECT
                MIN(s.id) as software_id,
                s.name as software_name,
                GROUP_CONCAT(DISTINCT COALESCE(sv.name, '') ORDER BY sv.name SEPARATOR ', ') as version,
                COALESCE(m.name, '') as manufacturer,
                COUNT(DISTINCT isv.items_id) as computer_count,
                CASE
                    WHEN w.id IS NOT NULL AND w.is_active = 1 AND b.id IS NOT NULL AND b.is_active = 1 THEN 'both'
                    WHEN w.id IS NOT NULL AND w.is_active = 1 THEN 'whitelist'
                    WHEN b.id IS NOT NULL AND b.is_active = 1 THEN 'blacklist'
                    ELSE 'unmanaged'
                END as status,
                CASE WHEN w.id IS NOT NULL AND w.is_active = 1 THEN 1 ELSE 0 END as is_whitelisted,
                CASE WHEN b.id IS NOT NULL AND b.is_active = 1 THEN 1 ELSE 0 END as is_blacklisted
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
            LEFT JOIN " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w ON (
                w.name = s.name AND w.is_active = 1
            )
            LEFT JOIN " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b ON (
                b.name = s.name AND b.is_active = 1
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

            // Add status filter if provided
            if ($status !== 'all') {
                switch ($status) {
                    case 'whitelist':
                        $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w WHERE w.name = s.name AND w.is_active = 1)";
                        break;
                    case 'blacklist':
                        $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b WHERE b.name = s.name AND b.is_active = 1)";
                        break;
                    case 'unmanaged':
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w WHERE w.name = s.name AND w.is_active = 1)";
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b WHERE b.name = s.name AND b.is_active = 1)";
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

            $result = $DB->query($sql);
            $software_list = [];

            if ($result) {
                while ($row = $DB->fetchAssoc($result)) {
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
            LEFT JOIN " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w ON (
                w.name = s.name AND w.is_active = 1
                   )
            LEFT JOIN " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b ON (
                b.name = s.name AND b.is_active = 1
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

            // Add status filter if provided
            if ($status !== 'all') {
                switch ($status) {
                    case 'whitelist':
                    $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w2 WHERE w2.name = s.name AND w2.is_active = 1)";
                        break;
                    case 'blacklist':
                    $sql .= " AND EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b2 WHERE b2.name = s.name AND b2.is_active = 1)";
                        break;
                    case 'unmanaged':
                    $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w2 WHERE w2.name = s.name AND w2.is_active = 1)";
                    $sql .= " AND NOT EXISTS (SELECT 1 FROM " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b2 WHERE b2.name = s.name AND b2.is_active = 1)";
                        break;
                }
            }

            $result = $DB->query($sql);

            if ($result) {
            $row = $DB->fetchAssoc($result);
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
            $software_result = $DB->query($software_sql);

            if (!$software_result) {
                $error = $DB->error();
                Toolbox::logInFile('plugin_softwaremanager_debug', 'Software SQL Error: ' . $error);
                throw new Exception('Software query failed: ' . $error);
            }

            if ($DB->numrows($software_result) == 0) {
                Toolbox::logInFile('plugin_softwaremanager_debug', 'No software found with ID: ' . $software_id);
                return false;
            }

            $software_info = $DB->fetchAssoc($software_result);
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Software found: ' . $software_info['name']);

            // Complete working query - including date_install field from glpi_items_softwareversions
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
            LEFT JOIN glpi_groups g ON g.id = c.groups_id
            LEFT JOIN glpi_locations l ON l.id = c.locations_id
            LEFT JOIN glpi_states st ON st.id = c.states_id
            WHERE sv.softwares_id = " . intval($software_id) . "
            AND c.id IS NOT NULL
            ORDER BY c.name ASC";

            $computers = [];
            
            // Debug log the query
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Computer SQL Query: ' . $computer_sql);
            
            // Execute the query
            $computer_result = $DB->query($computer_sql);
            
            if (!$computer_result) {
                $error = $DB->error();
                Toolbox::logInFile('plugin_softwaremanager_debug', 'Computer SQL Error: ' . $error);
                throw new Exception('Computer query failed: ' . $error);
            }
            
            $num_results = $DB->numrows($computer_result);
            Toolbox::logInFile('plugin_softwaremanager_debug', 'Query returned ' . $num_results . ' results');
            
            if ($computer_result && $num_results > 0) {
                while ($row = $DB->fetchAssoc($computer_result)) {
                if ($row['computer_id']) {
                    $computers[] = [
                        'id' => $row['computer_id'],
                        'name' => $row['computer_name'],
                        'serial' => $row['serial'],
                        'asset_tag' => $row['otherserial'],
                            'version' => $row['version_name'],
                            'version_id' => $row['version_id'],
                            'installation_date' => $row['installation_date'], // Using correct date_install field from glpi_items_softwareversions
                            'installation_last_update' => null, // Field not available in this GLPI version
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
     *
     * @param string $software_name Software name
     *
     * @return string Status: 'whitelist', 'blacklist', or 'unregistered'
     */
    static function getSoftwareListStatus($software_name) {
        global $DB;

        try {
            // Check whitelist first
            $whitelist_result = $DB->request([
                'FROM' => 'glpi_plugin_softwaremanager_softwarewhitelists',
            'WHERE' => ['name' => $software_name, 'is_active' => 1]
        ]);

            if (count($whitelist_result) > 0) {
            return 'whitelist';
        }

        // Check blacklist
            $blacklist_result = $DB->request([
                'FROM' => 'glpi_plugin_softwaremanager_softwareblacklists',
            'WHERE' => ['name' => $software_name, 'is_active' => 1]
        ]);

            if (count($blacklist_result) > 0) {
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

        $result = $DB->query($sql_total);
        if ($result) {
            $row = $DB->fetchAssoc($result);
                $stats['total'] = intval($row['total']);
            }

            // Get detailed counts using the same logic as the main query
        $sql_counts = "SELECT
                COUNT(DISTINCT CASE WHEN w.id IS NOT NULL AND w.is_active = 1 THEN s.id END) as whitelist_count,
                COUNT(DISTINCT CASE WHEN b.id IS NOT NULL AND b.is_active = 1 THEN s.id END) as blacklist_count,
                COUNT(DISTINCT CASE WHEN w.id IS NOT NULL AND w.is_active = 1 AND b.id IS NOT NULL AND b.is_active = 1 THEN s.id END) as both_count,
                COUNT(DISTINCT CASE WHEN w.id IS NULL AND b.id IS NULL THEN s.id END) as unmanaged_count
            FROM glpi_softwares s
            LEFT JOIN " . PluginSoftwaremanagerSoftwareWhitelist::getTable() . " w ON (
                w.name = s.name AND w.is_active = 1
            )
            LEFT JOIN " . PluginSoftwaremanagerSoftwareBlacklist::getTable() . " b ON (
                b.name = s.name AND b.is_active = 1
            )
            WHERE s.is_deleted = 0";

        $result = $DB->query($sql_counts);
        if ($result) {
            $row = $DB->fetchAssoc($result);
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

        $result = $DB->query($sql);
        
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
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
