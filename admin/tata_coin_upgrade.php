<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$success = [];
$errors = [];
$upgradeCompleted = false;

// 处理升级请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_upgrade'])) {
    try {
        $db = Database::getInstance();
        
        // 1. 检查并清理可能存在的有问题的表
        try {
            // 如果user_levels表存在但有外键问题，先删除它
            $tableExists = $db->fetchOne("SHOW TABLES LIKE 'user_levels'");
            if ($tableExists) {
                $db->query("DROP TABLE IF EXISTS user_levels");
                $success[] = "✓ 清理了可能有问题的user_levels表";
            }
        } catch (Exception $e) {
            // 忽略清理错误
        }

        // 2. 创建新表
        $tables = [
            'daily_check_ins' => "CREATE TABLE IF NOT EXISTS daily_check_ins (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表'",
            
            'page_browse_rewards' => "CREATE TABLE IF NOT EXISTS page_browse_rewards (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面浏览奖励记录表'",
            
            'user_levels' => "CREATE TABLE IF NOT EXISTS user_levels (
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
                UNIQUE KEY unique_user_level (user_id, user_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户等级表'"
        ];
        
        foreach ($tables as $tableName => $sql) {
            $db->query($sql);
            $success[] = "✓ {$tableName} 表创建成功";
        }
        
        // 3. 更新交易类型枚举
        try {
            $sql = "ALTER TABLE tata_coin_transactions 
                    MODIFY COLUMN transaction_type ENUM(
                        'earn', 'spend', 'admin_add', 'admin_subtract', 'transfer',
                        'daily_checkin', 'browse_reward', 'profile_completion', 
                        'invitation_reward', 'level_bonus'
                    ) NOT NULL COMMENT '交易类型'";
            $db->query($sql);
            $success[] = "✓ 交易类型枚举更新成功";
        } catch (Exception $e) {
            $errors[] = "⚠ 交易类型枚举更新失败（可能已存在）: " . $e->getMessage();
        }
        
        // 4. 插入新的系统设置
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
            $success[] = "✓ 设置 {$key} 更新成功";
        }
        
        // 5. 初始化现有用户等级
        // 先检查user_levels表是否需要临时禁用外键约束
        $db->query("SET FOREIGN_KEY_CHECKS = 0");

        try {
            $users = $db->fetchAll("SELECT id FROM users WHERE is_active = 1");
            $userCount = 0;

            foreach ($users as $user) {
                // 验证用户ID确实存在
                $userExists = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$user['id']]);
                if (!$userExists) {
                    continue; // 跳过不存在的用户
                }

                // 计算用户累计消费
                $totalSpent = $db->fetchOne(
                    "SELECT COALESCE(SUM(ABS(amount)), 0) as total FROM tata_coin_transactions
                     WHERE user_id = ? AND user_type = 'user' AND amount < 0",
                    [$user['id']]
                )['total'] ?? 0;

                // 计算等级
                $level = 1;
                $levelName = 'L1';
                $discountRate = 0;

                if ($totalSpent >= 1000) {
                    $level = 5;
                    $levelName = 'L5';
                    $discountRate = 20;
                } elseif ($totalSpent >= 501) {
                    $level = 4;
                    $levelName = 'L4';
                    $discountRate = 15;
                } elseif ($totalSpent >= 201) {
                    $level = 3;
                    $levelName = 'L3';
                    $discountRate = 10;
                } elseif ($totalSpent >= 101) {
                    $level = 2;
                    $levelName = 'L2';
                    $discountRate = 5;
                }

                try {
                    // 插入或更新用户等级
                    $sql = "INSERT INTO user_levels (user_id, user_type, level, level_name, total_spent, discount_rate)
                            VALUES (?, 'user', ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                level = VALUES(level),
                                level_name = VALUES(level_name),
                                total_spent = VALUES(total_spent),
                                discount_rate = VALUES(discount_rate)";
                    $db->query($sql, [$user['id'], $level, $levelName, $totalSpent, $discountRate]);
                    $userCount++;
                } catch (Exception $e) {
                    $errors[] = "用户 {$user['id']} 等级初始化失败: " . $e->getMessage();
                }
            }
            $success[] = "✓ {$userCount} 个用户等级初始化完成";

        } finally {
            // 重新启用外键约束
            $db->query("SET FOREIGN_KEY_CHECKS = 1");
        }
        
        // 6. 初始化塔罗师等级
        $db->query("SET FOREIGN_KEY_CHECKS = 0");

        try {
            $readers = $db->fetchAll("SELECT id FROM readers WHERE is_active = 1");
            $readerCount = 0;

            foreach ($readers as $reader) {
                // 验证塔罗师ID确实存在
                $readerExists = $db->fetchOne("SELECT id FROM readers WHERE id = ?", [$reader['id']]);
                if (!$readerExists) {
                    continue; // 跳过不存在的塔罗师
                }

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

                // 计算等级（简化为两种）
                $readerInfo = $db->fetchOne("SELECT is_featured FROM readers WHERE id = ?", [$reader['id']]);
                $isFeatured = $readerInfo['is_featured'] ?? false;

                $level = $isFeatured ? 2 : 1;
                $levelName = $isFeatured ? '推荐塔罗师' : '塔罗师';
                $priorityScore = $isFeatured ? 100 : 0;

                try {
                    // 插入或更新塔罗师等级
                    $sql = "INSERT INTO user_levels (user_id, user_type, level, level_name, total_earned, priority_score)
                            VALUES (?, 'reader', ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                level = VALUES(level),
                                level_name = VALUES(level_name),
                                total_earned = VALUES(total_earned),
                                priority_score = VALUES(priority_score)";
                    $db->query($sql, [$reader['id'], $level, $levelName, $totalEarned, $priorityScore]);
                    $readerCount++;
                } catch (Exception $e) {
                    $errors[] = "塔罗师 {$reader['id']} 等级初始化失败: " . $e->getMessage();
                }
            }
            $success[] = "✓ {$readerCount} 个塔罗师等级初始化完成";

        } finally {
            // 重新启用外键约束
            $db->query("SET FOREIGN_KEY_CHECKS = 1");
        }
        
        $upgradeCompleted = true;
        
    } catch (Exception $e) {
        $errors[] = "升级失败：" . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tata Coin 系统升级 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .upgrade-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .feature-list li {
            margin-bottom: 8px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .upgrade-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .upgrade-btn:hover {
            background: #218838;
        }
        
        .upgrade-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="upgrade-container">
        <h1>🚀 Tata Coin 经济体系升级</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h4>❌ 升级过程中出现错误：</h4>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <h4>✅ 升级进度：</h4>
                <?php foreach ($success as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($upgradeCompleted): ?>
            <div class="success-box">
                <h3>🎉 升级完成！</h3>
                <p><strong>新功能已启用：</strong></p>
                <ul>
                    <li>✅ 每日签到系统（连续7天可获得57个Tata Coin）</li>
                    <li>✅ 页面浏览奖励（每页1个Tata Coin，每日最多10个）</li>
                    <li>✅ 完善资料奖励（20个Tata Coin）</li>
                    <li>✅ 用户等级系统（享受折扣优惠）</li>
                    <li>✅ 塔罗师等级系统（提升曝光优先级）</li>
                    <li>✅ 邀请奖励机制</li>
                </ul>
                
                <p><strong>建议操作：</strong></p>
                <ul>
                    <li>1. 在网站头部添加Tata Coin余额显示</li>
                    <li>2. 在用户中心添加签到按钮</li>
                    <li>3. 通知用户新功能上线</li>
                </ul>
                
                <p><a href="dashboard.php" class="btn btn-primary">返回管理后台</a></p>
            </div>
        <?php else: ?>
            <div class="feature-list">
                <h3>📋 本次升级将添加以下功能：</h3>
                <ul>
                    <li><strong>每日签到系统</strong> - 连续签到7天可获得57个Tata Coin</li>
                    <li><strong>页面浏览奖励</strong> - 每个页面停留5秒可获得1个Tata Coin（每日最多10个）</li>
                    <li><strong>完善资料奖励</strong> - 完善头像、性别等信息可获得20个Tata Coin</li>
                    <li><strong>用户等级系统</strong> - 基于累计消费的5级等级系统，高等级享受折扣</li>
                    <li><strong>塔罗师等级系统</strong> - 基于收入和评价的等级系统，影响推荐优先级</li>
                    <li><strong>邀请奖励机制</strong> - 邀请用户和塔罗师可获得相应奖励</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h4>⚠️ 升级前请注意：</h4>
                <ul>
                    <li>本次升级将创建新的数据库表</li>
                    <li>会为现有用户和塔罗师初始化等级信息</li>
                    <li>升级过程中请勿关闭页面</li>
                    <li>建议在低峰期进行升级</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="confirm_upgrade" class="upgrade-btn" 
                        onclick="return confirm('确定要开始升级吗？升级过程可能需要几分钟时间。')">
                    🚀 开始升级 Tata Coin 系统
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
