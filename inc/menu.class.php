<?php
/**
 * Software Manager Plugin for GLPI
 * Menu Management Class
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * PluginSoftwaremanagerMenu class
 */
class PluginSoftwaremanagerMenu extends CommonGLPI {

    static $rightname = 'plugin_softwaremanager_menu';

    /**
     * Get menu name
     *
     * @return string Menu name
     */
    static function getMenuName() {
        return 'Software Manager';
    }

    /**
     * Get menu content
     *
     * @return array Menu content
     */
    static function getMenuContent() {
        global $CFG_GLPI;

        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = '/plugins/softwaremanager/front/softwarelist.php';
        $menu['icon']  = 'fas fa-laptop';

        // Check if user has access
        if (self::canView()) {
            // Software inventory
            $menu['options']['softwarelist'] = [
                'title' => 'Software Inventory',
                'page'  => '/plugins/softwaremanager/front/softwarelist.php',
                'icon'  => 'fas fa-list'
            ];

            // Compliance scan history
            $menu['options']['scanhistory'] = [
                'title' => 'Compliance Scan History',
                'page'  => '/plugins/softwaremanager/front/scanhistory.php',
                'icon'  => 'fas fa-history'
            ];

            // Whitelist management
            $menu['options']['whitelist'] = [
                'title' => 'Whitelist Management',
                'page'  => '/plugins/softwaremanager/front/whitelist.php',
                'icon'  => 'fas fa-check-circle',
                'links' => [
                    'search' => '/plugins/softwaremanager/front/whitelist.php',
                    'add'    => '/plugins/softwaremanager/front/whitelist.form.php'
                ]
            ];

            // Blacklist management
            $menu['options']['blacklist'] = [
                'title' => 'Blacklist Management',
                'page'  => '/plugins/softwaremanager/front/blacklist.php',
                'icon'  => 'fas fa-times-circle',
                'links' => [
                    'search' => '/plugins/softwaremanager/front/blacklist.php',
                    'add'    => '/plugins/softwaremanager/front/blacklist.form.php'
                ]
            ];

            // Import/Export functionality
            $menu['options']['import'] = [
                'title' => 'Import/Export',
                'page'  => '/plugins/softwaremanager/front/enhanced_import.php',
                'icon'  => 'fas fa-file-import'
            ];

            // Plugin configuration
            $menu['options']['config'] = [
                'title' => 'Plugin Configuration',
                'page'  => '/plugins/softwaremanager/front/config.php',
                'icon'  => 'fas fa-cog'
            ];

            // Email configuration/test entries removed (migrated to Notification-based flow)
        }

        return $menu;
    }

    /**
     * Check if user can view the menu
     *
     * @return boolean
     */
    static function canView() {
        // All logged-in users can access the plugin
        return isset($_SESSION['glpiID']) && $_SESSION['glpiID'];
    }

    /**
     * Display navigation header
     *
     * @param string $current_page Current page identifier
     *
     * @return void
     */
    public static function displayNavigationHeader($current_page = '') {
        global $CFG_GLPI;

        // 轻量级样式优化，保持GLPI原生风格
        echo "<style type='text/css'>
        .software-manager-nav-table {
            margin: 15px auto 25px auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .software-manager-nav-table th {
            background: #343a40;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            padding: 12px 15px;
            border: none;
            text-align: center;
        }
        
        .nav-item-cell {
            padding: 12px 15px;
            text-align: center;
            border-right: 1px solid #dee2e6;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .nav-item-cell:last-child {
            border-right: none;
        }
        
        .nav-item-cell:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .nav-item-cell.active {
            background-color: #e3f2fd;
            border-bottom: 3px solid #2196F3;
        }
        
        .nav-item-link {
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: color 0.2s ease;
        }
        
        .nav-item-link:hover {
            color: #2196F3;
            text-decoration: none;
        }
        
        .nav-item-cell.active .nav-item-link {
            color: #1976D2;
            font-weight: 600;
        }
        
        .nav-icon {
            font-size: 14px;
            width: 16px;
            text-align: center;
        }
        
        /* 图标颜色主题 */
        .nav-item-cell[data-page='whitelist'] .nav-icon {
            color: #28a745;
        }
        .nav-item-cell[data-page='blacklist'] .nav-icon {
            color: #dc3545;
        }
        .nav-item-cell[data-page='scanhistory'] .nav-icon {
            color: #17a2b8;
        }
        .nav-item-cell[data-page='softwarelist'] .nav-icon {
            color: #ffc107;
        }
        .nav-item-cell[data-page='import'] .nav-icon {
            color: #6f42c1;
        }
        .nav-item-cell[data-page='config'] .nav-icon {
            color: #fd7e14;
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .software-manager-nav-table {
                margin: 10px 5px 20px 5px;
            }
            .nav-item-cell {
                padding: 8px 10px;
                font-size: 13px;
            }
            .nav-icon {
                font-size: 12px;
            }
        }
        </style>";

        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe software-manager-nav-table'>";
        echo "<tr><th colspan='8'><i class='fas fa-shield-alt' style='margin-right: 8px; color: #17a2b8;'></i>软件合规管理系统</th></tr>";
        echo "<tr class='tab_bg_1'>";

        $menu_items = [
            'softwarelist' => ['软件清单', 'fas fa-list-ul'],
            'scanhistory'  => ['扫描历史', 'fas fa-history'],
            'whitelist'    => ['白名单', 'fas fa-check-circle'],
            'blacklist'    => ['黑名单', 'fas fa-ban'],
            'import'       => ['导入导出', 'fas fa-exchange-alt'],
            'config'       => ['系统配置', 'fas fa-cogs']
        ];

        foreach ($menu_items as $key => $item) {
            $active_class = ($current_page == $key) ? 'active' : '';
            $url = $CFG_GLPI['root_doc'] . "/plugins/softwaremanager/front/" . $key . ".php";

            echo "<td class='nav-item-cell {$active_class}' data-page='{$key}'>";
            echo "<a href='{$url}' class='nav-item-link'>";
            echo "<i class='{$item[1]} nav-icon'></i>";
            echo "<span>{$item[0]}</span>";
            echo "</a>";
            echo "</td>";
        }

        echo "</tr>";
        echo "</table>";
        echo "</div>";
    }
}
