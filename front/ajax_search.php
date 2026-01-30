<?php
/**
 * AJAX Search Handler for Enhanced Selector
 * Software Manager Plugin for GLPI
 *
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// 检查用户权限
Session::checkRight('plugin_softwaremanager', READ);

header('Content-Type: application/json; charset=UTF-8');

// 获取请求参数
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$preload_ids = isset($_GET['preload_ids']) ? $_GET['preload_ids'] : false;

// 兼容两种查询参数名称
if (empty($query) && isset($_GET['query'])) {
    $query = trim($_GET['query']);
}

$response = [
    'success' => false,
    'items' => [],
    'error' => null
];

try {
    global $DB;
    
    // 如果是预加载模式，使用ID列表查询
    if ($preload_ids && !empty($query)) {
        $ids = array_map('intval', explode(',', $query));
        $ids = array_filter($ids); // 移除无效ID
        
        if (!empty($ids)) {
            switch ($type) {
                case 'computers':
                    $response['items'] = getComputersByIds($ids, $DB);
                    break;
                    
                case 'users':
                    $response['items'] = getUsersByIds($ids, $DB);
                    break;
                    
                case 'groups':
                    $response['items'] = getGroupsByIds($ids, $DB);
                    break;
                    
                default:
                    throw new Exception('Invalid search type');
            }
        }
    } else {
        // 正常搜索模式
        switch ($type) {
            case 'computers':
                $response['items'] = searchComputers($query, $DB);
                break;
                
            case 'users':
                $response['items'] = searchUsers($query, $DB);
                break;
                
            case 'groups':
                $response['items'] = searchGroups($query, $DB);
                break;
                
            default:
                throw new Exception('Invalid search type');
        }
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;

/**
 * 搜索计算机（支持按使用人搜索）
 */
function searchComputers($query, $DB) {
    $items = [];
    $limit = 50; // 限制返回数量
    
    if (empty($query)) {
        // 无搜索条件时返回前50台计算机
        $computers = $DB->request([
            'FROM' => 'glpi_computers',
            'WHERE' => [
                'is_deleted' => 0,
                'is_template' => 0
            ],
            'ORDER' => 'name ASC',
            'LIMIT' => $limit
        ]);
        
        foreach ($computers as $computer) {
            $items[] = [
                'id' => $computer['id'],
                'text' => $computer['name'],
                'meta' => 'ID: ' . $computer['id']
            ];
        }
    } else {
        // 首先尝试按计算机名搜索
        $computerResults = $DB->request([
            'FROM' => 'glpi_computers',
            'WHERE' => [
                'is_deleted' => 0,
                'is_template' => 0,
                'name' => ['LIKE', '%' . $query . '%']
            ],
            'ORDER' => 'name ASC',
            'LIMIT' => $limit
        ]);
        
        foreach ($computerResults as $computer) {
            $items[] = [
                'id' => $computer['id'],
                'text' => $computer['name'],
                'meta' => 'ID: ' . $computer['id']
            ];
        }
        
        // 如果结果少于限制数量，尝试按使用人搜索
        if (count($items) < $limit) {
            $remainingLimit = $limit - count($items);
            $existingIds = array_column($items, 'id');
            
            // 搜索用户
            $userResults = $DB->request([
                'FROM' => 'glpi_users',
                'WHERE' => [
                    'is_deleted' => 0,
                    'is_active' => 1,
                    'OR' => [
                        'name' => ['LIKE', '%' . $query . '%'],
                        'realname' => ['LIKE', '%' . $query . '%'],
                        'firstname' => ['LIKE', '%' . $query . '%']
                    ]
                ]
            ]);
            
            foreach ($userResults as $user) {
                // 查找这个用户的所有计算机
                $userComputers = $DB->request([
                    'FROM' => 'glpi_computers',
                    'WHERE' => [
                        'is_deleted' => 0,
                        'is_template' => 0,
                        'users_id' => $user['id']
                    ],
                    'ORDER' => 'name ASC',
                    'LIMIT' => $remainingLimit
                ]);
                
                foreach ($userComputers as $computer) {
                    // 避免重复添加
                    if (!in_array($computer['id'], $existingIds)) {
                        $userName = trim($user['firstname'] . ' ' . $user['realname']);
                        if (empty($userName)) {
                            $userName = $user['name'];
                        }
                        
                        $items[] = [
                            'id' => $computer['id'],
                            'text' => $computer['name'],
                            'meta' => '使用人: ' . $userName
                        ];
                        
                        $existingIds[] = $computer['id'];
                        $remainingLimit--;
                        
                        if ($remainingLimit <= 0) {
                            break 2; // 跳出两层循环
                        }
                    }
                }
            }
        }
    }
    
    return $items;
}

/**
 * 搜索用户
 */
function searchUsers($query, $DB) {
    $items = [];
    $limit = 50;
    
    $where = [
        'is_deleted' => 0,
        'is_active' => 1
    ];
    
    if (!empty($query)) {
        $where['OR'] = [
            'name' => ['LIKE', '%' . $query . '%'],
            'realname' => ['LIKE', '%' . $query . '%'],
            'firstname' => ['LIKE', '%' . $query . '%']
        ];
    }
    
    $users = $DB->request([
        'FROM' => 'glpi_users',
        'WHERE' => $where,
        'ORDER' => 'name ASC',
        'LIMIT' => $limit
    ]);
    
    foreach ($users as $user) {
        $displayName = trim($user['firstname'] . ' ' . $user['realname']);
        if (empty($displayName)) {
            $displayName = $user['name'];
        } else {
            $displayName .= ' (' . $user['name'] . ')';
        }
        
        $items[] = [
            'id' => $user['id'],
            'text' => $displayName,
            'meta' => 'ID: ' . $user['id']
        ];
    }
    
    return $items;
}

/**
 * 搜索群组
 */
function searchGroups($query, $DB) {
    $items = [];
    $limit = 50;
    
    $where = [];
    
    if (!empty($query)) {
        $where['name'] = ['LIKE', '%' . $query . '%'];
    }
    
    $groups = $DB->request([
        'FROM' => 'glpi_groups',
        'WHERE' => $where,
        'ORDER' => 'name ASC',
        'LIMIT' => $limit
    ]);
    
    foreach ($groups as $group) {
        $items[] = [
            'id' => $group['id'],
            'text' => $group['name'],
            'meta' => 'ID: ' . $group['id']
        ];
    }
    
    return $items;
}

/**
 * 根据ID列表获取计算机信息
 */
function getComputersByIds($ids, $DB) {
    $items = [];
    
    if (empty($ids)) {
        return $items;
    }
    
    $computers = $DB->request([
        'FROM' => 'glpi_computers',
        'WHERE' => [
            'id' => $ids,
            'is_deleted' => 0,
            'is_template' => 0
        ],
        'ORDER' => 'name ASC'
    ]);
    
    foreach ($computers as $computer) {
        $meta = 'ID: ' . $computer['id'];
        if ($computer['serial']) {
            $meta .= ' | SN: ' . $computer['serial'];
        }
        
        $items[] = [
            'id' => $computer['id'],
            'text' => $computer['name'],
            'meta' => $meta
        ];
    }
    
    return $items;
}

/**
 * 根据ID列表获取用户信息
 */
function getUsersByIds($ids, $DB) {
    $items = [];
    
    if (empty($ids)) {
        return $items;
    }
    
    $users = $DB->request([
        'FROM' => 'glpi_users',
        'WHERE' => [
            'id' => $ids,
            'is_deleted' => 0,
            'is_active' => 1
        ],
        'ORDER' => 'name ASC'
    ]);
    
    foreach ($users as $user) {
        $displayName = trim($user['firstname'] . ' ' . $user['realname']);
        if (empty($displayName)) {
            $displayName = $user['name'];
        } else {
            $displayName .= ' (' . $user['name'] . ')';
        }
        
        $items[] = [
            'id' => $user['id'],
            'text' => $displayName,
            'meta' => 'ID: ' . $user['id']
        ];
    }
    
    return $items;
}

/**
 * 根据ID列表获取群组信息
 */
function getGroupsByIds($ids, $DB) {
    $items = [];
    
    if (empty($ids)) {
        return $items;
    }
    
    $groups = $DB->request([
        'FROM' => 'glpi_groups',
        'WHERE' => [
            'id' => $ids
        ],
        'ORDER' => 'name ASC'
    ]);
    
    foreach ($groups as $group) {
        $items[] = [
            'id' => $group['id'],
            'text' => $group['name'],
            'meta' => 'ID: ' . $group['id']
        ];
    }
    
    return $items;
}
?>