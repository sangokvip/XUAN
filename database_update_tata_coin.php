<?php
/**
 * Tata Coin 经济体系升级脚本
 * 用于将现有系统升级到新的Tata Coin经济体系
 */

// 避免重复启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';

// 调试信息：显示当前session状态
echo "<h2>Session 调试信息</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session 数据：</p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// 检查是否为管理员访问
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo '<h2>权限错误</h2>';
    echo '<p>需要管理员权限才能执行此操作。</p>';
    echo '<p>请先<a href="auth/admin_login.php">登录管理员账户</a>。</p>';
    echo '<p>当前Session状态：' . (isset($_SESSION['admin_id']) ? '已设置admin_id但值为: ' . $_SESSION['admin_id'] : '未设置admin_id') . '</p>';

    // 检查其他可能的管理员session变量
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        echo '<p>检测到user_type为admin，但admin_id未设置。这可能是session设置问题。</p>';
    }

    exit;
}

echo "<p>✅ 管理员权限验证通过，管理员ID: " . $_SESSION['admin_id'] . "</p>";
echo "<hr>";

$success = [];
$errors = [];

try {
    $db = Database::getInstance();
    
    echo "<h2>Tata Coin 经济体系升级</h2>";
    echo "<p>开始升级数据库结构...</p>";
    
    // 1. 创建新表
    echo "<h3>1. 创建新数据表</h3>";
    
    // 每日签到记录表
    $sql = "CREATE TABLE IF NOT EXISTS daily_check_ins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        check_in_date DATE NOT NULL,
        consecutive_days INT NOT NULL DEFAULT 1,
        reward_coins INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, check_in_date),
        INDEX idx_date (check_in_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_date (user_id, check_in_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表'";
    
    $db->query($sql);
    echo "✓ 每日签到记录表创建成功<br>";
    
    // 页面浏览奖励记录表
    $sql = "CREATE TABLE IF NOT EXISTS page_browse_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_url VARCHAR(500) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        reward_coins INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_ip_date (ip_address, created_at),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面浏览奖励记录表'";
    
    $db->query($sql);
    echo "✓ 页面浏览奖励记录表创建成功<br>";
    
    // 用户等级表
    $sql = "CREATE TABLE IF NOT EXISTS user_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('user', 'reader') NOT NULL,
        level INT NOT NULL DEFAULT 1,
        level_name VARCHAR(50) NOT NULL,
        total_spent INT DEFAULT 0 COMMENT '累计消费（用户）',
        total_earned INT DEFAULT 0 COMMENT '累计收入（塔罗师）',
        discount_rate INT DEFAULT 0 COMMENT '折扣率（%）',
        priority_score INT DEFAULT 0 COMMENT '优先级分数（塔罗师）',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id, user_type),
        INDEX idx_level (level),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_level (user_id, user_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户等级表'";
    
    $db->query($sql);
    echo "✓ 用户等级表创建成功<br>";
    
    // 2. 更新现有表结构
    echo "<h3>2. 更新现有表结构</h3>";
    
    // 更新交易类型枚举
    try {
        $sql = "ALTER TABLE tata_coin_transactions 
                MODIFY COLUMN transaction_type ENUM(
                    'earn', 'spend', 'admin_add', 'admin_subtract', 'transfer',
                    'daily_checkin', 'browse_reward', 'profile_completion', 
                    'invitation_reward', 'level_bonus'
                ) NOT NULL COMMENT '交易类型'";
        $db->query($sql);
        echo "✓ 交易类型枚举更新成功<br>";
    } catch (Exception $e) {
        echo "⚠ 交易类型枚举更新失败（可能已存在）: " . $e->getMessage() . "<br>";
    }
    
    // 3. 插入新的系统设置
    echo "<h3>3. 更新系统设置</h3>";
    
    $settings = [
        'daily_browse_limit' => ['10', '每日浏览奖励上限'],
        'profile_completion_reward' => ['20', '完善资料奖励金额'],
        'invitation_user_reward' => ['20', '邀请用户奖励'],
        'invitation_reader_reward' => ['50', '邀请塔罗师奖励'],
        'daily_earning_limit' => ['30', '每日非付费获取上限'],
        'reader_commission_rate' => ['50', '塔罗师分成比例（%）'],
        'featured_reader_cost' => ['30', '查看推荐塔罗师费用'],
        'normal_reader_cost' => ['10', '查看普通塔罗师费用'],
        'new_user_tata_coin' => ['100', '新用户注册赠送金额']
    ];
    
    foreach ($settings as $key => $value) {
        $sql = "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)";
        $db->query($sql, [$key, $value[0], $value[1]]);
        echo "✓ 设置 {$key} 更新成功<br>";
    }
    
    // 4. 初始化现有用户等级
    echo "<h3>4. 初始化现有用户等级</h3>";
    
    // 初始化用户等级
    $users = $db->fetchAll("SELECT id FROM users WHERE is_active = 1");
    foreach ($users as $user) {
        // 计算用户累计消费
        $totalSpent = $db->fetchOne(
            "SELECT COALESCE(SUM(ABS(amount)), 0) as total FROM tata_coin_transactions 
             WHERE user_id = ? AND user_type = 'user' AND amount < 0",
            [$user['id']]
        )['total'] ?? 0;
        
        // 计算等级
        $level = 1;
        $levelName = '新手';
        $discountRate = 0;
        
        if ($totalSpent >= 1000) {
            $level = 5;
            $levelName = '大师';
            $discountRate = 20;
        } elseif ($totalSpent >= 500) {
            $level = 4;
            $levelName = '专家';
            $discountRate = 15;
        } elseif ($totalSpent >= 200) {
            $level = 3;
            $levelName = '熟练';
            $discountRate = 10;
        } elseif ($totalSpent >= 50) {
            $level = 2;
            $levelName = '进阶';
            $discountRate = 5;
        }
        
        // 插入或更新用户等级
        $sql = "INSERT INTO user_levels (user_id, user_type, level, level_name, total_spent, discount_rate)
                VALUES (?, 'user', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    level = VALUES(level),
                    level_name = VALUES(level_name),
                    total_spent = VALUES(total_spent),
                    discount_rate = VALUES(discount_rate)";
        $db->query($sql, [$user['id'], $level, $levelName, $totalSpent, $discountRate]);
    }
    echo "✓ " . count($users) . " 个用户等级初始化完成<br>";
    
    // 初始化塔罗师等级
    $readers = $db->fetchAll("SELECT id FROM readers WHERE is_active = 1");
    foreach ($readers as $reader) {
        // 计算塔罗师累计收入
        $totalEarned = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total FROM tata_coin_transactions 
             WHERE user_id = ? AND user_type = 'reader' AND amount > 0",
            [$reader['id']]
        )['total'] ?? 0;
        
        // 获取评价信息
        $readerInfo = $db->fetchOne(
            "SELECT average_rating, total_reviews FROM readers WHERE id = ?",
            [$reader['id']]
        );
        
        $avgRating = $readerInfo['average_rating'] ?? 0;
        $totalReviews = $readerInfo['total_reviews'] ?? 0;
        
        // 计算等级
        $level = 1;
        $levelName = '新人塔罗师';
        $priorityScore = 0;
        
        if ($totalEarned >= 1000 && $avgRating >= 4.5 && $totalReviews >= 50) {
            $level = 5;
            $levelName = '大师级塔罗师';
            $priorityScore = 100;
        } elseif ($totalEarned >= 500 && $avgRating >= 4.0 && $totalReviews >= 20) {
            $level = 4;
            $levelName = '专业塔罗师';
            $priorityScore = 80;
        } elseif ($totalEarned >= 200 && $avgRating >= 3.5 && $totalReviews >= 10) {
            $level = 3;
            $levelName = '资深塔罗师';
            $priorityScore = 60;
        } elseif ($totalEarned >= 50 && $avgRating >= 3.0 && $totalReviews >= 5) {
            $level = 2;
            $levelName = '认证塔罗师';
            $priorityScore = 40;
        }
        
        // 插入或更新塔罗师等级
        $sql = "INSERT INTO user_levels (user_id, user_type, level, level_name, total_earned, priority_score)
                VALUES (?, 'reader', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    level = VALUES(level),
                    level_name = VALUES(level_name),
                    total_earned = VALUES(total_earned),
                    priority_score = VALUES(priority_score)";
        $db->query($sql, [$reader['id'], $level, $levelName, $totalEarned, $priorityScore]);
    }
    echo "✓ " . count($readers) . " 个塔罗师等级初始化完成<br>";
    
    // 5. 创建清理任务
    echo "<h3>5. 创建数据清理存储过程</h3>";
    
    $sql = "DROP PROCEDURE IF EXISTS CleanupOldRewards";
    $db->query($sql);
    
    $sql = "CREATE PROCEDURE CleanupOldRewards()
            BEGIN
                DELETE FROM page_browse_rewards WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
                DELETE FROM daily_check_ins WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
                DELETE FROM tata_coin_transactions 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
                AND transaction_type IN ('browse_reward', 'daily_checkin');
            END";
    $db->query($sql);
    echo "✓ 数据清理存储过程创建成功<br>";
    
    echo "<h3>🎉 升级完成！</h3>";
    echo "<p><strong>新功能已启用：</strong></p>";
    echo "<ul>";
    echo "<li>✅ 每日签到系统（连续7天可获得57个Tata Coin）</li>";
    echo "<li>✅ 页面浏览奖励（每页1个Tata Coin，每日最多10个）</li>";
    echo "<li>✅ 完善资料奖励（20个Tata Coin）</li>";
    echo "<li>✅ 用户等级系统（享受折扣优惠）</li>";
    echo "<li>✅ 塔罗师等级系统（提升曝光优先级）</li>";
    echo "<li>✅ 邀请奖励机制</li>";
    echo "</ul>";
    
    echo "<p><strong>建议操作：</strong></p>";
    echo "<ul>";
    echo "<li>1. 在网站头部添加Tata Coin余额显示</li>";
    echo "<li>2. 在用户中心添加签到按钮</li>";
    echo "<li>3. 设置定期清理任务（建议每周执行一次 CALL CleanupOldRewards()）</li>";
    echo "<li>4. 通知用户新功能上线</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>升级失败：" . $e->getMessage() . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}
?>
