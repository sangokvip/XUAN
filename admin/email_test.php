<?php
session_start();
require_once '../config/config.php';
require_once '../includes/EmailHelper.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$success = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = trim($_POST['test_email'] ?? '');
    
    if (empty($testEmail)) {
        $error = '请输入测试邮箱地址';
    } elseif (!EmailHelper::validateEmail($testEmail)) {
        $error = '请输入有效的邮箱地址';
    } else {
        // 发送测试邮件
        $testResult = EmailHelper::sendTestEmail($testEmail);
        
        if ($testResult['success']) {
            $success = '测试邮件发送成功！请检查邮箱 ' . $testEmail;
        } else {
            $error = '测试邮件发送失败：' . $testResult['message'];
        }
    }
}

// 获取邮件配置状态
$emailConfig = getEmailConfigStatus();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮件服务测试 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>邮件服务测试</h1>
                <p>测试邮件发送功能是否正常工作</p>
            </div>
            
            <!-- 配置状态 -->
            <div class="card">
                <div class="card-header">
                    <h2>配置状态</h2>
                </div>
                <div class="card-body">
                    <?php if ($emailConfig['configured']): ?>
                        <div class="status-success">
                            <div class="status-icon">✅</div>
                            <div class="status-content">
                                <h3>邮件服务已配置</h3>
                                <p>邮件服务器配置完成，可以进行测试</p>
                            </div>
                        </div>
                        
                        <div class="config-details">
                            <h4>当前配置：</h4>
                            <ul>
                                <li><strong>SMTP服务器：</strong><?php echo h(SMTP_HOST); ?></li>
                                <li><strong>端口：</strong><?php echo h(SMTP_PORT); ?></li>
                                <li><strong>加密方式：</strong><?php echo h(SMTP_SECURE); ?></li>
                                <li><strong>发件人：</strong><?php echo h(FROM_EMAIL); ?></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="status-error">
                            <div class="status-icon">❌</div>
                            <div class="status-content">
                                <h3>邮件服务未配置</h3>
                                <p><?php echo h($emailConfig['message']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 配置指导 -->
            <?php if (!$emailConfig['configured']): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>配置指导</h2>
                    </div>
                    <div class="card-body">
                        <div class="config-guide">
                            <h3>📋 配置步骤：</h3>
                            <ol>
                                <li>
                                    <strong>编辑配置文件</strong><br>
                                    打开 <code>config/email_config.php</code> 文件
                                </li>
                                <li>
                                    <strong>填写SMTP信息</strong><br>
                                    根据您的邮件服务商填写相应的SMTP配置
                                </li>
                                <li>
                                    <strong>常用配置参考</strong><br>
                                    <div class="smtp-examples">
                                        <div class="smtp-example">
                                            <h4>QQ邮箱</h4>
                                            <ul>
                                                <li>SMTP_HOST: smtp.qq.com</li>
                                                <li>SMTP_PORT: 587</li>
                                                <li>SMTP_SECURE: tls</li>
                                            </ul>
                                        </div>
                                        <div class="smtp-example">
                                            <h4>163邮箱</h4>
                                            <ul>
                                                <li>SMTP_HOST: smtp.163.com</li>
                                                <li>SMTP_PORT: 25</li>
                                                <li>SMTP_SECURE: 无</li>
                                            </ul>
                                        </div>
                                        <div class="smtp-example">
                                            <h4>Gmail</h4>
                                            <ul>
                                                <li>SMTP_HOST: smtp.gmail.com</li>
                                                <li>SMTP_PORT: 587</li>
                                                <li>SMTP_SECURE: tls</li>
                                            </ul>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <strong>测试配置</strong><br>
                                    配置完成后回到此页面进行测试
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 邮件测试 -->
            <?php if ($emailConfig['configured']): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>发送测试邮件</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo h($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo h($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label for="test_email">测试邮箱地址</label>
                                <input type="email" id="test_email" name="test_email" required
                                       placeholder="请输入要接收测试邮件的邮箱地址"
                                       value="<?php echo h($_POST['test_email'] ?? ''); ?>">
                                <small>我们将向此邮箱发送一封测试邮件</small>
                            </div>

                            <button type="submit" class="btn btn-primary">发送测试邮件</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 功能说明 -->
            <div class="card">
                <div class="card-header">
                    <h2>功能说明</h2>
                </div>
                <div class="card-body">
                    <div class="feature-info">
                        <h3>📧 邮件功能用途：</h3>
                        <ul>
                            <li><strong>忘记密码</strong> - 用户和占卜师可以通过邮件重置密码</li>
                            <li><strong>账户通知</strong> - 重要账户变更通知</li>
                            <li><strong>系统消息</strong> - 平台重要消息推送</li>
                        </ul>

                        <h3>🔧 技术说明：</h3>
                        <ul>
                            <li>使用PHP内置mail()函数发送邮件</li>
                            <li>支持HTML格式邮件内容</li>
                            <li>自动处理邮件头部和编码</li>
                            <li>支持多种SMTP服务器配置</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .status-success, .status-error {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .status-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        .status-icon {
            font-size: 2rem;
        }

        .status-content h3 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .status-content p {
            margin: 0;
            color: #666;
        }

        .config-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .config-details h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .config-details ul {
            margin: 0;
            padding-left: 20px;
        }

        .config-guide ol {
            padding-left: 20px;
        }

        .config-guide li {
            margin-bottom: 15px;
        }

        .smtp-examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .smtp-example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .smtp-example h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }

        .smtp-example ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.9rem;
        }

        .feature-info h3 {
            color: #333;
            margin: 20px 0 10px 0;
        }

        .feature-info ul {
            margin: 0 0 20px 20px;
        }

        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
    </style>
</body>
</html>
