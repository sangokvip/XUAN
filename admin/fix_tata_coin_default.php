<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$success = [];
$errors = [];
$fixCompleted = false;

// 处理修复请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_fix'])) {
    try {
        $db = Database::getInstance();
        
        // 1. 修改users表的tata_coin字段默认值
        $sql = "ALTER TABLE users MODIFY COLUMN tata_coin INT DEFAULT 0 COMMENT 'Tata Coin余额，通过系统发放'";
        $db->query($sql);
        $success[] = "✓ 修改users表tata_coin字段默认值为0";
        
        // 2. 检查是否有用户获得了双倍Tata Coin（200个）
        $doubleUsers = $db->fetchAll(
            "SELECT u.id, u.username, u.full_name, u.tata_coin, u.created_at 
             FROM users u 
             WHERE u.tata_coin = 200 
             AND u.created_at > '2024-01-01'
             AND NOT EXISTS (
                 SELECT 1 FROM tata_coin_transactions t 
                 WHERE t.user_id = u.id AND t.user_type = 'user' 
                 AND t.transaction_type IN ('spend', 'admin_subtract')
             )"
        );
        
        if (!empty($doubleUsers)) {
            $success[] = "✓ 发现 " . count($doubleUsers) . " 个用户可能获得了双倍Tata Coin";
            
            // 3. 修复双倍Tata Coin问题
            foreach ($doubleUsers as $user) {
                // 检查该用户的注册奖励记录
                $registrationReward = $db->fetchOne(
                    "SELECT * FROM tata_coin_transactions 
                     WHERE user_id = ? AND user_type = 'user' 
                     AND description = '新用户注册赠送'",
                    [$user['id']]
                );
                
                if ($registrationReward) {
                    // 如果有注册奖励记录，说明是通过系统发放的，需要扣除多余的100
                    $newBalance = 100; // 应该只有100
                    $db->query("UPDATE users SET tata_coin = ? WHERE id = ?", [$newBalance, $user['id']]);
                    
                    // 记录调整
                    $db->insert('tata_coin_transactions', [
                        'user_id' => $user['id'],
                        'user_type' => 'user',
                        'transaction_type' => 'admin_subtract',
                        'amount' => -100,
                        'balance_after' => $newBalance,
                        'description' => '修复双倍注册奖励问题'
                    ]);
                    
                    $success[] = "✓ 修复用户 {$user['username']} 的Tata Coin余额：200 → 100";
                }
            }
        } else {
            $success[] = "✓ 未发现双倍Tata Coin问题";
        }
        
        // 4. 检查新注册用户是否正常获得100个Tata Coin
        $recentUsers = $db->fetchAll(
            "SELECT u.id, u.username, u.tata_coin, 
                    (SELECT COUNT(*) FROM tata_coin_transactions t 
                     WHERE t.user_id = u.id AND t.user_type = 'user' 
                     AND t.description = '新用户注册赠送') as has_reward
             FROM users u 
             WHERE u.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
             ORDER BY u.created_at DESC
             LIMIT 10"
        );
        
        if (!empty($recentUsers)) {
            $success[] = "✓ 最近注册的用户Tata Coin状态：";
            foreach ($recentUsers as $user) {
                $status = $user['has_reward'] ? "✓" : "✗";
                $success[] = "  {$status} {$user['username']}: {$user['tata_coin']} Tata Coin";
            }
        }
        
        $fixCompleted = true;
        
    } catch (Exception $e) {
        $errors[] = "修复失败：" . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修复Tata Coin默认值问题 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fix-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .fix-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .fix-btn:hover {
            background: #c82333;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="fix-container">
        <h1>🔧 修复Tata Coin默认值问题</h1>
        
        <a href="dashboard.php" class="btn-back">← 返回管理后台</a>
        
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h4>❌ 修复过程中出现错误：</h4>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <h4>✅ 修复进度：</h4>
                <?php foreach ($success as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($fixCompleted): ?>
            <div class="success-box">
                <h3>🎉 修复完成！</h3>
                <p><strong>已完成的修复：</strong></p>
                <ul>
                    <li>✅ 修改users表tata_coin字段默认值为0</li>
                    <li>✅ 检查并修复双倍Tata Coin问题</li>
                    <li>✅ 验证新用户注册奖励机制</li>
                </ul>
                
                <p><strong>现在新注册用户将正确获得100个Tata Coin（不是200个）</strong></p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h4>⚠️ 发现的问题：</h4>
                <p>新用户注册时获得了200个Tata Coin，而不是预期的100个。这是因为：</p>
                <ul>
                    <li>users表的tata_coin字段默认值设置为100</li>
                    <li>注册时TataCoinManager又发放了100个</li>
                    <li>导致总共获得200个Tata Coin</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h4>🔧 本次修复将执行：</h4>
                <ul>
                    <li>修改users表tata_coin字段默认值为0</li>
                    <li>检查并修复已经获得双倍Tata Coin的用户</li>
                    <li>确保新用户只获得100个Tata Coin</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="confirm_fix" class="fix-btn" 
                        onclick="return confirm('确定要修复Tata Coin默认值问题吗？这将影响数据库结构。')">
                    🔧 开始修复
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
