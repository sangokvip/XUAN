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
    $readerId = (int)($_POST['reader_id'] ?? 0);
    
    if ($action === 'toggle_featured' && $readerId) {
        $reader = $db->fetchOne("SELECT is_featured FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            $newStatus = $reader['is_featured'] ? 0 : 1;
            $db->update('readers', ['is_featured' => $newStatus], 'id = ?', [$readerId]);
            $success = $newStatus ? '已设为推荐塔罗师' : '已取消推荐';
        }
    }
    
    elseif ($action === 'toggle_active' && $readerId) {
        $reader = $db->fetchOne("SELECT is_active FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            $newStatus = $reader['is_active'] ? 0 : 1;
            $db->update('readers', ['is_active' => $newStatus], 'id = ?', [$readerId]);
            $success = $newStatus ? '已激活塔罗师' : '已禁用塔罗师';
        }
    }

    elseif ($action === 'delete_reader' && $readerId) {
        // 获取塔罗师信息
        $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            // 删除相关文件
            $filesToDelete = [];
            if (!empty($reader['photo']) && file_exists('../' . $reader['photo'])) {
                $filesToDelete[] = '../' . $reader['photo'];
            }
            if (!empty($reader['price_list_image']) && file_exists('../' . $reader['price_list_image'])) {
                $filesToDelete[] = '../' . $reader['price_list_image'];
            }

            // 删除证书文件
            if (!empty($reader['certificates'])) {
                $certificates = json_decode($reader['certificates'], true);
                if (is_array($certificates)) {
                    foreach ($certificates as $cert) {
                        if (file_exists('../' . $cert)) {
                            $filesToDelete[] = '../' . $cert;
                        }
                    }
                }
            }

            // 删除数据库记录
            $db->delete('readers', 'id = ?', [$readerId]);

            // 删除文件
            foreach ($filesToDelete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $success = '塔罗师已删除';
        }
    }

    elseif ($action === 'batch_delete') {
        $readerIds = $_POST['reader_ids'] ?? [];
        if (!empty($readerIds)) {
            $deletedCount = 0;

            foreach ($readerIds as $id) {
                $id = (int)$id;
                // 获取塔罗师信息
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$id]);
                if ($reader) {
                    // 删除相关文件
                    $filesToDelete = [];
                    if (!empty($reader['photo']) && file_exists('../' . $reader['photo'])) {
                        $filesToDelete[] = '../' . $reader['photo'];
                    }
                    if (!empty($reader['price_list_image']) && file_exists('../' . $reader['price_list_image'])) {
                        $filesToDelete[] = '../' . $reader['price_list_image'];
                    }

                    // 删除证书文件
                    if (!empty($reader['certificates'])) {
                        $certificates = json_decode($reader['certificates'], true);
                        if (is_array($certificates)) {
                            foreach ($certificates as $cert) {
                                if (file_exists('../' . $cert)) {
                                    $filesToDelete[] = '../' . $cert;
                                }
                            }
                        }
                    }

                    // 删除数据库记录
                    $db->delete('readers', 'id = ?', [$id]);

                    // 删除文件
                    foreach ($filesToDelete as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }

                    $deletedCount++;
                }
            }

            $success = "已删除 {$deletedCount} 个塔罗师";
        }
    }

    elseif ($action === 'auto_register') {
        $count = (int)($_POST['register_count'] ?? 5);
        $count = max(1, min(50, $count)); // 限制在1-50之间

        $genders = ['male', 'female'];
        $maleNames = [
            // 玄学相关中文名
            '星辰', '墨羽', '云深', '夜澜', '玄机', '慕白', '凌霄', '司徒', '北辰', '苍穹',
            '幽冥', '天机', '玄武', '青龙', '白虎', '朱雀', '麒麟', '凤凰', '龙吟', '虎啸',
            // 优美中文名
            '君墨', '清风', '明月', '流云', '寒星', '暮雪', '晨曦', '夕阳', '秋水', '春山',
            // 英文名
            'Orion', 'Phoenix', 'Sage', 'Mystic', 'Raven', 'Atlas', 'Zephyr', 'Cosmos', 'Dante', 'Kai',
            'Leo', 'Aries', 'Scorpio', 'Aquarius', 'Gemini', 'Virgo', 'Libra', 'Pisces', 'Taurus', 'Cancer'
        ];
        $femaleNames = [
            // 玄学相关中文名
            '月影', '星语', '紫薇', '青鸾', '白凤', '玄女', '素心', '冰魄', '雪莲', '梦蝶',
            '幻音', '灵犀', '仙子', '神女', '天使', '精灵', '妖姬', '魅影', '幽兰', '静心',
            // 优美中文名
            '若水', '如梦', '似雪', '诗涵', '雅琴', '慕容', '欧阳', '上官', '东方', '西门',
            // 英文名
            'Luna', 'Stella', 'Aurora', 'Celeste', 'Seraphina', 'Mystique', 'Iris', 'Nova', 'Aria', 'Lyra',
            'Athena', 'Diana', 'Venus', 'Minerva', 'Freya', 'Isis', 'Selene', 'Artemis', 'Hecate', 'Persephone'
        ];
        $specialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
        $descriptions = [
            '穿越时空的智慧使者，用古老的塔罗牌为您揭示命运的奥秘。',
            '星辰指引下的占卜师，擅长解读宇宙能量，为迷茫的心灵点亮明灯。',
            '拥有敏锐直觉的神秘学者，通过塔罗牌与您的潜意识对话。',
            '月光下的预言家，用神圣的塔罗智慧为您指引人生方向。',
            '连接天地灵性的占卜大师，专注于解读生命中的神秘密码。',
            '具有深厚玄学底蕴的塔罗师，善于洞察因果轮回的奥义。',
            '来自古老传承的智慧守护者，用塔罗牌为您解开心灵枷锁。',
            '拥有第六感天赋的神秘导师，专精于情感与命运的占卜解读。',
            '星座与塔罗的双重修行者，为您揭示宇宙中隐藏的真相。',
            '具有灵性觉醒能力的占卜师，用爱与光为您照亮前行的道路。',
            '深谙东西方神秘学的塔罗大师，融合古老智慧与现代心理学。',
            '拥有纯净心灵的占卜天使，用温暖的能量为您传递宇宙的讯息。'
        ];
        $createdCount = 0;

        for ($i = 1; $i <= $count; $i++) {
            $gender = $genders[array_rand($genders)];
            $avatar = $gender === 'male' ? 'img/tm.jpg' : 'img/tf.jpg';
            $names = $gender === 'male' ? $maleNames : $femaleNames;
            $fullName = $names[array_rand($names)];
            $selectedSpecialties = array_rand(array_flip($specialties), rand(2, 5));
            if (!is_array($selectedSpecialties)) {
                $selectedSpecialties = [$selectedSpecialties];
            }
            $description = $descriptions[array_rand($descriptions)];

            $readerData = [
                'username' => 'reader' . time() . rand(100, 999),
                'email' => 'reader' . time() . rand(100, 999) . '@example.com',
                'password_hash' => password_hash('787878', PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'phone' => '1' . rand(30, 89) . rand(10000000, 99999999),
                'gender' => $gender,
                'experience_years' => rand(1, 20),
                'specialties' => implode('、', $selectedSpecialties),
                'description' => $description,
                'photo' => $avatar,
                'is_active' => 1,
                'is_featured' => rand(0, 3) === 0 ? 1 : 0, // 25%概率成为推荐塔罗师
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($db->insert('readers', $readerData)) {
                $createdCount++;
            }

            // 避免时间戳重复
            usleep(1000);
        }

        $success = "成功创建 {$createdCount} 个测试塔罗师（密码：787878）";
    }
}

// 获取筛选参数
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$featured = $_GET['featured'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// 构建查询条件
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (full_name LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if ($status === 'active') {
    $whereClause .= " AND is_active = 1";
} elseif ($status === 'inactive') {
    $whereClause .= " AND is_active = 0";
}

if ($featured === 'yes') {
    $whereClause .= " AND is_featured = 1";
} elseif ($featured === 'no') {
    $whereClause .= " AND is_featured = 0";
}

// 获取塔罗师数据
$offset = ($page - 1) * ADMIN_ITEMS_PER_PAGE;

$readers = $db->fetchAll(
    "SELECT r.*,
            COALESCE(r.view_count, (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id)) as view_count
     FROM readers r
     {$whereClause}
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [ADMIN_ITEMS_PER_PAGE, $offset])
);

// 获取总数
$totalResult = $db->fetchOne(
    "SELECT COUNT(*) as total FROM readers r {$whereClause}",
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
    <title>塔罗师管理 - 管理后台</title>
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
            <div class="page-header">
                <h1>塔罗师管理</h1>
                <div class="page-actions">
                    <a href="reader_add.php" class="btn btn-primary">添加塔罗师</a>
                </div>
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
                                   placeholder="姓名或邮箱"
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
                            <label for="featured">推荐状态</label>
                            <select id="featured" name="featured">
                                <option value="">全部</option>
                                <option value="yes" <?php echo $featured === 'yes' ? 'selected' : ''; ?>>已推荐</option>
                                <option value="no" <?php echo $featured === 'no' ? 'selected' : ''; ?>>未推荐</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">筛选</button>
                            <a href="readers.php" class="btn btn-secondary">重置</a>
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
                                <label for="register_count">创建测试塔罗师数量：</label>
                                <input type="number" id="register_count" name="register_count"
                                       value="5" min="1" max="50" style="width: 80px;">
                            </div>
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('确定要创建测试塔罗师吗？')">
                                一键注册塔罗师
                            </button>
                        </form>

                        <!-- 批量删除 -->
                        <form method="POST" id="batchDeleteForm" style="display: inline-block;">
                            <input type="hidden" name="action" value="batch_delete">
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirmBatchDelete()">
                                批量删除选中塔罗师
                            </button>
                        </form>

                        <!-- 工具链接 -->
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="clear_cache.php" class="btn btn-primary" title="清除缓存以更新排序">🗑️ 清除缓存</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 塔罗师列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>塔罗师列表 (共 <?php echo $total; ?> 位)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($readers)): ?>
                        <p class="no-data">没有找到塔罗师</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                        <th>姓名</th>
                                        <th>性别</th>
                                        <th>邮箱</th>
                                        <th>从业年数</th>
                                        <th>查看次数</th>
                                        <th>状态</th>
                                        <th>推荐</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($readers as $reader): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="reader_ids[]" value="<?php echo $reader['id']; ?>" class="reader-checkbox">
                                            </td>
                                            <td>
                                                <a href="../reader.php?id=<?php echo $reader['id']; ?>" target="_blank"
                                                   style="color: #d4af37; text-decoration: none; font-weight: 500;"
                                                   title="查看前端详情页">
                                                    <?php echo h($reader['full_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (isset($reader['gender'])): ?>
                                                    <span class="gender-badge gender-<?php echo $reader['gender']; ?>">
                                                        <?php echo $reader['gender'] === 'male' ? '男' : '女'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #999;">未设置</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($reader['email']); ?></td>
                                            <td><?php echo h($reader['experience_years']); ?>年</td>
                                            <td><?php echo h($reader['view_count']); ?></td>
                                            <td>
                                                <?php if ($reader['is_active']): ?>
                                                    <span class="status-active">已激活</span>
                                                <?php else: ?>
                                                    <span class="status-expired">已禁用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reader['is_featured']): ?>
                                                    <span class="status-active">已推荐</span>
                                                <?php else: ?>
                                                    <span class="status-used">未推荐</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($reader['created_at'])); ?></td>
                                            <td>
                                                <div class="reader-actions">
                                                    <a href="reader_edit.php?id=<?php echo $reader['id']; ?>" class="btn btn-sm btn-primary">编辑</a>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_featured">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $reader['is_featured'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                                            <?php echo $reader['is_featured'] ? '取消推荐' : '设为推荐'; ?>
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $reader['is_active'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                                            <?php echo $reader['is_active'] ? '禁用' : '激活'; ?>
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这位塔罗师吗？此操作不可恢复！');">
                                                        <input type="hidden" name="action" value="delete_reader">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                                    </form>
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
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
                                       class="btn btn-secondary">上一页</a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
                                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
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
            const checkboxes = document.querySelectorAll('.reader-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function confirmBatchDelete() {
            const checkedBoxes = document.querySelectorAll('.reader-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert('请选择要删除的塔罗师');
                return false;
            }

            return confirm(`确定要删除选中的 ${checkedBoxes.length} 个塔罗师吗？此操作不可恢复！`);
        }

        // 将选中的复选框添加到批量删除表单中
        document.getElementById('batchDeleteForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.reader-checkbox:checked');

            // 清除之前的隐藏字段
            const existingInputs = this.querySelectorAll('input[name="reader_ids[]"]');
            existingInputs.forEach(input => input.remove());

            // 添加选中的塔罗师ID
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'reader_ids[]';
                hiddenInput.value = checkbox.value;
                this.appendChild(hiddenInput);
            });
        });
    </script>
</body>
</html>
