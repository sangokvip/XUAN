<?php
session_start();
require_once 'config/config.php';

$query = trim($_GET['q'] ?? '');
$results = [];
$totalResults = 0;

if (!empty($query)) {
    $db = Database::getInstance();
    
    // 搜索塔罗师
    $searchSql = "SELECT * FROM readers 
                  WHERE is_active = 1 
                  AND (full_name LIKE ? OR specialties LIKE ? OR description LIKE ?)
                  ORDER BY is_featured DESC, created_at DESC";
    
    $searchParam = '%' . $query . '%';
    $results = $db->fetchAll($searchSql, [$searchParam, $searchParam, $searchParam]);
    $totalResults = count($results);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索结果 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h1>搜索结果</h1>
                <?php if (!empty($query)): ?>
                    <p>搜索关键词：<strong><?php echo h($query); ?></strong></p>
                    <p>找到 <?php echo $totalResults; ?> 个结果</p>
                <?php endif; ?>
            </div>
            
            <!-- 搜索框 -->
            <div class="search-section">
                <form action="search.php" method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="q" value="<?php echo h($query); ?>" 
                               placeholder="搜索占卜师姓名、专长或简介..." class="search-input">
                        <button type="submit" class="btn btn-primary">搜索</button>
                    </div>
                </form>
                
                <?php if (!empty($query)): ?>
                    <div class="search-info">
                        <a href="search.php" class="clear-search">清空搜索</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($query)): ?>
                <!-- 搜索提示 -->
                <div class="search-tips">
                    <h3>搜索建议</h3>
                    <ul>
                        <li>输入占卜师的昵称进行搜索</li>
                        <li>根据专长搜索，如：感情、事业、财运等</li>
                        <li>搜索关键词，如：塔罗、占卜、咨询等</li>
                    </ul>
                </div>
                
            <?php elseif (empty($results)): ?>
                <!-- 无结果 -->
                <div class="no-results">
                    <h2>未找到相关结果</h2>
                    <p>抱歉，没有找到与 "<?php echo h($query); ?>" 相关的占卜师。</p>
                    <p>建议：</p>
                    <ul>
                        <li>检查搜索词的拼写</li>
                        <li>尝试使用更简单的关键词</li>
                        <li>浏览所有占卜师：<a href="readers.php">查看全部</a></li>
                    </ul>
                </div>
                
            <?php else: ?>
                <!-- 搜索结果 -->
                <div class="search-results">
                    <div class="readers-grid">
                        <?php foreach ($results as $reader): ?>
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
                                                <i class="icon-user">🔮</i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="reader-info">
                                    <h3><?php echo h($reader['full_name']); ?></h3>

                                    <div class="reader-meta">
                                        <span class="experience">从业 <?php echo h($reader['experience_years']); ?> 年</span>
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
                                            $maxLength = 60;
                                            if (mb_strlen($description) > $maxLength) {
                                                echo mb_substr($description, 0, $maxLength) . '...';
                                            } else {
                                                echo $description;
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="reader-actions">
                                        <a href="reader.php?id=<?php echo $reader['id']; ?>" class="btn btn-primary">查看详情</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <style>
        .search-tips {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 40px 0;
        }
        
        .search-tips h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .search-tips ul {
            list-style: none;
            padding: 0;
        }
        
        .search-tips li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .search-tips li:before {
            content: "💡";
            margin-right: 10px;
        }
        
        .search-results {
            margin-top: 40px;
        }
        
        .search-info {
            text-align: center;
            margin-top: 15px;
        }
        
        .clear-search {
            color: #d4af37;
            text-decoration: none;
            font-size: 14px;
        }
        
        .clear-search:hover {
            text-decoration: underline;
        }
    </style>
</body>
</html>
