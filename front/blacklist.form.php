<?php
/**
 * Software Manager Plugin for GLPI
 * Blacklist Form Page
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights
Session::checkRight('plugin_softwaremanager', READ);

$blacklist = new PluginSoftwaremanagerSoftwareBlacklist();

if (isset($_POST["add"])) {
    $blacklist->check(-1, CREATE, $_POST);
    if ($newID = $blacklist->add($_POST)) {
        Event::log($newID, "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
                   sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($blacklist->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST["delete"])) {
    $blacklist->check($_POST["id"], DELETE);
    $blacklist->delete($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s deletes an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["restore"])) {
    $blacklist->check($_POST["id"], DELETE);
    $blacklist->restore($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s restores an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["purge"])) {
    $blacklist->check($_POST["id"], PURGE);
    $blacklist->delete($_POST, 1);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
    $blacklist->redirectToList();

} else if (isset($_POST["update"])) {
    $blacklist->check($_POST["id"], UPDATE);
    $blacklist->update($_POST);
    Event::log($_POST["id"], "PluginSoftwaremanagerSoftwareBlacklist", 4, "setup",
               sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
    Html::back();

} else {
    $menus = ["admin", "PluginSoftwaremanagerMenu"];
    PluginSoftwaremanagerSoftwareBlacklist::displayFullPageForItem($_GET["id"], $menus, [
        'formoptions'  => "method='post'"
    ]);
}
