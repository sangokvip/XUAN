<?php
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>数据库更新：创建评价系统</h2>";
    
    // 1. 创建"问大家"表
    $createQuestionsTable = "
    CREATE TABLE IF NOT EXISTS reader_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reader_id INT NOT NULL,
        user_id INT NOT NULL,
        question TEXT NOT NULL,
        is_anonymous BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reader_id (reader_id),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师问答表'";
    
    $db->query($createQuestionsTable);
    echo "<p style='color: green;'>✅ 创建 reader_questions 表</p>";
    
    // 2. 创建"问大家"回答表
    $createAnswersTable = "
    CREATE TABLE IF NOT EXISTS reader_question_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        user_id INT NOT NULL,
        answer TEXT NOT NULL,
        is_anonymous BOOLEAN DEFAULT FALSE,
        is_purchased BOOLEAN DEFAULT FALSE COMMENT '是否为已购买用户',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_question_id (question_id),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (question_id) REFERENCES reader_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师问答回答表'";
    
    $db->query($createAnswersTable);
    echo "<p style='color: green;'>✅ 创建 reader_question_answers 表</p>";
    
    // 3. 创建评价表
    $createReviewsTable = "
    CREATE TABLE IF NOT EXISTS reader_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reader_id INT NOT NULL,
        user_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        review_text TEXT,
        is_anonymous BOOLEAN DEFAULT FALSE,
        is_purchased BOOLEAN DEFAULT TRUE COMMENT '是否为已购买用户（评价需要购买后才能写）',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_reader (user_id, reader_id) COMMENT '每个用户对每个塔罗师只能评价一次',
        INDEX idx_reader_id (reader_id),
        INDEX idx_user_id (user_id),
        INDEX idx_rating (rating),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师评价表'";
    
    $db->query($createReviewsTable);
    echo "<p style='color: green;'>✅ 创建 reader_reviews 表</p>";
    
    // 4. 创建评价点赞表
    $createReviewLikesTable = "
    CREATE TABLE IF NOT EXISTS reader_review_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_review (user_id, review_id),
        INDEX idx_review_id (review_id),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (review_id) REFERENCES reader_reviews(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评价点赞表'";
    
    $db->query($createReviewLikesTable);
    echo "<p style='color: green;'>✅ 创建 reader_review_likes 表</p>";
    
    // 5. 为readers表添加评分统计字段
    $checkRatingFields = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'average_rating'");
    if (!$checkRatingFields) {
        $db->query("ALTER TABLE readers ADD COLUMN average_rating DECIMAL(3,2) DEFAULT 0.00 COMMENT '平均评分'");
        $db->query("ALTER TABLE readers ADD COLUMN total_reviews INT DEFAULT 0 COMMENT '总评价数'");
        echo "<p style='color: green;'>✅ 为 readers 表添加评分统计字段</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ readers 表评分统计字段已存在</p>";
    }
    
    // 6. 创建评价系统设置
    $checkReviewSettings = $db->fetchOne("SELECT * FROM site_settings WHERE setting_key = 'review_system_enabled'");
    if (!$checkReviewSettings) {
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('review_system_enabled', '1', '是否启用评价系统')");
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('question_system_enabled', '1', '是否启用问大家功能')");
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('anonymous_review_allowed', '1', '是否允许匿名评价')");
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('review_moderation_enabled', '0', '是否启用评价审核')");
        echo "<p style='color: green;'>✅ 添加评价系统设置</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ 评价系统设置已存在</p>";
    }
    
    // 7. 初始化现有占卜师的评分统计（不使用触发器）
    echo "<p style='color: blue;'>ℹ️ 跳过触发器创建（需要SUPER权限），将使用程序化更新评分统计</p>";

    // 初始化所有占卜师的评分统计
    $readers = $db->fetchAll("SELECT id FROM readers");
    foreach ($readers as $reader) {
        $stats = $db->fetchOne("
            SELECT
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews
            FROM reader_reviews
            WHERE reader_id = ?
        ", [$reader['id']]);

        $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 2) : 0;
        $totalReviews = $stats['total_reviews'] ?: 0;

        $db->query("
            UPDATE readers
            SET average_rating = ?, total_reviews = ?
            WHERE id = ?
        ", [$avgRating, $totalReviews, $reader['id']]);
    }
    echo "<p style='color: green;'>✅ 初始化占卜师评分统计数据</p>";
    
    echo "<h3>数据库表结构：</h3>";
    
    // 显示新创建的表结构
    $tables = ['reader_questions', 'reader_question_answers', 'reader_reviews', 'reader_review_likes'];
    foreach ($tables as $table) {
        echo "<h4>{$table} 表结构：</h4>";
        $columns = $db->fetchAll("SHOW COLUMNS FROM {$table}");
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>默认值</th><th>注释</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Comment'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>评价系统数据库更新完成！</p>";
    echo "<p><strong>重要提示：</strong>由于没有SUPER权限无法创建触发器，评分统计将通过程序自动更新。</p>";
    echo "<p><a href='update_reader_ratings.php'>手动更新所有塔罗师评分统计</a></p>";
    echo "<p><a href='reader.php?id=1'>测试塔罗师页面评价功能</a></p>";
    echo "<p><a href='admin/reviews.php'>管理评价系统</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
