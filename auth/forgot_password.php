<?php
session_start();
require_once '../config/config.php';
require_once '../includes/EmailHelper.php';

$success = '';
$error = '';
$userType = $_GET['type'] ?? 'user'; // user 或 reader

// 验证用户类型
if (!in_array($userType, ['user', 'reader'])) {
    $userType = 'user';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = '请输入邮箱地址';
    } elseif (!EmailHelper::validateEmail($email)) {
        $error = '请输入有效的邮箱地址';
    } else {
        // 检查邮件服务是否配置
        $emailConfig = getEmailConfigStatus();
        if (!$emailConfig['configured']) {
            $error = $emailConfig['message'];
        } else {
            $db = Database::getInstance();
            
            // 根据用户类型查找用户
            if ($userType === 'user') {
                $user = $db->fetchOne("SELECT id, username, email FROM users WHERE email = ?", [$email]);
                $tableName = 'users';
                $nameField = 'username';
            } else {
                $user = $db->fetchOne("SELECT id, full_name, email FROM readers WHERE email = ?", [$email]);
                $tableName = 'readers';
                $nameField = 'full_name';
            }
            
            if (!$user) {
                $error = '该邮箱地址未注册';
            } else {
                // 生成重置令牌
                $resetToken = EmailHelper::generateResetToken();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // 删除该用户之前的重置记录
                $db->execute("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 
                           [$user['id'], $userType]);
                
                // 插入新的重置记录
                $resetData = [
                    'user_id' => $user['id'],
                    'user_type' => $userType,
                    'email' => $email,
                    'token' => $resetToken,
                    'expires_at' => $expiresAt,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $result = $db->insert('password_resets', $resetData);
                
                if ($result) {
                    // 发送重置邮件
                    $emailResult = EmailHelper::sendPasswordResetEmail(
                        $email, 
                        $user[$nameField], 
                        $resetToken, 
                        $userType
                    );
                    
                    if ($emailResult['success']) {
                        $success = '密码重置邮件已发送到您的邮箱，请查收并按照邮件中的指引重置密码。';
                    } else {
                        $error = '邮件发送失败：' . $emailResult['message'];
                    }
                } else {
                    $error = '系统错误，请稍后重试';
                }
            }
        }
    }
}

$pageTitle = $userType === 'reader' ? '占卜师忘记密码' : '用户忘记密码';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Microsoft YaHei', sans-serif;
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .forgot-header {
            margin-bottom: 30px;
        }

        .forgot-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .forgot-header p {
            color: #666;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-bottom: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .auth-links {
            text-align: center;
            margin-top: 20px;
        }

        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .user-type-indicator {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: inline-block;
        }

        .email-config-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: left;
        }

        .email-config-warning h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }

        .email-config-warning ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="user-type-indicator">
                <?php echo $userType === 'reader' ? '🔮 占卜师' : '👤 用户'; ?>
            </div>
            <h1><?php echo $pageTitle; ?></h1>
            <p>请输入您的邮箱地址，我们将发送密码重置链接</p>
        </div>

        <?php if (!isEmailConfigured()): ?>
            <div class="email-config-warning">
                <h4>⚠️ 邮件服务未配置</h4>
                <p>管理员需要先配置邮件服务才能使用忘记密码功能。</p>
                <p><strong>配置步骤：</strong></p>
                <ul>
                    <li>编辑 <code>config/email_config.php</code> 文件</li>
                    <li>填写SMTP服务器信息</li>
                    <li>测试邮件发送功能</li>
                </ul>
            </div>
        <?php endif; ?>

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
                <label for="email">邮箱地址</label>
                <input type="email" id="email" name="email" required 
                       placeholder="请输入您的邮箱地址"
                       value="<?php echo h($_POST['email'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn-submit">发送重置邮件</button>
        </form>

        <div class="auth-links">
            <p>
                <a href="<?php echo $userType === 'reader' ? 'reader_login.php' : 'login.php'; ?>">
                    ← 返回登录
                </a>
            </p>
            <p>
                <a href="../index.php">返回首页</a>
            </p>
        </div>
    </div>
</body>
</html>
