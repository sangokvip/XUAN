<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 处理余额调整
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adjust_balance') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $userType = $_POST['user_type'] ?? '';
        $amount = (int)($_POST['amount'] ?? 0);
        $operation = $_POST['operation'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        
        if ($userId && $userType && $amount && $operation && $reason) {
            try {
                require_once '../includes/TataCoinManager.php';
                $tataCoinManager = new TataCoinManager();
                
                $table = $userType === 'reader' ? 'readers' : 'users';
                $user = $db->fetchOne("SELECT full_name FROM {$table} WHERE id = ?", [$userId]);
                
                if ($user) {
                    if ($operation === 'add') {
                        $tataCoinManager->addBalance($userId, $userType, $amount, "管理员调整：{$reason}");
                        $success = "成功为 {$user['full_name']} 增加 {$amount} Tata Coin";
                    } else {
                        $tataCoinManager->subtractBalance($userId, $userType, $amount, "管理员调整：{$reason}");
                        $success = "成功为 {$user['full_name']} 减少 {$amount} Tata Coin";
                    }
                } else {
                    $errors[] = '用户不存在';
                }
            } catch (Exception $e) {
                $errors[] = '操作失败：' . $e->getMessage();
            }
        } else {
            $errors[] = '请填写完整信息';
        }
    }
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 搜索参数
$search = trim($_GET['search'] ?? '');
$userType = $_GET['user_type'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取用户数据
if ($userType === 'reader' || $userType === '') {
    $readers = $db->fetchAll(
        "SELECT id, username, full_name, email, tata_coin, created_at, 'reader' as user_type
         FROM readers {$whereClause}
         ORDER BY tata_coin DESC, full_name ASC
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
} else {
    $readers = [];
}

if ($userType === 'user' || $userType === '') {
    $users = $db->fetchAll(
        "SELECT id, username, full_name, email, tata_coin, created_at, 'user' as user_type
         FROM users {$whereClause}
         ORDER BY tata_coin DESC, full_name ASC
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
} else {
    $users = [];
}

// 合并并排序
$allUsers = array_merge($readers, $users);
usort($allUsers, function($a, $b) {
    if ($a['tata_coin'] == $b['tata_coin']) {
        return strcmp($a['full_name'], $b['full_name']);
    }
    return $b['tata_coin'] - $a['tata_coin'];
});

// 获取总数
$totalUsers = 0;
if ($userType === 'reader' || $userType === '') {
    $readerCount = $db->fetchOne("SELECT COUNT(*) as count FROM readers {$whereClause}", $params)['count'];
    $totalUsers += $readerCount;
}
if ($userType === 'user' || $userType === '') {
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users {$whereClause}", $params)['count'];
    $totalUsers += $userCount;
}

$totalPages = ceil($totalUsers / $limit);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户余额管理 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .search-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-form .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .users-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .users-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .user-type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .user-type-badge.user {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .user-type-badge.reader {
            background: #fef3c7;
            color: #92400e;
        }
        
        .balance-amount {
            font-weight: 600;
            color: #059669;
        }
        
        .adjust-form {
            display: inline-flex;
            gap: 5px;
            align-items: center;
        }
        
        .adjust-form input,
        .adjust-form select {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .adjust-form input[type="number"] {
            width: 80px;
        }
        
        .adjust-form input[type="text"] {
            width: 120px;
        }
        
        .btn-adjust {
            padding: 4px 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        
        .btn-adjust:hover {
            background: #2563eb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-decoration: none;
            color: #374151;
        }
        
        .pagination .current {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination a:hover {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <h1>💰 用户余额管理</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- 搜索表单 -->
            <div class="search-form">
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">搜索用户</label>
                            <input type="text" id="search" name="search" 
                                   value="<?php echo h($search); ?>" 
                                   placeholder="姓名、邮箱或用户名">
                        </div>
                        <div class="form-group">
                            <label for="user_type">用户类型</label>
                            <select id="user_type" name="user_type">
                                <option value="">全部</option>
                                <option value="user" <?php echo $userType === 'user' ? 'selected' : ''; ?>>普通用户</option>
                                <option value="reader" <?php echo $userType === 'reader' ? 'selected' : ''; ?>>塔罗师</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">搜索</button>
                            <a href="tata_coin_users.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 用户列表 -->
            <div class="users-table">
                <?php if (empty($allUsers)): ?>
                    <div style="text-align: center; padding: 60px; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 20px;">👥</div>
                        <h3>暂无用户数据</h3>
                        <p>没有找到符合条件的用户</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>用户信息</th>
                                <th>类型</th>
                                <th>Tata Coin余额</th>
                                <th>注册时间</th>
                                <th>余额调整</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($user['full_name']); ?></strong><br>
                                        <small style="color: #6b7280;">
                                            <?php echo h($user['email']); ?><br>
                                            @<?php echo h($user['username']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="user-type-badge <?php echo $user['user_type']; ?>">
                                            <?php echo $user['user_type'] === 'user' ? '普通用户' : '塔罗师'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="balance-amount"><?php echo number_format($user['tata_coin']); ?></span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" class="adjust-form" onsubmit="return confirm('确定要调整余额吗？')">
                                            <input type="hidden" name="action" value="adjust_balance">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                            
                                            <select name="operation" required>
                                                <option value="add">增加</option>
                                                <option value="subtract">减少</option>
                                            </select>
                                            
                                            <input type="number" name="amount" min="1" placeholder="数量" required>
                                            <input type="text" name="reason" placeholder="调整原因" required>
                                            
                                            <button type="submit" class="btn-adjust">调整</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($userType); ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($userType); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($userType); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
