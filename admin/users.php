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

    elseif ($action === 'batch_delete') {
        $userIds = $_POST['user_ids'] ?? [];
        if (!empty($userIds)) {
            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($userIds as $id) {
                $id = (int)$id;
                // 检查用户是否有查看记录
                $viewCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_views WHERE user_id = ?", [$id]);

                if ($viewCount['count'] == 0) {
                    $db->delete('users', 'id = ?', [$id]);
                    $deletedCount++;
                } else {
                    $skippedCount++;
                }
            }

            $success = "已删除 {$deletedCount} 个用户";
            if ($skippedCount > 0) {
                $success .= "，跳过 {$skippedCount} 个有查看记录的用户";
            }
        }
    }

    elseif ($action === 'auto_register') {
        $count = (int)($_POST['register_count'] ?? 5);
        $count = max(1, min(50, $count)); // 限制在1-50之间

        $genders = ['male', 'female'];
        $maleNames = ['张伟', '王强', '李明', '刘涛', '陈杰', '杨帆', '赵磊', '孙鹏', '周勇', '吴斌', '郑浩', '王磊', '李强', '张勇', '刘伟'];
        $femaleNames = ['王芳', '李娜', '张敏', '刘静', '陈丽', '杨洋', '赵雪', '孙莉', '周敏', '吴娟', '郑红', '王丽', '李敏', '张静', '刘芳'];
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '南京', '成都', '武汉', '西安', '重庆', '天津', '苏州', '长沙', '郑州', '青岛'];
        $createdCount = 0;

        for ($i = 1; $i <= $count; $i++) {
            $gender = $genders[array_rand($genders)];
            $avatar = $gender === 'male' ? 'img/nm.jpg' : 'img/nf.jpg';
            $names = $gender === 'male' ? $maleNames : $femaleNames;
            $fullName = $names[array_rand($names)];
            $city = $cities[array_rand($cities)];

            $userData = [
                'username' => 'user' . time() . rand(100, 999),
                'email' => 'user' . time() . rand(100, 999) . '@example.com',
                'password_hash' => password_hash('787878', PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'phone' => '1' . rand(30, 89) . rand(10000000, 99999999),
                'gender' => $gender,
                'avatar' => $avatar,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($db->insert('users', $userData)) {
                $createdCount++;
            }

            // 避免时间戳重复
            usleep(1000);
        }

        $success = "成功创建 {$createdCount} 个测试用户（密码：787878）";
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
            
            <!-- 批量操作和一键注册 -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3>批量操作</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 20px; align-items: end;">
                        <!-- 一键注册 -->
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="action" value="auto_register">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="register_count">创建测试用户数量：</label>
                                <input type="number" id="register_count" name="register_count"
                                       value="5" min="1" max="50" style="width: 80px;">
                            </div>
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('确定要创建测试用户吗？')">
                                一键注册用户
                            </button>
                        </form>

                        <!-- 批量删除 -->
                        <form method="POST" id="batchDeleteForm" style="display: inline-block;">
                            <input type="hidden" name="action" value="batch_delete">
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirmBatchDelete()">
                                批量删除选中用户
                            </button>
                        </form>
                    </div>
                </div>
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
                                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                        <th>用户名</th>
                                        <th>姓名</th>
                                        <th>性别</th>
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
                                            <td>
                                                <?php if ($user['view_count'] == 0): ?>
                                                    <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                                <?php else: ?>
                                                    <span title="有查看记录，无法删除">🔒</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($user['username']); ?></td>
                                            <td><?php echo h($user['full_name']); ?></td>
                                            <td>
                                                <?php if (isset($user['gender'])): ?>
                                                    <span class="gender-badge gender-<?php echo $user['gender']; ?>">
                                                        <?php echo $user['gender'] === 'male' ? '男' : '女'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #999;">未设置</span>
                                                <?php endif; ?>
                                            </td>
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

    <style>
        .gender-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            min-width: 30px;
        }

        .gender-male {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .gender-female {
            background-color: #fce4ec;
            color: #c2185b;
            border: 1px solid #f8bbd9;
        }
    </style>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function confirmBatchDelete() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert('请选择要删除的用户');
                return false;
            }

            return confirm(`确定要删除选中的 ${checkedBoxes.length} 个用户吗？此操作不可恢复！`);
        }

        // 将选中的复选框添加到批量删除表单中
        document.getElementById('batchDeleteForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');

            // 清除之前的隐藏字段
            const existingInputs = this.querySelectorAll('input[name="user_ids[]"]');
            existingInputs.forEach(input => input.remove());

            // 添加选中的用户ID
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'user_ids[]';
                hiddenInput.value = checkbox.value;
                this.appendChild(hiddenInput);
            });
        });
    </script>
</body>
</html>
