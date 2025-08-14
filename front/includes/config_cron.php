<?php
// Cron tab content (extracted from front/config.php)

// Auto-scan information card
echo "<div class='tab_cadre_fixe' style='margin-top:20px;'>";
echo "<div class='center'><h3>" . __('Auto Scan (GLPI automated actions)', 'softwaremanager') . "</h3></div>";

// Handle manual run
if (isset($_POST['run_autoscan'])) {
    include_once(__DIR__ . '/../../inc/compliance_runner.class.php');
    try {
        $stats = PluginSoftwaremanagerComplianceRunner::run();
        echo "<div class='center'><div class='alert alert-success' style='padding:8px;margin:10px;'>";
        echo sprintf(__('Auto scan completed. Total:%1$s, Approved:%2$s, Blacklisted:%3$s, Unmanaged:%4$s', 'softwaremanager'),
                      (int)($stats['total']??0),(int)($stats['approved']??0),(int)($stats['blacklisted']??0),(int)($stats['unmanaged']??0));
        echo "</div></div>";
    } catch (Throwable $e) {
        echo "<div class='center'><div class='alert alert-danger' style='padding:8px;margin:10px;'>";
        echo Html::clean($e->getMessage());
        echo "</div></div>";
    }
}

// Read cron tasks (correct itemtype = plugin cron class names)
$autoscan = new CronTask();
$hasAutoscan = $autoscan->getFromDBByName('PluginSoftwaremanagerAutoscan', 'softwaremanager_autoscan');
$automailer = new CronTask();
$hasAutomailer = $automailer->getFromDBByName('PluginSoftwaremanagerAutomailer', 'softwaremanager_autoscan_mailer');

// Helper to print one task's info
$printTask = function(string $label, CronTask $t, bool $ok) {
    echo "<table class='tab_cadre_fixe' style='width:100%; margin-top:10px;'>";
    echo "<tr class='tab_bg_1'><th style='width:220px;'>" . __('Task name') . "</th><td>" . Html::clean($label) . "</td></tr>";
    if ($ok) {
        $stateMap = [CronTask::STATE_DISABLE => __('Disabled'), CronTask::STATE_ENABLE => __('Enabled'), CronTask::STATE_RUNNING => __('Running')];
        $state = $stateMap[$t->fields['state']] ?? __('Unknown');
        $freq  = (int)($t->fields['frequency'] ?? 0);
        $freqLabel = $freq >= WEEK_TIMESTAMP ? __('Weekly') : ($freq >= DAY_TIMESTAMP ? __('Daily') : sprintf(__('%s seconds'), $freq));
        $lastrun = !empty($t->fields['lastrun']) ? Html::convDateTime($t->fields['lastrun']) : __('Never');
        $nextrunraw = $t->fields['nextrun'] ?? '';
        $nextFriendly = __('Not available');
        if (!empty($nextrunraw)) {
            $ts = is_numeric($nextrunraw) ? (int)$nextrunraw : strtotime($nextrunraw);
            if ($ts) {
                $timeStr = date('H:i', $ts);
                if ($freq >= WEEK_TIMESTAMP) {
                    $dow = (int)date('N', $ts);
                    $map = [1=>'周一',2=>'周二',3=>'周三',4=>'周四',5=>'周五',6=>'周六',7=>'周日'];
                    $nextFriendly = sprintf('每%s %s', $map[$dow] ?? date('D', $ts), $timeStr);
                } elseif ($freq >= (28*DAY_TIMESTAMP)) {
                    $day = (int)date('j', $ts);
                    $nextFriendly = sprintf('每月 %d 日 %s', $day, $timeStr);
                } elseif ($freq >= DAY_TIMESTAMP) {
                    $nextFriendly = sprintf('每天 %s', $timeStr);
                } else {
                    $nextFriendly = Html::convDateTime(date('Y-m-d H:i:s', $ts));
                }
            }
        } else {
            $nextFriendly = __('Computed by GLPI');
        }
        $comment = Html::clean($t->fields['comment'] ?? '');

        echo "<tr class='tab_bg_1'><th>" . __('State') . "</th><td>" . $state . "</td></tr>";
        echo "<tr class='tab_bg_1'><th>" . __('Frequency') . "</th><td>" . $freqLabel . " (" . $freq . "s)</td></tr>";
        echo "<tr class='tab_bg_1'><th>" . __('Last run') . "</th><td>" . $lastrun . "</td></tr>";
        echo "<tr class='tab_bg_1'><th>" . __('Next run', 'softwaremanager') . "</th><td>" . Html::clean($nextFriendly) . "</td></tr>";
        if (!empty($comment)) {
            echo "<tr class='tab_bg_1'><th>" . __('Comment') . "</th><td>" . $comment . "</td></tr>";
        }
    } else {
        echo "<tr class='tab_bg_1'><td colspan='2' class='center'>" . __('Cron task not registered yet. Reinstall plugin or contact admin.', 'softwaremanager') . "</td></tr>";
    }
    echo "</table>";
};

$printTask('softwaremanager_autoscan (PluginSoftwaremanagerAutoscan)', $autoscan, $hasAutoscan);
$printTask('softwaremanager_autoscan_mailer (PluginSoftwaremanagerAutomailer)', $automailer, $hasAutomailer);
echo "</div>"; // card

// Actions row only for cron tab
echo "<div class='center' style='margin-top:15px;'>";
echo "<form method='post' style='display:inline-block;margin-right:10px;'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
echo "<button class='vsubmit' name='run_autoscan' value='1'><i class='fas fa-play'></i> " . __('Run now', 'softwaremanager') . "</button>";
echo "</form>";
echo "<a class='vsubmit' style='margin-right:10px;' href='" . $CFG_GLPI['root_doc'] . "/front/crontask.php'>" . __('Open automated actions', 'softwaremanager') . "</a>";
echo "<a class='vsubmit' href='scanhistory.php'>" . __('Open scan history', 'softwaremanager') . "</a>";
echo "</div>";


