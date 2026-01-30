<?php
// Help tab content (extracted from front/config.php)

echo "<div style='max-width:1100px;margin:20px auto;' class='tab_cadre_fixe'>";
echo "<div class='center'><h3>".__('Software Manager – Plugin usage guide','softwaremanager')."</h3></div>";
echo "<div style='line-height:1.7; font-size:13px; color:#374151;'>";
echo "<h4>1. " . __('Installation & enable') . "</h4>";
echo "<p>".__('将插件拷贝到 GLPI/plugins/softwaremanager 目录，进入“设置→插件”启用本插件。首次启用会创建必要的数据库表与定时任务。','softwaremanager')."</p>";

echo "<h4>2. " . __('Permissions & menu') . "</h4>";
echo "<p>".__('需要具备插件对应的管理权限才能访问“系统配置→Software Manager”页面。','softwaremanager')."</p>";

echo "<h4>3. " . __('Automated actions (Cron)') . "</h4>";
echo "<p>".__('在 “Automated actions” 子页可以查看/手动运行定时任务。softwaremanager_autoscan 负责周期性软件扫描，softwaremanager_autoscan_mailer 负责根据最新扫描结果批量生成并发送邮件报告。','softwaremanager')."</p>";
echo "<div class='tab_cadre_fixe' style='margin:8px 0; padding:10px;'>";
echo "<div style='font-weight:bold;margin-bottom:6px;'>".__('使用建议','softwaremanager')."</div>";
echo "<ol style='margin:0 0 8px 18px; line-height:1.6;'>";
echo "<li>".__('进入 GLPI：设置 → 自动化动作，找到 softwaremanager_autoscan 与 softwaremanager_autoscan_mailer','softwaremanager')."</li>";
echo "<li>".__('将“运行方式(Mode)”改为 CLI，并根据需要设置“频率(Frequency)”与“启用(Active)”','softwaremanager')."</li>";
echo "<li>".__('保存后，这两个任务将按所设周期在后台运行','softwaremanager')."</li>";
echo "</ol>";
echo "<div style='color:#6b7280;font-size:12px;margin-top:6px;'>".__('如服务器采用标准的 GLPI CLI 方式运行自动化动作，可在系统计划任务中调用（GLPI 10+）：','softwaremanager')."</div>";
echo "<pre style='background:#f8f9fa;border:1px solid #e5e7eb;padding:8px;border-radius:4px;white-space:pre-wrap;'>*/5 * * * * php /path/to/glpi/bin/console glpi:cron</pre>";
echo "<div style='color:#6b7280;font-size:12px;'>".__('上面的例子表示每5分钟触发一次 CLI 计划任务；具体周期可根据业务自行决定。','softwaremanager')."</div>";
echo "</div>";

echo "<h4>4. " . __('Report targets') . "</h4>";
echo "<p>".__('在 “Report targets” 子页点击“+ 添加”，选择实体、主体群组（可多选，用于批量生成报告）、并在 Recipients 区域选择邮件收件人（支持用户/群组/配置文件/额外邮箱）。','softwaremanager')."</p>";
echo "<p>".__('“Options” 区域可设置：仅在违规时发信、未登记阈值、将多个群组合并为一封邮件、以及范围（主要/技术/两种）。','softwaremanager')."</p>";

echo "<h4>5. " . __('Emails & templates') . "</h4>";
echo "<p>".__('插件使用 GLPI 原生通知机制。邮件正文简洁展示重点问题（黑名单、未登记），完整 PDF 附件为详细报告。可在 GLPI 的“通知”处自定义模板与队列策略。','softwaremanager')."</p>";

echo "<h4>6. " . __('Manual test') . "</h4>";
echo "<p>".__('在列表中输入邮箱点击 “Send test” 可立即发送测试邮件，帮助验证模板与排障。','softwaremanager')."</p>";

echo "<h4>7. " . __('Troubleshooting') . "</h4>";
echo "<ul style=\"margin:6px 0 12px 18px;\"><li>".__('如果未见到定时任务，请重新启用插件或在 GLPI 的“自动化动作”中检查任务是否被禁用。','softwaremanager')."</li><li>".__('若邮件未收到，请查看“队列通知”与服务器邮件日志；必要时在 config.php 打开 debug。','softwaremanager')."</li><li>".__('PDF 中文乱码：服务器需安装 CJK 字体；插件已内置常用字体并在生成时启用。','softwaremanager')."</li></ul>";

echo "<h4>8. " . __('Upgrade & changes') . "</h4>";
echo "<p>".__('升级前建议备份数据库。若新增字段未自动创建，可在系统配置页看到提示或手动执行 SQL。','softwaremanager')."</p>";
echo "</div>";
echo "</div>";


