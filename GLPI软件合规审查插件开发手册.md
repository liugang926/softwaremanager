# **GLPI 软件管理插件开发文档**

## **插件核心功能概述**

本插件旨在增强 GLPI 的软件资产管理能力。核心功能是基于 GLPI 数据库中已有的软件清单，通过管理员配置的黑白名单，实现对组织内软件安装的合规性审查。插件将提供一个集中的软件清单视图，方便管理员快速将软件归类至黑名单或白名单。通过手动或自动化的合规性审查，系统能生成详细的审查报告，清晰地列出安装了违规软件（黑名单）和未报备软件（非白名单）的电脑及使用人信息，并支持邮件通知功能，从而实现高效、自动化的软件合规闭环管理。

开发作者：Abner Liu(大刘讲IT)
插件仓库地址：https://github.com/liugang926/softwaremanager.git
创作日期：2025年7月

## **插件名称与结构 (现代实践版)**

*   **插件名称：** `softwaremanager` (建议)
*   **目录结构：**
    ```
    plugins/softwaremanager/
    ├── inc/                           // 主要业务逻辑类 (CommonDBTM, CronTask 等)
    ├── front/                         // 前端页面 (PHP 文件)
    ├── ajax/                          // AJAX 处理脚本
    ├── css/                           // CSS 样式表
    ├── js/                            // JavaScript 文件
    ├── locales/                       // 语言文件 (.po, .mo)
    ├── vendor/                        // Composer 依赖 (自动生成)
    ├── setup.php                      // 插件初始化、安装/卸载脚本 (核心)
    ├── hook.php                       // GLPI 钩子定义，用于高级集成
    ├── plugin.xml                     // 插件市场元数据 (推荐)
    ├── composer.json                  // PHP 依赖管理 (推荐)
    ├── phpunit.xml                    // 单元测试配置 (推荐)
    ├── .phpcs.xml                     // 代码风格检查配置 (推荐)
    └── README.md                      // 插件说明文件
    ```

## **全新开发实施步骤**

### **开发任务执行总纲 (Implementation High-Level Plan)**
**AI 执行指令**: 作为一个综合性的开发任务，请严格遵循以下从第一步到第七步的顺序进行开发。每个步骤都为后续步骤奠定了基础，因此**必须按顺序完成**，不可跳过或并行执行。

### **第一步：插件基础框架搭建**

1.  **环境与骨架**：
    *   搭建 GLPI 开发环境，创建 `softwaremanager` 插件目录及[标准的子目录结构](#插件名称与结构-现代实践版)。
    *   初始化 `composer.json`，为后续引入依赖做准备。
2.  **插件注册与初始化 (`setup.php`)**：
    *   实现 `plugin_init_softwaremanager()` 函数，作为插件入口。
    *   实现 `plugin_version_softwaremanager()` 函数，定义插件元数据（名称、版本等）。
    *   实现 `plugin_softwaremanager_install()` 和 `plugin_softwaremanager_uninstall()` 存根函数，用于后续添加数据库表的创建和清理逻辑。此时插件应可在 GLPI 中被识别、安装和卸载。
3.  **创建菜单和空页面 (`front/`)**：
    *   在 `setup.php` 中使用 `Plugin::registerClass` 结合 `getMenuContent` 或直接操作全局菜单数组，预先定义好所有功能的导航菜单项。
    *   **目标菜单结构**:
        *   软件管理 (顶级菜单)
            *   软件清单
            *   合规审查记录
            *   白名单管理
            *   黑名单管理
            *   插件配置
    *   为以上每个菜单项创建对应的空白 PHP 文件（如 `front/softwarelist.php`, `front/scanhistory.php` 等）。每个文件应包含基本的 GLPI 页面框架代码，确保点击菜单能成功跳转到一个空白但有效的页面，并显示正确的标题。

### **第二步：黑白名单管理**

1.  **创建数据模型 (`inc/`)**：
    *   创建 `PluginSoftwaremanagerSoftwareWhitelist` 和 `PluginSoftwaremanagerSoftwareBlacklist` 类，继承自 `CommonDBTM`。
    *   在 `install` 函数中添加创建 `glpi_plugin_softwaremanager_whitelists` 和 `glpi_plugin_softwaremanager_blacklists` 数据库表的逻辑。
    *   **核心方法**:
        *   `getSearchOptions()`: 为管理页面的列表定义搜索条件。
        *   `check()`: 在数据保存前进行验证（如名称不能为空）。

2.  **黑白名单匹配规则 (Matching Rules)**
    为了提供灵活而强大的匹配能力，黑白名单的规则遵循以下逻辑，以录入的软件名称中是否包含星号（`*`）作为判断依据：

    *   **默认：精确匹配 (Exact Match)**
        *   **规则**: 如果名单中的条目不包含星号，则进行不区分大小写的精确匹配。
        *   **示例**: 黑名单条目为 `7-zip 19.00 (x64)`，则只有资产清单中软件名称完全为 `7-zip 19.00 (x64)` 的软件才会被匹配。`7-zip` 或 `7-zip 21.07` **不会**被匹配。

    *   **后缀通配符 (Suffix Wildcard)**
        *   **规则**: `名称*` (星号在后)。匹配所有以此名称开头（不区分大小写）的软件。
        *   **示例**: 黑名单条目为 `solidworks*`，则 `SolidWorks 2022`, `Solidworks eDrawings`, 甚至 `solidworks` 自身都会被匹配。

    *   **前缀通配符 (Prefix Wildcard)**
        *   **规则**: `*名称` (星号在前)。匹配所有以此名称结尾（不区分大小写）的软件。
        *   **示例**: 白名单条目为 `*viewer`，则 `Microsoft Office Excel Viewer`, `IrfanView` 等软件都会被视为合规。

    *   **包含通配符 (Contains Wildcard)**
        *   **规则**: `*名称*` (前后都有星号)。匹配所有包含此名称（不区分大小写）的软件。
        *   **示例**: 黑名单条目为 `*toolbar*`，则 `Google Toolbar`, `Ask Toolbar` 等所有名称中含有 `toolbar` 的软件都会被匹配。

3.  **实现前端页面 (`front/whitelist.php` & `blacklist.php`)**：
    *   完善 `front/whitelist.php` 和 `blacklist.php`。
    *   使用 `Search::show('PluginSoftwaremanagerSoftwareWhitelist')` / `...Blacklist` 快速生成功能完整的列表页面。
    *   **UI 元素**:
        *   表单应包含软件名称、备注等字段。
        *   提供一个 "导入" 按钮，指向 `ajax/import.php` 的文件上传表单。
    *   **权限**: `plugin_softwaremanager_lists` (需要写入 `w` 权限才能看到添加和导入按钮)
3.  **实现导入接口 (`ajax/import.php`)**
    *   **核心逻辑**:
        1.  检查权限 (`plugin_softwaremanager_lists`, 'w') 和 CSRF 令牌。
        2.  处理 `$_FILES` 上传的 CSV 或 Excel 文件 (推荐使用 `PhpSpreadsheet` 库)。
        3.  使用 `fgetcsv` 或 `PhpSpreadsheet` 解析文件。
        4.  批量插入到黑名单或白名单数据表。
        5.  返回 JSON 格式的处理结果（成功导入数，失败数等）。

### **第三步：软件清单展示与管理**

1.  **实现核心查询逻辑 (`inc/softwareinventory.class.php`)**:
    *   此步骤是软件清单功能的核心。当前 `getSoftwareInventory` 方法中的查询虽然能工作，但在数据量较大时可能会遇到性能瓶颈，并且逻辑耦合度较高。
    *   **优化后的SQL查询建议**:
        我们强烈建议重构 `getSoftwareInventory` 方法中的 SQL 查询，以提升性能和可维护性。以下是一个经过优化的版本，它利用子查询来解耦状态判断，效率更高。

        ```php
        // 在 inc/softwareinventory.class.php -> getSoftwareInventory() 中
        // ... (方法参数和变量验证保持不变) ...

        // 基础查询，仅获取软件和制造商信息
        $sql = "SELECT
                    s.id AS software_id,
                    s.name AS software_name,
                    s.date_creation,
                    m.name AS manufacturer,
                    -- 使用子查询预先判断状态，避免在主查询中进行复杂的LEFT JOIN判断
                    (SELECT 
                        CASE
                            WHEN EXISTS (SELECT 1 FROM `glpi_plugin_softwaremanager_softwarewhitelists` w WHERE w.name = s.name AND w.is_active = 1) AND EXISTS (SELECT 1 FROM `glpi_plugin_softwaremanager_softwareblacklists` b WHERE b.name = s.name AND b.is_active = 1) THEN 'both'
                            WHEN EXISTS (SELECT 1 FROM `glpi_plugin_softwaremanager_softwarewhitelists` w WHERE w.name = s.name AND w.is_active = 1) THEN 'whitelist'
                            WHEN EXISTS (SELECT 1 FROM `glpi_plugin_softwaremanager_softwareblacklists` b WHERE b.name = s.name AND b.is_active = 1) THEN 'blacklist'
                            ELSE 'unmanaged'
                        END
                    ) AS status,
                    -- 使用子查询独立计算安装总数，确保聚合的准确性
                    (SELECT COUNT(DISTINCT isv.items_id)
                     FROM `glpi_softwareversions` sv_count
                     JOIN `glpi_items_softwareversions` isv ON sv_count.id = isv.softwareversions_id
                     WHERE sv_count.softwares_id = s.id AND isv.itemtype = 'Computer' AND isv.is_deleted = 0
                    ) AS computer_count,
                    -- 使用子查询聚合版本信息
                    (SELECT GROUP_CONCAT(DISTINCT sv_ver.name SEPARATOR ', ')
                     FROM `glpi_softwareversions` sv_ver
                     WHERE sv_ver.softwares_id = s.id
                    ) AS version
                FROM
                    `glpi_softwares` AS s
                LEFT JOIN
                    `glpi_manufacturers` AS m ON s.manufacturers_id = m.id
                WHERE
                    s.is_deleted = 0";

        // -- 过滤条件 --
        // 搜索过滤 (软件名或制造商名)
        if (!empty($search)) {
            $search_escaped = $DB->escape($search);
            $sql .= " AND (s.name LIKE '%$search_escaped%' OR m.name LIKE '%$search_escaped%')";
        }

        // 制造商ID过滤
        if ($manufacturer > 0) {
            $sql .= " AND s.manufacturers_id = " . intval($manufacturer);
        }

        // 状态过滤 (现在可以直接在 HAVING 子句中过滤子查询的结果)
        if ($status !== 'all') {
            // 注意：因为 status 是一个计算字段，所以我们使用 HAVING 而不是 WHERE
            $sql .= " HAVING status = '" . $DB->escape($status) . "'";
        }

        // -- 排序 --
        $order_by_clauses = [];
        // 主排序
        switch ($sort) {
            case 'manufacturer':
                $order_by_clauses[] = "manufacturer $order";
                break;
            case 'computer_count':
                $order_by_clauses[] = "computer_count $order";
                break;
            case 'date_creation':
                $order_by_clauses[] = "s.date_creation $order";
                break;
            default: // 'name'
                $order_by_clauses[] = "software_name $order";
                break;
        }
        // 默认的次级排序，确保结果稳定
        if ($sort !== 'name') {
            $order_by_clauses[] = "software_name ASC";
        }
        $sql .= " ORDER BY " . implode(', ', $order_by_clauses);

        // -- 分页 --
        if ($limit > 0) {
            $sql .= " LIMIT " . intval($start) . ", " . intval($limit);
        }

        // ... (执行查询和返回结果的代码保持不变) ...
        ```
    *   **同样，`getSoftwareInventoryCount` 方法也应进行相应优化**，以确保总数计算与列表查询的逻辑保持一致。

2.  **开发前端页面 (`front/softwarelist.php`)**：
    *   创建一个自定义的列表页面来展示上述查询结果。
    *   **UI 元素**:
        *   一个自定义表格，列出软件名称、已安装电脑数量。
        *   在每一行软件的旁边，添加 "添加到白名单" 和 "添加到黑名单" 的按钮或链接。点击后，通过 AJAX 将该软件名称填充到对应的黑/白名单添加表单中。
        *   提供一个 "详情" 链接，点击后可以跳转到新页面或通过 AJAX 弹窗，显示安装了该软件的所有电脑、资产编号和主要使用人列表。
    *   **权限**: `plugin_softwaremanager_software` (需要读取 `r` 权限)

### **第四步：合规性审查与报告**

1.  **创建数据模型 (`inc/`)**：
    *   `PluginSoftwaremanagerScanhistory.php`：用于记录每次扫描的摘要信息（ID, 扫描日期, 软件总数, 黑名单数, 未报备数, 报告发送状态）。
        *   **核心方法**:
            *   `addRecord()`: 开始一次新的扫描时，创建一条初始记录。
            *   `updateRecordStats($historyId, $stats)`: 扫描完成后，使用统计数据（软件总数、黑名单数、未报备数）更新记录。
            *   `getSearchOptions()`: 为审查历史记录列表页定义搜索条件。
    *   `PluginSoftwaremanagerScanresult.php`：用于存储单次扫描发现的具体违规项（关联 `scanhistory_id`, 软件名, 电脑, 用户, 类型（黑名单/未报备）等）。
        *   **核心方法**:
            *   `addBlacklistRecord($historyId, $softwareName, $computerId, $userId)`: 添加一条黑名单软件记录。
            *   `addUnregisteredRecord($historyId, $softwareName, $computerId, $userId, $groupId)`: 添加一条未报备软件记录。
            *   `getResultsForHistory($historyId, $type)`: 获取指定扫描历史记录下的所有结果（可按 `type` 区分黑名单或未报备）。
    *   在 `install` 函数中添加创建相应数据表的逻辑。
2.  **实现审查逻辑 (`ajax/compliance_scan.php`)**：
    *   此脚本用于触发一次手动的合规性扫描。其核心逻辑必须精确实现新的匹配规则和结果聚合。
    *   **核心审查逻辑 (重构后)**:
        1.  **权限与安全检查**: 检查 `plugin_softwaremanager_scan_run` 权限和 CSRF 令牌。
        2.  **初始化**:
            *   创建一个新的 `scanhistory` 记录，获取其ID。
            *   从数据库一次性加载所有活跃的白名单规则和黑名单规则到内存中的数组。
            *   获取所有电脑及其安装的软件列表（`glpi_computers` JOIN `glpi_items_softwareversions` JOIN `glpi_softwareversions` JOIN `glpi_softwares`）。将结果组织成以 `computer_id` 为键的结构。
        3.  **遍历与匹配**:
            *   以电脑为单位进行外层循环 (`foreach ($computers as $computer_id => $installed_softwares)`).
            *   在每台电脑内部，创建一个临时的 `violations` 数组，用于暂存本机的违规情况，实现结果聚合。`$violations = ['blacklist' => [], 'unregistered' => []];`
            *   对该电脑上安装的每一个软件进行内层循环 (`foreach ($installed_softwares as $software)`).
            *   **匹配流程**:
                a. **白名单检查**: 将当前软件名称与内存中的所有白名单规则进行匹配（按精确 -> 通配符的优先级）。如果匹配成功，则该软件合规，直接 `continue` 到下一个软件。
                b. **黑名单检查**: 如果未匹配到白名单，则继续将软件名称与黑名单规则进行匹配。如果匹配成功，则**执行聚合操作**：
                   *   获取匹配到的黑名单规则（例如 `solidworks*`）。
                   *   将这条规则存入当前电脑的违规暂存区：`$violations['blacklist'][$matched_rule] = true;`。使用规则作为键可以自动去重，确保同一规则只记录一次。
                   *   `continue` 到下一个软件（因为一旦匹配到黑名单，就无需再检查其他黑名单规则或将其视为未报备）。
                c. **未报备软件**: 如果该软件既不符合白名单也不符合黑名单，则将其视为“未报备软件”。同样**执行聚合操作**：
                   *   使用软件的字典名称 (`glpi_softwares.name`) 作为键来聚合所有不同版本的软件：`$violations['unregistered'][$software['name']] = true;`
        4.  **结果入库**:
            *   在外层电脑循环结束时，处理该电脑的 `violations` 数组。
            *   遍历 `$violations['blacklist']`，为每一个键（即每一个匹配到的黑名单规则）创建一条 `scanresult` 记录 (type='blacklist')。
            *   遍历 `$violations['unregistered']`，为每一个键（即每一个未报备的软件名称）创建一条 `scanresult` 记录 (type='unregistered')。
        5.  **完成与更新**:
            *   所有电脑都处理完毕后，根据 `scanresult` 表中的统计数据，更新 `scanhistory` 记录中的各项统计（黑名单总数、未报备总数等）。
            *   向前端返回JSON消息，告知任务已完成。
3.  **实现审查记录页面 (`front/`)**：
    *   `front/scanhistory.php`：使用 `Search::show('PluginSoftwaremanagerScanhistory')` 展示 `scanhistory` 列表。
        *   **UI 元素**: 列表包含扫描日期、各项统计数据、报告发送状态等。提供“查看详情”链接，跳转到 `scanresult.php`。
    *   `front/scanresult.php`：接收一个 `scanhistory_id` 参数，展示该次扫描的所有结果详情。
        *   **UI 元素**: 使用标签页或两个独立的列表，分别展示 "黑名单软件记录" 和 "未报备软件记录"。详细信息包括：软件名称、版本、安装电脑、使用者、使用者所属组等。
    *   **权限**: `plugin_softwaremanager_scan` (需要读取 `r` 权限才能查看)

### **第五步：自动化任务与通知**

1.  **创建定时任务类 (`inc/mycron.php`)**：
    *   创建一个继承自 `CronTask` 的类，如 `PluginSoftwaremanagerMyCron`。
    *   在 `install` 函数中使用 `CronTask::register()` 将此类中的任务注册到 GLPI 的定时任务系统中。
    *   **核心方法**:
        *   `runComplianceScan(CronTask $task)`: **核心审查逻辑**。此方法的逻辑与手动扫描完全相同。在成功生成报告后，检查插件配置，调用 GLPI 邮件函数发送报告，并更新 `scanhistory` 的发送状态。
        *   `personal_warning_job(CronTask $task)`: **（可选）个人警告发送逻辑**。查询所有未发送个人警告的违规记录，根据用户 ID 获取邮箱，使用个人警告模板发送邮件并更新发送状态。

### **第六步：插件配置**

1.  **创建配置模型 (`inc/config.class.php`)**：
    *   创建一个类用于统一管理插件的所有配置项，数据存储在 `glpi_plugin_softwaremanager_configs` 表中。
    *   **核心方法**:
        *   `getOption($key, $default)`: 获取指定配置项的值。
        *   `setOption($key, $value)`: 设置配置项的值。
2.  **开发配置页面 (`front/config.php`)**：
    *   提供一个表单，用于修改插件的各项设置。
    *   **UI 元素**:
        *   使用标签页 (`Html::openRightTabs`) 将配置项分类。
        *   **定时任务配置**: 启用/禁用自动扫描，设置执行周期。
        *   **邮件通知配置**: 报告接收人（可多选）、个人警告启用开关。
        *   **邮件模板配置**: 两个 `<textarea>` 分别用于编辑管理员报告和个人警告模板，并提供可用变量说明。
    *   **权限**: `plugin_softwaremanager_config` (需要写入 `w` 权限)

### **第七步：(可选) 高级功能集成**
*   **在电脑表单上添加Tab页**:
    *   **目标**: 为了方便管理员查看，可以在 GLPI 的电脑资产页面直接添加一个“软件合规”的 Tab 页，用于显示这台电脑具体的违规软件列表。
    *   **实现**:
        1.  创建一个新的 `inc/computerinfraction.class.php` 类，它继承自 `CommonDBTM`，但其主要作用是展示信息。
        2.  在 `setup.php` 的 `plugin_init_softwaremanager` 函数中，使用 `$plugin->registerClass('PluginSoftwaremanagerComputerInfraction', ['addtabon' => ['Computer']]);` 来注册这个类。
        3.  实现 `PluginSoftwaremanagerComputerInfraction` 类中的 `display()` 方法，该方法将负责查询并渲染指定电脑的违规软件列表。

## **插件关键文件与现代实践**

### **1. `setup.php` - 插件的“大脑”**

`setup.php` 是现代 GLPI 插件的核心，取代了过去 `hook.php` 的许多职责。

*   **`plugin_init_{pluginname}()`**: **插件初始化入口**。此函数在每次加载页面时都会被调用。
    *   **核心职责**:
        *   **类注册 (`$plugin->registerClass`)**: 向 GLPI 注册你的所有 `CommonDBTM` 子类和其他核心类。这是让 GLPI “知道”你插件模型的关键。
        *   **钩子绑定**: 在此绑定大部分钩子，例如 `$PLUGIN_HOOKS[Hooks::POST_INIT]['softwaremanager'] = 'plugin_softwaremanager_post_init_hook';`。
        *   **CSS/JS 加载**: 注册插件所需的 CSS 和 JavaScript 文件。
*   **`plugin_version_{pluginname}()`**: 定义插件的**元数据**。
    *   返回一个数组，包含插件的名称 (`name`)、版本 (`version`)、作者 (`author`)、许可证 (`license`)、主页 (`homepage`) 等信息。这些信息会显示在 GLPI 的插件管理页面。
*   **`plugin_{pluginname}_install()` / `_uninstall()`**: **安装与卸载逻辑**。
    *   **安装**: 创建数据库表、注册默认配置、注册定时任务 (`CronTask::register`)。
    *   **卸载**: 删除数据库表、清理配置、注销定时任务 (`CronTask::unregister`)。

### **2. `hook.php` - 高级集成与动态逻辑**

`hook.php` 现在专注于通过钩子实现与 GLPI 核心功能的深度交互和动态逻辑处理。

*   **钩子 (Hook)**: 是一种允许你的插件在 GLPI 执行特定操作时“挂入”自定义代码的机制。
*   **常用高级钩子示例**:
    *   `plugin_{pluginname}_getAddSearchOptions($itemtype)`: 为 GLPI 的核心对象（如 `Computer`）添加新的搜索字段。
    *   `plugin_{pluginname}_hook_dashboard_cards($cards)`: 向 GLPI 的原生仪表盘（Central）添加自定义卡片。
    *   `plugin_pre_item_update_{pluginname}($item)`: 在 GLPI 核心对象（如 `Computer`）被更新**之前**执行操作。可用于数据验证或联动修改。
    *   `plugin_post_item_add_{pluginname}($item)`: 在 GLPI 核心对象被创建**之后**执行操作。

### **3. `plugin.xml` - 插件市场的“名片” (推荐)**

这是一个用于 GLPI 官方应用市场的插件描述文件，对于公开发布的插件至关重要。

*   **功能**: 提供结构化的、多语言的插件信息，包括详细描述、版本兼容性、下载地址、问题追踪链接等。
*   **示例结构**:
    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <root>
       <name>Software Manager</name>
       <key>softwaremanager</key>
       <state>stable</state>
       <description>
          <long>
             <en><![CDATA[A long description of what the plugin does.]]></en>
             <zh_CN><![CDATA[关于插件功能的详细中文描述。]]></zh_CN>
          </long>
       </description>
       <homepage>https://your-plugin-homepage.com</homepage>
       <versions>
          <version>
             <num>1.0.0</num>
             <compatibility>~10.0.11</compatibility>
          </version>
       </versions>
    </root>
    ```

### **4. `composer.json` & `vendor/` - 依赖管理 (推荐)**

*   **为什么要用 Composer?**: GLPI 自身就是用 Composer 构建的。使用它来管理你的插件依赖（例如，用于处理 Excel 导入的 `PhpSpreadsheet` 库），可以确保与 GLPI 核心的兼容性，并简化依赖的安装和更新。
*   **如何使用**:
    1.  在插件根目录运行 `composer require phpoffice/phpspreadsheet`。
    2.  在 `setup.php` 顶部包含 Composer 的自动加载文件：`require_once __DIR__ . '/vendor/autoload.php';`。
    3.  现在你就可以在代码中直接使用 `PhpSpreadsheet` 的类了。

### **5. 代码质量工具 (强烈推荐)**

为了编写出高质量、可维护的插件，强烈建议集成以下工具：

*   **PHPUnit (`phpunit.xml`)**: 用于自动化单元测试，确保你的业务逻辑（如黑白名单匹配）在修改后依然能正常工作。
*   **PHP_CodeSniffer (`.phpcs.xml`)**: 检查你的代码是否符合 PSR-12 等编码规范，保证代码风格的一致性。
*   **PHPStan / Psalm**: 进行静态代码分析，能在不运行代码的情况下发现潜在的 bug 和类型错误。

---

## **开发最佳实践与企业级功能 (Best Practices & Enterprise Features)**

### **1. 数据库迁移与版本升级 (Database Migration)**
仅有 `install` 和 `uninstall` 是不够的。一个专业的插件必须能够处理版本升级带来的数据库结构变更。
*   **机制**: GLPI 提供了一套版本迁移机制。您需要在 `install/` 目录下创建一个 `update.native.php` 文件，它返回一个包含所有版本升级SQL脚本的数组。
*   **AI 提示**: “请为插件创建一个 `install/update.native.php` 文件。此文件应返回一个数组，其中包含一个键为 `1.0.1` 的升级脚本。该脚本负责为 `glpi_plugin_softwaremanager_whitelists` 表添加一个名为 `notes` 的 `TEXT` 类型字段。”
    ```php
    // install/update.native.php
    <?php
    return [
        '1.0.1' => "
            ALTER TABLE `glpi_plugin_softwaremanager_whitelists`
            ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `comment`;
        ",
        // '1.0.2' => "...", // 未来版本的迁移脚本
    ];
    ```

### **2. 动态加载资源 (Dynamic Asset Loading)**
为避免性能问题，不应在所有页面都加载插件的 CSS 和 JS。应根据当前访问的页面按需加载。
*   **AI 提示**: "请在 `setup.php` 的 `plugin_init_softwaremanager` 函数中，添加逻辑来判断当前是否为 `deploypackage.form.php` 页面，并且只在该页面加载 `extjs` 库和相关的 `deploy.css` 文件。"
    ```php
    // setup.php -> plugin_init_softwaremanager()
    $current_url = parse_url($_SERVER['REQUEST_URI'] ?? '')['path'];
    if (str_ends_with($current_url, 'softwarelist.php')) {
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['softwaremanager'][] = 'css/softwarelist.css';
    }
    ```

### **3. 配置缓存 (Configuration Caching)**
对于频繁访问的配置项，每次都从数据库读取会影响性能。应在插件初始化时将其加载到缓存中。
*   **AI 提示**: “请重构 `inc/config.class.php`。添加一个静态私有变量 `$cache` 和一个公共静态方法 `loadCache()`。`loadCache()` 应在 `plugin_init_softwaremanager` 中被调用一次，它负责从数据库读取所有配置并存入 `$cache`。最后，修改 `getOption()` 方法，使其优先从 `$cache` 中读取数据。”

### **4. 使用模板引擎 (Templating with Twig/Mustache)**
对于复杂的 UI，直接在 PHP 中拼接 HTML 是不可维护的。`glpiinventory` 大量使用了 Mustache.js 和 Twig。
*   **原则**: 应将所有复杂的 HTML 片段移到独立的 `.html.twig` 或 `.mustache` 模板文件中。在 PHP 中获取数据后，将数据传递给模板进行渲染。
*   **AI 提示**: "请在 `templates/forms/` 目录下创建一个 `software_details.html.twig` 模板，用于显示软件安装详情。然后，在 `front/softwarelist.php` 的 AJAX 处理逻辑中，调用此模板并传入电脑列表数据，最后将渲染好的 HTML 返回给前端。"

### **5. AJAX & API 端点规范**
*   **统一入口**: 像 `glpiinventory` 一样，可以考虑将所有 AJAX 请求指向一个统一的文件（如 `ajax/handler.php`），通过传递 `action` 参数来区分不同的操作。这比为每个操作创建一个文件更易于管理。
*   **CSRF 跨站请求伪造防护**:
    *   **原则**: 所有会改变服务器状态的请求（POST 表单、AJAX 调用）都必须受 CSRF 保护。
    *   **实现**: 在表单中通过 `Html::Hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()])` 生成令牌，并在服务端用 `Session::checkCSRF()` 验证。

*   **安全文件上传 (针对 `ajax/import.php`)**:
        1.  **文件类型与扩展名**: 严格限制为 `.csv`, `.xlsx`。
        2.  **MIME 类型**: 在服务端再次确认文件类型。
        3.  **文件大小**: 设置合理的上限，防止服务器资源耗尽。
        4.  **内容清理**: 读取文件内容时，对每一行每一列的数据进行清理和验证（如使用 `Html::cleanInput()`），防止 XSS 或 SQL 注入。

### **6. 权限管理与安全性 (企业级实践)**

完全遵循 `GLPI的AJAX权限设计说明.md` 中阐述的最佳实践，我们采用“规划 -> 声明 -> 翻译 -> 检查”的专业流程来构建插件的权限体系。

#### **a) 第一步：权限规划 (Planning)**
在编写代码前，首先要规划好插件需要哪些权限。一个好的权限划分应该遵循“最小权限原则”，功能划分清晰。

*   **核心权限键 (Right Key)**:
    *   `plugin_softwaremanager_config`: 负责管理插件的全局配置。
    *   `plugin_softwaremanager_list_manage`: 负责管理软件的黑名单和白名单。
    *   `plugin_softwaremanager_inventory_view`: 负责查看软件清单列表。
    *   `plugin_softwaremanager_scan_view`: 负责查看合规审查的历史与结果报告。
    *   `plugin_softwaremanager_scan_run`: 负责执行一次新的合规审查扫描。

#### **b) 第二步：权限声明 (Declaration)**
规划好的权限需要在插件安装时向GLPI系统进行“声明”或“注册”。

*   **位置**: `inc/install.class.php` 中的安装方法 (例如 `install` 或 `installProfiles`)。
*   **方法**: 使用 `Profile::addRight()` 函数。

**示例代码**:
```php
// inc/install.class.php
private function installOrUpdateProfiles() {
    $rights = [
        'config'           => 'w', // 配置插件需要写权限
        'list_manage'      => 'w', // 管理黑白名单需要写权限
        'inventory_view'   => 'r', // 查看软件清单为读权限
        'scan_view'        => 'r', // 查看报告为读权限
        'scan_run'         => 'w', // 执行扫描是写/创建操作
    ];

    $profile = new Profile();
    foreach ($rights as $right => $default) {
        // 向GLPI注册权限，并设置默认值
        $profile->addDefaultRight("plugin_softwaremanager_$right", $default);
    }
    
    // （可选）为 admin 角色默认授予所有权限
    if ($profile_id = Profile::getProfileIDByName('admin')) {
       $profile->addRightsToProfile($profile_id, array_keys($rights), "plugin_softwaremanager_");
    }
}
```

#### **c) 第三步：权限翻译 (Translation)**
为了让系统管理员在“配置文件”管理界面能看懂这些权限的用途，必须提供翻译。

*   **位置**: `locales/` 目录下的 `.po` 语言文件 (如 `zh_CN.po`)。
*   **方法**: 添加 `msgctxt "right"` 上下文的翻译条目。

**示例代码 (`locales/zh_CN.po`)**:
```po
#: inc/install.class.php
msgctxt "right"
msgid "plugin_softwaremanager_config"
msgstr "配置 (软件管理器)"

#: inc/install.class.php
msgctxt "right"
msgid "plugin_softwaremanager_list_manage"
msgstr "管理黑白名单 (软件管理器)"

#: inc/install.class.php
msgctxt "right"
msgid "plugin_softwaremanager_inventory_view"
msgstr "查看软件清单 (软件管理器)"

#: inc/install.class.php
msgctxt "right"
msgid "plugin_softwaremanager_scan_view"
msgstr "查看审查报告 (软件管理器)"

#: inc/install.class.php
msgctxt "right"
msgid "plugin_softwaremanager_scan_run"
msgstr "执行新扫描 (软件管理器)"
```

#### **d) 第四步：在代码中检查权限 (Checking)**
这是将权限系统落地应用的关键，在所有功能入口对当前用户的权限进行检查。

*   **保护一个完整的页面 (`front/*.php`)**:
    在页面文件顶部使用 `Session::checkRight()`。如果用户无权访问，脚本会在此处终止并显示“拒绝访问”页面。

    ```php
    // front/config.php
    include('../../../inc/includes.php');

    // 检查用户是否有权配置插件
    Session::checkRight('plugin_softwaremanager_config', UPDATE); // UPDATE, CREATE, WRITE 均可
    // ... 只有授权用户才能看到配置页面
    ```

*   **保护页面上的一个按钮或一个功能**:
    使用 `if (Session::haveRight(...))` 来判断是否渲染某个UI元素。

    ```php
    // front/scanhistory.php
    // ... 显示历史记录列表 ...
    
    // 只有拥有“执行扫描”权限的用户才能看到这个按钮
    if (Session::haveRight('plugin_softwaremanager_scan_run', CREATE)) {
        // 使用Html类库来创建按钮，能自动处理CSRF Token
        echo Html::link(
            __('Run a new compliance scan'),
            '#',
            ['class' => 'vsubmit', 'id' => 'run-scan-button']
        );
    }
    ```

*   **保护AJAX接口 (`ajax/*.php`)**:
    AJAX接口是常见的安全弱点，**必须**同时检查 **CSRF令牌** 和 **用户权限**。

    ```php
    // ajax/compliance_scan.php
    include('../../../inc/includes.php');

    // 1. 检查权限，无权则直接退出
    Session::checkRight('plugin_softwaremanager_scan_run', CREATE);

    // 2. 检查CSRF令牌，防止跨站请求伪造
    Session::checkCSRFToken();
    
    // ... 执行扫描的核心业务逻辑 ...
    // 返回 JSON 格式的结果
    ```

*   **保护数据模型 (`inc/*.class.php`)**:
    对于继承自 `CommonDBTM` 的类，必须重写 `can*` 系列方法，以确保 `Search::show()` 等自动化UI能正确处理权限。

    ```php
    // inc/softwarewhitelist.class.php
    class PluginSoftwaremanagerSoftwareWhitelist extends CommonDBTM {
        
        function canView() {
            // 查看白名单需要管理权限
            return Session::haveRight('plugin_softwaremanager_list_manage', READ);
        }

        function canCreate() {
            // 创建白名单条目需要管理权限
            return Session::haveRight('plugin_softwaremanager_list_manage', CREATE);
        }

        function canUpdate() {
            return $this->canCreate(); // 更新和创建通常使用相同权限
        }

        function canDelete() {
            return $this->canCreate();
        }
    }
    ```

### **7. 国际化 (i18n - Internationalization)**
为了让插件支持多语言，所有在界面上展示给用户的字符串都必须是可翻译的。

*   **AI 提示**: 在代码中，请使用 `__('Text to be translated', 'plugin_softwaremanager')` 的格式来包裹所有面向用户的文本。第二个参数是插件的“文本域 (text-domain)”，通常是插件的名称。

    ```php
    // 示例
    echo "<h1>" . __('Software Whitelist', 'softwaremanager') . "</h1>";
    ```

### **8. 日志记录 (Logging)**
在关键的自动化任务或可能出错的地方记录日志，对于调试和追踪问题至关重要。

*   **AI 提示**: 当需要记录调试信息或错误时，请使用 GLPI 内置的日志功能。

    ```php
    // 示例: 记录一条错误日志到 /files/_log/softwaremanager.log
    Toolbox::logInFile('softwaremanager', 'Failed to send report email to admin@example.com');
    ```

## **GLPI 核心资产数据表说明 (根据插件代码更新)**

为了确保开发手册与插件的实际代码实现保持一致，本章节已根据 `inc/softwareinventory.class.php` 中的查询逻辑进行更新。插件通过以下核心数据表获取电脑、软件和用户的信息，特别是采用了现代GLPI版本中基于“软件版本”的关联模型。

### **1. `glpi_computers` - 电脑资产表**

此表存储了所有电脑资产的详细信息。

| 字段名 | 类型 | 说明 |
| :--- | :--- | :--- |
| `id` | INT | 主键，电脑的唯一标识符。 |
| `name` | VARCHAR(255) | 电脑的名称，通常是主机名。这是巡检时需要获取的关键信息。 |
| `users_id` | INT | **关键外键**，关联到 `glpi_users` 表的 `id`，表示该电脑的 **主要使用人**。 |
| `groups_id` | INT | **关键外键**，关联到 `glpi_groups` 表的 `id`，表示该电脑所属的组。 |
| `locations_id` | INT | **关键外键**，关联到 `glpi_locations` 表，表示电脑所在的位置。 |
| `states_id`| INT | **关键外键**, 关联到 `glpi_states` 表，表示电脑的当前状态 (例如：生产中, 库存中)。 |
| `serial` | VARCHAR(255) | 电脑的序列号。 |
| `otherserial` | VARCHAR(255) | 其他序列号，通常用于存放资产编号 (Asset Tag)。 |
| `is_deleted` | TINYINT(1) | 软删除标记。查询时应始终包含 `WHERE is_deleted = 0`。 |
| `is_template` | TINYINT(1) | 是否为模板。查询时应始终包含 `WHERE is_template = 0`。 |

### **2. `glpi_softwares` - 软件字典表**

此表是系统中所有已发现软件的“字典”，每种软件（按名称区分）在这里只有一条记录。**注意：版本的具体信息存储在 `glpi_softwareversions` 表中。**

| 字段名 | 类型 | 说明 |
| :--- | :--- | :--- |
| `id` | INT | 主键，软件的唯一标识符。 |
| `name` | VARCHAR(255) | **核心字段**，软件的名称。黑白名单匹配的主要依据。 |
| `manufacturers_id` | INT | **关键外键**，关联到 `glpi_manufacturers` 表，表示软件的制造商。 |
| `is_deleted` | TINYINT(1) | 软删除标记。 |

### **3. `glpi_softwareversions` - 软件版本表**

此表存储了每个软件字典条目的具体版本信息。

| 字段名 | 类型 | 说明 |
| :--- | :--- | :--- |
| `id` | INT | 主键，软件版本的唯一标识符。 |
| `softwares_id` | INT | **关键外键**，关联到 `glpi_softwares` 表的 `id`，指明这个版本属于哪个软件。 |
| `name` | VARCHAR(255) | **核心字段**，具体的版本号，例如 '12.1.5' 或 'Creative Cloud 2024'。 |

### **4. `glpi_items_softwareversions` - 项目与软件版本的关联表**

这是连接资产（Items，在这里是电脑）和软件版本的关键多对多关联表，它明确指出了“哪台电脑安装了哪个软件的哪个版本”。

| 字段名 | 类型 | 说明 |
| :--- | :--- | :--- |
| `id` | INT | 主键。 |
| `items_id` | INT | **关键外键**，关联到资产表（如 `glpi_computers`）的 `id`。 |
| `itemtype` | VARCHAR(100) | **关键字段**，指明资产类型。对于本插件，我们只关心 `'Computer'` 类型。 |
| `softwareversions_id` | INT | **关键外键**，关联到 `glpi_softwareversions` 表的 `id`。 |
| `date_install` | DATETIME | 该软件版本的安装日期。 |
| `is_deleted`| TINYINT(1) | 软删除标记。 |

**合规审查的核心查询逻辑正是基于 `glpi_computers` -> `glpi_items_softwareversions` -> `glpi_softwareversions` -> `glpi_softwares` 这条链路的 `JOIN` 操作。**

### **5. `glpi_users` - 用户信息表**

此表存储了 GLPI 系统中的所有用户信息。

| 字段名 | 类型 | 说明 |
| :--- | :--- | :--- |
| `id` | INT | 主键，用户的唯一标识符。 |
| `name` | VARCHAR(255) | 用户的登录名。 |
| `firstname` | VARCHAR(255) | 用户的名字 (First Name)。 |
| `realname` | VARCHAR(255) | 用户的姓氏 (Last Name)。 |
| `email` | VARCHAR(255) | **关键字段**，用户的电子邮件地址，用于发送通知。 |
| `is_active` | TINYINT(1) | 用户是否激活。 |
| `is_deleted` | TINYINT(1) | 软删除标记。 |

### **6. 其他关联表**
为提供更丰富的上下文信息，插件还会关联以下表：
*   `glpi_manufacturers`: 存储软件制造商的名称。
*   `glpi_groups`: 存储用户所属的组/实体信息，通常用于在报告中显示部门。
*   `glpi_locations`: 存储资产的地理位置信息。
*   `glpi_states`: 存储资产的当前状态（如在用、维修、库存等）。