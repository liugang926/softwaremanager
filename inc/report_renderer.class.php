<?php
/**
 * Softwaremanager - Report HTML renderer for mail/PDF
 */

if (!defined('GLPI_ROOT')) {
   return;
}

class PluginSoftwaremanagerReportRenderer {

   /**
    * Build a group full report HTML (overview style + group detail + user detail)
    * @param string $groupName
    * @param array  $rows       scandetails rows filtered to this group
    * @param array  $stats      ['total','approved','blacklisted','unmanaged']
    */
   public static function renderGroupFull(string $groupName, array $rows, array $stats): string {
      $css = self::style();

      // Aggregate per computer and per user
      $perComputer = [];
      $perUser     = [];
      $softwareByUser = [];

      foreach ($rows as $r) {
         $comp = (string)($r['computer_name'] ?? '');
         $userLogin = trim((string)($r['user_name'] ?? ''));
         $userReal  = trim((string)($r['user_realname'] ?? ''));
         $user = $userReal !== '' ? $userReal : ($userLogin !== '' ? $userLogin : '未绑定');
         $status = (string)($r['compliance_status'] ?? 'unmanaged');

         if (!isset($perComputer[$comp])) {
            $perComputer[$comp] = ['total'=>0,'approved'=>0,'blacklisted'=>0,'unmanaged'=>0,'owner'=>''];
         }
         // capture owner display name (first non-empty wins)
         if ($perComputer[$comp]['owner'] === '') {
            $perComputer[$comp]['owner'] = self::composeOwner((string)($r['owner_realname'] ?? ''), (string)($r['owner_login'] ?? ''));
         }
         if (!isset($perUser[$user])) {
            $perUser[$user] = ['total'=>0,'approved'=>0,'blacklisted'=>0,'unmanaged'=>0];
         }
         $perComputer[$comp]['total']++;
         $perUser[$user]['total']++;
         if (isset($perComputer[$comp][$status])) $perComputer[$comp][$status]++;
         if (isset($perUser[$user][$status])) $perUser[$user][$status]++;

         // software list per user (for user detail table)
         if (!isset($softwareByUser[$user])) $softwareByUser[$user] = [];
         $softwareByUser[$user][] = [
            'software' => (string)$r['software_name'],
            'version'  => (string)$r['software_version'],
            'status'   => $status,
            'computer' => $comp,
            'owner'    => self::composeOwner((string)($r['owner_realname'] ?? ''), (string)($r['owner_login'] ?? ''))
         ];
      }

      // Overview header cards
      $header = '<div class="cards">'
              . self::card('软件安装总数', $stats['total'], '#1f2937')
              . self::card('合规软件', $stats['approved'], '#059669')
              . self::card('违规软件', $stats['blacklisted'], '#dc2626')
              . self::card('未登记软件', $stats['unmanaged'], '#92400e')
              . '</div>';

      // Computer table
      $tblComp = '<h2>群组内计算机合规状况</h2>'
               . '<table><thead><tr><th>计算机名称</th><th>资产使用人</th><th>软件总数</th><th>合规软件</th><th>违规软件</th><th>未登记软件</th></tr></thead><tbody>';
      foreach ($perComputer as $name => $agg) {
         $tblComp .= '<tr>'
                   . '<td>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</td>'
                   . '<td>'.htmlspecialchars((string)$agg['owner'], ENT_QUOTES, 'UTF-8').'</td>'
                   . '<td>'.$agg['total'].'</td>'
                   . '<td>'.$agg['approved'].'</td>'
                   . '<td>'.$agg['blacklisted'].'</td>'
                   . '<td>'.$agg['unmanaged'].'</td>'
                   . '</tr>';
      }
      if (empty($perComputer)) $tblComp .= '<tr><td colspan="6">(无)</td></tr>';
      $tblComp .= '</tbody></table>';

      // User table
      $tblUser = '<h2>群组内用户合规状况</h2>'
               . '<table><thead><tr><th>用户</th><th>软件总数</th><th>合规软件</th><th>违规软件</th><th>未登记软件</th></tr></thead><tbody>';
      foreach ($perUser as $name => $agg) {
         $tblUser .= '<tr>'
                   . '<td>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</td>'
                   . '<td>'.$agg['total'].'</td>'
                   . '<td>'.$agg['approved'].'</td>'
                   . '<td>'.$agg['blacklisted'].'</td>'
                   . '<td>'.$agg['unmanaged'].'</td>'
                   . '</tr>';
      }
      if (empty($perUser)) $tblUser .= '<tr><td colspan="5">(无)</td></tr>';
      $tblUser .= '</tbody></table>';

      // User detail – list software (only blacklisted/unmanaged; do not list approved)
      $detailHtml = '';
      foreach ($softwareByUser as $uname => $softs) {
         // Filter out approved
         $filtered = array_values(array_filter($softs, function($s){ return ($s['status'] !== 'approved'); }));
         if (empty($filtered)) { continue; }
         $detailHtml .= '<h3>用户详情 · '.htmlspecialchars($uname, ENT_QUOTES, 'UTF-8').'</h3>'
                      . '<table><thead><tr><th>软件名称</th><th>版本</th><th>合规状态</th><th>计算机</th><th>资产使用人</th></tr></thead><tbody>';
         foreach ($filtered as $s) {
            $stateLabel = ($s['status']==='blacklisted') ? '<span class="badge b-red">违规</span>' : '<span class="badge b-amber">未登记</span>';
            $detailHtml .= '<tr>'
                         . '<td>'.htmlspecialchars($s['software'], ENT_QUOTES, 'UTF-8').'</td>'
                         . '<td>'.htmlspecialchars($s['version'], ENT_QUOTES, 'UTF-8').'</td>'
                         . '<td>'.$stateLabel.'</td>'
                         . '<td>'.htmlspecialchars($s['computer'], ENT_QUOTES, 'UTF-8').'</td>'
                         . '<td>'.htmlspecialchars((string)($s['owner'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                         . '</tr>';
         }
         $detailHtml .= '</tbody></table>';
      }

      $titleText = '群组合规报告 - '.htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8');
      $title = '<h1>'.$titleText.'</h1>';
      return '<html><head>'.$css.'</head><body>'.$title.$header.$tblComp.$tblUser.$detailHtml.'</body></html>';
   }

   private static function card(string $label, int $value, string $color): string {
      return '<div class="card"><div class="card-num" style="color:'.$color.'">'.$value.'</div><div class="card-label">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</div></div>';
   }

   /**
    * Build a computer full report HTML (computer view, include all software rows)
    * @param string $computerName
    * @param array  $items   Each: ['software'=>, 'version'=>, 'status'=> approved|blacklisted|unmanaged]
    */
   public static function renderComputerFull(string $computerName, array $items, array $meta = []): string {
      $css = self::style();

      $total = count($items);
      $approved = 0; $blacklisted = 0; $unmanaged = 0;
      foreach ($items as $it) {
         $st = (string)($it['status'] ?? 'unmanaged');
         if ($st === 'approved') $approved++; elseif ($st === 'blacklisted') $blacklisted++; else $unmanaged++;
      }

      $header = '<div class="cards">'
              . self::card('软件安装总数', $total, '#1f2937')
              . self::card('合规软件', $approved, '#059669')
              . self::card('违规软件', $blacklisted, '#dc2626')
              . self::card('未登记软件', $unmanaged, '#92400e')
              . '</div>';

      $infoOwner   = htmlspecialchars((string)($meta['owner_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $infoOS      = htmlspecialchars((string)($meta['os_version'] ?? ''), ENT_QUOTES, 'UTF-8');
      $infoOSDate  = htmlspecialchars(self::formatDateOnly((string)($meta['os_install_date'] ?? '')), ENT_QUOTES, 'UTF-8');
      $metaHtml = '<div style="margin:6px 0;color:#374151">'
                . '<span style="margin-right:16px;">资产使用人：'.$infoOwner.'</span>'
                . '<span style="margin-right:16px;">系统版本：'.$infoOS.'</span>'
                . '<span>系统安装日期：'.$infoOSDate.'</span>'
                . '</div>';
      $table = '<h2>计算机 · '.htmlspecialchars($computerName, ENT_QUOTES, 'UTF-8').'</h2>'
             . $metaHtml
             . '<table><thead><tr><th>软件名称</th><th>版本</th><th>合规状态</th><th>安装时间</th></tr></thead><tbody>';
      foreach ($items as $it) {
         $st = (string)($it['status'] ?? 'unmanaged');
         $stateLabel = ($st==='blacklisted') ? '<span class="badge b-red">违规</span>' : (($st==='approved') ? '<span class="badge b-green">合规</span>' : '<span class="badge b-amber">未登记</span>');
         $table .= '<tr>'
                 . '<td>'.htmlspecialchars((string)($it['software'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                 . '<td>'.htmlspecialchars((string)($it['version'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                 . '<td>'.$stateLabel.'</td>'
                 . '<td>'.htmlspecialchars((string)($it['installed'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                 . '</tr>';
      }
      if ($total === 0) { $table .= '<tr><td colspan="4">(无)</td></tr>'; }
      $table .= '</tbody></table>';

      $title = '<h1>计算机合规报告 · '.htmlspecialchars($computerName, ENT_QUOTES, 'UTF-8').'</h1>';
      return '<html><head>'.$css.'</head><body>'.$title.$header.$table.'</body></html>';
   }

   /**
    * Build a compact problems-only body for computer emails (HTML blocks, no compliant list).
    * @param string $computerName
    * @param array  $badItems Each: ['software'=>, 'version'=>, 'status'=> blacklisted|unmanaged]
    */
   public static function renderComputerProblems(string $computerName, array $allItems, array $badItems, array $meta = []): string {
      // Aggregate stats for cards
      $total = count($allItems);
      $approved = 0; $blacklisted = 0; $unmanaged = 0;
      foreach ($allItems as $it) {
         $st = (string)($it['status'] ?? 'unmanaged');
         if ($st === 'approved') $approved++; elseif ($st === 'blacklisted') $blacklisted++; else $unmanaged++;
      }

      $viol = [];
      $unmg = [];
      foreach ($badItems as $it) {
         $st = (string)($it['status'] ?? 'unmanaged');
         $row = [
            'software'  => (string)($it['software'] ?? ''),
            'version'   => (string)($it['version'] ?? ''),
            'computer'  => (string)($it['computer'] ?? ''),
            'installed' => (string)($it['installed'] ?? '')
         ];
         if ($st === 'blacklisted') { $viol[] = $row; } else { $unmg[] = $row; }
      }

      $renderTable = function(array $arr): string {
         $cell = 'style="padding:8px 10px;border:1px solid #f1f5f9;text-align:left;"';
         $th   = 'style="padding:8px 10px;border:1px solid #e5e7eb;background:#f3f4f6;text-align:left;"';
         $out = '<table style="width:100%;border-collapse:collapse;margin:6px 0;">'
              . '<thead><tr>'
              . '<th '.$th.'>软件名称</th>'
              . '<th '.$th.'>版本</th>'
              . '<th '.$th.'>计算机</th>'
              . '<th '.$th.'>安装日期</th>'
              . '</tr></thead><tbody>';
         if (empty($arr)) {
            $out .= '<tr><td '.$cell.' colspan="4" style="color:#6b7280">(无)</td></tr>';
         } else {
            foreach ($arr as $x) {
               $out .= '<tr>'
                     . '<td '.$cell.'>'.htmlspecialchars((string)($x['software'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                     . '<td '.$cell.'>'.htmlspecialchars((string)($x['version'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                     . '<td '.$cell.'>'.htmlspecialchars((string)($x['computer'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                     . '<td '.$cell.'>'.htmlspecialchars(self::formatDateOnly((string)($x['installed'] ?? '')), ENT_QUOTES, 'UTF-8').'</td>'
                     . '</tr>';
            }
         }
         return $out.'</tbody></table>';
      };

      $summary = htmlspecialchars($computerName, ENT_QUOTES, 'UTF-8');
      $note = '<div style="margin-top:12px;padding:8px 10px;border:1px dashed #f59e0b;background:#fffbe6;border-radius:6px;color:#7c2d12;font-size:12px">'
            . '提醒：存在“违规安装”的软件请立即卸载，或联系 IT 管理员与部门负责人提出申请；“未登记安装”的软件请及时完成登记，登记方法请联系 IT 人员。'
            . '</div>';
      $recipient = htmlspecialchars((string)($meta['recipient_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $greet = ($recipient !== '') ? ('<div style="margin-bottom:6px;">尊敬的 '.$recipient.'：您的电脑安装了不合格的软件，触发了安全巡检，请及时处理下列内容和信息。</div>') : '';
      $infoOwner   = htmlspecialchars((string)($meta['owner_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $infoOS      = htmlspecialchars((string)($meta['os_version'] ?? ''), ENT_QUOTES, 'UTF-8');
      $infoOSDate  = htmlspecialchars(self::formatDateOnly((string)($meta['os_install_date'] ?? '')), ENT_QUOTES, 'UTF-8');
      $cell = 'style="padding:8px 10px;border:1px solid #f1f5f9;text-align:left;"';
      $th   = 'style="padding:8px 10px;border:1px solid #e5e7eb;background:#f3f4f6;text-align:left;white-space:nowrap;"';
      $metaTable = '<table style="width:100%;border-collapse:collapse;margin:6px 0;">'
                 . '<tbody>'
                 . '<tr><th '.$th.'>计算机</th><td '.$cell.'>'.$summary.'</td><th '.$th.'>资产使用人</th><td '.$cell.'>'.$infoOwner.'</td></tr>'
                 . '<tr><th '.$th.'>系统版本</th><td '.$cell.'>'.$infoOS.'</td><th '.$th.'>系统安装日期</th><td '.$cell.'>'.$infoOSDate.'</td></tr>'
                 . '</tbody></table>';
      $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;">'
            . '<div style="margin-bottom:10px;padding:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">'
            . $greet
            . $metaTable
            . '</div>'
            . '<div class="cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:8px 0">'
            .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
            .     '<div class="card-num" style="font-size:20px;color:#1f2937">'.(int)$total.'</div>'
            .     '<div class="card-label" style="font-size:12px;color:#6b7280">软件安装总数</div>'
            .   '</div>'
            .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
            .     '<div class="card-num" style="font-size:20px;color:#059669">'.(int)$approved.'</div>'
            .     '<div class="card-label" style="font-size:12px;color:#6b7280">合规安装</div>'
            .   '</div>'
            .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
            .     '<div class="card-num" style="font-size:20px;color:#dc2626">'.(int)$blacklisted.'</div>'
            .     '<div class="card-label" style="font-size:12px;color:#6b7280">违规安装</div>'
            .   '</div>'
            .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
            .     '<div class="card-num" style="font-size:20px;color:#92400e">'.(int)$unmanaged.'</div>'
            .     '<div class="card-label" style="font-size:12px;color:#6b7280">未登记安装</div>'
            .   '</div>'
            . '</div>'
            . '<div style="display:grid;grid-template-columns:1fr;gap:12px;">'
            .   '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:6px;padding:10px;">'
            .     '<div style="font-weight:600;color:#b91c1c;margin-bottom:6px;">⚠ 违规（Blacklisted） · 共 '.count($viol).'</div>'
            .     $renderTable($viol)
            .   '</div>'
            .   '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:10px;">'
            .     '<div style="font-weight:600;color:#92400e;margin-bottom:6px;">❓ 未登记（Unmanaged） · 共 '.count($unmg).'</div>'
            .     $renderTable($unmg)
            .   '</div>'
            . '</div>'
            . $note
            . '</div>';
      return $html;
   }

   /**
    * Owner problems (merged across computers) – email body table view
    */
   public static function renderOwnerProblems(string $ownerEmail, array $rows, array $computers = []): string {
      // Build counts
      $total = count($rows); $blacklisted=0; $unmanaged=0;
      foreach ($rows as $r) { if (($r['status'] ?? 'unmanaged')==='blacklisted') $blacklisted++; else $unmanaged++; }
      $greet = '<div style="margin-bottom:6px;">尊敬的 '.htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8').'：您的电脑安装了不合格的软件，触发了安全巡检，请及时处理下列内容和信息。</div>';
      $metaHtml = '';
      if (!empty($computers)) {
         $cell = 'style="padding:8px 10px;border:1px solid #f1f5f9;text-align:left;"';
         $th   = 'style="padding:8px 10px;border:1px solid #e5e7eb;background:#f3f4f6;text-align:left;white-space:nowrap;"';
         $metaHtml = '<table style="width:100%;border-collapse:collapse;margin:6px 0;">'
                   . '<thead><tr><th '.$th.'>计算机</th><th '.$th.'>系统版本</th><th '.$th.'>系统安装日期</th></tr></thead><tbody>';
         foreach ($computers as $cname => $m) {
            $metaHtml .= '<tr>'
                      . '<td '.$cell.'>'.htmlspecialchars((string)$cname, ENT_QUOTES, 'UTF-8').'</td>'
                      . '<td '.$cell.'>'.htmlspecialchars((string)($m['os_version'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                      . '<td '.$cell.'>'.htmlspecialchars(self::formatDateOnly((string)($m['os_install_date'] ?? '')), ENT_QUOTES, 'UTF-8').'</td>'
                      . '</tr>';
         }
         $metaHtml .= '</tbody></table>';
      }
      $cards = '<div class="cards" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:8px 0">'
             .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
             .     '<div class="card-num" style="font-size:20px;color:#1f2937">'.$total.'</div>'
             .     '<div class="card-label" style="font-size:12px;color:#6b7280">问题安装</div>'
             .   '</div>'
             .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
             .     '<div class="card-num" style="font-size:20px;color:#dc2626">'.$blacklisted.'</div>'
             .     '<div class="card-label" style="font-size:12px;color:#6b7280">违规</div>'
             .   '</div>'
             .   '<div class="card" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center">'
             .     '<div class="card-num" style="font-size:20px;color:#92400e">'.$unmanaged.'</div>'
             .     '<div class="card-label" style="font-size:12px;color:#6b7280">未登记</div>'
             .   '</div>'
             . '</div>';
      // Table
      $cell = 'style="padding:8px 10px;border:1px solid #f1f5f9;text-align:left;"';
      $th   = 'style="padding:8px 10px;border:1px solid #e5e7eb;background:#f3f4f6;text-align:left;"';
      $tbl = '<table style="width:100%;border-collapse:collapse;margin:6px 0;">'
           . '<thead><tr><th '.$th.'>软件名称</th><th '.$th.'>版本</th><th '.$th.'>状态</th><th '.$th.'>计算机</th><th '.$th.'>安装日期</th></tr></thead><tbody>';
      if (empty($rows)) {
         $tbl .= '<tr><td '.$cell.' colspan="5" style="color:#6b7280">(无)</td></tr>';
      } else {
         foreach ($rows as $r) {
            $st = (string)($r['status'] ?? 'unmanaged');
            $stateLabel = ($st==='blacklisted') ? '<span class="badge b-red">违规</span>' : '<span class="badge b-amber">未登记</span>';
            $tbl .= '<tr>'
                  . '<td '.$cell.'>'.htmlspecialchars((string)($r['software'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td '.$cell.'>'.htmlspecialchars((string)($r['version'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td '.$cell.'>'.$stateLabel.'</td>'
                  . '<td '.$cell.'>'.htmlspecialchars((string)($r['computer'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td '.$cell.'>'.htmlspecialchars(self::formatDateOnly((string)($r['installed'] ?? '')), ENT_QUOTES, 'UTF-8').'</td>'
                  . '</tr>';
         }
      }
      $tbl .= '</tbody></table>';
      $note = '<div style="margin-top:12px;padding:8px 10px;border:1px dashed #f59e0b;background:#fffbe6;border-radius:6px;color:#7c2d12;font-size:12px">'
            . '提醒：存在“违规安装”的软件请立即卸载，或联系 IT 管理员与部门负责人提出申请；“未登记安装”的软件请及时完成登记，登记方法请联系 IT 人员。'
            . '</div>';
      return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;">'.$greet.$metaHtml.$cards.$tbl.$note.'</div>';
   }

   private static function formatDateOnly(string $raw): string {
      $raw = trim($raw);
      if ($raw === '') return '';
      $ts = strtotime($raw);
      if ($ts !== false) return date('Y-m-d', $ts);
      if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) { return substr($raw, 0, 10); }
      return $raw;
   }

   /**
    * Owner full – PDF content with all installs
    */
   public static function renderOwnerFull(string $ownerEmail, array $rows): string {
      $css = self::style();
      // Aggregate stats
      $total=0;$approved=0;$blacklisted=0;$unmanaged=0;
      foreach ($rows as $r){ $total++; $st=(string)($r['status']??'unmanaged'); if($st==='approved')$approved++; elseif($st==='blacklisted')$blacklisted++; else $unmanaged++; }
      $cards = '<div class="cards">'
             . self::card('软件安装总数', $total, '#1f2937')
             . self::card('合规软件', $approved, '#059669')
             . self::card('违规软件', $blacklisted, '#dc2626')
             . self::card('未登记软件', $unmanaged, '#92400e')
             . '</div>';
      $tbl = '<table><thead><tr><th>软件名称</th><th>版本</th><th>状态</th><th>计算机</th><th>安装时间</th></tr></thead><tbody>';
      if (empty($rows)) { $tbl .= '<tr><td colspan="5">(无)</td></tr>'; }
      else {
         foreach ($rows as $r) {
            $st = (string)($r['status'] ?? 'unmanaged');
            $stateLabel = ($st==='blacklisted') ? '<span class="badge b-red">违规</span>' : (($st==='approved') ? '<span class="badge b-green">合规</span>' : '<span class="badge b-amber">未登记</span>');
            $tbl .= '<tr>'
                  . '<td>'.htmlspecialchars((string)($r['software'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td>'.htmlspecialchars((string)($r['version'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td>'.$stateLabel.'</td>'
                  . '<td>'.htmlspecialchars((string)($r['computer'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '<td>'.htmlspecialchars((string)($r['installed'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                  . '</tr>';
         }
      }
      $tbl .= '</tbody></table>';
      $title = '<h1>个人合规报告 · '.htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8').'</h1>';
      return '<html><head>'.$css.'</head><body>'.$title.$cards.$tbl.'</body></html>';
   }
   private static function composeOwner(string $realname, string $login): string {
      $realname = trim($realname);
      $login = trim($login);
      if ($realname !== '') return $realname;
      return $login;
   }

   private static function style(): string {
      return '<style>
      body{font-size:12px;color:#111827;font-weight:normal}
      h1{font-size:18px;margin:10px 0;font-weight:normal}
      h2{font-size:15px;margin:12px 0;font-weight:normal}
      h3{font-size:14px;margin:10px 0;font-weight:normal}
      .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:8px 0}
      .card{border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb;text-align:center}
      .card-num{font-size:20px;font-weight:normal}
      .card-label{font-size:12px;color:#6b7280}
      table{width:100%;border-collapse:collapse;margin:8px 0}
      th,td{border:1px solid #e5e7eb;padding:6px 8px;text-align:left;font-weight:normal}
      thead{background:#f3f4f6}
      .badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:normal}
      .b-red{background:#fee2e2;color:#991b1b}
      .b-amber{background:#fef3c7;color:#92400e}
      .b-green{background:#dcfce7;color:#065f46}
      </style>';
   }
}
