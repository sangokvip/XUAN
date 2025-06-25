<?php
/**
 * 测试邀请返点功能
 */
session_start();
require_once 'config/config.php';
require_once 'includes/InvitationManager.php';
require_once 'includes/TataCoinManager.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $invitationManager = new InvitationManager();
        $tataCoinManager = new TataCoinManager();
        
        if (isset($_POST['test_commission'])) {
            // 测试邀请返点
            $testUserId = 1; // 假设用户ID为1
            $testAmount = 30; // 消费30币
            
            $messages[] = "🧪 开始测试邀请返点...";
            
            // 检查用户是否有邀请人
            $user = $db->fetchOne("SELECT invited_by, invited_by_type, full_name FROM users WHERE id = ?", [$testUserId]);
            if ($user && $user['invited_by']) {
                $messages[] = "✅ 用户 {$user['full_name']} 有邀请人 (ID: {$user['invited_by']}, 类型: {$user['invited_by_type']})";
                
                // 模拟消费
                $beforeBalance = $tataCoinManager->getBalance($testUserId, 'user');
                $messages[] = "💰 用户消费前余额: {$beforeBalance} 币";
                
                if ($beforeBalance >= $testAmount) {
                    // 执行消费
                    $result = $tataCoinManager->spend($testUserId, 'user', $testAmount, "测试邀请返点消费");
                    
                    if ($result) {
                        $afterBalance = $tataCoinManager->getBalance($testUserId, 'user');
                        $messages[] = "✅ 消费成功，用户余额: {$afterBalance} 币";
                        
                        // 检查邀请人是否收到返点
                        $inviterBalance = $tataCoinManager->getBalance($user['invited_by'], $user['invited_by_type']);
                        $messages[] = "💎 邀请人当前余额: {$inviterBalance} 币";
                        
                        // 检查返点记录
                        $commissionHistory = $invitationManager->getCommissionHistory($user['invited_by'], $user['invited_by_type'], 5, 0);
                        if (!empty($commissionHistory)) {
                            $messages[] = "📊 最近的返点记录:";
                            foreach ($commissionHistory as $record) {
                                $messages[] = "  - {$record['invitee_name']}: +{$record['commission_amount']}币 ({$record['commission_rate']}%)";
                            }
                        } else {
                            $messages[] = "❌ 没有找到返点记录";
                        }
                    } else {
                        $messages[] = "❌ 消费失败";
                    }
                } else {
                    $messages[] = "❌ 用户余额不足，无法测试";
                }
            } else {
                $messages[] = "❌ 用户没有邀请人，无法测试返点";
            }
        }
        
        if (isset($_POST['check_users'])) {
            // 检查用户邀请关系
            $users = $db->fetchAll(
                "SELECT id, full_name, email, invited_by, invited_by_type, tata_coin 
                 FROM users 
                 ORDER BY id DESC LIMIT 10"
            );
            
            $messages[] = "👥 最近10个用户的邀请关系:";
            foreach ($users as $user) {
                $inviteInfo = $user['invited_by'] ? 
                    "邀请人: {$user['invited_by']} ({$user['invited_by_type']})" : 
                    "无邀请人";
                $messages[] = "  - {$user['full_name']} (ID:{$user['id']}, 余额:{$user['tata_coin']}币) - {$inviteInfo}";
            }
        }
        
        if (isset($_POST['check_transactions'])) {
            // 检查最近的交易记录
            $transactions = $db->fetchAll(
                "SELECT t.*, u.full_name as user_name 
                 FROM tata_coin_transactions t
                 LEFT JOIN users u ON t.user_id = u.id AND t.user_type = 'user'
                 LEFT JOIN readers r ON t.user_id = r.id AND t.user_type = 'reader'
                 ORDER BY t.created_at DESC LIMIT 10"
            );
            
            $messages[] = "💳 最近10条交易记录:";
            foreach ($transactions as $tx) {
                $userName = $tx['user_name'] ?: '未知用户';
                $messages[] = "  - {$userName} ({$tx['user_type']}): {$tx['amount']}币 - {$tx['description']}";
            }
        }
        
        if (isset($_POST['setup_test_data'])) {
            // 设置测试数据
            $messages[] = "🔧 设置测试数据...";
            
            // 创建测试塔罗师（邀请人）
            $testReader = $db->fetchOne("SELECT id FROM readers WHERE email = 'test_reader@example.com'");
            if (!$testReader) {
                $readerId = $db->insert('readers', [
                    'username' => 'test_reader',
                    'email' => 'test_reader@example.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '测试塔罗师',
                    'gender' => 'female',
                    'experience_years' => 5,
                    'specialties' => '感情、事业',
                    'description' => '测试用塔罗师',
                    'photo' => 'img/tf.jpg',
                    'tata_coin' => 0
                ]);
                $messages[] = "✅ 创建测试塔罗师 (ID: {$readerId})";
            } else {
                $readerId = $testReader['id'];
                $messages[] = "✅ 使用现有测试塔罗师 (ID: {$readerId})";
            }
            
            // 创建测试用户（被邀请人）
            $testUser = $db->fetchOne("SELECT id FROM users WHERE email = 'test_user@example.com'");
            if (!$testUser) {
                $userId = $db->insert('users', [
                    'username' => 'test_user',
                    'email' => 'test_user@example.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '测试用户',
                    'gender' => 'male',
                    'avatar' => 'img/nm.jpg',
                    'tata_coin' => 100,
                    'invited_by' => $readerId,
                    'invited_by_type' => 'reader'
                ]);
                $messages[] = "✅ 创建测试用户 (ID: {$userId})，邀请人: {$readerId}";
            } else {
                $userId = $testUser['id'];
                // 更新邀请关系
                $db->query(
                    "UPDATE users SET invited_by = ?, invited_by_type = ?, tata_coin = 100 WHERE id = ?",
                    [$readerId, 'reader', $userId]
                );
                $messages[] = "✅ 更新测试用户 (ID: {$userId}) 的邀请关系";
            }
        }
        
    } catch (Exception $e) {
        $messages[] = "❌ 错误: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邀请返点功能测试</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .messages {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            white-space: pre-line;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 邀请返点功能测试</h1>
        
        <div class="warning">
            <strong>⚠️ 测试工具</strong><br>
            这是邀请返点功能的测试工具，用于验证邀请-注册-消费-返点的完整流程。
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages"><?php echo implode("\n", $messages); ?></div>
        <?php endif; ?>
        
        <div style="margin: 20px 0;">
            <form method="POST" style="display: inline;">
                <button type="submit" name="setup_test_data" class="btn btn-success">
                    🔧 设置测试数据
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_users" class="btn">
                    👥 检查用户邀请关系
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="test_commission" class="btn btn-warning">
                    💰 测试邀请返点
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_transactions" class="btn">
                    💳 检查交易记录
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📋 测试步骤：</h3>
            <ol>
                <li>点击"设置测试数据"创建测试用户和塔罗师</li>
                <li>点击"检查用户邀请关系"确认邀请关系正确</li>
                <li>点击"测试邀请返点"模拟用户消费并检查返点</li>
                <li>点击"检查交易记录"查看详细的交易流水</li>
            </ol>
            
            <h3>🎯 预期结果：</h3>
            <ul>
                <li>用户消费30币后，邀请人应该收到1.5币返点（5%）</li>
                <li>返点记录应该出现在邀请人的返点历史中</li>
                <li>交易记录中应该有消费和返点两条记录</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin/upgrade_invitation_system.php" class="btn">升级邀请系统</a>
            <a href="reader/invitation.php" class="btn">塔罗师邀请管理</a>
        </div>
    </div>
</body>
</html>
