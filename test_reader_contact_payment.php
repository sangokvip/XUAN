<?php
/**
 * 测试塔罗师联系方式付费查看功能
 */
session_start();
require_once 'config/config.php';
require_once 'includes/TataCoinManager.php';
require_once 'includes/InvitationManager.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $tataCoinManager = new TataCoinManager();
        $invitationManager = new InvitationManager();
        
        if (isset($_POST['setup_test_data'])) {
            // 设置测试数据
            $messages[] = "🔧 设置测试数据...";
            
            // 1. 创建邀请塔罗师
            $inviterReader = $db->fetchOne("SELECT id FROM readers WHERE email = 'inviter_reader@test.com'");
            if (!$inviterReader) {
                $inviterReaderId = $db->insert('readers', [
                    'username' => 'inviter_reader',
                    'email' => 'inviter_reader@test.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '邀请塔罗师',
                    'gender' => 'female',
                    'experience_years' => 5,
                    'specialties' => '感情、事业',
                    'description' => '测试邀请塔罗师',
                    'photo' => 'img/tf.jpg',
                    'tata_coin' => 0
                ]);
                $messages[] = "✅ 创建邀请塔罗师 (ID: {$inviterReaderId})";
            } else {
                $inviterReaderId = $inviterReader['id'];
                $messages[] = "✅ 使用现有邀请塔罗师 (ID: {$inviterReaderId})";
            }
            
            // 2. 创建被查看的塔罗师
            $targetReader = $db->fetchOne("SELECT id FROM readers WHERE email = 'target_reader@test.com'");
            if (!$targetReader) {
                $targetReaderId = $db->insert('readers', [
                    'username' => 'target_reader',
                    'email' => 'target_reader@test.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '目标塔罗师',
                    'gender' => 'male',
                    'experience_years' => 3,
                    'specialties' => '财运、桃花',
                    'description' => '被查看的塔罗师',
                    'photo' => 'img/tm.jpg',
                    'tata_coin' => 0,
                    'contact_info' => 'WeChat: target_reader_wx'
                ]);
                $messages[] = "✅ 创建目标塔罗师 (ID: {$targetReaderId})";
            } else {
                $targetReaderId = $targetReader['id'];
                $messages[] = "✅ 使用现有目标塔罗师 (ID: {$targetReaderId})";
            }
            
            // 3. 创建被邀请用户
            $invitedUser = $db->fetchOne("SELECT id FROM users WHERE email = 'invited_user@test.com'");
            if (!$invitedUser) {
                $invitedUserId = $db->insert('users', [
                    'username' => 'invited_user',
                    'email' => 'invited_user@test.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '被邀请用户',
                    'gender' => 'female',
                    'avatar' => 'img/nf.jpg',
                    'tata_coin' => 100,
                    'invited_by' => $inviterReaderId,
                    'invited_by_type' => 'reader'
                ]);
                $messages[] = "✅ 创建被邀请用户 (ID: {$invitedUserId})";
            } else {
                $invitedUserId = $invitedUser['id'];
                // 更新邀请关系和余额
                $db->query(
                    "UPDATE users SET invited_by = ?, invited_by_type = 'reader', tata_coin = 100 WHERE id = ?",
                    [$inviterReaderId, $invitedUserId]
                );
                $messages[] = "✅ 更新被邀请用户 (ID: {$invitedUserId})";
            }
            
            $messages[] = "📊 测试数据设置完成：";
            $messages[] = "  - 邀请塔罗师ID: {$inviterReaderId}";
            $messages[] = "  - 目标塔罗师ID: {$targetReaderId}";
            $messages[] = "  - 被邀请用户ID: {$invitedUserId}";
        }
        
        if (isset($_POST['test_payment'])) {
            // 测试付费查看
            $userId = (int)($_POST['user_id'] ?? 0);
            $readerId = (int)($_POST['reader_id'] ?? 0);
            
            if (!$userId || !$readerId) {
                $messages[] = "❌ 请输入有效的用户ID和塔罗师ID";
            } else {
                $messages[] = "🧪 开始测试付费查看...";
                $messages[] = "👤 用户ID: {$userId}";
                $messages[] = "🔮 塔罗师ID: {$readerId}";
                
                // 检查用户余额
                $beforeBalance = $tataCoinManager->getBalance($userId, 'user');
                $messages[] = "💰 用户付费前余额: {$beforeBalance} 币";
                
                // 检查邀请人信息
                $user = $db->fetchOne("SELECT invited_by, invited_by_type, full_name FROM users WHERE id = ?", [$userId]);
                if ($user && $user['invited_by']) {
                    $inviterBalance = $tataCoinManager->getBalance($user['invited_by'], $user['invited_by_type']);
                    $messages[] = "🔗 邀请人余额: {$inviterBalance} 币";
                } else {
                    $messages[] = "ℹ️ 用户无邀请人";
                }
                
                // 执行付费查看
                $result = $tataCoinManager->viewReaderContact($userId, $readerId);
                
                if ($result['success']) {
                    $messages[] = "✅ 付费查看成功！";
                    $messages[] = "📞 联系方式: {$result['contact_info']}";
                    
                    // 检查用户余额变化
                    $afterBalance = $tataCoinManager->getBalance($userId, 'user');
                    $messages[] = "💰 用户付费后余额: {$afterBalance} 币";
                    $messages[] = "💸 消费金额: " . ($beforeBalance - $afterBalance) . " 币";
                    
                    // 检查邀请人余额变化
                    if ($user && $user['invited_by']) {
                        $inviterAfterBalance = $tataCoinManager->getBalance($user['invited_by'], $user['invited_by_type']);
                        $messages[] = "🎁 邀请人余额变化: {$inviterBalance} → {$inviterAfterBalance} 币";
                        $commission = $inviterAfterBalance - $inviterBalance;
                        if ($commission > 0) {
                            $messages[] = "✅ 邀请返点: +{$commission} 币";
                        } else {
                            $messages[] = "❌ 未收到邀请返点";
                        }
                    }
                    
                    // 检查交易记录
                    $transactions = $db->fetchAll(
                        "SELECT * FROM tata_coin_transactions 
                         WHERE (user_id = ? AND user_type = 'user') OR (user_id = ? AND user_type = ?)
                         ORDER BY created_at DESC LIMIT 5",
                        [$userId, $user['invited_by'] ?? 0, $user['invited_by_type'] ?? 'reader']
                    );
                    
                    $messages[] = "💳 最近交易记录:";
                    foreach ($transactions as $tx) {
                        $messages[] = "  - {$tx['user_type']} {$tx['user_id']}: {$tx['transaction_type']} {$tx['amount']}币 - {$tx['description']}";
                    }
                    
                } else {
                    $messages[] = "❌ 付费查看失败: {$result['message']}";
                }
            }
        }
        
        if (isset($_POST['check_status'])) {
            // 检查当前状态
            $messages[] = "📊 当前系统状态:";
            
            // 检查测试用户
            $testUsers = $db->fetchAll(
                "SELECT id, full_name, email, tata_coin, invited_by, invited_by_type 
                 FROM users 
                 WHERE email LIKE '%@test.com' 
                 ORDER BY id DESC LIMIT 5"
            );
            
            $messages[] = "👥 测试用户:";
            foreach ($testUsers as $user) {
                $inviteInfo = $user['invited_by'] ? "邀请人:{$user['invited_by']}({$user['invited_by_type']})" : "无邀请人";
                $messages[] = "  - ID:{$user['id']}, {$user['full_name']}, 余额:{$user['tata_coin']}币, {$inviteInfo}";
            }
            
            // 检查测试塔罗师
            $testReaders = $db->fetchAll(
                "SELECT id, full_name, email, tata_coin 
                 FROM readers 
                 WHERE email LIKE '%@test.com' 
                 ORDER BY id DESC LIMIT 5"
            );
            
            $messages[] = "🔮 测试塔罗师:";
            foreach ($testReaders as $reader) {
                $messages[] = "  - ID:{$reader['id']}, {$reader['full_name']}, 余额:{$reader['tata_coin']}币";
            }
        }
        
    } catch (Exception $e) {
        $messages[] = "❌ 错误: " . $e->getMessage();
        $messages[] = "📍 位置: " . $e->getFile() . ":" . $e->getLine();
        $messages[] = "🔍 追踪: " . $e->getTraceAsString();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师联系方式付费测试</title>
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
            font-family: monospace;
            font-size: 14px;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 塔罗师联系方式付费测试</h1>
        
        <div class="warning">
            <strong>⚠️ 测试工具</strong><br>
            这是塔罗师联系方式付费查看功能的测试工具，用于验证邀请返点机制是否正常工作。
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
                <button type="submit" name="check_status" class="btn">
                    📊 检查当前状态
                </button>
            </form>
        </div>
        
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3>🧪 测试付费查看</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="user_id">用户ID:</label>
                    <input type="number" id="user_id" name="user_id" placeholder="输入被邀请用户的ID">
                </div>
                
                <div class="form-group">
                    <label for="reader_id">塔罗师ID:</label>
                    <input type="number" id="reader_id" name="reader_id" placeholder="输入目标塔罗师的ID">
                </div>
                
                <button type="submit" name="test_payment" class="btn btn-warning">
                    💰 测试付费查看
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📋 测试步骤：</h3>
            <ol>
                <li>点击"设置测试数据"创建测试用户和塔罗师</li>
                <li>点击"检查当前状态"查看测试数据ID</li>
                <li>输入用户ID和塔罗师ID</li>
                <li>点击"测试付费查看"</li>
                <li>检查邀请返点是否正常发放</li>
            </ol>
            
            <h3>🎯 预期结果：</h3>
            <ul>
                <li>用户应该能成功付费查看塔罗师联系方式</li>
                <li>用户余额应该减少相应金额</li>
                <li>邀请人应该收到5%的返点</li>
                <li>应该有相应的交易记录</li>
                <li>不应该出现addBalance()错误</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="reader.php?id=1" class="btn">查看塔罗师页面</a>
            <a href="test_invitation_commission.php" class="btn">邀请返点测试</a>
        </div>
    </div>
</body>
</html>
