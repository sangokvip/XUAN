<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_now'])) {
    try {
        // 简单修复：直接处理用户余额问题
        
        // 1. 检查并修复用户余额
        $usersWithProblems = $db->fetchAll("
            SELECT id, username, full_name, tata_coin 
            FROM users 
            WHERE tata_coin > 100
        ");
        
        $fixedCount = 0;
        foreach ($usersWithProblems as $user) {
            // 将余额重置为100（正确的新用户赠送金额）
            $db->query("UPDATE users SET tata_coin = 100 WHERE id = ?", [$user['id']]);
            $fixedCount++;
        }
        
        // 2. 删除重复的交易记录
        $duplicateTransactions = $db->fetchAll("
            SELECT user_id, MIN(id) as keep_id, COUNT(*) as count
            FROM tata_coin_transactions 
            WHERE user_type = 'user' AND description = '新用户注册赠送'
            GROUP BY user_id
            HAVING count > 1
        ");
        
        $deletedCount = 0;
        foreach ($duplicateTransactions as $duplicate) {
            // 删除除了最早的记录外的所有重复记录
            $deleted = $db->query("
                DELETE FROM tata_coin_transactions 
                WHERE user_id = ? AND user_type = 'user' AND description = '新用户注册赠送' AND id != ?
            ", [$duplicate['user_id'], $duplicate['keep_id']]);
            $deletedCount += $deleted;
        }
        
        // 3. 修复交易记录的余额字段
        $allUsers = $db->fetchAll("SELECT DISTINCT user_id FROM tata_coin_transactions WHERE user_type = 'user'");
        foreach ($allUsers as $userInfo) {
            $userId = $userInfo['user_id'];
            
            // 获取该用户的所有交易记录
            $transactions = $db->fetchAll("
                SELECT id, amount 
                FROM tata_coin_transactions 
                WHERE user_id = ? AND user_type = 'user' 
                ORDER BY created_at ASC, id ASC
            ", [$userId]);
            
            // 重新计算余额
            $runningBalance = 0;
            foreach ($transactions as $trans) {
                $runningBalance += $trans['amount'];
                $db->query("UPDATE tata_coin_transactions SET balance_after = ? WHERE id = ?", [$runningBalance, $trans['id']]);
            }
            
            // 确保用户表中的余额与最终计算的余额一致
            $db->query("UPDATE users SET tata_coin = ? WHERE id = ?", [$runningBalance, $userId]);
        }
        
        $success = "修复完成！修复了 {$fixedCount} 个用户的余额，删除了 {$deletedCount} 条重复交易记录。";
        
    } catch (Exception $e) {
        $errors[] = "修复失败：" . $e->getMessage();
    }
}

// 检查当前状态
$stats = [];
try {
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT CASE WHEN u.tata_coin > 100 THEN u.id END) as users_with_excess,
            COUNT(CASE WHEN t.description = '新用户注册赠送' THEN 1 END) as gift_transactions,
            COUNT(DISTINCT CASE WHEN t.description = '新用户注册赠送' THEN t.user_id END) as users_with_gifts
        FROM users u
        LEFT JOIN tata_coin_transactions t ON u.id = t.user_id AND t.user_type = 'user'
        WHERE u.tata_coin IS NOT NULL
    ");
} catch (Exception $e) {
    $stats = ['total_users' => 0, 'users_with_excess' => 0, 'gift_transactions' => 0, 'users_with_gifts' => 0];
}

$pageTitle = '简单Tata Coin修复';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fix-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="fix-container">
        <div class="page-header">
            <h1>🔧 简单Tata Coin修复</h1>
            <p>快速修复用户余额问题</p>
        </div>
        
        <a href="tata_coin.php" class="btn btn-secondary" style="margin-bottom: 20px;">← 返回Tata Coin管理</a>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-card">
            <h3>📊 当前状态</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">总用户数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['users_with_excess']; ?></div>
                    <div class="stat-label">余额异常用户</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['gift_transactions']; ?></div>
                    <div class="stat-label">赠送交易记录</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['users_with_gifts']; ?></div>
                    <div class="stat-label">已赠送用户数</div>
                </div>
            </div>
            
            <?php if ($stats['users_with_excess'] > 0): ?>
                <div class="warning-box">
                    <h4>⚠️ 发现问题</h4>
                    <p>检测到 <?php echo $stats['users_with_excess']; ?> 个用户的Tata Coin余额超过100，可能是重复赠送导致的。</p>
                    <p><strong>修复操作将：</strong></p>
                    <ul>
                        <li>将所有用户余额重置为正确值</li>
                        <li>删除重复的赠送交易记录</li>
                        <li>重新计算交易记录中的余额字段</li>
                    </ul>
                </div>
                
                <form method="POST" onsubmit="return confirm('确定要执行修复操作吗？')">
                    <button type="submit" name="fix_now" class="btn btn-success">
                        🔧 立即修复
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>✅ 系统正常</strong><br>
                    所有用户的Tata Coin余额都正常，无需修复。
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>
