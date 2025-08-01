# Software Manager Plugin for GLPI

## 概述 (Overview)

Software Manager 是一个为 GLPI 设计的增强型软件资产管理插件，提供软件合规性检查和管理功能。

Software Manager is an enhanced software asset management plugin for GLPI that provides software compliance checking and management capabilities.

## 功能特性 (Features)

### 核心功能 (Core Features)
- **软件清单管理** - 基于 GLPI 现有软件清单的增强管理
- **黑白名单管理** - 配置允许和禁止的软件列表
- **合规性扫描** - 自动化软件合规性检查
- **报告生成** - 详细的合规性报告
- **邮件通知** - 违规情况的自动通知
- **权限管理** - 细粒度的用户权限控制

### English Features
- **Software Inventory Management** - Enhanced management based on GLPI's existing software inventory
- **Blacklist/Whitelist Management** - Configure allowed and prohibited software lists
- **Compliance Scanning** - Automated software compliance checking
- **Report Generation** - Detailed compliance reports
- **Email Notifications** - Automatic notifications for violations
- **Permission Management** - Fine-grained user permission control

## 系统要求 (Requirements)

- GLPI >= 10.0.0
- PHP >= 8.0
- MySQL/MariaDB

## 安装 (Installation)

1. 下载插件文件到 GLPI 的 plugins 目录
2. 在 GLPI 管理界面中启用插件
3. 配置权限和初始设置

1. Download plugin files to GLPI's plugins directory
2. Enable the plugin in GLPI administration interface
3. Configure permissions and initial settings

## 使用说明 (Usage)

### 菜单结构 (Menu Structure)
- **软件清单** (Software Inventory) - 查看和管理软件清单
- **合规审查记录** (Compliance Scan History) - 查看扫描历史
- **白名单管理** (Whitelist Management) - 管理允许的软件
- **黑名单管理** (Blacklist Management) - 管理禁止的软件
- **插件配置** (Plugin Configuration) - 配置插件设置

## 开发信息 (Development)

### 作者 (Author)
- **Abner Liu** (大刘讲IT)

### 仓库 (Repository)
- https://github.com/liugang926/GLPI_softwaremanager.git

### 许可证 (License)
- GPL-2.0+

## 版本历史 (Version History)

### v1.0.0
- 初始版本发布
- 基础框架实现
- 菜单和权限系统

### v1.0.0
- Initial release
- Basic framework implementation
- Menu and permission system

## 支持 (Support)

如有问题或建议，请在 GitHub 仓库中提交 Issue。

For issues or suggestions, please submit an Issue in the GitHub repository.

## 贡献 (Contributing)

欢迎提交 Pull Request 来改进这个插件。

Pull Requests are welcome to improve this plugin.
