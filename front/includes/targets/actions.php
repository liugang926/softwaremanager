<?php
// Handles POST actions for Targets tab

global $DB;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight('plugin_softwaremanager', UPDATE);

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $id                 = (int)($_POST['id'] ?? 0);
        $entities_id        = (int)($_POST['entities_id'] ?? 0);
        $groups_id          = (int)($_POST['groups_id'] ?? 0);
        $recipients_raw     = trim($_POST['recipients_json'] ?? '');
        $target_groups_raw  = trim($_POST['target_groups_json'] ?? '');
        $options_raw        = trim($_POST['options_json'] ?? '');
        $is_active          = isset($_POST['is_active']) ? 1 : 0;

        $recipients_json = $recipients_raw !== '' ? PluginSoftwaremanagerUtils::normalizeJsonString($recipients_raw) : '{}';
        $options_json    = $options_raw   !== '' ? PluginSoftwaremanagerUtils::normalizeJsonString($options_raw)   : '{}';

        $target_groups_json = '[]';
        $tg = json_decode($target_groups_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tg)) {
            $tg = array_values(array_unique(array_map('intval', $tg)));
            $target_groups_json = json_encode($tg, JSON_UNESCAPED_UNICODE);
        }
        $tmp2 = json_decode($options_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp2)) {
            $options_json = json_encode($tmp2, JSON_UNESCAPED_UNICODE);
        }

        $fields = [
            'entities_id'         => $entities_id,
            'groups_id'           => $groups_id,
            'recipients_json'     => $recipients_json,
            'target_groups_json'  => $target_groups_json,
            'options_json'        => $options_json,
            'is_active'           => $is_active,
            'date_mod'            => date('Y-m-d H:i:s')
        ];

        if ($action === 'add') {
            $fields['date_creation'] = date('Y-m-d H:i:s');
            $DB->insert('glpi_plugin_softwaremanager_group_mail_targets', $fields);
            Session::addMessageAfterRedirect(__('Group mail target added', 'softwaremanager'), false, INFO);
        } else {
            $DB->update('glpi_plugin_softwaremanager_group_mail_targets', $fields, ['id' => $id]);
            Session::addMessageAfterRedirect(__('Group mail target updated', 'softwaremanager'), false, INFO);
        }

        $_SESSION['sm_scroll_to_list'] = true;
        Html::redirect($_SERVER['PHP_SELF']);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $DB->delete('glpi_plugin_softwaremanager_group_mail_targets', ['id' => $id]);
            Session::addMessageAfterRedirect(__('Deleted successfully'));
        }
        $_SESSION['sm_scroll_to_list'] = true;
        Html::redirect($_SERVER['PHP_SELF']);
    } elseif ($action === 'toggle') {
        $id    = (int)($_POST['id'] ?? 0);
        $state = (int)($_POST['state'] ?? 0) ? 1 : 0;
        if ($id > 0) {
            $DB->update('glpi_plugin_softwaremanager_group_mail_targets', [
                'is_active' => $state,
                'date_mod'  => date('Y-m-d H:i:s')
            ], ['id' => $id]);
        }
        $_SESSION['sm_scroll_to_list'] = true;
        Html::redirect($_SERVER['PHP_SELF']);
    } elseif ($action === 'preview_target') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $scanId = 0;
            $rs = $DB->query("SELECT id FROM glpi_plugin_softwaremanager_scanhistory WHERE status='completed' ORDER BY id DESC LIMIT 1");
            if ($rs && ($r = $DB->fetchAssoc($rs))) { $scanId = (int)$r['id']; }

            if ($scanId > 0) {
                $t = null;
                $it = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_group_mail_targets', 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
                foreach ($it as $row) { $t = $row; break; }
                if ($t) {
                    $gid  = (int)$t['groups_id'];
                    $opts = json_decode((string)($t['options_json'] ?? '{}'), true);
                    if (!is_array($opts)) { $opts = []; }
                    $scope = isset($opts['scope']) ? (string)$opts['scope'] : 'both';

                    $details = [];
                    $q = "SELECT d.computer_id, d.computer_name, d.software_name, d.software_version, d.compliance_status, c.groups_id, c.groups_id_tech
                          FROM glpi_plugin_softwaremanager_scandetails d
                          LEFT JOIN glpi_computers c ON c.id=d.computer_id
                          WHERE d.scanhistory_id=$scanId";
                    $drs = $DB->query($q);
                    if ($drs) { while ($r = $DB->fetchAssoc($drs)) { $details[] = $r; } }

                    $rows = [];
                    foreach ($details as $d) {
                        $match = false;
                        if ($scope === 'main')      { $match = ((int)($d['groups_id'] ?? 0) === $gid); }
                        elseif ($scope === 'tech')  { $match = ((int)($d['groups_id_tech'] ?? 0) === $gid); }
                        else                        { $match = ((int)($d['groups_id'] ?? 0) === $gid) || ((int)($d['groups_id_tech'] ?? 0) === $gid); }
                        if ($match) { $rows[] = $d; }
                    }

                    $stats = ['total'=>0,'approved'=>0,'blacklisted'=>0,'unmanaged'=>0];
                    foreach ($rows as $r) {
                        $stats['total']++;
                        $cs = (string)($r['compliance_status'] ?? 'unmanaged');
                        if ($cs === 'approved') $stats['approved']++; else if ($cs === 'blacklisted') $stats['blacklisted']++; else $stats['unmanaged']++;
                    }

                    echo "<div class='center'><div class='alert alert-info' style='padding:10px;margin:10px;'>";
                    echo "<div><strong>".sprintf(__('Preview for group ID %d (scope=%s)', 'softwaremanager'), $gid, Html::clean($scope))."</strong></div>";
                    echo "<div style='margin-top:6px;'>".
                         sprintf('总计:%d 合规:%d 违规:%d 未登记:%d', $stats['total'], $stats['approved'], $stats['blacklisted'], $stats['unmanaged']).
                         "</div>";
                    if (!empty($rows)) {
                        echo "<div style='max-height:220px;overflow:auto;margin-top:8px;border:1px solid #ddd;padding:6px;background:#fafafa;'>";
                        $lim = 50; $i=0;
                        foreach ($rows as $r) {
                            $i++; if ($i>$lim) { echo "<div>...".__('more truncated')."</div>"; break; }
                            $label = sprintf('%s (%s) @ %s', Html::clean($r['software_name']), Html::clean($r['software_version']), Html::clean($r['computer_name'] ?? ''));
                            $tag = ($r['compliance_status']==='blacklisted') ? "<span style='color:#dc3545'>[黑]</span>" : (($r['compliance_status']==='approved') ? "<span style='color:#28a745'>[合]</span>" : "<span style='color:#ff9800'>[未]</span>");
                            echo "<div>$tag $label</div>";
                        }
                        echo "</div>";
                    }
                    echo "</div></div>";
                }
            } else {
                echo "<div class='center'><div class='alert alert-warning' style='padding:10px;margin:10px;'>".
                     __('No completed scan found for preview', 'softwaremanager')."</div></div>";
            }
        }
        // Do not redirect; let preview display inline
    } elseif ($action === 'send_test') {
        $id = (int)($_POST['id'] ?? 0);
        $email_raw = (string)($_POST['test_email'] ?? '');

        $email = PluginSoftwaremanagerUtils::normalizeEmail($email_raw);

        if (($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) && $id > 0) {
            $t = null;
            $it = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_group_mail_targets', 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
            foreach ($it as $row) { $t = $row; break; }
            if ($t) {
                $spec = json_decode((string)($t['recipients_json'] ?? ''), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $tmp = strtr((string)($t['recipients_json'] ?? ''), ['，' => ',', '“' => '"', '”' => '"', '：' => ':']);
                    $spec = json_decode($tmp, true);
                }
                if (is_array($spec) && !empty($spec['emails']) && is_array($spec['emails'])) {
                    $fallback = PluginSoftwaremanagerUtils::normalizeEmail((string)$spec['emails'][0]);
                    if (filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                        $email = $fallback;
                    }
                }
            }
        }

        if (($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) && Session::getLoginUserID()) {
            $uid = (int)Session::getLoginUserID();
            $qe = $DB->query("SELECT email FROM glpi_useremails WHERE users_id=".$uid." ORDER BY is_default DESC, id ASC LIMIT 1");
            if ($qe && ($er = $DB->fetchAssoc($qe))) {
                $fallback = PluginSoftwaremanagerUtils::normalizeEmail((string)$er['email']);
                if (filter_var($fallback, FILTER_VALIDATE_EMAIL)) { $email = $fallback; }
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $qu = $DB->query("SELECT email FROM glpi_users WHERE id=".$uid." LIMIT 1");
                if ($qu && ($ur = $DB->fetchAssoc($qu))) {
                    $fallback = PluginSoftwaremanagerUtils::normalizeEmail((string)$ur['email']);
                    if (filter_var($fallback, FILTER_VALIDATE_EMAIL)) { $email = $fallback; }
                }
            }
        }

        if ($id > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            include_once(__DIR__ . '/../../../inc/automailer.class.php');
            $ok = PluginSoftwaremanagerAutomailer::sendTestForTarget($id, $email);
            $msg = '';
            $type = INFO;
            if ($ok) {
                $qr = $DB->query("SELECT id, sendtime, state, errormessage FROM glpi_queuednotifications WHERE recipient LIKE '%".$DB->escape($email)."%' ORDER BY id DESC LIMIT 1");
                if ($qr && ($row = $DB->fetchAssoc($qr))) {
                    $state = (int)($row['state'] ?? 0);
                    if ($state === 2) {
                        $msg = sprintf(__('Delivered to queue; last state: sent. ID=%d', 'softwaremanager'), (int)$row['id']);
                        $type = INFO;
                    } elseif ($state === 1) {
                        $msg = sprintf(__('Test email enqueued to %s (queued)', 'softwaremanager'), $email);
                        $type = INFO;
                    } else {
                        $err = (string)($row['errormessage'] ?? '');
                        $msg = sprintf(__('Queued but not sent yet. State=%d %s', 'softwaremanager'), $state, $err);
                        $type = WARNING;
                    }
                } else {
                    $msg = __('Enqueued (pending); run queuednotification to send', 'softwaremanager');
                    $type = INFO;
                }
            } else {
                $msg = __('No data found for preview or enqueue failed', 'softwaremanager');
                $type = WARNING;
            }
            Session::addMessageAfterRedirect($msg, false, $type);
            $_SESSION['sm_scroll_to_list'] = true;
            Html::redirect($_SERVER['PHP_SELF']);
        } else {
            Session::addMessageAfterRedirect(__('Invalid email address', 'softwaremanager') . ': ' . Html::clean($email_raw), false, WARNING);
            $_SESSION['sm_scroll_to_list'] = true;
            Html::redirect($_SERVER['PHP_SELF']);
        }
    } elseif ($action === 'preview_recipients') {
        include_once(__DIR__ . '/../../../inc/automailer.class.php');
        $spec = ['users'=>[], 'groups'=>[], 'profiles'=>[], 'emails'=>[]];
        if (isset($edit_row) && $edit_row) {
            $tmp = json_decode((string)($edit_row['recipients_json'] ?? ''), true);
            if (is_array($tmp)) { $spec = array_merge($spec, $tmp); }
        }
        $emails = PluginSoftwaremanagerAutomailer::resolveRecipientEmails($spec);
        if (empty($emails)) {
            Session::addMessageAfterRedirect(__('No recipients resolved (check users/groups profiles emails).','softwaremanager'), false, WARNING);
        } else {
            Session::addMessageAfterRedirect(__('Resolved recipients: ','softwaremanager') . implode(', ', array_map('Html::clean', $emails)), false, INFO);
        }
        $_SESSION['sm_scroll_to_list'] = true;
        Html::redirect($_SERVER['PHP_SELF']);
    }
}


