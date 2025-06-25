<?php
/**
 * 测试用户注册功能
 */
session_start();
require_once 'config/config.php';
require_once 'includes/InvitationManager.php';
require_once 'includes/TataCoinManager.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        if (isset($_POST['test_registration'])) {
            // 测试用户注册
            $inviteToken = $_POST['invite_token'] ?? '';
            
            // 生成测试用户数据
            $testEmail = 'test_user_' . time() . '@example.com';
            $testUsername = 'test_user_' . time();
            
            $userData = [
                'username' => $testUsername,
                'email' => $testEmail,
                'password' => '123456',
                'confirm_password' => '123456',
                'full_name' => '测试用户' . time(),
                'gender' => 'male'
            ];
            
            $messages[] = "🧪 开始测试用户注册...";
            $messages[] = "📧 测试邮箱: {$testEmail}";
            $messages[] = "👤 测试用户名: {$testUsername}";
            
            if (!empty($inviteToken)) {
                $messages[] = "🔗 使用邀请码: {$inviteToken}";
            }
            
            // 调用注册函数
            require_once 'includes/auth.php';
            $result = registerUser($userData, $inviteToken);
            
            if ($result['success']) {
                $messages[] = "✅ 用户注册成功！";
                $messages[] = "🆔 用户ID: {$result['user_id']}";
                
                // 检查用户信息
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$result['user_id']]);
                $messages[] = "👤 用户信息: {$user['full_name']} ({$user['email']})";
                $messages[] = "💰 Tata Coin余额: {$user['tata_coin']}";
                
                if ($user['invited_by']) {
                    $messages[] = "🔗 邀请人: ID {$user['invited_by']} ({$user['invited_by_type']})";
                } else {
                    $messages[] = "ℹ️ 无邀请人";
                }
                
                // 检查交易记录
                $transactions = $db->fetchAll(
                    "SELECT * FROM tata_coin_transactions WHERE user_id = ? AND user_type = 'user' ORDER BY created_at DESC",
                    [$result['user_id']]
                );
                
                $messages[] = "💳 交易记录 (" . count($transactions) . " 条):";
                foreach ($transactions as $tx) {
                    $messages[] = "  - {$tx['transaction_type']}: {$tx['amount']}币 - {$tx['description']}";
                }
                
            } else {
                $messages[] = "❌ 用户注册失败:";
                foreach ($result['errors'] as $error) {
                    $messages[] = "  - {$error}";
                }
            }
        }
        
        if (isset($_POST['create_inviter'])) {
            // 创建邀请人
            $invitationManager = new InvitationManager();
            
            // 创建测试塔罗师
            $testReader = $db->fetchOne("SELECT id FROM readers WHERE email = 'test_inviter_reader@example.com'");
            if (!$testReader) {
                $readerId = $db->insert('readers', [
                    'username' => 'test_inviter_reader',
                    'email' => 'test_inviter_reader@example.com',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'full_name' => '测试邀请塔罗师',
                    'gender' => 'female',
                    'experience_years' => 3,
                    'specialties' => '感情、事业',
                    'description' => '测试用邀请塔罗师',
                    'photo' => 'img/tf.jpg',
                    'tata_coin' => 0
                ]);
                $messages[] = "✅ 创建测试塔罗师 (ID: {$readerId})";
            } else {
                $readerId = $testReader['id'];
                $messages[] = "✅ 使用现有测试塔罗师 (ID: {$readerId})";
            }
            
            // 生成邀请链接
            $invitationToken = $invitationManager->generateInvitationLink($readerId, 'reader');
            $messages[] = "🔗 生成邀请Token: {$invitationToken}";
            
            $userInviteUrl = SITE_URL . '/auth/register.php?invite=' . $invitationToken;
            $messages[] = "👤 用户注册链接: {$userInviteUrl}";
        }
        
        if (isset($_POST['check_database'])) {
            // 检查数据库状态
            $messages[] = "📊 数据库状态检查:";
            
            // 检查表是否存在
            $tables = ['users', 'tata_coin_transactions', 'invitation_links', 'invitation_relations'];
            foreach ($tables as $table) {
                try {
                    $count = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}")['count'];
                    $messages[] = "✅ {$table}: {$count} 条记录";
                } catch (Exception $e) {
                    $messages[] = "❌ {$table}: 表不存在或有问题";
                }
            }
            
            // 检查字段是否存在
            try {
                $db->fetchOne("SELECT invited_by, invited_by_type FROM users LIMIT 1");
                $messages[] = "✅ users表有邀请字段";
            } catch (Exception $e) {
                $messages[] = "❌ users表缺少邀请字段";
            }
        }
        
    } catch (Exception $e) {
        $messages[] = "❌ 严重错误: " . $e->getMessage();
        $messages[] = "📍 错误位置: " . $e->getFile() . ":" . $e->getLine();
        $messages[] = "🔍 错误追踪: " . $e->getTraceAsString();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册功能测试</title>
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
        <h1>🧪 用户注册功能测试</h1>
        
        <div class="warning">
            <strong>⚠️ 测试工具</strong><br>
            这是用户注册功能的测试工具，用于验证邀请注册和Tata Coin初始化是否正常工作。
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages"><?php echo implode("\n", $messages); ?></div>
        <?php endif; ?>
        
        <div style="margin: 20px 0;">
            <form method="POST" style="display: inline;">
                <button type="submit" name="create_inviter" class="btn btn-success">
                    🔧 创建邀请人并生成邀请码
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_database" class="btn">
                    📊 检查数据库状态
                </button>
            </form>
        </div>
        
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3>🧪 测试用户注册</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="invite_token">邀请Token (可选):</label>
                    <input type="text" id="invite_token" name="invite_token" placeholder="输入邀请Token，留空则测试无邀请注册">
                </div>
                
                <button type="submit" name="test_registration" class="btn btn-warning">
                    👤 测试用户注册
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📋 测试步骤：</h3>
            <ol>
                <li>点击"检查数据库状态"确认系统正常</li>
                <li>点击"创建邀请人并生成邀请码"</li>
                <li>复制生成的邀请Token</li>
                <li>在测试区域输入Token并点击"测试用户注册"</li>
                <li>检查注册结果和邀请关系</li>
            </ol>
            
            <h3>🎯 预期结果：</h3>
            <ul>
                <li>用户注册应该成功</li>
                <li>用户应该获得100 Tata Coin初始余额</li>
                <li>如果有邀请码，应该建立邀请关系</li>
                <li>应该有相应的交易记录</li>
                <li>不应该出现lastInsertId()错误</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="auth/register.php" class="btn">用户注册页面</a>
            <a href="test_invitation_links.php" class="btn">邀请链接测试</a>
        </div>
    </div>
</body>
</html>
