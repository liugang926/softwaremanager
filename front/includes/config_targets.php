<?php
// Targets tab content (extracted from front/config.php)

global $DB, $CFG_GLPI;

// ===================== Group Mail Targets Configuration =====================
echo "<link rel='stylesheet' type='text/css' href='".$CFG_GLPI['root_doc']."/plugins/softwaremanager/css/mail-config.css'>";
echo "<script src='".$CFG_GLPI['root_doc']."/plugins/softwaremanager/js/mail-config.js' defer></script>";

echo "<div id='sm-mail-config' style='max-width:1100px;margin:25px auto 0 auto;'>";
echo "<div class='tab_cadre_fixe sm-section'>";
echo "<div class='sm-title'>" . __('Group report recipients (group-view mail targets)', 'softwaremanager') . "</div>";

include_once(__DIR__ . '/../../inc/groupmailtarget.class.php');
include_once(__DIR__ . '/../../inc/utils_softwaremanager.php');

// Handle actions via dedicated include
include __DIR__ . '/targets/actions.php';

// Fetch entities and groups for selects
$entities = [];
$res = $DB->query("SELECT id, name FROM glpi_entities ORDER BY name");
if ($res) {
   while ($row = $DB->fetchAssoc($res)) { $entities[(int)$row['id']] = $row['name']; }
}
$groups = [];
$res = $DB->query("SELECT g.id, g.name, e.name AS ename FROM glpi_groups g LEFT JOIN glpi_entities e ON e.id=g.entities_id ORDER BY e.name, g.name");
if ($res) {
   while ($row = $DB->fetchAssoc($res)) { $groups[(int)$row['id']] = $row['name'] . ' [' . ($row['ename'] ?? '') . ']'; }
}

// Edit mode
$edit_id = (int)($_GET['edit_id'] ?? 0);
$edit_row = null;
$pre_users = $pre_groups = $pre_profiles = $pre_emails = $pre_target_groups = [];
$opt_pref = ['only_on_violation'=>true,'threshold_unmanaged'=>0,'merge'=>true,'scope'=>'both','attach_csv'=>false];
if ($edit_id > 0) {
   $it = $DB->request(['FROM' => 'glpi_plugin_softwaremanager_group_mail_targets', 'WHERE' => ['id' => $edit_id], 'LIMIT' => 1]);
   foreach ($it as $r) { $edit_row = $r; break; }
      if ($edit_row) {
         $spec = json_decode((string)($edit_row['recipients_json'] ?? ''), true);
         if (is_array($spec)) {
            $pre_users = array_map('intval', (array)($spec['users'] ?? []));
            $pre_groups = array_map('intval', (array)($spec['groups'] ?? []));
            $pre_profiles = array_map('intval', (array)($spec['profiles'] ?? []));
            $pre_emails = (array)($spec['emails'] ?? []);
         }
         // Read multi target groups from dedicated column for UI preselect
         $tgj = (string)($edit_row['target_groups_json'] ?? '[]');
         $tgarr = json_decode($tgj, true);
         if (is_array($tgarr)) { $pre_target_groups = array_map('intval', $tgarr); }

         $opts = json_decode((string)($edit_row['options_json'] ?? ''), true);
         if (is_array($opts)) {
            $opt_pref = array_merge($opt_pref, $opts);
         }
      }
}

// Modal launcher
echo "<div class='center' style='margin:6px 0 10px 0;'>";
echo "<button class='vsubmit' id='sm-open-modal-add'><i class='fas fa-plus'></i> " . __('Add') . "</button>";
if ($edit_row) {
   echo " <button class='vsubmit' id='sm-open-modal-edit'><i class='fas fa-edit'></i> " . __('Edit') . "</button>";
   echo " <a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "#sm-target-list'>".__('Cancel')."</a>";
}
echo "</div>";

// Modal container
echo "<div class='sm-modal-overlay" . ($edit_row ? " show" : "") . "' id='sm-modal'>";
echo " <div class='sm-modal'>";
echo "  <div class='sm-modal-header'><div class='sm-modal-title' id='sm-modal-title'>" . ($edit_row ? __('Edit group mail target','softwaremanager') : __('Add group mail target','softwaremanager')) . "</div><button type='button' class='sm-modal-close' id='sm-close-modal' aria-label='Close'>&times;</button></div>";

// Add / Edit form (inside modal)
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
// Hidden real field to store the primary group id (first selected)
$current_group_id = ($edit_row ? (int)$edit_row['groups_id'] : 0);
echo Html::hidden('groups_id', ['id' => 'sm-target-group-id', 'value' => $current_group_id]);
// Pre-fill hidden with saved JSON for reliable preselect
$saved_tgj = '[]';
if (!empty($pre_target_groups)) { $saved_tgj = json_encode(array_values(array_unique(array_map('intval',$pre_target_groups))), JSON_UNESCAPED_UNICODE); }
echo Html::hidden('target_groups_json', ['id' => 'sm-target-groups-json', 'value' => Html::clean($saved_tgj)]);

// Searchable multi-select UI (does not filter primary view; only for batch processing)
echo "<div class='sm-col' style='background:#fff;border:0;padding:0;'>";
echo "  <div class='sm-label'>".__('Groups')."</div>";
echo "  <div class='sm-tokenbox' id='sm-target-groups-tokenbox'>";
echo "    <input id='sm-target-groups-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'>";
echo "  </div>";
echo "  <div id='sm-target-groups-list' class='sm-option-list'>";
// Preselect union of primary group + saved target_groups if any
$target_pref = array_unique(array_filter(array_merge([$current_group_id], $pre_target_groups)));
foreach ($groups as $gid => $gname) {
   $sel = in_array((int)$gid, $target_pref, true) ? 'selected' : '';
   echo "<div class='sm-option-item $sel' data-type='target-group' data-value='".(int)$gid."' data-label='".Html::clean($gname)."'>".Html::clean($gname)."</div>";
}
echo "  </div>";
// kept for compatibility; tokenbox 将作为主展示区，此处可保留或移除
echo "  <div class='sm-label'>".__('Selected Groups')."</div><div id='sm-target-groups-picked' class='sm-picked-area sm-hidden'></div>";
echo "</div>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><th>" . __('Recipients', 'softwaremanager') . "</th><td>";
echo "<div class='sm-grid'>";
echo "  <div class='sm-col'><div class='sm-label'>".__('Users')."</div>";
echo "  <div class='sm-tokenbox' id='sm-users-tokenbox'><input id='sm-users-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-users-list' class='sm-option-list'>";
$u_rs = $DB->query("SELECT id, CONCAT(IFNULL(realname,''),' ',IFNULL(firstname,''),' (',name,')') AS label FROM glpi_users ORDER BY realname, firstname, name LIMIT 200");
if ($u_rs) { 
    while ($u = $DB->fetchAssoc($u_rs)) { 
        $sel = in_array((int)$u['id'],$pre_users,true) ? 'selected' : '';
        echo "<div class='sm-option-item $sel' data-type='user' data-value='".(int)$u['id']."' data-label='".Html::clean($u['label'])."'>".Html::clean($u['label'])."</div>";
    } 
}
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Users')."</div><div id='sm-users-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

echo "  <div class='sm-col'><div class='sm-label'>".__('Groups')."</div>";
echo "  <div class='sm-tokenbox' id='sm-groups-tokenbox'><input id='sm-groups-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-groups-list' class='sm-option-list'>";
foreach ($groups as $gid => $gname) { 
    $sel = in_array((int)$gid,$pre_groups,true) ? 'selected' : '';
    echo "<div class='sm-option-item $sel' data-type='group' data-value='".(int)$gid."' data-label='".Html::clean($gname)."'>".Html::clean($gname)."</div>";
}
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Groups')."</div><div id='sm-groups-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

echo "  <div class='sm-col'><div class='sm-label'>".__('Profiles')."</div>";
echo "  <div class='sm-tokenbox' id='sm-profiles-tokenbox'><input id='sm-profiles-filter' class='sm-filter' type='text' placeholder='".__('Type to filter','softwaremanager')."'></div>";
echo "  <div id='sm-profiles-list' class='sm-option-list'>";
$p_rs = $DB->query("SELECT id, name FROM glpi_profiles ORDER BY name");
if ($p_rs) { 
    while ($p = $DB->fetchAssoc($p_rs)) { 
        $sel = in_array((int)$p['id'],$pre_profiles,true) ? 'selected' : '';
        echo "<div class='sm-option-item $sel' data-type='profile' data-value='".(int)$p['id']."' data-label='".Html::clean($p['name'])."'>".Html::clean($p['name'])."</div>";
    } 
}
echo "  </div>";
echo "  <div class='sm-label sm-hidden'>".__('Selected Profiles')."</div><div id='sm-profiles-picked' class='sm-picked-area sm-hidden'></div>";
echo "  </div>";

$pre_emails_str = !empty($pre_emails) ? Html::clean(implode(', ', $pre_emails)) : '';
echo "  <div class='sm-col'><div class='sm-label'>".__('Extra emails')."</div><input id='sm-emails' type='text' placeholder='a@x.com,b@y.com' value='$pre_emails_str' style='width:100%'><div class='sm-note'>".__('Comma separated')."</div>";
echo "  <div class='sm-label'>".__('Email List')."</div><div id='sm-emails-picked' class='sm-picked-area'></div>";
echo "  </div>";
echo "</div>";

// Hidden fields for form submission
echo "<select id='sm-users' class='sm-hidden' multiple>";
$u_rs2 = $DB->query("SELECT id, CONCAT(IFNULL(realname,''),' ',IFNULL(firstname,''),' (',name,')') AS label FROM glpi_users ORDER BY realname, firstname, name LIMIT 200");
if ($u_rs2) { 
    while ($u = $DB->fetchAssoc($u_rs2)) { 
        $sel = in_array((int)$u['id'],$pre_users,true) ? 'selected' : '';
        echo "<option value='".(int)$u['id']."' $sel>".Html::clean($u['label'])."</option>";
    } 
}
echo "</select>";
echo "<select id='sm-groups' class='sm-hidden' multiple>";
foreach ($groups as $gid => $gname) { 
    $sel = in_array((int)$gid,$pre_groups,true) ? 'selected' : '';
    echo "<option value='".(int)$gid."' $sel>".Html::clean($gname)."</option>";
}
echo "</select>";
echo "<select id='sm-profiles' class='sm-hidden' multiple>";
$p_rs2 = $DB->query("SELECT id, name FROM glpi_profiles ORDER BY name");
if ($p_rs2) { 
    while ($p = $DB->fetchAssoc($p_rs2)) { 
        $sel = in_array((int)$p['id'],$pre_profiles,true) ? 'selected' : '';
        echo "<option value='".(int)$p['id']."' $sel>".Html::clean($p['name'])."</option>";
    } 
}
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
if ($edit_row) {
   echo " <a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "#sm-target-list'>" . __('Cancel') . "</a>";
}
echo "</td></tr>";
echo "  </table>";
echo "  </form>";
echo " </div>"; // .sm-modal
echo "</div>"; // .sm-modal-overlay

// JS is handled in js/mail-config.js

// List existing targets - simplified approach
$rows = [];
$totalCount = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

try {
   // Get all rows first (simpler approach)
   $allRowsQuery = $DB->request([
      'FROM'  => 'glpi_plugin_softwaremanager_group_mail_targets',
      'ORDER' => 'entities_id, groups_id'
   ]);
   
   $allRows = [];
   foreach ($allRowsQuery as $row) {
      $allRows[] = $row;
   }
   
   $totalCount = count($allRows);
   $totalPages = ceil($totalCount / $perPage);
   
   // Get current page rows
   $offset = ($page - 1) * $perPage;
   $rows = array_slice($allRows, $offset, $perPage);
   
} catch (Exception $e) {
   error_log("Config page error: " . $e->getMessage());
   $rows = [];
   $totalCount = 0;
   $totalPages = 1;
}
 
echo "<div id='sm-target-list' style='margin:10px;'>";
 
// Debug info (remove in production)
if (isset($_GET['debug'])) {
   echo "<div style='background:#fffbeb; border:1px solid #fbbf24; padding:8px; margin-bottom:10px; font-size:12px;'>";
   echo "Debug: Total Count = " . $totalCount . ", Page = " . $page . ", Total Pages = " . $totalPages . ", Rows = " . count($rows);
   echo "</div>";
}
 
// List controls
echo "<div class='sm-list-controls' style='margin-bottom:10px; padding:10px; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px;'>";
echo "<div style='display:flex; gap:10px; align-items:center; flex-wrap:wrap;'>";
 
// Search box
echo "<div style='flex:1; min-width:200px;'>";
echo "<input type='text' id='sm-search' placeholder='" . __('Search targets...', 'softwaremanager') . "' style='width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'>";
echo "</div>";
 
// Entity filter
echo "<div>";
echo "<select id='sm-filter-entity' style='padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'>";
echo "<option value=''>" . __('All Entities') . "</option>";
foreach ($entities as $eid => $ename) {
   echo "<option value='" . (int)$eid . "'>" . Html::clean($ename) . "</option>";
}
echo "</select>";
echo "</div>";
 
// Status filter
echo "<div>";
echo "<select id='sm-filter-status' style='padding:6px 10px; border:1px solid #d1d5db; border-radius:4px;'>";
echo "<option value=''>" . __('All Status') . "</option>";
echo "<option value='1'>" . __('Active') . "</option>";
echo "<option value='0'>" . __('Inactive') . "</option>";
echo "</select>";
echo "</div>";
 
// Results info
echo "<div id='sm-results-info' style='color:#6b7280; font-size:13px;'>";
echo "<span id='sm-visible-count'>0</span> / <span id='sm-total-count'>0</span> " . __('records');
echo "</div>";
 
echo "</div>";
echo "</div>";
 
echo "<table class='sm-list'>";
echo "<thead><tr><th class='sm-col-id'>ID</th><th class='sm-col-entity'>" . __('Entity') . "</th><th class='sm-col-group'>" . __('Group') . "</th><th class='sm-col-active'>" . __('Active') . "</th><th class='sm-col-rec'>" . __('Recipients') . "</th><th class='sm-col-opt'>" . __('Options') . "</th><th class='sm-col-actions'>" . __('Actions') . "</th><th class='sm-col-test'>" . __('Test') . "</th></tr></thead><tbody>";
if (!empty($rows)) {
   foreach ($rows as $r) {
      $eid = (int)$r['entities_id'];
      $gid = (int)$r['groups_id'];
      $ename = $entities[$eid] ?? (string)$eid;
      $gname = $groups[$gid] ?? (string)$gid;
      // Build target groups pills (multi) from target_groups_json for list display
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
      
      // Add data attributes for filtering
      $searchData = strtolower($ename . ' ' . $gname);
      echo "<tr class='sm-list-row' data-entity-id='" . $eid . "' data-status='" . (int)$r['is_active'] . "' data-search='" . Html::clean($searchData) . "'>";
      echo "<td>" . (int)$r['id'] . "</td>";
      echo "<td>" . Html::clean($ename) . "</td>";
      echo "<td>" . Html::clean($gname) . $targetPills . "</td>";
      echo "<td>" . $activeBadge . "</td>";
      // recipients with real names (compact)
      $rec_arr = json_decode((string)$r['recipients_json'], true);
      if (!is_array($rec_arr)) { $rec_arr = []; }
      echo "<td class='sm-col-rec'>";

      $segments = [];

      // helper to shorten list with +N
      $shorten = [PluginSoftwaremanagerUtils::class, 'shortenList'];


      // Users → tooltip lists each user's emails (glpi_users + glpi_useremails)
      if (!empty($rec_arr['users']) && is_array($rec_arr['users'])) {
         $uids = array_values(array_unique(array_filter(array_map('intval', (array)$rec_arr['users']), function($id) { return $id > 0; })));
         if (!empty($uids)) {
            $labels = [];
            $emailsByUid = [];

      // Try multiple methods to get user names
            foreach ($uids as $uid) {
               $label = '';
               
               // Method 1: Use User class
               $user = new User();
               if ($user->getFromDB($uid)) {
                  $rn = trim($user->fields['realname'] ?? '');
                  $fn = trim($user->fields['firstname'] ?? '');
                  if ($rn !== '' && $fn !== '') {
                     $label = $rn . ' ' . $fn;
                  } elseif ($rn !== '') {
                     $label = $rn;
                  } elseif ($fn !== '') {
                     $label = $fn;
                  } else {
                     $label = $user->fields['name'] ?? '';
                  }
                  
                  // Get user email
                  $e = trim($user->fields['email'] ?? '');
                  if ($e !== '') { 
                     $emailsByUid[$uid][$e] = true; 
                  }
               }
               
               // Method 2: Fallback to Dropdown if User class failed
               if (empty($label)) {
                  $dropdownName = Dropdown::getDropdownName('glpi_users', $uid);
                  if ($dropdownName && $dropdownName !== '&nbsp;') {
                     $label = $dropdownName;
                  }
               }
               
               // Method 3: Final fallback
               if (empty($label)) {
                  $label = 'User #' . $uid;
               }
               
               $labels[$uid] = $label;
            }
            // Get additional emails from glpi_useremails
            foreach ($uids as $uid) {
               $userEmail = new UserEmail();
               $emails = $userEmail->find(['users_id' => $uid]);
               foreach ($emails as $emailData) {
                  $em = trim($emailData['email'] ?? '');
                  if ($em !== '') {
                     $emailsByUid[$uid][$em] = true;
                  }
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

      // Groups → tooltip preview of member emails (first 20)
      if (!empty($rec_arr['groups']) && is_array($rec_arr['groups'])) {
         $gids = array_values(array_unique(array_map('intval', (array)$rec_arr['groups'])));
         foreach ($gids as $gid2) {
      $gname = $groups[$gid2] ?? ('#'.$gid2);

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

            $title = Html::clean(($gname ?: 'Group').': '.($preview !== '' ? $preview : '无邮箱'));
            $segments[] = '<span class="sm-pill" data-title="'.$title.'">群组: '.Html::clean($gname).'</span>';
         }
      }

      // Emails
      if (!empty($rec_arr['emails']) && is_array($rec_arr['emails'])) {
         $title = Html::clean(implode(', ', (array)$rec_arr['emails']));
          $short = Html::clean(call_user_func($shorten, (array)$rec_arr['emails'], 2));
         $segments[] = '<span class="sm-pill" data-title="'.$title.'">邮箱: '.$short.'</span>';
      }

      if (empty($segments)) { echo '<em>无收件人</em>'; }
      else { echo implode(' ', $segments); }

      echo "</td>";
      // options with readable labels (compact)
      $opt_arr = json_decode((string)$r['options_json'], true);
      if (!is_array($opt_arr)) { $opt_arr = []; }
      echo "<td class='sm-col-opt'>";

      $option_parts = [];
      if (isset($opt_arr['only_on_violation'])) { $option_parts[] = '<strong>触发:</strong> '.($opt_arr['only_on_violation'] ? '仅违规' : '总是'); }
      if (isset($opt_arr['merge'])) { $option_parts[] = '<strong>方式:</strong> '.($opt_arr['merge'] ? '合并' : '分别'); }
      if (!empty($opt_arr['scope'])) {
         $scope_labels = ['main'=>'主要','tech'=>'技术','both'=>'两种'];
         $option_parts[] = '<strong>范围:</strong> '.($scope_labels[$opt_arr['scope']] ?? Html::clean($opt_arr['scope']));
      }
      if (isset($opt_arr['threshold_unmanaged']) && (int)$opt_arr['threshold_unmanaged'] > 0) {
         $option_parts[] = '<strong>未登记阈值:</strong> ≥ '.(int)$opt_arr['threshold_unmanaged'];
      }
      if (isset($opt_arr['attach_csv'])) { $option_parts[] = '<strong>CSV:</strong> '.($opt_arr['attach_csv'] ? '附加' : '不附加'); }

      if (empty($option_parts)) echo '<em>默认选项</em>';
      else echo implode(' <span style="color:#9ca3af">|</span> ', $option_parts);

      echo "</td>";
      echo "<td>";
      echo "<div class='sm-actions-horiz'>";
      echo "<a class='vsubmit' href='" . Html::clean($_SERVER['PHP_SELF']) . "?edit_id=" . (int)$r['id'] . "' style='margin-right:6px;'><i class='fas fa-edit'></i> " . __('Edit') . "</a>";
      echo "<form method='post' style='display:inline-block;' onsubmit=\"return confirm('".__('Confirm the final deletion?')."');\">";
      echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
      echo Html::hidden('action', ['value' => 'delete']);
      echo Html::hidden('id', ['value' => (int)$r['id']]);
      echo "<button class='vsubmit' type='submit' style='background:#dc3545;border-color:#dc3545;'><i class='fas fa-trash'></i> " . __('Delete') . "</button>";
      echo "</form>";
      echo "</div></td>";
      
      // Test column
      echo "<td>";
      echo "<form method='post'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => $sm_csrf_token]);
      echo Html::hidden('action', ['value' => 'send_test']);
      echo Html::hidden('id', ['value' => (int)$r['id']]);
      echo "<input type='email' name='test_email' placeholder='you@example.com' style='height:28px;padding:4px 6px;width:100%;margin-bottom:4px;'>";
      echo "<button class='vsubmit' type='submit' style='width:100%;'><i class='fas fa-paper-plane'></i> " . __('Send test') . "</button>";
      echo "</form>";
      echo "</td>";
      echo "</tr>";
   }
} else {
   echo "<tr class='tab_bg_1'><td colspan='8' class='center' style='padding:20px; color:#6b7280;'>";
   echo "<i class='fas fa-inbox' style='font-size:24px; margin-bottom:8px; display:block;'></i>";
   echo __('No mail targets configured yet.', 'softwaremanager') . "<br>";
   echo "<small>" . __('Click \"Add\" button above to create your first mail target.', 'softwaremanager') . "</small>";
   echo "</td></tr>";
}
echo "</tbody></table>";
 
// Pagination controls
if ($totalPages > 1) {
   echo "<div class='sm-pagination' style='margin-top:15px; text-align:center;'>";
   
   $baseUrl = $_SERVER['PHP_SELF'] . '?';
   
   // Previous button
   if ($page > 1) {
      echo "<a href='" . $baseUrl . "page=" . ($page - 1) . "#sm-target-list' class='sm-page-btn'>&laquo; " . __('Previous') . "</a>";
   }
   
   // Page numbers
   $start = max(1, $page - 2);
   $end = min($totalPages, $page + 2);
   
   if ($start > 1) {
      echo "<a href='" . $baseUrl . "page=1#sm-target-list' class='sm-page-btn'>1</a>";
      if ($start > 2) echo "<span class='sm-page-dots'>...</span>";
   }
   
   for ($i = $start; $i <= $end; $i++) {
      $class = ($i == $page) ? 'sm-page-btn active' : 'sm-page-btn';
      echo "<a href='" . $baseUrl . "page=" . $i . "#sm-target-list' class='" . $class . "'>" . $i . "</a>";
   }
   
   if ($end < $totalPages) {
      if ($end < $totalPages - 1) echo "<span class='sm-page-dots'>...</span>";
      echo "<a href='" . $baseUrl . "page=" . $totalPages . "#sm-target-list' class='sm-page-btn'>" . $totalPages . "</a>";
   }
   
   // Next button
   if ($page < $totalPages) {
      echo "<a href='" . $baseUrl . "page=" . ($page + 1) . "#sm-target-list' class='sm-page-btn'>" . __('Next') . " &raquo;</a>";
   }
   
   // Page info
   echo "<div class='sm-page-info' style='margin-top:8px; color:#6b7280; font-size:12px;'>";
   echo sprintf(__('Page %d of %d (%d total records)', 'softwaremanager'), $page, $totalPages, $totalCount);
   echo "</div>";
   
   echo "</div>";
}
 
echo "</div>";
 
 echo "</div>"; // card
 echo "</div>"; // wrap


