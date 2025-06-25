<?php
/**
 * 邀请系统独立安装脚本
 */
require_once 'config/config.php';

$success = [];
$errors = [];
$installCompleted = false;

// 处理安装请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_install'])) {
    try {
        $db = Database::getInstance();
        
        echo "<h2>🔗 邀请系统安装进度</h2>";
        
        // 1. 创建邀请链接表
        try {
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
            $success[] = "✅ 创建invitation_links表";
        } catch (Exception $e) {
            $errors[] = "❌ 创建invitation_links表失败: " . $e->getMessage();
        }
        
        // 2. 创建邀请关系表
        try {
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
            $success[] = "✅ 创建invitation_relations表";
        } catch (Exception $e) {
            $errors[] = "❌ 创建invitation_relations表失败: " . $e->getMessage();
        }
        
        // 3. 创建邀请返点记录表
        try {
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
            $success[] = "✅ 创建invitation_commissions表";
        } catch (Exception $e) {
            $errors[] = "❌ 创建invitation_commissions表失败: " . $e->getMessage();
        }
        
        // 4. 为users表添加邀请字段
        try {
            $db->fetchOne("SELECT invited_by FROM users LIMIT 1");
            $success[] = "✅ users表已有invited_by字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE users ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
                $success[] = "✅ 为users表添加invited_by字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为users表添加invited_by字段失败: " . $e2->getMessage();
            }
        }
        
        try {
            $db->fetchOne("SELECT invited_by_type FROM users LIMIT 1");
            $success[] = "✅ users表已有invited_by_type字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE users ADD COLUMN invited_by_type ENUM('user', 'reader') DEFAULT NULL COMMENT '邀请人类型'");
                $success[] = "✅ 为users表添加invited_by_type字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为users表添加invited_by_type字段失败: " . $e2->getMessage();
            }
        }
        
        // 5. 为readers表添加邀请字段
        try {
            $db->fetchOne("SELECT invited_by FROM readers LIMIT 1");
            $success[] = "✅ readers表已有invited_by字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE readers ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
                $success[] = "✅ 为readers表添加invited_by字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为readers表添加invited_by字段失败: " . $e2->getMessage();
            }
        }
        
        try {
            $db->fetchOne("SELECT invited_by_type FROM readers LIMIT 1");
            $success[] = "✅ readers表已有invited_by_type字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE readers ADD COLUMN invited_by_type ENUM('user', 'reader') DEFAULT NULL COMMENT '邀请人类型'");
                $success[] = "✅ 为readers表添加invited_by_type字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为readers表添加invited_by_type字段失败: " . $e2->getMessage();
            }
        }
        
        // 6. 为tata_coin_transactions表添加邀请相关字段
        try {
            $db->fetchOne("SELECT is_commission FROM tata_coin_transactions LIMIT 1");
            $success[] = "✅ tata_coin_transactions表已有is_commission字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN is_commission BOOLEAN DEFAULT FALSE COMMENT '是否为邀请返点'");
                $success[] = "✅ 为tata_coin_transactions表添加is_commission字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为tata_coin_transactions表添加is_commission字段失败: " . $e2->getMessage();
            }
        }
        
        try {
            $db->fetchOne("SELECT commission_from_user_id FROM tata_coin_transactions LIMIT 1");
            $success[] = "✅ tata_coin_transactions表已有commission_from_user_id字段";
        } catch (Exception $e) {
            try {
                $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_id INT DEFAULT NULL COMMENT '返点来源用户ID'");
                $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_type ENUM('reader', 'user') DEFAULT NULL COMMENT '返点来源用户类型'");
                $success[] = "✅ 为tata_coin_transactions表添加返点来源字段";
            } catch (Exception $e2) {
                $errors[] = "❌ 为tata_coin_transactions表添加返点来源字段失败: " . $e2->getMessage();
            }
        }
        
        // 7. 测试邀请系统
        try {
            require_once 'includes/InvitationManager.php';
            $invitationManager = new InvitationManager();
            if ($invitationManager->isInstalled()) {
                $success[] = "✅ 邀请系统安装成功并可用";
                $installCompleted = true;
            } else {
                $errors[] = "❌ 邀请系统安装后测试失败";
            }
        } catch (Exception $e) {
            $errors[] = "❌ 邀请系统测试失败: " . $e->getMessage();
        }
        
        // 显示结果
        if (!empty($success)) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h4>✅ 安装成功的项目：</h4>";
            foreach ($success as $msg) {
                echo "<p style='margin: 5px 0;'>" . htmlspecialchars($msg) . "</p>";
            }
            echo "</div>";
        }
        
        if (!empty($errors)) {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h4>❌ 安装失败的项目：</h4>";
            foreach ($errors as $error) {
                echo "<p style='margin: 5px 0;'>" . htmlspecialchars($error) . "</p>";
            }
            echo "</div>";
        }
        
        if ($installCompleted) {
            echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h3>🎉 邀请系统安装完成！</h3>";
            echo "<p>现在您可以：</p>";
            echo "<ul>";
            echo "<li>在塔罗师后台生成邀请链接</li>";
            echo "<li>邀请新用户和塔罗师注册</li>";
            echo "<li>自动获得邀请返点</li>";
            echo "<li>查看详细的邀请统计</li>";
            echo "</ul>";
            echo "<p><a href='admin/upgrade_invitation_system.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>完成系统升级</a></p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h4>❌ 安装过程中出现严重错误：</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "<p style='text-align: center; margin-top: 30px;'>";
    echo "<a href='admin/dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>返回管理后台</a>";
    echo "</p>";
    
} else {
    // 显示安装界面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邀请系统安装 - 塔罗师平台</title>
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
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .install-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .install-btn:hover {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 邀请系统安装</h1>
        
        <a href="admin/dashboard.php" class="btn-back">← 返回管理后台</a>
        
        <div class="warning-box">
            <h4>⚠️ 安装说明</h4>
            <p>此脚本将为您的塔罗师平台安装完整的邀请系统，包括：</p>
            <ul>
                <li>邀请链接管理</li>
                <li>邀请关系追踪</li>
                <li>自动返点计算</li>
                <li>详细统计报表</li>
            </ul>
        </div>
        
        <div class="info-box">
            <h4>📋 安装内容</h4>
            <ul>
                <li><strong>数据库表：</strong>创建invitation_links、invitation_relations、invitation_commissions表</li>
                <li><strong>用户表：</strong>添加invited_by和invited_by_type字段</li>
                <li><strong>塔罗师表：</strong>添加invited_by和invited_by_type字段</li>
                <li><strong>交易表：</strong>添加邀请返点相关字段</li>
                <li><strong>功能测试：</strong>验证邀请系统是否正常工作</li>
            </ul>
        </div>
        
        <div class="warning-box">
            <h4>🔧 安装前准备</h4>
            <ul>
                <li>确保数据库连接正常</li>
                <li>确保有足够的数据库权限</li>
                <li>建议先备份数据库</li>
                <li>确保Tata Coin系统已安装</li>
            </ul>
        </div>
        
        <form method="POST">
            <button type="submit" name="confirm_install" class="install-btn" 
                    onclick="return confirm('确定要安装邀请系统吗？建议先备份数据库。')">
                🚀 开始安装邀请系统
            </button>
        </form>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📚 相关文档</h3>
            <ul>
                <li><a href="admin/upgrade_invitation_system.php">升级邀请返点系统</a></li>
                <li><a href="test_invitation_commission.php">测试邀请返点功能</a></li>
                <li><a href="reader/invitation.php">塔罗师邀请管理</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
<?php
}
?>
