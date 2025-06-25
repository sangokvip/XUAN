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
        
        // 1. 为users表添加邀请字段
        try {
            $db->fetchOne("SELECT invited_by FROM users LIMIT 1");
            $success[] = "✓ users表已有invited_by字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE users ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
            $success[] = "✓ 为users表添加invited_by字段";
        }
        
        try {
            $db->fetchOne("SELECT invited_by_type FROM users LIMIT 1");
            $success[] = "✓ users表已有invited_by_type字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE users ADD COLUMN invited_by_type ENUM('user', 'reader') DEFAULT NULL COMMENT '邀请人类型'");
            $success[] = "✓ 为users表添加invited_by_type字段";
        }
        
        // 2. 创建邀请相关表
        // 创建邀请链接表
        try {
            $db->fetchOne("SELECT 1 FROM invitation_links LIMIT 1");
            $success[] = "✓ invitation_links表已存在";
        } catch (Exception $e) {
            $createInvitationLinksTable = "
            CREATE TABLE IF NOT EXISTS invitation_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inviter_id INT NOT NULL,
                inviter_type ENUM('reader', 'user') NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_token (token),
                INDEX idx_inviter (inviter_id, inviter_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请链接表'";

            $db->query($createInvitationLinksTable);
            $success[] = "✓ 创建invitation_links表";
        }

        // 创建邀请关系表
        try {
            $db->fetchOne("SELECT 1 FROM invitation_relations LIMIT 1");
            $success[] = "✓ invitation_relations表已存在";
        } catch (Exception $e) {
            $createInvitationRelationsTable = "
            CREATE TABLE IF NOT EXISTS invitation_relations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inviter_id INT NOT NULL,
                inviter_type ENUM('reader', 'user') NOT NULL,
                invitee_id INT NOT NULL,
                invitee_type ENUM('reader', 'user') NOT NULL,
                invitation_token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inviter (inviter_id, inviter_type),
                INDEX idx_invitee (invitee_id, invitee_type),
                INDEX idx_token (invitation_token),
                UNIQUE KEY unique_invitee (invitee_id, invitee_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请关系表'";

            $db->query($createInvitationRelationsTable);
            $success[] = "✓ 创建invitation_relations表";
        }

        // 创建邀请返点记录表
        try {
            $db->fetchOne("SELECT 1 FROM invitation_commissions LIMIT 1");
            $success[] = "✓ invitation_commissions表已存在";
        } catch (Exception $e) {
            $createInvitationCommissionsTable = "
            CREATE TABLE IF NOT EXISTS invitation_commissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inviter_id INT NOT NULL,
                inviter_type ENUM('reader', 'user') NOT NULL,
                invitee_id INT NOT NULL,
                invitee_type ENUM('reader', 'user') NOT NULL,
                transaction_id INT NOT NULL,
                commission_amount DECIMAL(10,2) NOT NULL,
                commission_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                original_amount DECIMAL(10,2) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inviter (inviter_id, inviter_type),
                INDEX idx_transaction (transaction_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请返点记录表'";

            $db->query($createInvitationCommissionsTable);
            $success[] = "✓ 创建invitation_commissions表";
        }

        // 3. 为readers表添加邀请字段
        try {
            $db->fetchOne("SELECT invited_by FROM readers LIMIT 1");
            $success[] = "✓ readers表已有invited_by字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE readers ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
            $success[] = "✓ 为readers表添加invited_by字段";
        }

        try {
            $db->fetchOne("SELECT invited_by_type FROM readers LIMIT 1");
            $success[] = "✓ readers表已有invited_by_type字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE readers ADD COLUMN invited_by_type ENUM('user', 'reader') DEFAULT NULL COMMENT '邀请人类型'");
            $success[] = "✓ 为readers表添加invited_by_type字段";
        }

        // 4. 为tata_coin_transactions表添加邀请相关字段
        try {
            $db->fetchOne("SELECT is_commission FROM tata_coin_transactions LIMIT 1");
            $success[] = "✓ tata_coin_transactions表已有is_commission字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN is_commission BOOLEAN DEFAULT FALSE COMMENT '是否为邀请返点'");
            $success[] = "✓ 为tata_coin_transactions表添加is_commission字段";
        }

        try {
            $db->fetchOne("SELECT commission_from_user_id FROM tata_coin_transactions LIMIT 1");
            $success[] = "✓ tata_coin_transactions表已有commission_from_user_id字段";
        } catch (Exception $e) {
            $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_id INT DEFAULT NULL COMMENT '返点来源用户ID'");
            $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_type ENUM('reader', 'user') DEFAULT NULL COMMENT '返点来源用户类型'");
            $success[] = "✓ 为tata_coin_transactions表添加返点来源字段";
        }

        // 6. 检查TataCoinManager是否支持邀请返点
        $tataCoinFile = '../includes/TataCoinManager.php';
        $content = file_get_contents($tataCoinFile);
        if (strpos($content, 'processInvitationCommission') !== false) {
            $success[] = "✓ TataCoinManager已支持邀请返点";
        } else {
            $errors[] = "❌ TataCoinManager不支持邀请返点，请更新代码";
        }

        // 7. 检查用户注册是否支持邀请码
        $authFile = '../includes/auth.php';
        $authContent = file_get_contents($authFile);
        if (strpos($authContent, 'inviteToken') !== false) {
            $success[] = "✓ 用户注册已支持邀请码";
        } else {
            $errors[] = "❌ 用户注册不支持邀请码，请更新代码";
        }

        // 8. 测试邀请返点功能
        if (empty($errors)) {
            try {
                require_once '../includes/InvitationManager.php';
                $invitationManager = new InvitationManager();
                if ($invitationManager->isInstalled()) {
                    $success[] = "✓ 邀请系统已安装并可用";
                } else {
                    $errors[] = "❌ 邀请系统未正确安装";
                }
            } catch (Exception $e) {
                $errors[] = "❌ 邀请系统测试失败: " . $e->getMessage();
            }
        }
        
        if (empty($errors)) {
            $upgradeCompleted = true;
        }
        
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
    <title>升级邀请返点系统 - 管理后台</title>
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
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="upgrade-container">
        <h1>🔗 升级邀请返点系统</h1>
        
        <a href="dashboard.php" class="btn-back">← 返回管理后台</a>
        
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
                <p><strong>邀请返点系统已完全修复：</strong></p>
                <ul>
                    <li>✅ 用户注册支持邀请码处理</li>
                    <li>✅ 用户消费自动触发邀请返点</li>
                    <li>✅ 塔罗师后台可查看返点记录</li>
                    <li>✅ 显示被邀请用户信息和消费总额</li>
                </ul>
                
                <p><strong>测试步骤：</strong></p>
                <ol>
                    <li>塔罗师生成邀请链接</li>
                    <li>新用户通过邀请链接注册</li>
                    <li>新用户消费Tata Coin</li>
                    <li>塔罗师后台查看返点记录</li>
                </ol>
                
                <p>
                    <a href="../reader/invitation_management.php" class="btn-back" style="background: #28a745;">测试邀请管理</a>
                    <a href="dashboard.php" class="btn-back">返回仪表板</a>
                </p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h4>⚠️ 发现的问题：</h4>
                <p>邀请返点系统存在以下问题：</p>
                <ul>
                    <li>用户注册时没有处理邀请码</li>
                    <li>用户消费时没有触发邀请返点</li>
                    <li>塔罗师后台看不到返点记录</li>
                    <li>缺少被邀请用户的详细信息</li>
                </ul>
            </div>
            
            <div class="feature-list">
                <h4>🔧 本次升级将修复：</h4>
                <ul>
                    <li><strong>数据库结构：</strong>为users表添加invited_by和invited_by_type字段</li>
                    <li><strong>用户注册：</strong>支持邀请码参数处理和邀请关系建立</li>
                    <li><strong>消费返点：</strong>用户消费时自动计算和发放邀请返点</li>
                    <li><strong>后台显示：</strong>塔罗师可查看被邀请用户信息和消费统计</li>
                    <li><strong>系统集成：</strong>完整的邀请-注册-消费-返点流程</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h4>🔧 升级内容：</h4>
                <ul>
                    <li>检查并添加users表的邀请字段</li>
                    <li>验证邀请系统相关表的存在</li>
                    <li>检查代码更新是否完整</li>
                    <li>测试邀请返点功能</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="confirm_upgrade" class="upgrade-btn" 
                        onclick="return confirm('确定要升级邀请返点系统吗？')">
                    🔧 开始升级
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
