<?php
/**
 * Chinese translation - Software Manager Plugin for GLPI
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 * @package GLPI\Plugin\Softwaremanager
 */

global $LANG;

$LANG['softwaremanager']['menu']['softwarelist'] = '软件清单';
$LANG['softwaremanager']['menu']['scanhistory'] = '扫描历史';
$LANG['softwaremanager']['menu']['whitelist'] = '白名单管理';
$LANG['softwaremanager']['menu']['blacklist'] = '黑名单管理';
$LANG['softwaremanager']['menu']['import'] = '导入导出';
$LANG['softwaremanager']['menu']['config'] = '系统配置';
$LANG['softwaremanager']['menu']['analytics'] = '分析报表';

$LANG['softwaremanager']['config']['tab_cron'] = '自动化动作';
$LANG['softwaremanager']['config']['tab_targets'] = '报告目标';
$LANG['softwaremanager']['config']['tab_help'] = '帮助';

// Scan related
$LANG['softwaremanager']['scan']['title'] = '软件合规性扫描';
$LANG['softwaremanager']['scan']['running'] = '扫描进行中...';
$LANG['softwaremanager']['scan']['completed'] = '扫描完成';
$LANG['softwaremanager']['scan']['failed'] = '扫描失败';
$LANG['softwaremanager']['scan']['no_data'] = '暂无扫描数据';
$LANG['softwaremanager']['scan']['latest_scan'] = '最新扫描';

// Common vocabulary
$LANG['softwaremanager']['common']['total'] = '总计';
$LANG['softwaremanager']['common']['approved'] = '合规';
$LANG['softwaremanager']['common']['whitelist'] = '白名单';
$LANG['softwaremanager']['common']['blacklisted'] = '违规';
$LANG['softwaremanager']['common']['blacklist'] = '黑名单';
$LANG['softwaremanager']['common']['unmanaged'] = '未登记';
$LANG['softwaremanager']['common']['software'] = '软件';
$LANG['softwaremanager']['common']['version'] = '版本';
$LANG['softwaremanager']['common']['computer'] = '计算机';
$LANG['softwaremanager']['common']['user'] = '用户';
$LANG['softwaremanager']['common']['group'] = '群组';
$LANG['softwaremanager']['common']['entity'] = '实体';
$LANG['softwaremanager']['common']['actions'] = '操作';
$LANG['softwaremanager']['common']['add'] = '添加';
$LANG['softwaremanager']['common']['edit'] = '编辑';
$LANG['softwaremanager']['common']['delete'] = '删除';
$LANG['softwaremanager']['common']['save'] = '保存';
$LANG['softwaremanager']['common']['cancel'] = '取消';
$LANG['softwaremanager']['common']['search'] = '搜索';
$LANG['softwaremanager']['common']['export'] = '导出';
$LANG['softwaremanager']['common']['import'] = '导入';
$LANG['softwaremanager']['common']['confirm_delete'] = '确认删除？此操作不可撤销。';
$LANG['softwaremanager']['common']['status'] = '状态';
$LANG['softwaremanager']['common']['enabled'] = '已启用';
$LANG['softwaremanager']['common']['disabled'] = '已禁用';
$LANG['softwaremanager']['common']['yes'] = '是';
$LANG['softwaremanager']['common']['no'] = '否';
$LANG['softwaremanager']['common']['all'] = '全部';
$LANG['softwaremanager']['common']['none'] = '无';
$LANG['softwaremanager']['common']['name'] = '名称';
$LANG['softwaremanager']['common']['date'] = '日期';
$LANG['softwaremanager']['common']['description'] = '描述';
$LANG['softwaremanager']['common']['comment'] = '备注';

// Error messages
$LANG['softwaremanager']['error']['not_found'] = '未找到记录';
$LANG['softwaremanager']['error']['no_permission'] = '权限不足';
$LANG['softwaremanager']['error']['invalid_input'] = '输入无效';
$LANG['softwaremanager']['error']['database_error'] = '数据库错误';
$LANG['softwaremanager']['error']['scan_failed'] = '扫描失败';
$LANG['softwaremanager']['error']['file_not_found'] = '文件未找到';
$LANG['softwaremanager']['error']['invalid_file'] = '文件格式无效';

// Success messages
$LANG['softwaremanager']['success']['saved'] = '保存成功';
$LANG['softwaremanager']['success']['deleted'] = '删除成功';
$LANG['softwaremanager']['success']['imported'] = '导入完成';
$LANG['softwaremanager']['success']['exported'] = '导出完成';
$LANG['softwaremanager']['success']['scan_completed'] = '扫描完成';
$LANG['softwaremanager']['success']['settings_updated'] = '设置已更新';

// Page titles
$LANG['softwaremanager']['title']['plugin_configuration'] = '插件配置';
$LANG['softwaremanager']['title']['software_list'] = '软件清单';
$LANG['softwaremanager']['title']['scan_history'] = '扫描历史';
$LANG['softwaremanager']['title']['whitelist_management'] = '白名单管理';
$LANG['softwaremanager']['title']['blacklist_management'] = '黑名单管理';
$LANG['softwaremanager']['title']['import_export'] = '导入导出';
$LANG['softwaremanager']['title']['analytics'] = '分析报表';

// Cron task names
$LANG['softwaremanager']['cron']['autoscan'] = '自动软件合规扫描';
$LANG['softwaremanager']['cron']['automailer'] = '自动发送合规报告邮件';

// Notification messages
$LANG['softwaremanager']['notification']['report_subject'] = '[GLPI] 软件合规报告';
$LANG['softwaremanager']['notification']['group_report'] = '群组合规报告';
$LANG['softwaremanager']['notification']['computer_report'] = '计算机违规提醒';

// Config options
$LANG['softwaremanager']['config']['enable_autoscan'] = '启用自动扫描';
$LANG['softwaremanager']['config']['scan_interval'] = '扫描间隔';
$LANG['softwaremanager']['config']['enable_notifications'] = '启用通知';
$LANG['softwaremanager']['config']['notification_email'] = '通知邮箱';

// Button labels
$LANG['softwaremanager']['button']['run_scan'] = '运行扫描';
$LANG['softwaremanager']['button']['view_details'] = '查看详情';
$LANG['softwaremanager']['button']['download_report'] = '下载报告';
$LANG['softwaremanager']['button']['send_test_email'] = '发送测试邮件';

// Help text
$LANG['softwaremanager']['help']['intro'] = '软件管理插件帮助您管理GLPI中的软件合规性。';
$LANG['softwaremanager']['help']['whitelist_desc'] = '白名单允许您标记已批准的软件。';
$LANG['softwaremanager']['help']['blacklist_desc'] = '黑名单允许您标记禁止的软件。';
$LANG['softwaremanager']['help']['scan_desc'] = '扫描功能检测所有计算机上的软件并将其与规则进行比较。';
