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

    elseif ($action === 'edit_user' && $userId) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $tataCoin = (int)($_POST['tata_coin'] ?? 0);

        if (empty($fullName) || empty($email)) {
            $error = '姓名和邮箱不能为空';
        } else {
            // 检查邮箱是否被其他用户使用
            $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($existingUser) {
                $error = '该邮箱已被其他用户使用';
            } else {
                $updateData = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'gender' => $gender ?: null
                ];

                // 如果Tata Coin系统存在，更新余额
                try {
                    $tataCoinExists = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
                    if ($tataCoinExists) {
                        $updateData['tata_coin'] = max(0, $tataCoin);
                    }
                } catch (Exception $e) {
                    // 忽略错误
                }

                $db->update('users', $updateData, 'id = ?', [$userId]);
                $success = '用户信息已更新';
            }
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
        $maleNames = [
            // 优美中文名
            '子轩', '浩然', '宇轩', '博文', '志强', '建华', '俊杰', '明辉', '文博', '天宇',
            '梓豪', '皓轩', '嘉懿', '煜祺', '智宸', '正豪', '昊然', '明杰', '立诚', '立轩',
            // 英文名
            'Alex', 'David', 'Michael', 'James', 'Robert', 'John', 'William', 'Richard', 'Joseph', 'Thomas',
            'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth'
        ];
        $femaleNames = [
            // 优美中文名
            '梓涵', '诗涵', '欣怡', '雨桐', '语嫣', '思涵', '若汐', '艺涵', '苡沫', '雨萱',
            '语桐', '梓萱', '语汐', '雨汐', '语萱', '梓汐', '诗语', '语诗', '雨诗', '诗雨',
            // 英文名
            'Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Charlotte', 'Mia', 'Amelia', 'Harper', 'Evelyn',
            'Abigail', 'Emily', 'Elizabeth', 'Mila', 'Ella', 'Avery', 'Sofia', 'Camila', 'Aria', 'Scarlett'
        ];
        $cities = [
            // 一线城市
            '北京', '上海', '广州', '深圳',
            // 新一线城市
            '杭州', '南京', '成都', '武汉', '西安', '重庆', '天津', '苏州', '长沙', '郑州', '青岛',
            // 有特色的城市
            '大理', '丽江', '拉萨', '厦门', '三亚', '桂林', '张家界', '九寨沟', '香格里拉', '凤凰古城'
        ];
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

            $newUserId = $db->insert('users', $userData);
            if ($newUserId) {
                $createdCount++;

                // 为新用户初始化Tata Coin
                try {
                    // 检查Tata Coin系统是否已安装
                    $tataCoinExists = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
                    if ($tataCoinExists) {
                        require_once '../includes/TataCoinManager.php';
                        $tataCoinManager = new TataCoinManager();
                        $tataCoinManager->initializeNewUser($newUserId, 'user');
                    }
                } catch (Exception $e) {
                    // 忽略Tata Coin初始化错误
                }
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

// 检查是否有tata_coin字段
$tataCoinExists = false;
try {
    $tataCoinExists = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
} catch (Exception $e) {
    // 忽略错误
}

$tataCoinSelect = $tataCoinExists ? ', u.tata_coin' : ', 0 as tata_coin';

$users = $db->fetchAll(
    "SELECT u.* {$tataCoinSelect},
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

            <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 20px; font-size: 0.9rem;">
                <strong>💡 功能说明：</strong>
                <ul style="margin: 5px 0 0 20px; color: #92400e;">
                    <li><strong>编辑</strong>：修改用户基本信息和Tata Coin余额</li>
                    <li><strong>禁用/激活</strong>：禁用后用户无法登录网站，但数据保留；激活后恢复正常使用</li>
                    <li><strong>删除</strong>：永久删除用户账户（仅限无查看记录的用户）</li>
                </ul>
            </div>
            
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
                                        <?php if ($tataCoinExists): ?>
                                        <th>Tata Coin</th>
                                        <?php endif; ?>
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
                                            <?php if ($tataCoinExists): ?>
                                            <td>
                                                <span class="tata-coin-amount"><?php echo number_format($user['tata_coin']); ?></span>
                                            </td>
                                            <?php endif; ?>
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
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                            onclick="editUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo addslashes($user['phone'] ?? ''); ?>', '<?php echo $user['gender'] ?? ''; ?>', <?php echo $user['tata_coin']; ?>)">
                                                        编辑
                                                    </button>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn <?php echo $user['is_active'] ? 'btn-secondary' : 'btn-primary'; ?> btn-sm">
                                                            <?php echo $user['is_active'] ? '禁用' : '激活'; ?>
                                                        </button>
                                                    </form>

                                                    <?php if ($user['view_count'] == 0): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-secondary btn-sm"
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

    <!-- 编辑用户模态框 -->
    <div id="editUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑用户</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_full_name">姓名 *</label>
                        <input type="text" id="edit_full_name" name="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_email">邮箱 *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_phone">电话</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="edit_gender">性别</label>
                        <select id="edit_gender" name="gender">
                            <option value="">请选择</option>
                            <option value="male">男</option>
                            <option value="female">女</option>
                        </select>
                    </div>

                    <?php if ($tataCoinExists): ?>
                    <div class="form-group">
                        <label for="edit_tata_coin">Tata Coin余额</label>
                        <input type="number" id="edit_tata_coin" name="tata_coin" min="0" step="1">
                        <small style="color: #666;">直接修改用户的Tata Coin余额</small>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
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

        .tata-coin-amount {
            color: #f59e0b;
            font-weight: 600;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        /* 模态框样式 */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        .modal-footer .btn {
            margin-left: 10px;
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

        // 编辑用户函数
        function editUser(id, fullName, email, phone, gender, tataCoin) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_gender').value = gender;

            const tataCoinField = document.getElementById('edit_tata_coin');
            if (tataCoinField) {
                tataCoinField.value = tataCoin;
            }

            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('editUserModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // ESC键关闭模态框
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
