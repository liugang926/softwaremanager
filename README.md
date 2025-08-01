# Software Manager Plugin for GLPI

[![License](https://img.shields.io/badge/License-GPL--2.0%2B-blue.svg)](LICENSE)
[![GLPI Version](https://img.shields.io/badge/GLPI-10.0.0%2B-green.svg)](https://glpi-project.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net/)

## 📋 概述 (Overview)

Software Manager 是一个为 GLPI 设计的增强型软件资产管理插件，专注于软件合规性检查和智能化管理。该插件通过先进的匹配规则引擎、自动化扫描机制和全面的报告系统，帮助组织有效管理软件资产，确保合规性，降低安全风险。

Software Manager is an enhanced software asset management plugin for GLPI that focuses on software compliance checking and intelligent management. Through advanced matching rule engines, automated scanning mechanisms, and comprehensive reporting systems, this plugin helps organizations effectively manage software assets, ensure compliance, and reduce security risks.

## ✨ 功能特性 (Features)

### 🎯 核心功能 (Core Features)
- **🗂️ 软件清单管理** - 基于 GLPI 现有软件清单的增强管理和智能分类
- **📋 黑白名单管理** - 灵活配置允许和禁止的软件列表，支持批量操作
- **🔍 合规性扫描** - 自动化软件合规性检查，实时监控违规软件
- **📊 数据分析** - 多维度分析报告（用户、群组、实体、软件趋势）
- **🎨 增强规则引擎** - 支持复杂匹配条件和自定义规则
- **📧 智能通知** - 违规情况的自动邮件通知和提醒
- **🔐 权限管理** - 细粒度的用户权限控制和角色管理
- **📈 历史追踪** - 完整的扫描历史记录和变更追踪

### 🌟 Advanced Features
- **Software Inventory Management** - Enhanced management and intelligent classification based on GLPI's existing software inventory
- **Blacklist/Whitelist Management** - Flexible configuration of allowed and prohibited software lists with batch operations
- **Compliance Scanning** - Automated software compliance checking with real-time violation monitoring
- **Data Analytics** - Multi-dimensional analysis reports (users, groups, entities, software trends)
- **Enhanced Rule Engine** - Support for complex matching conditions and custom rules
- **Smart Notifications** - Automatic email notifications and alerts for violations
- **Permission Management** - Fine-grained user permission control and role management
- **History Tracking** - Complete scan history records and change tracking

## 💾 系统要求 (Requirements)

### 最低要求 (Minimum Requirements)
- **GLPI**: >= 10.0.0 (测试兼容至 11.0.0)
- **PHP**: >= 8.0 (推荐 8.1+)
- **数据库**: MySQL >= 5.7 或 MariaDB >= 10.2
- **Web服务器**: Apache 2.4+ 或 Nginx 1.18+
- **内存**: 最少 256MB，推荐 512MB+

### 推荐配置 (Recommended Configuration)
- **PHP Extensions**: `mysqli`, `curl`, `json`, `mbstring`, `xml`
- **PHP Settings**: `memory_limit >= 256M`, `max_execution_time >= 300`

## 🚀 安装 (Installation)

### 方法一：从 GitHub 下载 (Method 1: Download from GitHub)

```bash
# 进入 GLPI 插件目录
cd /path/to/glpi/plugins

# 克隆仓库
git clone https://github.com/liugang926/softwaremanager.git softwaremanager

# 或下载并解压
wget https://github.com/liugang926/softwaremanager/archive/main.zip
unzip main.zip
mv softwarecompliance-main softwaremanager
```

### 方法二：手动安装 (Method 2: Manual Installation)

1. **下载插件** - 从 [GitHub Releases](https://github.com/liugang926/softwaremanager/releases) 下载最新版本
2. **解压文件** - 将压缩包解压到 `glpi/plugins/softwaremanager/` 目录
3. **设置权限** - 确保 Web 服务器对插件目录有读写权限

### 启用插件 (Enable Plugin)

1. **登录 GLPI** - 使用管理员账号登录 GLPI
2. **进入插件管理** - 导航到 `设置 > 插件`
3. **安装插件** - 找到 "Software Manager" 并点击 "安装"
4. **启用插件** - 安装完成后点击 "启用"

### 初始配置 (Initial Configuration)

```bash
# 如果需要手动运行安装脚本
php -f plugins/softwaremanager/install/setup_database.php

# 检查权限设置
php -f plugins/softwaremanager/front/install_permissions.php
```

## 📖 使用说明 (Usage Guide)

### 🗂️ 菜单结构 (Menu Structure)

插件安装后，在 GLPI 主菜单的 **管理** 部分会出现 **Software Manager** 菜单，包含以下子菜单：

- **📋 软件清单** (`Software Inventory`) - 查看和管理软件清单，支持搜索和过滤
- **📊 数据分析** (`Analytics`) - 多维度数据分析和趋势报告
- **📈 合规审查记录** (`Scan History`) - 查看历史扫描记录和结果
- **✅ 白名单管理** (`Whitelist Management`) - 管理允许的软件列表
- **🚫 黑名单管理** (`Blacklist Management`) - 管理禁止的软件列表
- **⚙️ 插件配置** (`Configuration`) - 配置插件设置和规则

### 🚀 快速开始 (Quick Start)

#### 1. 配置黑白名单
```
1. 进入 "白名单管理" 添加允许的软件
2. 进入 "黑名单管理" 添加禁止的软件
3. 使用增强规则引擎设置复杂匹配条件
```

#### 2. 执行合规扫描
```
1. 进入 "软件清单" 页面
2. 点击 "开始扫描" 按钮
3. 选择扫描范围（全部或特定实体）
4. 等待扫描完成并查看结果
```

#### 3. 查看分析报告
```
1. 进入 "数据分析" 页面
2. 选择分析维度（用户/群组/实体/软件）
3. 设置时间范围和过滤条件
4. 查看图表和详细报告
```

### ⚙️ 详细配置 (Detailed Configuration)

#### 黑白名单配置 (Blacklist/Whitelist Configuration)

**白名单配置**：
- 支持软件名称精确匹配和模糊匹配
- 支持版本号过滤和范围设置
- 支持发布商和分类管理
- 支持批量导入导出

**黑名单配置**：
- 支持多种匹配模式（精确、包含、正则表达式）
- 支持优先级设置和继承规则
- 支持临时例外和时限设置

#### 增强规则引擎 (Enhanced Rule Engine)

```yaml
规则类型:
  - name_match: 软件名称匹配
  - version_range: 版本范围检查
  - publisher_filter: 发布商过滤
  - install_path: 安装路径匹配
  - file_signature: 文件签名验证

操作符:
  - equals: 完全匹配
  - contains: 包含匹配
  - regex: 正则表达式
  - version_compare: 版本比较
```

#### 通知配置 (Notification Configuration)

```php
邮件通知设置:
  - 启用/禁用邮件通知
  - 配置 SMTP 服务器
  - 设置收件人列表
  - 自定义邮件模板
  - 设置通知频率和条件
```

## 🏗️ 技术架构 (Technical Architecture)

### 📁 项目结构 (Project Structure)

```
softwaremanager/
├── 📁 ajax/                    # AJAX 处理脚本
│   ├── compliance_scan.php    # 合规扫描处理
│   ├── software_details.php   # 软件详情获取
│   └── setup_database.php     # 数据库设置
├── 📁 css/                     # 样式文件
│   ├── softwaremanager.css    # 主样式
│   ├── analytics.css          # 分析页面样式
│   └── compliance-report.css  # 报告样式
├── 📁 front/                   # 前端页面
│   ├── softwarelist.php       # 软件清单页面
│   ├── analytics.php          # 数据分析页面
│   ├── scanhistory.php        # 扫描历史页面
│   ├── whitelist.php          # 白名单管理页面
│   └── blacklist.php          # 黑名单管理页面
├── 📁 inc/                     # 核心类文件
│   ├── menu.class.php          # 菜单管理类
│   ├── rule.class.php          # 规则引擎类
│   ├── enhancedrule.class.php  # 增强规则类
│   ├── scanresult.class.php    # 扫描结果类
│   └── analytics_*.php         # 分析组件类
├── 📁 js/                      # JavaScript 文件
│   ├── softwaremanager.js     # 主脚本
│   ├── enhanced-selector.js   # 增强选择器
│   └── compliance-report.js   # 报告脚本
└── 📁 install/                 # 安装脚本
    ├── database_upgrade.php   # 数据库升级
    └── migrate_enhanced_rules.php # 规则迁移
```

### 🔧 核心组件 (Core Components)

#### 1. 菜单系统 (Menu System)
- **PluginSoftwaremanagerMenu**: 主菜单管理类
- 动态权限检查和菜单生成
- 支持多语言和自定义图标

#### 2. 规则引擎 (Rule Engine)
- **PluginSoftwaremanagerRule**: 基础规则类
- **PluginSoftwaremanagerEnhancedRule**: 增强规则类
- 支持复杂条件匹配和优先级处理

#### 3. 数据分析 (Analytics Engine)
- **多维度分析**: 用户、群组、实体、软件
- **趋势分析**: 时间序列数据和预测
- **可视化报告**: 图表和统计数据

#### 4. 扫描引擎 (Scanning Engine)
- **实时扫描**: 增量和全量扫描支持
- **批量处理**: 大规模数据处理优化
- **结果缓存**: 性能优化和数据持久化

### 🛠️ 开发指南 (Development Guide)

#### 环境设置 (Development Setup)

```bash
# 克隆开发版本
git clone https://github.com/liugang926/softwaremanager.git

# 安装开发依赖
composer install --dev

# 运行代码质量检查
composer run-script phpcs

# 运行单元测试
composer run-script phpunit
```

#### 代码规范 (Coding Standards)

- **PSR-12**: PHP 编码标准
- **GLPI 命名约定**: 类和方法命名遵循 GLPI 约定
- **文档注释**: 所有公共方法必须有 PHPDoc 注释
- **安全编码**: 遵循 OWASP 安全编码实践

#### 数据库设计 (Database Design)

```sql
-- 主要数据表
glpi_plugin_softwaremanager_rules         # 规则配置表
glpi_plugin_softwaremanager_whitelist     # 白名单表
glpi_plugin_softwaremanager_blacklist     # 黑名单表
glpi_plugin_softwaremanager_scanresults   # 扫描结果表
glpi_plugin_softwaremanager_scanhistory   # 扫描历史表
glpi_plugin_softwaremanager_analytics     # 分析数据表
```

#### API 接口 (API Interface)

```php
// AJAX API 示例
ajax/compliance_scan.php    # POST /ajax/compliance_scan.php
ajax/software_details.php  # GET  /ajax/software_details.php?id={id}
ajax/analytics_data.php    # GET  /ajax/analytics_data.php?type={type}
```

### 🧪 测试 (Testing)

#### 单元测试 (Unit Tests)
```bash
# 运行所有测试
./vendor/bin/phpunit

# 运行特定测试
./vendor/bin/phpunit tests/RuleEngineTest.php

# 生成覆盖率报告
./vendor/bin/phpunit --coverage-html coverage/
```

#### 集成测试 (Integration Tests)
- 数据库连接测试
- GLPI 兼容性测试
- 权限系统测试

## 🤝 开发信息 (Development Information)

### 👨‍💻 作者 (Author)
- **Abner Liu** (大刘讲IT)
- **Email**: 709840110@qq.com
- **GitHub**: [@liugang926](https://github.com/liugang926)

### 🔗 相关链接 (Links)
- **主仓库**: https://github.com/liugang926/softwaremanager
- **问题反馈**: https://github.com/liugang926/softwaremanager/issues
- **开发文档**: [GLPI软件合规审查插件开发手册.md](GLPI软件合规审查插件开发手册.md)

### 📄 许可证 (License)
- **GPL-2.0+** - 详见 [LICENSE](LICENSE) 文件

## 📋 版本历史 (Version History)

### v1.0.0 (Current)
- ✅ 初始版本发布
- ✅ 基础框架实现
- ✅ 菜单和权限系统
- ✅ 黑白名单管理功能
- ✅ 合规扫描引擎
- ✅ 数据分析和报告系统
- ✅ 增强规则引擎
- ✅ 多语言支持 (中文/英文)

### 计划中的功能 (Planned Features)
- 🔄 自动化定时扫描
- 🔄 高级报告模板
- 🔄 REST API 接口
- 🔄 移动端支持
- 🔄 更多语言支持

## ❓ 故障排除 (Troubleshooting)

### 🚨 常见问题 (Common Issues)

#### 1. 插件安装失败
**问题**: 插件在 GLPI 中无法安装或启用

**解决方案**:
```bash
# 检查 PHP 版本
php -v  # 需要 >= 8.0

# 检查 GLPI 版本兼容性
# 确保 GLPI >= 10.0.0

# 检查文件权限
chmod -R 755 plugins/softwaremanager/
chown -R www-data:www-data plugins/softwaremanager/

# 检查数据库连接
php plugins/softwaremanager/install/setup_database.php
```

#### 2. 扫描功能异常
**问题**: 合规扫描无法正常执行或结果异常

**解决方案**:
```php
// 检查数据库表是否正确创建
SELECT * FROM glpi_plugin_softwaremanager_scanresults LIMIT 1;

// 检查权限设置
// 确保用户有软件清单的读取权限

// 清除缓存
rm -rf files/_cache/softwaremanager_*
```

#### 3. 页面显示异常
**问题**: 页面样式错误或功能按钮不响应

**解决方案**:
```bash
# 清除浏览器缓存
# 检查 CSS/JS 文件是否正确加载

# 检查 Apache/Nginx 配置
# 确保静态文件可以正常访问

# 检查控制台错误
# F12 开发者工具查看 JavaScript 错误
```

#### 4. 性能问题
**问题**: 大量数据时扫描速度慢或页面响应慢

**解决方案**:
```php
// 优化 PHP 配置
memory_limit = 512M
max_execution_time = 300

// 数据库优化
-- 添加索引
CREATE INDEX idx_software_name ON glpi_softwares(name);
CREATE INDEX idx_computer_id ON glpi_plugin_softwaremanager_scanresults(computer_id);

// 启用缓存
// 在插件配置中启用结果缓存
```

### 🔧 调试模式 (Debug Mode)

启用调试模式获取详细错误信息：

```php
// 在 config/config_db.php 中添加
define('GLPI_DEBUG', true);

// 或在插件配置中启用调试日志
// 日志文件位置: files/_log/softwaremanager.log
```

### 📞 获取帮助 (Getting Help)

#### 1. 文档资源
- 📖 [开发手册](GLPI软件合规审查插件开发手册.md)
- 📖 [匹配规则强化开发文档](匹配规则强化开发.md)
- 📖 [群组维度分析说明](群组维度分析-数据关联说明.md)

#### 2. 社区支持
- 🐛 [问题反馈](https://github.com/liugang926/softwaremanager/issues)
- 💬 [讨论区](https://github.com/liugang926/softwaremanager/discussions)
- 📧 技术支持: 709840110@qq.com
- 🐧 QQ群：1097440406
-   公众号：大刘讲IT

#### 3. 日志分析
```bash
# GLPI 系统日志
tail -f files/_log/php-errors.log

# 插件专用日志
tail -f files/_log/softwaremanager.log

# Apache/Nginx 访问日志
tail -f /var/log/apache2/access.log
```

## 🤝 贡献 (Contributing)

### 贡献指南 (Contribution Guidelines)

我们欢迎各种形式的贡献！

#### 1. 代码贡献 (Code Contributions)
```bash
# Fork 仓库
git clone https://github.com/liugang926/softwaremanager.git

# 创建功能分支
git checkout -b feature/new-feature

# 提交更改
git commit -m "Add: 新功能描述"

# 推送分支
git push origin feature/new-feature

# 创建 Pull Request
```

#### 2. 问题报告 (Issue Reporting)
提交问题时请包含：
- GLPI 版本和插件版本
- PHP 版本和系统环境
- 详细的错误描述和复现步骤
- 相关的日志信息

#### 3. 文档改进 (Documentation)
- 修正错误和不准确的信息
- 添加使用示例和教程
- 翻译文档到其他语言

### 📋 开发规范 (Development Standards)
- 遵循 PSR-12 编码标准
- 编写单元测试
- 更新相关文档
- 保持向后兼容性

## 🙋‍♂️ 支持 (Support)

如有问题或建议，请通过以下方式联系：

- 📋 **问题反馈**: [GitHub Issues](https://github.com/liugang926/softwaremanager/issues)
- 💬 **功能建议**: [GitHub Discussions](https://github.com/liugang926/softwaremanager/discussions)
- 📧 **直接联系**: 709840110@qq.com

---

## 📜 许可证 (License)

本项目采用 **GPL-2.0+** 许可证 - 详见 [LICENSE](LICENSE) 文件。

Copyright © 2025 Abner Liu. All rights reserved.

---

**⭐ 如果这个项目对您有帮助，请给我们一个 Star！**

**🔄 欢迎 Fork 和贡献代码！**
