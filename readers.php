<?php
session_start();
require_once 'config/config.php';

// 检查是否已登录
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}

// 获取分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');

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
            (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count
     FROM readers r 
     {$whereClause} 
     ORDER BY r.is_featured DESC, r.created_at DESC 
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
    <title>塔罗师列表 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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

        .reader-photo img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            transition: transform 0.3s ease !important;
        }

        .reader-card:hover .reader-photo img {
            transform: scale(1.02) !important;
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
        }

        .specialty-tags {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 6px !important;
            margin-top: 8px !important;
        }

        .specialty-tag {
            display: inline-block !important;
            padding: 4px 10px !important;
            background: linear-gradient(135deg, #d4af37, #ffd700) !important;
            color: #000 !important;
            text-decoration: none !important;
            border-radius: 12px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            border: 1px solid #d4af37 !important;
        }

        .specialty-tag:hover {
            background: linear-gradient(135deg, #b8860b, #d4af37) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3) !important;
            color: #000 !important;
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
                                <?php if (!empty($reader['photo'])): ?>
                                    <img src="<?php echo h($reader['photo']); ?>" alt="<?php echo h($reader['full_name']); ?>">
                                <?php else: ?>
                                    <div class="default-photo">
                                        <i class="icon-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="reader-info">
                                <h3><?php echo h($reader['full_name']); ?></h3>
                                
                                <div class="reader-meta">
                                    <span class="experience">从业 <?php echo h($reader['experience_years']); ?> 年</span>
                                    <span class="views"><?php echo h($reader['view_count']); ?> 次查看</span>
                                </div>
                                
                                <?php if (!empty($reader['specialties'])): ?>
                                    <div class="specialties">
                                        <strong>擅长：</strong>
                                        <div class="specialty-tags">
                                            <?php
                                            $specialties = explode('、', $reader['specialties']);
                                            foreach ($specialties as $specialty):
                                                $specialty = trim($specialty);
                                                if (!empty($specialty)):
                                            ?>
                                                <a href="readers.php?specialty=<?php echo urlencode($specialty); ?>"
                                                   class="specialty-tag"><?php echo h($specialty); ?></a>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </div>
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
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($specialty) ? '&specialty=' . urlencode($specialty) : ''; ?>"
                               class="btn btn-secondary">上一页</a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($specialty) ? '&specialty=' . urlencode($specialty) : ''; ?>"
                                   class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($specialty) ? '&specialty=' . urlencode($specialty) : ''; ?>"
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
