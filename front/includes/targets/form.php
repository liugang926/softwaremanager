<?php
// Renders the add/edit modal form for Targets

global $DB, $CFG_GLPI, $sm_csrf_token, $edit_row;

// Fetch selects
$entities = [];
$res = $DB->query("SELECT id, name FROM glpi_entities ORDER BY name");
if ($res) { while ($row = $DB->fetchAssoc($res)) { $entities[(int)$row['id']] = $row['name']; } }

$groups = [];
$res = $DB->query("SELECT g.id, g.name, e.name AS ename FROM glpi_groups g LEFT JOIN glpi_entities e ON e.id=g.entities_id ORDER BY e.name, g.name");
if ($res) { while ($row = $DB->fetchAssoc($res)) { $groups[(int)$row['id']] = $row['name'] . ' [' . ($row['ename'] ?? '') . ']'; } }

// Prefill
$pre_users = $pre_groups = $pre_profiles = $pre_emails = $pre_target_groups = [];
$opt_pref = ['only_on_violation'=>true,'threshold_unmanaged'=>0,'merge'=>true,'scope'=>'both','attach_csv'=>false];

if ($edit_row) {
    $spec = json_decode((string)($edit_row['recipients_json'] ?? ''), true);
    if (is_array($spec)) {
        $pre_users    = array_map('intval', (array)($spec['users'] ?? []));
        $pre_groups   = array_map('intval', (array)($spec['groups'] ?? []));
        $pre_profiles = array_map('intval', (array)($spec['profiles'] ?? []));
        $pre_emails   = (array)($spec['emails'] ?? []);
    }
    $tgj   = (string)($edit_row['target_groups_json'] ?? '[]');
    $tgarr = json_decode($tgj, true);
    if (is_array($tgarr)) { $pre_target_groups = array_map('intval', $tgarr); }

    $opts = json_decode((string)($edit_row['options_json'] ?? ''), true);
    if (is_array($opts)) { $opt_pref = array_merge($opt_pref, $opts); }
}

echo "<div class='center' style='margin:6px 0 10px 0;'>";
echo "<button class='vsubmit' id='sm-open-modal-add'><i class='fas fa-plus'></i> " . __('Add') . "</button>";
if ($edit_row) {
   echo " <button class='vsubmit' id='sm-open-modal-edit'><i class='fas fa-edit'></i> " . __('Edit') . "</button>";
   echo " <a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "#sm-target-list'>".__('Cancel')."</a>";
}
echo "</div>";

echo "<div class='sm-modal-overlay" . ($edit_row ? " show" : "") . "' id='sm-modal'>";
echo " <div class='sm-modal'>";
echo "  <div class='sm-modal-header'><div class='sm-modal-title' id='sm-modal-title'>" . ($edit_row ? __('Edit group mail target','softwaremanager') : __('Add group mail target','softwaremanager')) . "</div><button type='button' class='sm-modal-close' id='sm-close-modal' aria-label='Close'>&times;</button></div>";

echo "  <form method='post' id='sm-add-form' style='margin:0;'>";
echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
echo Html::hidden('action', ['value' => $edit_row ? 'update' : 'add']);
if ($edit_row) { echo Html::hidden('id', ['value' => (int)$edit_row['id']]); }

echo "  <table class='tab_cadre_fixe' style='width:100%;'>";
echo "<tr class='tab_bg_1'><th style='width:160px;'>" . __('Entity') . "</th><td>";
echo "<select name='entities_id' style='width:100%'>";
foreach ($entities as $eid => $ename) {
   $sel = ($edit_row && (int)$edit_row['entities_id'] === (int)$eid) ? 'selected' : '';
   echo "<option value='".(int)$eid."' $sel>" . Html::clean($ename) . "</option>";
}
echo "</select></td></tr>";

echo "<tr class='tab_bg_1'><th>" . __('Group') . "</th><td>";
$current_group_id = ($edit_row ? (int)$edit_row['groups_id'] : 0);
echo Html::hidden('groups_id', ['id' => 'sm-target-group-id', 'value' => $current_group_id]);
$saved_tgj = !empty($pre_target_groups) ? json_encode(array_values(array_unique(array_map('intval',$pre_target_groups))), JSON_UNESCAPED_UNICODE) : '[]';
echo Html::hidden('target_groups_json', ['id' => 'sm-target-groups-json', 'value' => Html::clean($saved_tgj)]);

echo "<div class='sm-col' style='background:#fff;border:0;padding:0;'>";
echo "  <div class='sm-label'>".__('Groups')."</div>";
echo "  <div class='sm-tokenbox' id='sm-target-groups-tokenbox'>";
echo "    <input id='sm-target-groups-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'>";
echo "  </div>";
echo "  <div id='sm-target-groups-list' class='sm-option-list'>";
$target_pref = array_unique(array_filter(array_merge([$current_group_id], $pre_target_groups)));
foreach ($groups as $gid => $gname) {
   $sel = in_array((int)$gid, $target_pref, true) ? 'selected' : '';
   echo "<div class='sm-option-item $sel' data-type='target-group' data-value='".(int)$gid."' data-label='".Html::clean($gname)."'>".Html::clean($gname)."</div>";
}
echo "  </div>";
echo "  <div class='sm-label'>".__('Selected Groups')."</div><div id='sm-target-groups-picked' class='sm-picked-area sm-hidden'></div>";
echo "</div>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><th>" . __('Recipients', 'softwaremanager') . "</th><td>";
echo "<div class='sm-grid'>";
echo "  <div class='sm-col'><div class='sm-label'>".__('Users')."</div>";
echo "  <div class='sm-tokenbox' id='sm-users-tokenbox'><input id='sm-users-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-users-list' class='sm-option-list'>";
$u_rs = $DB->query("SELECT id, CONCAT(IFNULL(realname,''),' ',IFNULL(firstname,''),' (',name,')') AS label FROM glpi_users ORDER BY realname, firstname, name LIMIT 200");
if ($u_rs) { while ($u = $DB->fetchAssoc($u_rs)) { $sel = in_array((int)$u['id'],$pre_users,true) ? 'selected' : ''; echo "<div class='sm-option-item $sel' data-type='user' data-value='".(int)$u['id']."' data-label='".Html::clean($u['label'])."'>".Html::clean($u['label'])."</div>"; } }
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Users')."</div><div id='sm-users-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

echo "  <div class='sm-col'><div class='sm-label'>".__('Groups')."</div>";
echo "  <div class='sm-tokenbox' id='sm-groups-tokenbox'><input id='sm-groups-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-groups-list' class='sm-option-list'>";
foreach ($groups as $gid => $gname) { $sel = in_array((int)$gid,$pre_groups,true) ? 'selected' : ''; echo "<div class='sm-option-item $sel' data-type='group' data-value='".(int)$gid."' data-label='".Html::clean($gname)."'>".Html::clean($gname)."</div>"; }
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Groups')."</div><div id='sm-groups-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

echo "  <div class='sm-col'><div class='sm-label'>".__('Profiles')."</div>";
echo "  <div class='sm-tokenbox' id='sm-profiles-tokenbox'><input id='sm-profiles-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-profiles-list' class='sm-option-list'>";
$p_rs = $DB->query("SELECT id, name FROM glpi_profiles ORDER BY name");
if ($p_rs) { while ($p = $DB->fetchAssoc($p_rs)) { $sel = in_array((int)$p['id'],$pre_profiles,true) ? 'selected' : ''; echo "<div class='sm-option-item $sel' data-type='profile' data-value='".(int)$p['id']."' data-label='".Html::clean($p['name'])."'>".Html::clean($p['name'])."</div>"; } }
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Profiles')."</div><div id='sm-profiles-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

$pre_emails_str = !empty($pre_emails) ? Html::clean(implode(', ', $pre_emails)) : '';
echo "  <div class='sm-col'><div class='sm-label'>".__('Extra emails')."</div><input id='sm-emails' type='text' placeholder='a@x.com,b@y.com' value='$pre_emails_str' style='width:100%'><div class='sm-note'>".__('Comma separated')."</div>";
echo "  <div class='sm-label'>".__('Email List')."</div><div id='sm-emails-picked' class='sm-picked-area'></div>";
echo "  </div>";
echo "</div>";

echo "<select id='sm-users' class='sm-hidden' multiple>";
$u_rs2 = $DB->query("SELECT id, CONCAT(IFNULL(realname,''),' ',IFNULL(firstname,''),' (',name,')') AS label FROM glpi_users ORDER BY realname, firstname, name LIMIT 200");
if ($u_rs2) { while ($u = $DB->fetchAssoc($u_rs2)) { $sel = in_array((int)$u['id'],$pre_users,true) ? 'selected' : ''; echo "<option value='".(int)$u['id']."' $sel>".Html::clean($u['label'])."</option>"; } }
echo "</select>";
echo "<select id='sm-groups' class='sm-hidden' multiple>";
foreach ($groups as $gid => $gname) { $sel = in_array((int)$gid,$pre_groups,true) ? 'selected' : ''; echo "<option value='".(int)$gid."' $sel>".Html::clean($gname)."</option>"; }
echo "</select>";
echo "<select id='sm-profiles' class='sm-hidden' multiple>";
$p_rs2 = $DB->query("SELECT id, name FROM glpi_profiles ORDER BY name");
if ($p_rs2) { while ($p = $DB->fetchAssoc($p_rs2)) { $sel = in_array((int)$p['id'],$pre_profiles,true) ? 'selected' : ''; echo "<option value='".(int)$p['id']."' $sel>".Html::clean($p['name'])."</option>"; } }
echo "</select>";
$rec_val = $edit_row ? Html::clean($edit_row['recipients_json']) : '{"users":[],"groups":[],"profiles":[],"emails":[]}';
echo "<textarea id='sm-recipients-json' name='recipients_json' class='sm-hidden'>$rec_val</textarea>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><th>" . __('Options', 'softwaremanager') . "</th><td>";
echo "<div class='sm-grid'>";
echo " <div class='sm-col sm-inline'><label><input id='sm-opt-only' type='checkbox' checked> " . __('Only on violation', 'softwaremanager') . "</label></div>";
echo " <div class='sm-col sm-inline'><span class='sm-label'>".__('Unmanaged threshold','softwaremanager')."</span> <input id='sm-opt-thres' type='number' min='0' value='0' style='width:90px'></div>";
echo " <div class='sm-col sm-inline'><label><input id='sm-opt-merge' type='checkbox' checked> " . __('Merge multiple groups into one email', 'softwaremanager') . "</label></div>";
echo " <div class='sm-col'><div class='sm-label'>Scope</div><select id='sm-opt-scope'><option value='both'>both</option><option value='main'>main</option><option value='tech'>tech</option></select></div>";
echo "</div>";
echo "<textarea id='sm-options-json' name='options_json' class='sm-hidden'>".Html::clean(json_encode($opt_pref, JSON_UNESCAPED_UNICODE))."</textarea>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><th>" . __('Active') . "</th><td>";
$checked = ($edit_row ? ((int)$edit_row['is_active'] === 1) : true) ? 'checked' : '';
echo "<label><input type='checkbox' name='is_active' value='1' $checked> " . __('Enabled') . "</label>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
echo "<button class='vsubmit' type='submit'><i class='fas fa-save'></i> " . ($edit_row ? __('Update') : __('Add')) . "</button>";
if ($edit_row) { echo " <a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "#sm-target-list'>" . __('Cancel') . "</a>"; }
echo "</td></tr>";
echo "  </table>";
echo "  </form>";
echo " </div>";
echo "</div>";


