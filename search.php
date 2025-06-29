<?php
session_start();
require_once 'config/config.php';

$db = Database::getInstance();

// 获取搜索参数
$query = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$readers = [];
$totalCount = 0;
$totalPages = 0;

if (!empty($query)) {
    // 检查是否存在 custom_specialties 字段
    $hasCustomSpecialties = false;
    try {
        $checkField = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'custom_specialties'");
        $hasCustomSpecialties = !empty($checkField);
    } catch (Exception $e) {
        $hasCustomSpecialties = false;
    }

    // 搜索占卜师
    $searchTerm = "%{$query}%";

    if ($hasCustomSpecialties) {
        $readers = $db->fetchAll("
            SELECT r.*,
                   (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.full_name LIKE ?
                 OR r.specialties LIKE ?
                 OR r.custom_specialties LIKE ?
                 OR r.description LIKE ?)
            ORDER BY
                CASE WHEN r.full_name LIKE ? THEN 1 ELSE 2 END,
                r.is_featured DESC,
                view_count DESC,
                r.created_at DESC
            LIMIT ? OFFSET ?
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);

        // 获取总数
        $totalCount = $db->fetchOne("
            SELECT COUNT(*) as count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.full_name LIKE ?
                 OR r.specialties LIKE ?
                 OR r.custom_specialties LIKE ?
                 OR r.description LIKE ?)
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm])['count'];
    } else {
        $readers = $db->fetchAll("
            SELECT r.*,
                   (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.full_name LIKE ?
                 OR r.specialties LIKE ?
                 OR r.description LIKE ?)
            ORDER BY
                CASE WHEN r.full_name LIKE ? THEN 1 ELSE 2 END,
                r.is_featured DESC,
                view_count DESC,
                r.created_at DESC
            LIMIT ? OFFSET ?
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);

        // 获取总数
        $totalCount = $db->fetchOne("
            SELECT COUNT(*) as count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.full_name LIKE ?
                 OR r.specialties LIKE ?
                 OR r.description LIKE ?)
        ", [$searchTerm, $searchTerm, $searchTerm])['count'];
    }

    $totalPages = ceil($totalCount / $limit);
}

$pageTitle = !empty($query) ? "搜索：{$query}" : '搜索占卜师';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/image-optimization.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="search-container">
        <div class="search-header">
            <h1>🔍 搜索占卜师</h1>
            <form method="GET" class="search-form">
                <input type="text" name="q" value="<?php echo h($query); ?>"
                       placeholder="输入占卜师姓名、专长或关键词..."
                       class="search-input" autofocus>
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
            
        <?php if (!empty($query)): ?>
            <div class="search-results-header">
                <div class="results-count">
                    找到 <strong><?php echo $totalCount; ?></strong> 位占卜师
                    <?php if (!empty($query)): ?>
                        包含关键词 "<strong><?php echo h($query); ?></strong>"
                    <?php endif; ?>
                </div>
                <a href="readers.php" class="btn-view">浏览所有占卜师</a>
            </div>

            <?php if (empty($readers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>未找到相关占卜师</h3>
                    <p>尝试使用其他关键词搜索，或者<a href="readers.php">浏览所有占卜师</a></p>
                </div>
            <?php else: ?>
                <div class="readers-grid">
                    <?php foreach ($readers as $reader): ?>
                        <div class="reader-card">
                            <?php
                            $photoSrc = '';
                            if (!empty($reader['photo_circle'])) {
                                $photoSrc = $reader['photo_circle'];
                                // 清理路径格式
                                $photoSrc = str_replace('../', '', $photoSrc);
                                $photoSrc = ltrim($photoSrc, '/');
                            } elseif (!empty($reader['photo'])) {
                                $photoSrc = $reader['photo'];
                                // 清理路径格式
                                $photoSrc = str_replace('../', '', $photoSrc);
                                $photoSrc = ltrim($photoSrc, '/');
                            } else {
                                // 使用新的默认头像系统
                                require_once 'includes/AvatarHelper.php';
                                $photoSrc = AvatarHelper::getDefaultAvatar($reader['gender'], $reader['id']);
                            }
                            ?>
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E" data-src="<?php echo h($photoSrc); ?>" class="lazy-image"
                                 alt="<?php echo h($reader['full_name']); ?>"
                                 class="reader-avatar">

                            <div class="reader-name">
                                <?php echo h($reader['full_name']); ?>
                                <?php if ($reader['is_featured']): ?>
                                    <span style="color: #f59e0b; font-size: 0.8em;">⭐</span>
                                <?php endif; ?>
                            </div>

                            <div class="reader-meta">
                                从业 <?php echo h($reader['experience_years']); ?> 年 |
                                <?php echo $reader['view_count']; ?> 次查看
                            </div>

                            <div class="reader-specialties">
                                <?php
                                $specialties = array_filter(array_map('trim', explode(',', $reader['specialties'])));
                                $customSpecialties = [];
                                if ($hasCustomSpecialties && !empty($reader['custom_specialties'])) {
                                    $customSpecialties = array_filter(array_map('trim', explode(',', $reader['custom_specialties'])));
                                }
                                $allSpecialties = array_merge($specialties, $customSpecialties);

                                foreach (array_slice($allSpecialties, 0, 4) as $specialty):
                                ?>
                                    <span class="specialty-tag"><?php echo h($specialty); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <a href="reader.php?id=<?php echo $reader['id']; ?>" class="btn-view">
                                查看详情
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page - 1; ?>">← 上一页</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page + 1; ?>">下一页 →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔮</div>
                <h3>开始搜索占卜师</h3>
                <p>输入占卜师姓名、专长或关键词来查找您需要的占卜师</p>
                <a href="readers.php" class="btn-view" style="margin-top: 20px;">浏览所有占卜师</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <style>
        .search-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            font-family: 'Inter', sans-serif;
        }

        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
        }

        .search-header h1 {
            margin: 0 0 20px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .search-form {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 15px;
        }

        .search-input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
        }

        .search-input:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .search-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 15px 30px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .search-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .results-count {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .readers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .reader-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
        }

        .reader-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .reader-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid #f1f5f9;
        }

        .reader-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .reader-meta {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .reader-specialties {
            margin-bottom: 20px;
        }

        .specialty-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px;
            background: #f1f5f9;
            color: #374151;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .pagination a {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border: 1px solid #667eea;
        }

        @media (max-width: 768px) {
            .search-container {
                padding: 20px 15px;
            }

            .search-header {
                padding: 30px 20px;
            }

            .search-header h1 {
                font-size: 2rem;
            }

            .search-form {
                flex-direction: column;
                gap: 15px;
            }

            .readers-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .reader-card {
                padding: 20px;
            }

            .search-results-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
    <!-- 图片懒加载 -->
    <script src="assets/js/lazy-loading.js"></script>
</body>
</html>
