<?php
session_start();
require_once '../config/config.php';

$success = '';
$error = '';
$token = $_GET['token'] ?? '';
$userType = $_GET['type'] ?? 'user';

// 验证用户类型
if (!in_array($userType, ['user', 'reader'])) {
    $userType = 'user';
}

// 验证令牌
$resetRecord = null;
if (!empty($token)) {
    $db = Database::getInstance();
    $resetRecord = $db->fetchOne(
        "SELECT * FROM password_resets WHERE token = ? AND user_type = ? AND expires_at > NOW()",
        [$token, $userType]
    );
    
    if (!$resetRecord) {
        $error = '重置链接无效或已过期，请重新申请密码重置';
    }
}

if (empty($token)) {
    $error = '缺少重置令牌';
}

// 处理密码重置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRecord) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = '请输入新密码';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = '密码长度至少为' . PASSWORD_MIN_LENGTH . '个字符';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        // 更新密码
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        if ($userType === 'user') {
            $result = $db->update('users', 
                ['password_hash' => $hashedPassword], 
                'id = ?', 
                [$resetRecord['user_id']]
            );
        } else {
            $result = $db->update('readers', 
                ['password_hash' => $hashedPassword], 
                'id = ?', 
                [$resetRecord['user_id']]
            );
        }
        
        if ($result) {
            // 删除重置记录
            $db->execute("DELETE FROM password_resets WHERE token = ?", [$token]);
            
            $success = '密码重置成功！您现在可以使用新密码登录了。';
        } else {
            $error = '密码重置失败，请稍后重试';
        }
    }
}

$pageTitle = $userType === 'reader' ? '占卜师密码重置' : '用户密码重置';
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

        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .reset-header {
            margin-bottom: 30px;
        }

        .reset-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .reset-header p {
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

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
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

        .password-requirements {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            font-size: 0.9rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.8rem;
            color: #1976d2;
        }

        .success-actions {
            margin-top: 20px;
        }

        .success-actions .btn {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            margin: 5px;
            font-weight: 500;
        }

        .success-actions .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="user-type-indicator">
                <?php echo $userType === 'reader' ? '🔮 占卜师' : '👤 用户'; ?>
            </div>
            <h1><?php echo $pageTitle; ?></h1>
            <p>请设置您的新密码</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
            <div class="success-actions">
                <a href="<?php echo $userType === 'reader' ? 'reader_login.php' : 'login.php'; ?>" class="btn">
                    立即登录
                </a>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error">
                <?php echo h($error); ?>
            </div>
            <?php if (!$resetRecord): ?>
                <div class="auth-links">
                    <p>
                        <a href="forgot_password.php?type=<?php echo $userType; ?>">
                            重新申请密码重置
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($resetRecord && !$success): ?>
            <div class="password-requirements">
                <h4>密码要求：</h4>
                <ul>
                    <li>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</li>
                    <li>建议包含字母、数字和特殊字符</li>
                    <li>避免使用过于简单的密码</li>
                </ul>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="password">新密码</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="请输入新密码"
                           minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                </div>

                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="请再次输入新密码">
                </div>

                <button type="submit" class="btn-submit">重置密码</button>
            </form>
        <?php endif; ?>

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

    <script>
        // 密码确认验证
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value && confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('密码不一致');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }
            
            if (password && confirmPassword) {
                password.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>
