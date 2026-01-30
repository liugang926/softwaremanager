<?php
/**
 * English translation - Software Manager Plugin for GLPI
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 * @package GLPI\Plugin\Softwaremanager
 */

global $LANG;

$LANG['softwaremanager']['menu']['softwarelist'] = 'Software Inventory';
$LANG['softwaremanager']['menu']['scanhistory'] = 'Scan History';
$LANG['softwaremanager']['menu']['whitelist'] = 'Whitelist Management';
$LANG['softwaremanager']['menu']['blacklist'] = 'Blacklist Management';
$LANG['softwaremanager']['menu']['import'] = 'Import/Export';
$LANG['softwaremanager']['menu']['config'] = 'Plugin Configuration';
$LANG['softwaremanager']['menu']['analytics'] = 'Analytics Reports';

$LANG['softwaremanager']['config']['tab_cron'] = 'Automated Actions';
$LANG['softwaremanager']['config']['tab_targets'] = 'Report Targets';
$LANG['softwaremanager']['config']['tab_help'] = 'Help';

// Scan related
$LANG['softwaremanager']['scan']['title'] = 'Software Compliance Scan';
$LANG['softwaremanager']['scan']['running'] = 'Scan running...';
$LANG['softwaremanager']['scan']['completed'] = 'Scan completed';
$LANG['softwaremanager']['scan']['failed'] = 'Scan failed';
$LANG['softwaremanager']['scan']['no_data'] = 'No scan data available';
$LANG['softwaremanager']['scan']['latest_scan'] = 'Latest scan';

// Common vocabulary
$LANG['softwaremanager']['common']['total'] = 'Total';
$LANG['softwaremanager']['common']['approved'] = 'Approved';
$LANG['softwaremanager']['common']['whitelist'] = 'Whitelist';
$LANG['softwaremanager']['common']['blacklisted'] = 'Blacklisted';
$LANG['softwaremanager']['common']['blacklist'] = 'Blacklist';
$LANG['softwaremanager']['common']['unmanaged'] = 'Unmanaged';
$LANG['softwaremanager']['common']['software'] = 'Software';
$LANG['softwaremanager']['common']['version'] = 'Version';
$LANG['softwaremanager']['common']['computer'] = 'Computer';
$LANG['softwaremanager']['common']['user'] = 'User';
$LANG['softwaremanager']['common']['group'] = 'Group';
$LANG['softwaremanager']['common']['entity'] = 'Entity';
$LANG['softwaremanager']['common']['actions'] = 'Actions';
$LANG['softwaremanager']['common']['add'] = 'Add';
$LANG['softwaremanager']['common']['edit'] = 'Edit';
$LANG['softwaremanager']['common']['delete'] = 'Delete';
$LANG['softwaremanager']['common']['save'] = 'Save';
$LANG['softwaremanager']['common']['cancel'] = 'Cancel';
$LANG['softwaremanager']['common']['search'] = 'Search';
$LANG['softwaremanager']['common']['export'] = 'Export';
$LANG['softwaremanager']['common']['import'] = 'Import';
$LANG['softwaremanager']['common']['confirm_delete'] = 'Confirm delete? This action cannot be undone.';
$LANG['softwaremanager']['common']['status'] = 'Status';
$LANG['softwaremanager']['common']['enabled'] = 'Enabled';
$LANG['softwaremanager']['common']['disabled'] = 'Disabled';
$LANG['softwaremanager']['common']['yes'] = 'Yes';
$LANG['softwaremanager']['common']['no'] = 'No';
$LANG['softwaremanager']['common']['all'] = 'All';
$LANG['softwaremanager']['common']['none'] = 'None';
$LANG['softwaremanager']['common']['name'] = 'Name';
$LANG['softwaremanager']['common']['date'] = 'Date';
$LANG['softwaremanager']['common']['description'] = 'Description';
$LANG['softwaremanager']['common']['comment'] = 'Comment';

// Error messages
$LANG['softwaremanager']['error']['not_found'] = 'Record not found';
$LANG['softwaremanager']['error']['no_permission'] = 'Permission denied';
$LANG['softwaremanager']['error']['invalid_input'] = 'Invalid input';
$LANG['softwaremanager']['error']['database_error'] = 'Database error';
$LANG['softwaremanager']['error']['scan_failed'] = 'Scan failed';
$LANG['softwaremanager']['error']['file_not_found'] = 'File not found';
$LANG['softwaremanager']['error']['invalid_file'] = 'Invalid file format';

// Success messages
$LANG['softwaremanager']['success']['saved'] = 'Saved successfully';
$LANG['softwaremanager']['success']['deleted'] = 'Deleted successfully';
$LANG['softwaremanager']['success']['imported'] = 'Import completed';
$LANG['softwaremanager']['success']['exported'] = 'Export completed';
$LANG['softwaremanager']['success']['scan_completed'] = 'Scan completed';
$LANG['softwaremanager']['success']['settings_updated'] = 'Settings updated';

// Page titles
$LANG['softwaremanager']['title']['plugin_configuration'] = 'Plugin Configuration';
$LANG['softwaremanager']['title']['software_list'] = 'Software Inventory';
$LANG['softwaremanager']['title']['scan_history'] = 'Scan History';
$LANG['softwaremanager']['title']['whitelist_management'] = 'Whitelist Management';
$LANG['softwaremanager']['title']['blacklist_management'] = 'Blacklist Management';
$LANG['softwaremanager']['title']['import_export'] = 'Import/Export';
$LANG['softwaremanager']['title']['analytics'] = 'Analytics Reports';

// Cron task names
$LANG['softwaremanager']['cron']['autoscan'] = 'Auto Software Compliance Scan';
$LANG['softwaremanager']['cron']['automailer'] = 'Auto Send Compliance Report Emails';

// Notification messages
$LANG['softwaremanager']['notification']['report_subject'] = '[GLPI] Software Compliance Report';
$LANG['softwaremanager']['notification']['group_report'] = 'Group Compliance Report';
$LANG['softwaremanager']['notification']['computer_report'] = 'Computer Violation Reminder';

// Config options
$LANG['softwaremanager']['config']['enable_autoscan'] = 'Enable auto scan';
$LANG['softwaremanager']['config']['scan_interval'] = 'Scan interval';
$LANG['softwaremanager']['config']['enable_notifications'] = 'Enable notifications';
$LANG['softwaremanager']['config']['notification_email'] = 'Notification email';

// Button labels
$LANG['softwaremanager']['button']['run_scan'] = 'Run Scan';
$LANG['softwaremanager']['button']['view_details'] = 'View Details';
$LANG['softwaremanager']['button']['download_report'] = 'Download Report';
$LANG['softwaremanager']['button']['send_test_email'] = 'Send Test Email';

// Help text
$LANG['softwaremanager']['help']['intro'] = 'The Software Manager plugin helps you manage software compliance in GLPI.';
$LANG['softwaremanager']['help']['whitelist_desc'] = 'Whitelist allows you to mark approved software.';
$LANG['softwaremanager']['help']['blacklist_desc'] = 'Blacklist allows you to mark prohibited software.';
$LANG['softwaremanager']['help']['scan_desc'] = 'The scan feature detects all software on computers and compares it against rules.';
