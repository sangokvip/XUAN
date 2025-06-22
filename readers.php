<?php
session_start();
require_once 'config/config.php';

// 检查是否已登录
$user = null;
$isAdmin = false;

// 检查管理员登录状态
if (isset($_SESSION['admin_id'])) {
    $isAdmin = true;
} elseif (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}

// 获取分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');

// 调试信息（开发时使用，生产环境请删除）
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "GET参数: " . print_r($_GET, true);
    echo "页码: $page\n";
    echo "搜索: '$search'\n";
    echo "专长: '$specialty'\n";
    echo "</pre>";
}

// 构建查询条件
$whereClause = "WHERE r.is_active = 1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (r.full_name LIKE ? OR r.specialties LIKE ? OR r.description LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

if (!empty($specialty)) {
    $whereClause .= " AND r.specialties LIKE ?";
    $specialtyTerm = "%{$specialty}%";
    $params[] = $specialtyTerm;
}

// 获取塔罗师数据
$db = Database::getInstance();
$offset = ($page - 1) * READERS_PER_PAGE;

$readers = $db->fetchAll(
    "SELECT r.*,
            COALESCE(r.view_count, (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id)) as view_count
     FROM readers r
     {$whereClause}
     ORDER BY r.is_featured DESC, view_count DESC, r.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [READERS_PER_PAGE, $offset])
);

// 获取总数
$totalResult = $db->fetchOne(
    "SELECT COUNT(*) as total FROM readers r {$whereClause}",
    $params
);
$total = $totalResult['total'];
$totalPages = ceil($total / READERS_PER_PAGE);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师列表 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* 塔罗师照片竖向展示 - 适合竖图显示 */
        .reader-photo {
            height: 400px !important;
            overflow: hidden !important;
            position: relative !important;
            background: #f8f9fa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 15px 15px 0 0 !important;
        }

        /* 查看次数图标 - 显示在头像右下角 */
        .view-count-badge {
            position: absolute !important;
            bottom: 10px !important;
            right: 10px !important;
            background: rgba(0, 0, 0, 0.7) !important;
            color: white !important;
            padding: 4px 8px !important;
            border-radius: 12px !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            backdrop-filter: blur(5px) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }

        .view-count-badge .eye-icon {
            width: 14px !important;
            height: 14px !important;
            opacity: 0.9 !important;
        }

        .reader-photo img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            transition: transform 0.3s ease !important;
        }

        .reader-photo-link {
            display: block !important;
            width: 100% !important;
            height: 100% !important;
            text-decoration: none !important;
            color: inherit !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .reader-card:hover .reader-photo img {
            transform: scale(1.02) !important;
        }

        .reader-photo-link:hover {
            opacity: 0.9 !important;
        }

        .default-photo {
            width: calc(100% - 20px) !important;
            height: calc(100% - 20px) !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 60px !important;
            color: #6c757d !important;
            border: 2px dashed #d4af37 !important;
            border-radius: 10px !important;
            margin: 10px !important;
            box-sizing: border-box !important;
        }

        /* 调整卡片布局以适应竖向照片 */
        .readers-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
            gap: 25px !important;
            margin: 30px 0 !important;
        }

        .reader-card {
            background: #fff !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
            overflow: hidden !important;
            transition: transform 0.3s ease !important;
            display: flex !important;
            flex-direction: column !important;
        }

        .reader-info {
            padding: 20px !important;
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* 塔罗师名字和从业年数布局 */
        .reader-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            margin-bottom: 10px !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }

        .reader-name {
            font-size: 1.2rem !important;
            font-weight: 600 !important;
            color: #2c3e50 !important;
            margin: 0 !important;
        }

        .reader-experience {
            background: white !important;
            color: #d4af37 !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            border: 2px solid #d4af37 !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            white-space: nowrap !important;
        }

        .reader-actions {
            margin-top: auto !important;
            text-align: center !important;
        }

        /* 确保描述区域高度一致 */
        .description {
            min-height: 40px !important;
            line-height: 1.4 !important;
            margin-bottom: 15px !important;
            color: #666 !important;
            overflow: hidden !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
        }

        /* 占卜方向标签样式 */
        .specialties {
            margin-bottom: 15px !important;
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .specialties strong {
            margin-right: 0 !important;
            white-space: nowrap !important;
        }

        .specialty-tag {
            display: inline-block !important;
            padding: 4px 10px !important;
            text-decoration: none !important;
            border-radius: 12px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            border: 1px solid transparent !important;
        }

        /* 感情标签 - 红色 */
        .specialty-感情 {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e) !important;
            color: white !important;
            border-color: #ff6b6b !important;
        }

        .specialty-感情:hover {
            background: linear-gradient(135deg, #ff5252, #ff6b6b) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4) !important;
        }

        /* 桃花标签 - 粉红色 */
        .specialty-桃花 {
            background: linear-gradient(135deg, #ff69b4, #ff91d4) !important;
            color: white !important;
            border-color: #ff69b4 !important;
        }

        .specialty-桃花:hover {
            background: linear-gradient(135deg, #ff1493, #ff69b4) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(255, 105, 180, 0.4) !important;
        }

        /* 财运标签 - 金色 */
        .specialty-财运 {
            background: linear-gradient(135deg, #d4af37, #ffd700) !important;
            color: #000 !important;
            border-color: #d4af37 !important;
        }

        .specialty-财运:hover {
            background: linear-gradient(135deg, #b8860b, #d4af37) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4) !important;
        }

        /* 事业标签 - 绿色 */
        .specialty-事业 {
            background: linear-gradient(135deg, #28a745, #5cb85c) !important;
            color: white !important;
            border-color: #28a745 !important;
        }

        .specialty-事业:hover {
            background: linear-gradient(135deg, #218838, #28a745) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4) !important;
        }

        /* 运势标签 - 橘色 */
        .specialty-运势 {
            background: linear-gradient(135deg, #ff8c00, #ffa500) !important;
            color: white !important;
            border-color: #ff8c00 !important;
        }

        .specialty-运势:hover {
            background: linear-gradient(135deg, #e67e00, #ff8c00) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(255, 140, 0, 0.4) !important;
        }

        /* 学业标签 - 蓝色 */
        .specialty-学业 {
            background: linear-gradient(135deg, #007bff, #4dabf7) !important;
            color: white !important;
            border-color: #007bff !important;
        }

        .specialty-学业:hover {
            background: linear-gradient(135deg, #0056b3, #007bff) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.4) !important;
        }

        /* 寻物标签 - 紫色 */
        .specialty-寻物 {
            background: linear-gradient(135deg, #6f42c1, #8e44ad) !important;
            color: white !important;
            border-color: #6f42c1 !important;
        }

        .specialty-寻物:hover {
            background: linear-gradient(135deg, #5a2d91, #6f42c1) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(111, 66, 193, 0.4) !important;
        }

        .specialty-tag:active {
            transform: translateY(0) !important;
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .reader-photo {
                height: 350px !important;
            }

            .readers-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
                gap: 20px !important;
            }

            .default-photo {
                font-size: 50px !important;
            }

            /* 移动端查看次数徽章 */
            .view-count-badge {
                bottom: 8px !important;
                right: 8px !important;
                padding: 3px 6px !important;
                font-size: 11px !important;
            }

            .view-count-badge .eye-icon {
                width: 12px !important;
                height: 12px !important;
            }

            /* 移动端名字和从业年数布局 */
            .reader-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 6px !important;
            }

            .reader-name {
                font-size: 1.1rem !important;
            }

            .reader-experience {
                font-size: 10px !important;
                padding: 3px 8px !important;
            }

            /* 移动端擅长标签优化 */
            .specialties {
                flex-direction: row !important;
                align-items: center !important;
                gap: 6px !important;
                flex-wrap: wrap !important;
            }

            .specialties strong {
                margin-bottom: 0 !important;
                flex-shrink: 0 !important;
            }

            .specialty-tag {
                font-size: 10px !important;
                padding: 3px 6px !important;
            }
        }

        @media (max-width: 480px) {
            .reader-photo {
                height: 300px !important;
            }

            .readers-grid {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            .default-photo {
                font-size: 40px !important;
            }

            /* 小屏幕查看次数徽章 */
            .view-count-badge {
                bottom: 6px !important;
                right: 6px !important;
                padding: 2px 5px !important;
                font-size: 10px !important;
            }

            .view-count-badge .eye-icon {
                width: 10px !important;
                height: 10px !important;
            }

            /* 小屏幕名字和从业年数 */
            .reader-name {
                font-size: 1rem !important;
            }

            .reader-experience {
                font-size: 9px !important;
                padding: 2px 6px !important;
            }

            /* 小屏幕擅长标签优化 */
            .specialties {
                gap: 4px !important;
            }

            .specialty-tag {
                font-size: 9px !important;
                padding: 2px 5px !important;
                border-radius: 8px !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h1>塔罗师列表</h1>
                <p>寻找适合您的专业塔罗师</p>
            </div>
            
            <!-- 搜索栏 -->
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="搜索塔罗师姓名、擅长方向..." 
                               value="<?php echo h($search); ?>">
                        <button type="submit" class="btn btn-primary">搜索</button>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($specialty)): ?>
                    <div class="search-results-info">
                        <?php if (!empty($search) && !empty($specialty)): ?>
                            <p>搜索 "<?php echo h($search); ?>" 并筛选 "<?php echo h($specialty); ?>" 找到 <?php echo $total; ?> 位塔罗师</p>
                        <?php elseif (!empty($search)): ?>
                            <p>搜索 "<?php echo h($search); ?>" 找到 <?php echo $total; ?> 位塔罗师</p>
                        <?php elseif (!empty($specialty)): ?>
                            <p>筛选 "<?php echo h($specialty); ?>" 方向找到 <?php echo $total; ?> 位塔罗师</p>
                        <?php endif; ?>
                        <a href="readers.php" class="clear-search">清除筛选</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 塔罗师列表 -->
            <?php if (empty($readers)): ?>
                <div class="no-results">
                    <h2>暂无塔罗师</h2>
                    <p><?php echo !empty($search) ? '没有找到匹配的塔罗师，请尝试其他关键词。' : '目前还没有塔罗师注册。'; ?></p>
                </div>
            <?php else: ?>
                <div class="readers-grid">
                    <?php foreach ($readers as $reader): ?>
                        <div class="reader-card <?php echo $reader['is_featured'] ? 'featured' : ''; ?>">
                            <?php if ($reader['is_featured']): ?>
                                <div class="featured-badge">推荐</div>
                            <?php endif; ?>
                            
                            <div class="reader-photo">
                                <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $reader['id']; ?>" class="reader-photo-link">
                                    <?php if (!empty($reader['photo'])): ?>
                                        <img src="<?php echo h($reader['photo']); ?>" alt="<?php echo h($reader['full_name']); ?>">
                                    <?php else: ?>
                                        <div class="default-photo">
                                            <i class="icon-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <!-- 查看次数徽章 -->
                                <div class="view-count-badge">
                                    <svg class="eye-icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                    <span><?php echo h($reader['view_count']); ?></span>
                                </div>
                            </div>
                            
                            <div class="reader-info">
                                <!-- 塔罗师名字和从业年数 -->
                                <div class="reader-header">
                                    <h3 class="reader-name"><?php echo h($reader['full_name']); ?></h3>
                                    <span class="reader-experience">从业 <?php echo h($reader['experience_years']); ?> 年</span>
                                </div>
                                
                                <?php if (!empty($reader['specialties'])): ?>
                                    <div class="specialties">
                                        <strong>擅长：</strong>
                                        <?php
                                        // 系统提供的标准擅长方向
                                        $systemSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
                                        $specialties = explode('、', $reader['specialties']);
                                        foreach ($specialties as $specialtyItem):
                                            $specialtyItem = trim($specialtyItem);
                                            // 只显示系统提供的标准标签
                                            if (!empty($specialtyItem) && in_array($specialtyItem, $systemSpecialties)):
                                        ?>
                                            <a href="readers.php?specialty=<?php echo urlencode($specialtyItem); ?>"
                                               class="specialty-tag specialty-<?php echo h($specialtyItem); ?>"><?php echo h($specialtyItem); ?></a>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($reader['description'])): ?>
                                    <div class="description">
                                        <?php
                                        $description = h($reader['description']);
                                        $maxLength = 60; // 减少字符数以保持整齐
                                        if (mb_strlen($description) > $maxLength) {
                                            echo mb_substr($description, 0, $maxLength) . '...';
                                        } else {
                                            echo $description;
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="reader-actions">
                                    <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $reader['id']; ?>" class="btn btn-primary">查看详情</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                    <?php
                    // 构建基础URL参数
                    function buildPaginationUrl($pageNum, $search = '', $specialty = '') {
                        $params = ['page' => $pageNum];
                        if (!empty($search)) {
                            $params['search'] = $search;
                        }
                        if (!empty($specialty)) {
                            $params['specialty'] = $specialty;
                        }
                        return '?' . http_build_query($params);
                    }
                    ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildPaginationUrl($page - 1, $search, $specialty); ?>"
                               class="btn btn-secondary">上一页</a>
                        <?php endif; ?>

                        <div class="page-numbers">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);

                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="<?php echo buildPaginationUrl($i, $search, $specialty); ?>"
                                   class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildPaginationUrl($page + 1, $search, $specialty); ?>"
                               class="btn btn-secondary">下一页</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-info">
                        第 <?php echo $page; ?> 页，共 <?php echo $totalPages; ?> 页，总计 <?php echo $total; ?> 位塔罗师
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
