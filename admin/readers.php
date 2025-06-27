<?php
session_start();
require_once '../config/config.php';
require_once '../includes/DivinationTagHelper.php';

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
            $success = $newStatus ? '已设为推荐占卜师' : '已取消推荐';
        }
    }
    
    elseif ($action === 'toggle_active' && $readerId) {
        $reader = $db->fetchOne("SELECT is_active FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            $newStatus = $reader['is_active'] ? 0 : 1;
            $db->update('readers', ['is_active' => $newStatus], 'id = ?', [$readerId]);
            $success = $newStatus ? '已激活占卜师' : '已禁用占卜师';
        }
    }

    elseif ($action === 'delete_reader' && $readerId) {
        // 获取占卜师信息
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

            $success = '占卜师已删除';
        }
    }

    elseif ($action === 'batch_delete') {
        $readerIds = $_POST['reader_ids'] ?? [];
        if (!empty($readerIds)) {
            $deletedCount = 0;

            foreach ($readerIds as $id) {
                $id = (int)$id;
                // 获取占卜师信息
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

            $success = "已删除 {$deletedCount} 个占卜师";
        }
    }

    elseif ($action === 'auto_register') {
        require_once '../includes/DivinationConfig.php';

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
            '具有深厚玄学底蕴的占卜师，善于洞察因果轮回的奥义。',
            '来自古老传承的智慧守护者，用塔罗牌为您解开心灵枷锁。',
            '拥有第六感天赋的神秘导师，专精于情感与命运的占卜解读。',
            '星座与塔罗的双重修行者，为您揭示宇宙中隐藏的真相。',
            '具有灵性觉醒能力的占卜师，用爱与光为您照亮前行的道路。',
            '深谙东西方神秘学的占卜大师，融合古老智慧与现代心理学。',
            '拥有纯净心灵的占卜天使，用温暖的能量为您传递宇宙的讯息。'
        ];

        // 获取国籍列表
        $nationalities = array_keys(DivinationConfig::getNationalities());

        // 获取占卜类型
        $allDivinationTypes = DivinationConfig::getAllDivinationTypes();
        $westernTypes = array_keys($allDivinationTypes['western']['types']);
        $easternTypes = array_keys($allDivinationTypes['eastern']['types']);
        $allTypes = array_merge($westernTypes, $easternTypes);

        // 联系方式模板
        $wechatPrefixes = ['wx', 'wechat', 'tarot', 'mystic', 'star', 'moon', 'crystal'];
        $qqNumbers = ['123456789', '987654321', '555666777', '888999000', '111222333'];
        $xiaohongshuNames = ['塔罗星语', '神秘月影', '水晶占卜', '星辰指引', '灵性觉醒'];
        $douyinNames = ['塔罗师', '占卜大师', '神秘学者', '星座达人', '灵性导师'];

        $createdCount = 0;

        for ($i = 1; $i <= $count; $i++) {
            $gender = $genders[array_rand($genders)];

            // 根据性别从新的头像中随机选择
            if ($gender === 'male') {
                $maleAvatars = ['img/m1.jpg', 'img/m2.jpg', 'img/m3.jpg', 'img/m4.jpg'];
                $avatar = $maleAvatars[array_rand($maleAvatars)];
            } else {
                $femaleAvatars = ['img/f1.jpg', 'img/f2.jpg', 'img/f3.jpg', 'img/f4.jpg'];
                $avatar = $femaleAvatars[array_rand($femaleAvatars)];
            }

            $names = $gender === 'male' ? $maleNames : $femaleNames;
            $fullName = $names[array_rand($names)];
            $selectedSpecialties = array_rand(array_flip($specialties), rand(2, 5));
            if (!is_array($selectedSpecialties)) {
                $selectedSpecialties = [$selectedSpecialties];
            }
            $description = $descriptions[array_rand($descriptions)];

            // 随机选择国籍（80%中国，20%其他）
            $nationality = rand(1, 10) <= 8 ? 'CN' : $nationalities[array_rand($nationalities)];

            // 随机选择占卜类型（1-3个）
            $selectedDivinationTypes = [];
            $typeCount = rand(1, 3);
            $shuffledTypes = $allTypes;
            shuffle($shuffledTypes);
            $selectedDivinationTypes = array_slice($shuffledTypes, 0, $typeCount);

            // 选择主要身份标签
            $primaryIdentity = $selectedDivinationTypes[array_rand($selectedDivinationTypes)];

            // 生成自定义专长（30%概率）
            $customSpecialties = '';
            if (rand(1, 10) <= 3) {
                $customTags = ['心理', '情感', '婚姻', '学业'];
                shuffle($customTags);
                $customSpecialties = implode('、', array_slice($customTags, 0, rand(1, 2)));
            }

            // 生成联系方式（随机填写部分）
            $contactInfo = '专业占卜咨询，诚信服务，欢迎咨询预约。';
            $wechat = rand(1, 10) <= 7 ? $wechatPrefixes[array_rand($wechatPrefixes)] . rand(100, 999) : null;
            $qq = rand(1, 10) <= 5 ? $qqNumbers[array_rand($qqNumbers)] : null;
            $xiaohongshu = rand(1, 10) <= 4 ? $xiaohongshuNames[array_rand($xiaohongshuNames)] . rand(10, 99) : null;
            $douyin = rand(1, 10) <= 3 ? $douyinNames[array_rand($douyinNames)] . rand(100, 999) : null;
            $otherContact = rand(1, 10) <= 2 ? '可提供线上线下咨询服务' : null;

            // 基础字段数据（确保存在的字段）
            $readerData = [
                'username' => 'reader' . time() . rand(100, 999),
                'email' => 'reader' . time() . rand(100, 999) . '@example.com',
                'password_hash' => password_hash('787878', PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'experience_years' => rand(1, 20),
                'specialties' => implode('、', $selectedSpecialties),
                'description' => $description,
                'is_featured' => rand(0, 3) === 0 ? 1 : 0, // 25%概率成为推荐占卜师
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 可选字段，如果存在则添加
            $optionalFields = [
                'phone' => '1' . rand(30, 89) . rand(10000000, 99999999),
                'gender' => $gender,
                'photo' => $avatar,
                'photo_circle' => null,
                'certificates' => null,
                'price_list_image' => null,
                'nationality' => $nationality,
                'custom_specialties' => $customSpecialties,
                'divination_types' => json_encode($selectedDivinationTypes),
                'primary_identity' => $primaryIdentity,
                'identity_category' => DivinationConfig::getDivinationCategory($primaryIdentity),
                'contact_info' => $contactInfo,
                'wechat' => $wechat,
                'qq' => $qq,
                'xiaohongshu' => $xiaohongshu,
                'douyin' => $douyin,
                'other_contact' => $otherContact,
                'view_count' => rand(0, 1000),
                'average_rating' => 0.00,
                'total_reviews' => 0,
                'tata_coin' => 0,
                'registration_token' => null
            ];

            // 获取表的所有字段
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
                $existingFields = [];
                foreach ($columns as $column) {
                    $existingFields[] = $column['Field'];
                }

                // 只添加存在的可选字段
                foreach ($optionalFields as $field => $value) {
                    if (in_array($field, $existingFields)) {
                        $readerData[$field] = $value;
                    }
                }

                if ($db->insert('readers', $readerData)) {
                    $createdCount++;
                }
            } catch (Exception $e) {
                // 如果出错，尝试只用基础字段插入
                try {
                    if ($db->insert('readers', $readerData)) {
                        $createdCount++;
                    }
                } catch (Exception $e2) {
                    // 记录错误但继续
                    error_log("创建占卜师失败: " . $e2->getMessage());
                }
            }

            // 避免时间戳重复
            usleep(1000);
        }

        $success = "成功创建 {$createdCount} 个测试占卜师（密码：787878）";
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

// 获取占卜师数据
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
    <title>占卜师管理 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/divination-tags.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>占卜师管理</h1>
                <div class="page-actions">
                    <a href="reader_add.php" class="btn btn-primary">添加占卜师</a>
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
                    <!-- 数据库更新提示 -->
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h4 style="color: #856404; margin: 0 0 10px 0;">📋 数据库更新提示</h4>
                        <p style="color: #856404; margin: 0 0 10px 0;">
                            如果一键注册功能出现错误，可能是数据库表缺少新字段。请先运行数据库更新脚本：
                        </p>
                        <a href="../database_update_readers.php" target="_blank" class="btn btn-warning" style="font-size: 0.9rem;">
                            🔧 运行数据库更新
                        </a>
                    </div>

                    <div style="display: flex; gap: 20px; align-items: end;">
                        <!-- 一键注册 -->
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="action" value="auto_register">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="register_count">创建测试占卜师数量：</label>
                                <input type="number" id="register_count" name="register_count"
                                       value="5" min="1" max="50" style="width: 80px;">
                            </div>
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('确定要创建测试占卜师吗？这将自动填写所有必要信息包括国籍、占卜类型、联系方式等。')">
                                🎯 一键注册占卜师
                            </button>
                            <small style="display: block; color: #666; margin-top: 5px;">
                                自动填写：基本信息、国籍、占卜类型、专长、联系方式等所有字段
                            </small>
                        </form>

                        <!-- 批量删除 -->
                        <form method="POST" id="batchDeleteForm" style="display: inline-block;">
                            <input type="hidden" name="action" value="batch_delete">
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirmBatchDelete()">
                                批量删除选中占卜师
                            </button>
                        </form>

                        <!-- 工具链接 -->
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="clear_cache.php" class="btn btn-primary" title="清除缓存以更新排序">🗑️ 清除缓存</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 占卜师列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>占卜师列表 (共 <?php echo $total; ?> 位)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($readers)): ?>
                        <p class="no-data">没有找到占卜师</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                        <th>姓名</th>
                                        <th>性别</th>
                                        <th>邮箱</th>
                                        <th>身份标签</th>
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
                                            <td class="admin-table">
                                                <?php if (DivinationTagHelper::hasValidTags($reader)): ?>
                                                    <?php echo DivinationTagHelper::generateAdminTags($reader); ?>
                                                <?php else: ?>
                                                    <span style="color: #999; font-style: italic;">未设置</span>
                                                <?php endif; ?>
                                            </td>
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

                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这位占卜师吗？此操作不可恢复！');">
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
                alert('请选择要删除的占卜师');
                return false;
            }

            return confirm(`确定要删除选中的 ${checkedBoxes.length} 个占卜师吗？此操作不可恢复！`);
        }

        // 将选中的复选框添加到批量删除表单中
        document.getElementById('batchDeleteForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.reader-checkbox:checked');

            // 清除之前的隐藏字段
            const existingInputs = this.querySelectorAll('input[name="reader_ids[]"]');
            existingInputs.forEach(input => input.remove());

            // 添加选中的占卜师ID
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
