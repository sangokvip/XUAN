<?php
require_once 'config/config.php';
require_once 'includes/ReviewManager.php';

try {
    $db = Database::getInstance();
    $reviewManager = new ReviewManager();
    
    echo "<h2>更新塔罗师评分统计</h2>";
    
    // 检查评价系统是否已安装
    if (!$reviewManager->isInstalled()) {
        echo "<p style='color: red;'>❌ 评价系统尚未安装</p>";
        exit;
    }
    
    // 获取所有塔罗师
    $readers = $db->fetchAll("SELECT id, full_name FROM readers ORDER BY id");
    
    if (empty($readers)) {
        echo "<p style='color: orange;'>⚠️ 没有找到塔罗师</p>";
        exit;
    }
    
    echo "<p>开始更新 " . count($readers) . " 位塔罗师的评分统计...</p>";
    
    $updated = 0;
    $errors = 0;
    
    foreach ($readers as $reader) {
        try {
            // 获取该塔罗师的评价统计
            $stats = $db->fetchOne("
                SELECT 
                    AVG(rating) as avg_rating,
                    COUNT(*) as total_reviews
                FROM reader_reviews 
                WHERE reader_id = ?
            ", [$reader['id']]);
            
            $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 2) : 0;
            $totalReviews = $stats['total_reviews'] ?: 0;
            
            // 更新塔罗师表
            $db->query("
                UPDATE readers 
                SET average_rating = ?, total_reviews = ? 
                WHERE id = ?
            ", [$avgRating, $totalReviews, $reader['id']]);
            
            echo "<p style='color: green;'>✅ {$reader['full_name']}: 平均评分 {$avgRating}, 总评价 {$totalReviews} 条</p>";
            $updated++;
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ {$reader['full_name']}: 更新失败 - {$e->getMessage()}</p>";
            $errors++;
        }
    }
    
    echo "<h3>更新完成</h3>";
    echo "<p><strong>成功更新：</strong> {$updated} 位塔罗师</p>";
    if ($errors > 0) {
        echo "<p><strong>更新失败：</strong> {$errors} 位塔罗师</p>";
    }
    
    // 显示总体统计
    $totalStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_readers,
            SUM(total_reviews) as total_reviews,
            AVG(average_rating) as overall_avg_rating,
            COUNT(CASE WHEN total_reviews > 0 THEN 1 END) as readers_with_reviews
        FROM readers
    ");
    
    echo "<h3>总体统计</h3>";
    echo "<ul>";
    echo "<li>总塔罗师数：{$totalStats['total_readers']}</li>";
    echo "<li>有评价的塔罗师：{$totalStats['readers_with_reviews']}</li>";
    echo "<li>总评价数：{$totalStats['total_reviews']}</li>";
    echo "<li>整体平均评分：" . number_format($totalStats['overall_avg_rating'], 2) . "</li>";
    echo "</ul>";
    
    echo "<p style='color: green; font-weight: bold;'>评分统计更新完成！</p>";
    echo "<p><a href='readers.php'>查看塔罗师列表</a> | <a href='admin/reviews.php'>管理评价</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
