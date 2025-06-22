<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$error = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'toggle_active' && $userId) {
        $user = $db->fetchOne("SELECT is_active FROM users WHERE id = ?", [$userId]);
        if ($user) {
            $newStatus = $user['is_active'] ? 0 : 1;
            $db->update('users', ['is_active' => $newStatus], 'id = ?', [$userId]);
            $success = $newStatus ? '已激活用户' : '已禁用用户';
        }
    }
    
    elseif ($action === 'delete_user' && $userId) {
        // 检查用户是否有查看记录
        $viewCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_views WHERE user_id = ?", [$userId]);
        
        if ($viewCount['count'] > 0) {
            $error = '无法删除该用户，因为存在相关的查看记录';
        } else {
            $db->delete('users', 'id = ?', [$userId]);
            $success = '用户已删除';
        }
    }
}

// 获取筛选参数
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// 构建查询条件
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status === 'active') {
    $whereClause .= " AND is_active = 1";
} elseif ($status === 'inactive') {
    $whereClause .= " AND is_active = 0";
}

// 获取用户数据
$offset = ($page - 1) * ADMIN_ITEMS_PER_PAGE;

$users = $db->fetchAll(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM contact_views cv WHERE cv.user_id = u.id) as view_count
     FROM users u 
     {$whereClause} 
     ORDER BY u.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [ADMIN_ITEMS_PER_PAGE, $offset])
);

// 获取总数
$totalResult = $db->fetchOne(
    "SELECT COUNT(*) as total FROM users u {$whereClause}",
    $params
);
$total = $totalResult['total'];
$totalPages = ceil($total / ADMIN_ITEMS_PER_PAGE);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <h1>用户管理</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <!-- 筛选器 -->
            <div class="readers-filters">
                <form method="GET">
                    <div class="filters-row">
                        <div class="form-group">
                            <label for="search">搜索</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="姓名、邮箱或用户名"
                                   value="<?php echo h($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">状态</label>
                            <select id="status" name="status">
                                <option value="">全部状态</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>已激活</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>已禁用</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">筛选</button>
                            <a href="users.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 用户列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>用户列表 (共 <?php echo $total; ?> 位)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <p class="no-data">没有找到用户</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>姓名</th>
                                        <th>邮箱</th>
                                        <th>电话</th>
                                        <th>查看次数</th>
                                        <th>状态</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo h($user['username']); ?></td>
                                            <td><?php echo h($user['full_name']); ?></td>
                                            <td><?php echo h($user['email']); ?></td>
                                            <td><?php echo h($user['phone'] ?? '-'); ?></td>
                                            <td><?php echo h($user['view_count']); ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="status-active">已激活</span>
                                                <?php else: ?>
                                                    <span class="status-expired">已禁用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="reader-actions">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn <?php echo $user['is_active'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                                            <?php echo $user['is_active'] ? '禁用' : '激活'; ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if ($user['view_count'] == 0): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-secondary" 
                                                                    onclick="return confirm('确定要删除此用户吗？')">
                                                                删除
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                                       class="btn btn-secondary">上一页</a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                                       class="btn btn-secondary">下一页</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
