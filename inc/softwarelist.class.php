<?php
/**
 * Software Manager Plugin for GLPI
 * Software List Class for Search Interface
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Software List class for search interface
 */
class PluginSoftwaremanagerSoftwareList extends CommonDBTM {

    static $rightname = 'plugin_softwaremanager';

    /**
     * Get type name
     *
     * @param integer $nb Number of items
     *
     * @return string
     */
    static function getTypeName($nb = 0) {
        return _n('Software Inventory', 'Software Inventories', $nb, 'softwaremanager');
    }

    /**
     * Get search options
     *
     * @return array
     */
    function getSearchOptions() {
        $tab = [];

        $tab['common'] = __('Characteristics');

        $tab[1]['table']         = 'glpi_softwares';
        $tab[1]['field']         = 'name';
        $tab[1]['name']          = __('Software Name');
        $tab[1]['datatype']      = 'text';
        $tab[1]['massiveaction'] = false;

        $tab[2]['table']         = 'glpi_softwares';
        $tab[2]['field']         = 'id';
        $tab[2]['name']          = __('ID');
        $tab[2]['massiveaction'] = false;
        $tab[2]['datatype']      = 'number';

        $tab[3]['table']         = 'glpi_manufacturers';
        $tab[3]['field']         = 'name';
        $tab[3]['name']          = __('Manufacturer');
        $tab[3]['datatype']      = 'dropdown';
        $tab[3]['joinparams']    = [
            'jointype' => 'LEFT'
        ];

        $tab[4]['table']         = 'glpi_softwares';
        $tab[4]['field']         = 'comment';
        $tab[4]['name']          = __('Comments');
        $tab[4]['datatype']      = 'text';

        $tab[5]['table']         = 'glpi_softwares';
        $tab[5]['field']         = 'is_deleted';
        $tab[5]['name']          = __('Deleted');
        $tab[5]['datatype']      = 'bool';
        $tab[5]['massiveaction'] = false;

        return $tab;
    }

    /**
     * Get table name for this class
     *
     * @return string
     */
    static function getTable($classname = null) {
        return 'glpi_softwares';
    }

    /**
     * Get additional search criteria
     *
     * @return array
     */
    static function getDefaultSearchRequest() {
        return [
            'criteria' => [
                [
                    'field' => 5,
                    'searchtype' => 'equals',
                    'value' => 0
                ]
            ],
            'sort' => 1,
            'order' => 'ASC'
        ];
    }

    /**
     * Get search URL
     *
     * @return string
     */
    static function getSearchURL($full = true) {
        global $CFG_GLPI;

        $itemtype = get_called_class();
        $link = $CFG_GLPI["root_doc"] . "/plugins/softwaremanager/front/softwarelist.php";

        if ($full) {
            $link .= "?itemtype=" . $itemtype;
        }

        return $link;
    }

    /**
     * Check if user can view this item type
     *
     * @return boolean
     */
    static function canView() {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Check if user can create this item type
     *
     * @return boolean
     */
    static function canCreate() {
        return Session::haveRight(self::$rightname, CREATE);
    }
}
