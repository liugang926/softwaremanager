<?php
// Renders targets table with SQL pagination

global $DB, $entities, $groups, $sm_csrf_token;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Total count
$totalCount = 0;
$rsCount = $DB->query("SELECT COUNT(*) AS cnt FROM glpi_plugin_softwaremanager_group_mail_targets");
if ($rsCount && ($rc = $DB->fetchAssoc($rsCount))) { $totalCount = (int)$rc['cnt']; }
$totalPages = max(1, (int)ceil($totalCount / $perPage));

// Page rows
$rows = [];
$req = $DB->request([
    'SELECT' => ['id','entities_id','groups_id','recipients_json','target_groups_json','options_json','is_active'],
    'FROM'   => 'glpi_plugin_softwaremanager_group_mail_targets',
    'ORDER'  => 'entities_id, groups_id',
    'START'  => $offset,
    'LIMIT'  => $perPage
]);
foreach ($req as $row) { $rows[] = $row; }

echo "<div class='sm-list-controls' style='margin-bottom:10px; padding:10px; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px;'>";
echo "<div style='display:flex; gap:10px; align-items:center; flex-wrap:wrap;'>";

echo "<div style='flex:1; min-width:200px;'><input type='text' id='sm-search' placeholder='" . __('Search targets...', 'softwaremanager') . "' style='width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'></div>";

echo "<div><select id='sm-filter-entity' style='padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'><option value=''>" . __('All Entities') . "</option>";
foreach ($entities as $eid => $ename) { echo "<option value='".(int)$eid."'>".Html::clean($ename)."</option>"; }
echo "</select></div>";

echo "<div><select id='sm-filter-status' style='padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'><option value=''>" . __('All Status') . "</option><option value='1'>" . __('Active') . "</option><option value='0'>" . __('Inactive') . "</option></select></div>";

echo "<div id='sm-results-info' style='color:#6b7280; font-size:13px;'><span id='sm-visible-count'>0</span> / <span id='sm-total-count'>".$totalCount."</span> " . __('records') . "</div>";
echo "</div></div>";

echo "<table class='sm-list'>";
echo "<thead><tr><th class='sm-col-id'>ID</th><th class='sm-col-entity'>" . __('Entity') . "</th><th class='sm-col-group'>" . __('Group') . "</th><th class='sm-col-active'>" . __('Active') . "</th><th class='sm-col-rec'>" . __('Recipients') . "</th><th class='sm-col-opt'>" . __('Options') . "</th><th class='sm-col-actions'>" . __('Actions') . "</th><th class='sm-col-test'>" . __('Test') . "</th></tr></thead><tbody>";

if (!empty($rows)) {
    foreach ($rows as $r) {
        $eid = (int)$r['entities_id'];
        $gid = (int)$r['groups_id'];
        $ename = $entities[$eid] ?? (string)$eid;
        $gname = $groups[$gid] ?? (string)$gid;

        $targetPills = '';
        $tglist = [];
        $tgj = (string)($r['target_groups_json'] ?? '');
        if ($tgj !== '') {
            $tgarr = json_decode($tgj, true);
            if (is_array($tgarr)) {
                foreach ($tgarr as $tid) {
                    $tname = Dropdown::getDropdownName('glpi_groups', (int)$tid);
                    if ($tname && $tname !== '&nbsp;') { $tglist[] = '<span class="sm-pill">'.Html::clean($tname).'</span>'; }
                }
            }
        }
        if (!empty($tglist)) { $targetPills = '<div class="sm-badges" style="margin-top:4px;">'.implode(' ', $tglist).'</div>'; }
        $activeBadge = ((int)$r['is_active'] === 1) ? "<span class='badge b-green'>ON</span>" : "<span class='badge b-amber'>OFF</span>";

        $searchData = strtolower($ename . ' ' . $gname);
        echo "<tr class='sm-list-row' data-entity-id='" . $eid . "' data-status='" . (int)$r['is_active'] . "' data-search='" . Html::clean($searchData) . "'>";
        echo "<td>" . (int)$r['id'] . "</td>";
        echo "<td>" . Html::clean($ename) . "</td>";
        echo "<td>" . Html::clean($gname) . $targetPills . "</td>";
        echo "<td>" . $activeBadge . "</td>";

        $rec_arr = json_decode((string)$r['recipients_json'], true);
        if (!is_array($rec_arr)) { $rec_arr = []; }
        echo "<td class='sm-col-rec'>";

        $segments = [];
        $shorten = [PluginSoftwaremanagerUtils::class, 'shortenList'];

        if (!empty($rec_arr['users']) && is_array($rec_arr['users'])) {
            $uids = array_values(array_unique(array_filter(array_map('intval', (array)$rec_arr['users']), function($id) { return $id > 0; })));
            if (!empty($uids)) {
                $labels = [];
                $emailsByUid = [];
                foreach ($uids as $uid) {
                    $label = '';
                    $user = new User();
                    if ($user->getFromDB($uid)) {
                        $rn = trim($user->fields['realname'] ?? '');
                        $fn = trim($user->fields['firstname'] ?? '');
                        if ($rn !== '' && $fn !== '') { $label = $rn.' '.$fn; }
                        elseif ($rn !== '')       { $label = $rn; }
                        elseif ($fn !== '')       { $label = $fn; }
                        else                      { $label = $user->fields['name'] ?? ''; }
                        $e = trim($user->fields['email'] ?? '');
                        if ($e !== '') { $emailsByUid[$uid][$e] = true; }
                    }
                    if (empty($label)) {
                        $dropdownName = Dropdown::getDropdownName('glpi_users', $uid);
                        if ($dropdownName && $dropdownName !== '&nbsp;') { $label = $dropdownName; }
                    }
                    if (empty($label)) { $label = 'User #' . $uid; }
                    $labels[$uid] = $label;
                }
                foreach ($uids as $uid) {
                    $userEmail = new UserEmail();
                    $emails = $userEmail->find(['users_id' => $uid]);
                    foreach ($emails as $emailData) {
                        $em = trim($emailData['email'] ?? '');
                        if ($em !== '') { $emailsByUid[$uid][$em] = true; }
                    }
                }
                $nameList = [];
                $titleParts = [];
                foreach ($uids as $uid) {
                    $lbl = $labels[$uid] ?? ('#'.$uid);
                    $nameList[] = $lbl;
                    $ems = isset($emailsByUid[$uid]) ? array_keys($emailsByUid[$uid]) : [];
                    $titleParts[] = $lbl.': '.(!empty($ems) ? implode(', ', $ems) : '无邮箱');
                }
                if (!empty($nameList)) {
                    $title = Html::clean(implode(' | ', $titleParts));
                    $short = Html::clean(call_user_func($shorten, $nameList, 2));
                    $segments[] = '<span class="sm-pill" data-title="'.$title.'">用户: '.$short.'</span>';
                }
            }
        }

        if (!empty($rec_arr['groups']) && is_array($rec_arr['groups'])) {
            $gids = array_values(array_unique(array_map('intval', (array)$rec_arr['groups'])));
            foreach ($gids as $gid2) {
                $gname2 = $groups[$gid2] ?? ('#'.$gid2);
                $uids2 = [];
                $rg = $DB->query("SELECT users_id FROM glpi_groups_users WHERE groups_id=".(int)$gid2);
                if ($rg) { while ($gr = $DB->fetchAssoc($rg)) { $uids2[] = (int)$gr['users_id']; } }
                $uids2 = array_values(array_unique(array_filter($uids2)));
                $emails = [];
                if (!empty($uids2)) {
                    $in2 = implode(',', $uids2);
                    $ru = $DB->query("SELECT email FROM glpi_users WHERE id IN ($in2)");
                    if ($ru) { while ($u = $DB->fetchAssoc($ru)) { $em = trim((string)($u['email'] ?? '')); if ($em !== '') { $emails[$em] = true; } } }
                    $re = $DB->query("SELECT email FROM glpi_useremails WHERE users_id IN ($in2)");
                    if ($re) { while ($ue = $DB->fetchAssoc($re)) { $em = trim((string)($ue['email'] ?? '')); if ($em !== '') { $emails[$em] = true; } } }
                }
                $list = array_keys($emails);
                $preview = implode(', ', array_slice($list, 0, 20));
                if (count($list) > 20) { $preview .= ' …(+'.(count($list)-20).')'; }
                $title = Html::clean(($gname2 ?: 'Group').': '.($preview !== '' ? $preview : '无邮箱'));
                $segments[] = '<span class="sm-pill" data-title="'.$title.'">群组: '.Html::clean($gname2).'</span>';
            }
        }

        if (!empty($rec_arr['emails']) && is_array($rec_arr['emails'])) {
            $title = Html::clean(implode(', ', (array)$rec_arr['emails']));
            $short = Html::clean(call_user_func($shorten, (array)$rec_arr['emails'], 2));
            $segments[] = '<span class="sm-pill" data-title="'.$title.'">邮箱: '.$short.'</span>';
        }

        if (empty($segments)) { echo '<em>无收件人</em>'; }
        else { echo implode(' ', $segments); }

        echo "</td>";

        $opt_arr = json_decode((string)$r['options_json'], true);
        if (!is_array($opt_arr)) { $opt_arr = []; }
        echo "<td class='sm-col-opt'>";
        $option_parts = [];
        if (isset($opt_arr['only_on_violation'])) { $option_parts[] = '<strong>触发:</strong> '.($opt_arr['only_on_violation'] ? '仅违规' : '总是'); }
        if (isset($opt_arr['merge'])) { $option_parts[] = '<strong>方式:</strong> '.($opt_arr['merge'] ? '合并' : '分别'); }
        if (!empty($opt_arr['scope'])) { $scope_labels = ['main'=>'主要','tech'=>'技术','both'=>'两种']; $option_parts[] = '<strong>范围:</strong> '.($scope_labels[$opt_arr['scope']] ?? Html::clean($opt_arr['scope'])); }
        if (isset($opt_arr['threshold_unmanaged']) && (int)$opt_arr['threshold_unmanaged'] > 0) { $option_parts[] = '<strong>未登记阈值:</strong> ≥ '.(int)$opt_arr['threshold_unmanaged']; }
        if (isset($opt_arr['attach_csv'])) { $option_parts[] = '<strong>CSV:</strong> '.($opt_arr['attach_csv'] ? '附加' : '不附加'); }
        if (empty($option_parts)) echo '<em>默认选项</em>'; else echo implode(' <span style="color:#9ca3af">|</span> ', $option_parts);
        echo "</td>";

        echo "<td><div class='sm-actions-horiz'>";
        echo "<a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "?edit_id=" . (int)$r['id'] . "' style='margin-right:6px;'><i class='fas fa-edit'></i> " . __('Edit') . "</a>";
        echo "<form method='post' style='display:inline-block;' onsubmit=\"return confirm('".__('Confirm the final deletion?')."');\">";
        echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
        echo Html::hidden('action', ['value' => 'delete']);
        echo Html::hidden('id', ['value' => (int)$r['id']]);
        echo "<button class='vsubmit' type='submit' style='background:#dc3545;border-color:#dc3545;'><i class='fas fa-trash'></i> " . __('Delete') . "</button>";
        echo "</form></div></td>";

        echo "<td><form method='post'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
        echo Html::hidden('action', ['value' => 'send_test']);
        echo Html::hidden('id', ['value' => (int)$r['id']]);
        echo "<input type='email' name='test_email' placeholder='you@example.com' style='height:28px;padding:4px 6px;width:100%;margin-bottom:4px;'>";
        echo "<button class='vsubmit' type='submit' style='width:100%;'><i class='fas fa-paper-plane'></i> " . __('Send test') . "</button>";
        echo "</form></td>";

        echo "</tr>";
    }
} else {
    echo "<tr class='tab_bg_1'><td colspan='8' class='center' style='padding:20px; color:#6b7280;'><i class='fas fa-inbox' style='font-size:24px; margin-bottom:8px; display:block;'></i>".
         __('No mail targets configured yet.', 'softwaremanager') . "<br><small>" . __('Click \"Add\" button above to create your first mail target.', 'softwaremanager') . "</small></td></tr>";
}

echo "</tbody></table>";

if ($totalPages > 1) {
    echo "<div class='sm-pagination' style='margin-top:15px; text-align:center;'>";
    $baseUrl = $_SERVER['PHP_SELF'] . '?';
    if ($page > 1) { echo "<a href='" . $baseUrl . "page=" . ($page - 1) . "#sm-target-list' class='sm-page-btn'>&laquo; " . __('Previous') . "</a>"; }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) { echo "<a href='" . $baseUrl . "page=1#sm-target-list' class='sm-page-btn'>1</a>"; if ($start > 2) echo "<span class='sm-page-dots'>...</span>"; }
    for ($i = $start; $i <= $end; $i++) { $class = ($i == $page) ? 'sm-page-btn active' : 'sm-page-btn'; echo "<a href='" . $baseUrl . "page=" . $i . "#sm-target-list' class='" . $class . "'>" . $i . "</a>"; }
    if ($end < $totalPages) { if ($end < $totalPages - 1) echo "<span class='sm-page-dots'>...</span>"; echo "<a href='" . $baseUrl . "page=" . $totalPages . "#sm-target-list' class='sm-page-btn'>" . $totalPages . "</a>"; }
    if ($page < $totalPages) { echo "<a href='" . $baseUrl . "page=" . ($page + 1) . "#sm-target-list' class='sm-page-btn'>" . __('Next') . " &raquo;</a>"; }
    echo "<div class='sm-page-info' style='margin-top:8px; color:#6b7280; font-size:12px;'>".
         sprintf(__('Page %d of %d (%d total records)', 'softwaremanager'), $page, $totalPages, $totalCount) . "</div>";
    echo "</div>";
}


