<?php
/**
 * Softwaremanager – direct mail test page (uses GLPI mail transport)
 * Sends immediately without using queuednotification.
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_softwaremanager', UPDATE);

Html::header(__('Softwaremanager mail test', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

echo "<div class='center' style='max-width:760px;margin:20px auto'>";
echo "<h2>".__('Softwaremanager mail test', 'softwaremanager')."</h2>";

$result_msg = '';
$result_type = INFO;

// Accept GET to avoid CSRF requirement for simple test page
if (isset($_REQUEST['to'])) {

   $raw = (string)($_REQUEST['to'] ?? '');
   // Normalize common full-width/quotes
   $norm = function(string $s): string {
      $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
      if (function_exists('mb_convert_kana')) {
         $s = mb_convert_kana($s, 'asKV', 'UTF-8');
      }
      $s = strtr($s, ['＠'=>'@','．'=>'.','，'=>',','；'=>';','“'=>'"','”'=>'"','‘'=>"'",'’'=>"'"]);
      return trim($s, " <>\"'\t\r\n");
   };
   $to = $norm($raw);

   $subject = trim((string)($_REQUEST['subject'] ?? '[GLPI] Softwaremanager test email'));
   $body    = (string)($_REQUEST['body'] ?? '');
   if ($body === '') {
      $body = '<p>This is a test mail sent by Softwaremanager plugin using GLPI mail transport.</p>'
            . '<p>Timestamp: '.Html::clean(date('Y-m-d H:i:s')).'</p>';
   }

   if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      $result_msg  = __('Invalid email address', 'softwaremanager').': '.Html::clean($raw);
      $result_type = ERROR;
   } else {
      // Immediate send using configured GLPI mailer
      try {
         $mailer = new GLPIMailer();
         $mailer->isHTML(true);
         $mailer->Subject = $subject;
         $mailer->Body    = $body;
         $mailer->AltBody = strip_tags($body);
         $mailer->addAddress($to);
         $sent = $mailer->send();
         if ($sent) {
            $result_msg  = sprintf(__('Mail sent to %s', 'softwaremanager'), Html::clean($to));
            $result_type = INFO;
         } else {
            $result_msg  = __('Send failed', 'softwaremanager').': '.Html::clean($mailer->ErrorInfo ?? '');
            $result_type = ERROR;
         }
      } catch (Throwable $e) {
         $result_msg  = __('Send failed', 'softwaremanager').': '.Html::clean($e->getMessage());
         $result_type = ERROR;
      }
   }

   Session::addMessageAfterRedirect($result_msg, false, $result_type);
   Html::redirect($_SERVER['PHP_SELF']);
}

echo "<form method='get' style='margin-top:15px'>";
echo "<table class='tab_cadre_fixe' style='width:100%'>";
echo "<tr class='tab_bg_1'><th style='width:160px'>".__('Recipient (email)', 'softwaremanager')."</th><td><input type='email' name='to' style='width:100%' placeholder='you@example.com' required></td></tr>";
echo "<tr class='tab_bg_1'><th>".__('Subject')."</th><td><input type='text' name='subject' style='width:100%' value='[GLPI] Softwaremanager test email'></td></tr>";
echo "<tr class='tab_bg_1'><th>".__('Message')."</th><td><textarea name='body' rows='6' style='width:100%'></textarea></td></tr>";
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='vsubmit' type='submit'>".__('Send test')."</button></td></tr>";
echo "</table>";
echo "</form>";

echo "</div>";

Html::footer();

?>


