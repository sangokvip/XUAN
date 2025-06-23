<?php
session_start();
require_once 'config/config.php';

$db = Database::getInstance();

// 获取标签参数
$tag = trim($_GET['tag'] ?? '');
if (empty($tag)) {
    redirect('readers.php');
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// 获取有此标签的塔罗师
$readers = $db->fetchAll("
    SELECT r.*, 
           (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count
    FROM readers r 
    WHERE r.is_active = 1 
    AND (r.specialties LIKE ? OR r.custom_specialties LIKE ?)
    ORDER BY view_count DESC, r.created_at DESC
    LIMIT ? OFFSET ?
", ["%{$tag}%", "%{$tag}%", $limit, $offset]);

// 获取总数
$totalCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM readers r 
    WHERE r.is_active = 1 
    AND (r.specialties LIKE ? OR r.custom_specialties LIKE ?)
", ["%{$tag}%", "%{$tag}%"])['count'];

$totalPages = ceil($totalCount / $limit);

$pageTitle = "标签：{$tag} - 塔罗师";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            <h1>🏷️ <?php echo h($tag); ?></h1>
            <p>共找到 <?php echo $totalCount; ?> 位擅长此领域的塔罗师</p>
        </div>
    </div>
    
    <div class="container">
        <?php if (empty($readers)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3>暂无相关塔罗师</h3>
                <p>没有找到擅长"<?php echo h($tag); ?>"的塔罗师</p>
                <a href="readers.php" class="btn-view" style="margin-top: 20px;">查看所有塔罗师</a>
            </div>
        <?php else: ?>
            <div class="readers-grid">
                <?php foreach ($readers as $reader): ?>
                    <div class="reader-card">
                        <img src="<?php echo h($reader['photo_circle'] ?: ($reader['photo'] ?: 'img/tm.jpg')); ?>" 
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
                            $customSpecialties = array_filter(array_map('trim', explode(',', $reader['custom_specialties'] ?? '')));
                            $allSpecialties = array_merge($specialties, $customSpecialties);
                            
                            foreach ($allSpecialties as $specialty):
                                $isCurrentTag = (trim($specialty) === $tag);
                            ?>
                                <span class="specialty-tag <?php echo $isCurrentTag ? 'current' : ''; ?>">
                                    <?php echo h($specialty); ?>
                                </span>
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
