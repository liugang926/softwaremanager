<?php
/**
 * 测试数据注入脚本 - GLPI 11.x (Web访问版)
 * 注入50条电脑资产和500条软件安装记录
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// GLPI includes
define('GLPI_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));
include (GLPI_ROOT . "/inc/includes.php");

header('Content-Type: application/json; charset=utf-8');

try {
    // Check authentication
    if (!Session::getLoginUserID()) {
        echo json_encode(['success' => false, 'error' => '需要登录GLPI']);
        exit;
    }

    global $DB, $CFG_GLPI;

    // Get current entity
    $entities_id = intval($_SESSION['glpiactive_entity'] ?? 0);

    $response = [
        'success' => true,
        'message' => '',
        'stats' => [
            'computers_created' => 0,
            'software_created' => 0,
            'installations_created' => 0
        ]
    ];

    // === Software list ===
    $softwareList = [
        ['Adobe Acrobat Reader DC', 'Adobe', '2023.001.20093'],
        ['Adobe Photoshop', 'Adobe', '2024'],
        ['Microsoft Office 2021', 'Microsoft', '16.0.10328.20116'],
        ['Microsoft Office 2019', 'Microsoft', '16.0.10328.20098'],
        ['Visual Studio Code', 'Microsoft', '1.85.1'],
        ['Google Chrome', 'Google', '120.0.6099.109'],
        ['Mozilla Firefox', 'Mozilla', '121.0'],
        ['Microsoft Edge', 'Microsoft', '120.0.2210.61'],
        ['7-Zip', 'Igor Pavlov', '23.01'],
        ['WinRAR', 'RARLAB', '6.24'],
        ['Notepad++', 'Don Ho', '8.6.0'],
        ['VLC Media Player', 'VideoLAN', '3.0.20'],
        ['GIMP', 'GIMP Team', '2.10.34'],
        ['Inkscape', 'Inkscape Team', '1.3'],
        ['Blender', 'Blender Foundation', '4.0.2'],
        ['Python', 'Python Software Foundation', '3.12.1'],
        ['Java Runtime Environment', 'Oracle', '8.0.391'],
        ['Node.js', 'Node.js Foundation', '21.5.0'],
        ['Git', 'Git Community', '2.43.0'],
        ['Docker Desktop', 'Docker Inc', '4.26.1'],
        ['VMware Workstation Pro', 'VMware', '17.5.1'],
        ['VirtualBox', 'Oracle', '7.0.12'],
        ['PuTTY', 'Simon Tatham', '0.79'],
        ['FileZilla', 'FileZilla Project', '3.66.1'],
        ['WinSCP', 'Martin Prikryl', '6.3.0'],
        ['TeamViewer', 'TeamViewer GmbH', '15.49.3'],
        ['AnyDesk', 'AnyDesk Software GmbH', '8.0.3'],
        ['Skype', 'Microsoft', '8.106.0.205'],
        ['Zoom', 'Zoom Video Communications', '5.17.10'],
        ['Slack', 'Slack Technologies', '4.35.128'],
        ['Discord', 'Discord Inc', '1.0.9013'],
        ['Spotify', 'Spotify AB', '1.2.30.1135'],
        ['iTunes', 'Apple Inc', '12.13.1'],
        ['Winamp', 'Nullsoft', '5.9.2'],
        ['foobar2000', 'Peter Pawlowski', '2.0'],
        ['Thunderbird', 'Mozilla', '115.6.0'],
        ['Opera', 'Opera Software', '106.0.2524.73'],
        ['Brave Browser', 'Brave Software', '1.61.109'],
        ['Vivaldi', 'Vivaldi Technologies', '6.5.3206.53'],
        ['Tor Browser', 'Tor Project', '13.0.7'],
        ['KeePass', 'Dominik Reichl', '2.54'],
        ['Bitwarden', 'Bitwarden Inc', '2023.12.0'],
        ['1Password', 'AgileBits', '8.10.34'],
        ['LastPass', 'LastPass US LP', '4.118.0'],
        ['Cyberduck', 'Cyberduck Team', '8.8.3'],
        ['Wireshark', 'The Wireshark Foundation', '4.2.0'],
        ['Nmap', 'Nmap Project', '7.94'],
        ['Paint.NET', 'dotPDN LLC', '5.0.10'],
        ['IrfanView', 'Irfan Skiljan', '4.62'],
        ['XnView MP', 'XnView Ltd', '1.5.3'],
        ['HandBrake', 'HandBrake Team', '1.7.2'],
        ['OBS Studio', 'OBS Project', '30.1.2']
    ];

    // === Step 1: Create Software (50条) ===
    $softwareIds = [];
    $swClass = new Software();

    foreach ($softwareList as $sw) {
        $input = [
            'entities_id' => $entities_id,
            'name' => $sw[0],
            'comment' => 'Test software - Publisher: ' . $sw[1],
            'is_template' => 0,
            'is_deleted' => 0
        ];

        $id = $swClass->add($input);
        if ($id) {
            $softwareIds[$id] = [
                'name' => $sw[0],
                'publisher' => $sw[1],
                'version' => $sw[2]
            ];
            $response['stats']['software_created']++;
        }
    }

    // === Step 2: Create Computers (50条) ===
    $computerIds = [];
    $compClass = new Computer();
    $prefixes = ['PC', 'WS', 'NB', 'DT', 'SRV'];
    $locations = ['Beijing', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Chengdu'];

    for ($i = 1; $i <= 50; $i++) {
        $prefix = $prefixes[array_rand($prefixes)];
        $location = $locations[array_rand($locations)];

        $input = [
            'entities_id' => $entities_id,
            'name' => sprintf('%s-%s-%04d', $location, $prefix, $i),
            'serial' => generateSerial(),
            'otherserial' => 'INV-' . str_pad($i, 6, '0', STR_PAD_LEFT),
            'contact' => 'user' . $i . '@example.com',
            'comment' => 'Test computer ' . $i,
            'is_template' => 0,
            'is_deleted' => 0
        ];

        $id = $compClass->add($input);
        if ($id) {
            $computerIds[] = $id;
            $response['stats']['computers_created']++;
        }
    }

    // === Step 3: Create Software Versions and Installations ===
    $versionClass = new SoftwareVersion();
    $installClass = new Item_SoftwareVersion();
    $installationCount = 0;
    $installDateStart = strtotime('-6 months');

    foreach ($computerIds as $computerId) {
        $numSoftware = rand(8, 15);
        $shuffledSoftware = $softwareIds;
        shuffle($shuffledSoftware);

        for ($i = 0; $i < $numSoftware && $i < count($shuffledSoftware); $i++) {
            $swId = array_key_first($shuffledSoftware);
            $swInfo = $shuffledSoftware[$swId];

            // Check/Create version
            $versionId = 0;
            $versions = $versionClass->find([
                'softwares_id' => $swId,
                'name' => $swInfo['version']
            ], [], 1);

            if (!empty($versions)) {
                $versionId = key($versions);
            } else {
                $vInput = [
                    'softwares_id' => $swId,
                    'entities_id' => $entities_id,
                    'name' => $swInfo['version'],
                    'is_deleted' => 0
                ];
                $versionId = $versionClass->add($vInput);
            }

            if ($versionId) {
                $daysOffset = rand(0, 180);
                $installDate = date('Y-m-d H:i:s', $installDateStart + ($daysOffset * 86400));

                $installInput = [
                    'items_id' => $computerId,
                    'itemtype' => 'Computer',
                    'softwareversions_id' => $versionId,
                    'entities_id' => $entities_id,
                    'date_install' => $installDate
                ];

                $id = $installClass->add($installInput);
                if ($id) {
                    $installationCount++;
                    $response['stats']['installations_created']++;

                    if ($installationCount >= 500) {
                        break 2;
                    }
                }
            }
            unset($shuffledSoftware[$swId]);
        }
    }

    $response['message'] = sprintf(
        '成功注入测试数据: %d台电脑, %d个软件, %d条安装记录',
        $response['stats']['computers_created'],
        $response['stats']['software_created'],
        $response['stats']['installations_created']
    );

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function generateSerial() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $serial = '';
    for ($i = 0; $i < 16; $i++) {
        if ($i > 0 && $i % 4 === 0) {
            $serial .= '-';
        }
        $serial .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $serial;
}

exit;
?>
