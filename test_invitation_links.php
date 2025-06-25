<?php
/**
 * 测试邀请链接功能
 */
session_start();
require_once 'config/config.php';
require_once 'includes/InvitationManager.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $invitationManager = new InvitationManager();
        
        if (isset($_POST['create_test_reader'])) {
            // 创建测试塔罗师
            $testReader = $db->fetchOne("SELECT id FROM readers WHERE email = 'test_inviter@example.com'");
            if (!$testReader) {
                $readerId = $db->insert('readers', [
                    'username' => 'test_inviter',
                    'email' => 'test_inviter@example.com',
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
            $messages[] = "🔗 生成邀请链接 Token: {$invitationToken}";
            
            $userInviteUrl = SITE_URL . '/auth/register.php?invite=' . $invitationToken;
            $readerInviteUrl = SITE_URL . '/auth/reader_register.php?invite=' . $invitationToken;
            
            $messages[] = "👤 用户注册链接: {$userInviteUrl}";
            $messages[] = "🔮 塔罗师注册链接: {$readerInviteUrl}";
        }
        
        if (isset($_POST['test_user_invite'])) {
            // 测试用户邀请链接
            $token = $_POST['test_token'] ?? '';
            if (empty($token)) {
                $messages[] = "❌ 请输入邀请Token";
            } else {
                $invitation = $invitationManager->getInvitationByToken($token);
                if ($invitation) {
                    $messages[] = "✅ 用户邀请链接有效";
                    $messages[] = "📋 邀请人ID: {$invitation['inviter_id']} ({$invitation['inviter_type']})";
                    $messages[] = "📅 创建时间: {$invitation['created_at']}";
                    
                    $testUrl = SITE_URL . '/auth/register.php?invite=' . $token;
                    $messages[] = "🔗 测试链接: <a href='{$testUrl}' target='_blank'>{$testUrl}</a>";
                } else {
                    $messages[] = "❌ 用户邀请链接无效";
                }
            }
        }
        
        if (isset($_POST['test_reader_invite'])) {
            // 测试塔罗师邀请链接
            $token = $_POST['test_token'] ?? '';
            if (empty($token)) {
                $messages[] = "❌ 请输入邀请Token";
            } else {
                $invitation = $invitationManager->getInvitationByToken($token);
                if ($invitation) {
                    $messages[] = "✅ 塔罗师邀请链接有效";
                    $messages[] = "📋 邀请人ID: {$invitation['inviter_id']} ({$invitation['inviter_type']})";
                    $messages[] = "📅 创建时间: {$invitation['created_at']}";
                    
                    $testUrl = SITE_URL . '/auth/reader_register.php?invite=' . $token;
                    $messages[] = "🔗 测试链接: <a href='{$testUrl}' target='_blank'>{$testUrl}</a>";
                } else {
                    $messages[] = "❌ 塔罗师邀请链接无效";
                }
            }
        }
        
        if (isset($_POST['check_tables'])) {
            // 检查邀请相关表
            $tables = ['invitation_links', 'invitation_relations', 'invitation_commissions'];
            foreach ($tables as $table) {
                try {
                    $count = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}")['count'];
                    $messages[] = "✅ {$table} 表存在，共 {$count} 条记录";
                } catch (Exception $e) {
                    $messages[] = "❌ {$table} 表不存在或有问题: " . $e->getMessage();
                }
            }
            
            // 检查用户和塔罗师表的邀请字段
            try {
                $userFields = $db->fetchOne("SELECT invited_by, invited_by_type FROM users LIMIT 1");
                $messages[] = "✅ users表有邀请字段";
            } catch (Exception $e) {
                $messages[] = "❌ users表缺少邀请字段: " . $e->getMessage();
            }
            
            try {
                $readerFields = $db->fetchOne("SELECT invited_by, invited_by_type FROM readers LIMIT 1");
                $messages[] = "✅ readers表有邀请字段";
            } catch (Exception $e) {
                $messages[] = "❌ readers表缺少邀请字段: " . $e->getMessage();
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
    <title>邀请链接功能测试</title>
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
        <h1>🔗 邀请链接功能测试</h1>
        
        <div class="warning">
            <strong>⚠️ 测试工具</strong><br>
            这是邀请链接功能的测试工具，用于验证邀请链接的生成和使用。
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages"><?php echo implode("\n", $messages); ?></div>
        <?php endif; ?>
        
        <div style="margin: 20px 0;">
            <form method="POST" style="display: inline;">
                <button type="submit" name="create_test_reader" class="btn btn-success">
                    🔧 创建测试塔罗师并生成邀请链接
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_tables" class="btn">
                    📊 检查数据库表
                </button>
            </form>
        </div>
        
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3>🧪 测试邀请链接</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="test_token">邀请Token:</label>
                    <input type="text" id="test_token" name="test_token" placeholder="输入邀请Token进行测试">
                </div>
                
                <button type="submit" name="test_user_invite" class="btn btn-warning">
                    👤 测试用户邀请链接
                </button>
                
                <button type="submit" name="test_reader_invite" class="btn btn-warning">
                    🔮 测试塔罗师邀请链接
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📋 测试步骤：</h3>
            <ol>
                <li>点击"创建测试塔罗师并生成邀请链接"</li>
                <li>复制生成的邀请Token</li>
                <li>在测试区域输入Token并测试链接</li>
                <li>点击生成的测试链接验证注册页面</li>
                <li>尝试注册新用户或塔罗师</li>
            </ol>
            
            <h3>🎯 预期结果：</h3>
            <ul>
                <li>邀请链接应该能正常打开注册页面</li>
                <li>用户注册链接应该打开用户注册页面</li>
                <li>塔罗师注册链接应该打开塔罗师注册页面</li>
                <li>注册成功后应该建立邀请关系</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin/upgrade_invitation_system.php" class="btn">升级邀请系统</a>
            <a href="reader/invitation.php" class="btn">塔罗师邀请管理</a>
        </div>
    </div>
</body>
</html>
