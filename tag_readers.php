<?php
session_start();
require_once 'config/config.php';
require_once 'includes/DivinationTagHelper.php';
require_once 'includes/DivinationConfig.php';

$db = Database::getInstance();

// 获取标签参数
$tag = $_GET['tag'] ?? '';
$tagName = '';

if (empty($tag)) {
    header('Location: readers.php');
    exit;
}

// 验证标签是否有效并获取标签名称
$tagName = DivinationConfig::getDivinationTypeName($tag);
if (empty($tagName)) {
    // 如果不是新的占卜类型标签，尝试作为传统专长标签处理
    $tagName = $tag;
}

// 获取分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// 检查是否为新的占卜类型标签
$isNewDivinationType = DivinationConfig::getDivinationTypeName($tag) !== null;

if ($isNewDivinationType) {
    // 检查是否存在reviews表
    $hasReviewsTable = false;
    try {
        $checkTable = $db->fetchOne("SHOW TABLES LIKE 'reviews'");
        $hasReviewsTable = !empty($checkTable);
    } catch (Exception $e) {
        $hasReviewsTable = false;
    }

    if ($hasReviewsTable) {
        // 查询有此占卜类型标签的占卜师（包含评价）
        $sql = "SELECT r.*,
                AVG(rv.rating) as avg_rating,
                COUNT(rv.id) as review_count
                FROM readers r
                LEFT JOIN reviews rv ON r.id = rv.reader_id
                WHERE r.is_active = 1
                AND (r.primary_identity = ? OR JSON_CONTAINS(r.divination_types, JSON_QUOTE(?)))
                GROUP BY r.id
                ORDER BY r.is_featured DESC, avg_rating DESC, r.view_count DESC
                LIMIT {$limit} OFFSET {$offset}";
    } else {
        // 查询有此占卜类型标签的占卜师（不包含评价）
        $sql = "SELECT r.*,
                0 as avg_rating,
                0 as review_count
                FROM readers r
                WHERE r.is_active = 1
                AND (r.primary_identity = ? OR JSON_CONTAINS(r.divination_types, JSON_QUOTE(?)))
                ORDER BY r.is_featured DESC, r.view_count DESC
                LIMIT {$limit} OFFSET {$offset}";
    }

    $readers = $db->fetchAll($sql, [$tag, $tag]);

    // 获取总数
    $countSql = "SELECT COUNT(DISTINCT r.id) as total
                 FROM readers r
                 WHERE r.is_active = 1
                 AND (r.primary_identity = ? OR JSON_CONTAINS(r.divination_types, JSON_QUOTE(?)))";
    $totalResult = $db->fetchOne($countSql, [$tag, $tag]);
    $totalCount = $totalResult['total'];
} else {
    // 检查是否存在 custom_specialties 字段（兼容旧系统）
    $hasCustomSpecialties = false;
    try {
        $checkField = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'custom_specialties'");
        $hasCustomSpecialties = !empty($checkField);
    } catch (Exception $e) {
        $hasCustomSpecialties = false;
    }

    // 根据字段存在情况构建查询（传统专长查询）
    if ($hasCustomSpecialties) {
        $readers = $db->fetchAll("
            SELECT r.*,
                   (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count,
                   0 as avg_rating, 0 as review_count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.specialties LIKE ? OR r.custom_specialties LIKE ?)
            ORDER BY view_count DESC, r.created_at DESC
            LIMIT ? OFFSET ?
        ", ["%{$tag}%", "%{$tag}%", $limit, $offset]);

        $totalResult = $db->fetchOne("
            SELECT COUNT(*) as count
            FROM readers r
            WHERE r.is_active = 1
            AND (r.specialties LIKE ? OR r.custom_specialties LIKE ?)
        ", ["%{$tag}%", "%{$tag}%"]);
        $totalCount = $totalResult['count'];
    } else {
        $readers = $db->fetchAll("
            SELECT r.*,
                   (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count,
                   0 as avg_rating, 0 as review_count
            FROM readers r
            WHERE r.is_active = 1
            AND r.specialties LIKE ?
            ORDER BY view_count DESC, r.created_at DESC
            LIMIT ? OFFSET ?
        ", ["%{$tag}%", $limit, $offset]);

        $totalResult = $db->fetchOne("
            SELECT COUNT(*) as count
            FROM readers r
            WHERE r.is_active = 1
            AND r.specialties LIKE ?
        ", ["%{$tag}%"]);
        $totalCount = $totalResult['count'];
    }
}

$totalPages = ceil($totalCount / $limit);

// 获取标签类别信息
$tagCategory = '';
$tagClass = '';
if ($isNewDivinationType) {
    $tagCategory = DivinationConfig::getDivinationCategory($tag);
    $tagClass = DivinationConfig::getDivinationTagClass($tag);
}

$pageTitle = "{$tagName} - 占卜师";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/divination-tags.css?v=<?php echo time(); ?>">

    <!-- 强制移除标签下划线的内联样式 -->
    <style>
        a.divination-tag,
        a.divination-tag:link,
        a.divination-tag:visited,
        a.divination-tag:hover,
        a.divination-tag:active,
        a.divination-tag:focus {
            text-decoration: none !important;
            border-bottom: none !important;
            text-underline-offset: unset !important;
            text-decoration-line: none !important;
            text-decoration-style: none !important;
            text-decoration-color: transparent !important;
            text-decoration-thickness: 0 !important;
            border-bottom-width: 0 !important;
            border-bottom-style: none !important;
            border-bottom-color: transparent !important;
        }
    </style>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tag-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .tag-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .tag-header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
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
        
        .specialty-tag.current {
            background: #667eea;
            color: white;
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
        
        @media (max-width: 768px) {
            .tag-header {
                padding: 30px 20px;
            }
            
            .tag-header h1 {
                font-size: 2rem;
            }
            
            .readers-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .reader-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="tag-header">
        <div class="container">
            <?php if ($isNewDivinationType): ?>
                <h1>
                    <span class="divination-tag <?php echo h($tagClass); ?> primary-tag" style="margin-right: 15px;">
                        <?php echo h($tagName); ?>
                    </span>
                    占卜师
                </h1>
                <p>专业的<?php echo h($tagName); ?>占卜师为您提供精准的占卜服务</p>
                <p>共找到 <?php echo $totalCount; ?> 位<?php echo h($tagName); ?>占卜师</p>
            <?php else: ?>
                <h1>🏷️ <?php echo h($tagName); ?></h1>
                <p>共找到 <?php echo $totalCount; ?> 位擅长此领域的占卜师</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <?php if (empty($readers)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3>暂无相关占卜师</h3>
                <p>没有找到擅长"<?php echo h($tag); ?>"的占卜师</p>
                <a href="readers.php" class="btn-view" style="margin-top: 20px;">查看所有占卜师</a>
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
                        <img src="<?php echo h($photoSrc); ?>"
                             alt="<?php echo h($reader['full_name']); ?>"
                             class="reader-avatar">
                        
                        <div class="reader-name">
                            <?php echo h($reader['full_name']); ?>
                            <?php
                            // 显示主要身份标签（可点击）
                            if ($isNewDivinationType && DivinationTagHelper::hasValidTags($reader) && !empty($reader['primary_identity'])) {
                                echo DivinationTagHelper::generatePrimaryTag($reader, false, true);
                            }
                            ?>
                            <?php if ($reader['is_featured']): ?>
                                <span style="color: #f59e0b; font-size: 0.8em;">⭐</span>
                            <?php endif; ?>
                        </div>

                        <div class="reader-meta">
                            从业 <?php echo h($reader['experience_years']); ?> 年
                            <?php if (isset($reader['avg_rating']) && $reader['avg_rating'] > 0): ?>
                                | ⭐ <?php echo number_format($reader['avg_rating'], 1); ?>分
                                (<?php echo $reader['review_count']; ?>条评价)
                            <?php elseif (isset($reader['view_count'])): ?>
                                | <?php echo $reader['view_count']; ?> 次查看
                            <?php endif; ?>
                        </div>

                        <!-- 不再显示完整的身份标签容器 -->
                        <?php if (false): ?>
                            <?php echo DivinationTagHelper::generateTagsContainer($reader, 'center', true, true, 2); ?>
                        <?php else: ?>
                            <!-- 传统专长显示 -->
                            <div class="reader-specialties">
                                <?php
                                $specialties = array_filter(array_map('trim', explode(',', $reader['specialties'])));
                                $customSpecialties = [];
                                if (isset($hasCustomSpecialties) && $hasCustomSpecialties && !empty($reader['custom_specialties'])) {
                                    $customSpecialties = array_filter(array_map('trim', explode(',', $reader['custom_specialties'])));
                                }
                                $allSpecialties = array_merge($specialties, $customSpecialties);

                                foreach ($allSpecialties as $specialty):
                                    $isCurrentTag = (trim($specialty) === $tag);
                                ?>
                                    <span class="specialty-tag <?php echo $isCurrentTag ? 'current' : ''; ?>">
                                        <?php echo h($specialty); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <a href="reader.php?id=<?php echo $reader['id']; ?>" class="btn-view">
                            查看详情
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?tag=<?php echo urlencode($tag); ?>&page=<?php echo $page - 1; ?>">← 上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?tag=<?php echo urlencode($tag); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?tag=<?php echo urlencode($tag); ?>&page=<?php echo $page + 1; ?>">下一页 →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
