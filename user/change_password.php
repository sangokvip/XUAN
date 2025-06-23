<?php
session_start();
require_once '../config/config.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证当前密码
    if (empty($currentPassword)) {
        $errors[] = '请输入当前密码';
    } elseif (!verifyPassword($currentPassword, $user['password_hash'])) {
        $errors[] = '当前密码不正确';
    }
    
    // 验证新密码
    if (empty($newPassword)) {
        $errors[] = '请输入新密码';
    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        $errors[] = '新密码至少需要' . PASSWORD_MIN_LENGTH . '个字符';
    }
    
    // 验证确认密码
    if ($newPassword !== $confirmPassword) {
        $errors[] = '两次输入的新密码不一致';
    }
    
    // 检查新密码是否与当前密码相同
    if (!empty($newPassword) && verifyPassword($newPassword, $user['password_hash'])) {
        $errors[] = '新密码不能与当前密码相同';
    }
    
    if (empty($errors)) {
        try {
            $newPasswordHash = hashPassword($newPassword);
            $db->update('users', [
                'password_hash' => $newPasswordHash
            ], 'id = ?', [$userId]);
            
            $success = '密码修改成功！';
            
        } catch (Exception $e) {
            $errors[] = '密码修改失败，请稍后重试';
        }
    }
}

$pageTitle = '修改密码';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .password-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .password-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .password-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        .password-strength {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: none;
        }
        
        .strength-weak {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .strength-medium {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .strength-strong {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 16px 40px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .btn-submit:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: white;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .alert li {
            margin: 5px 0;
        }
        
        .security-tips {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .security-tips h4 {
            margin: 0 0 15px 0;
            color: #0c4a6e;
            font-size: 1.1rem;
        }
        
        .security-tips ul {
            margin: 0;
            padding-left: 20px;
            color: #0c4a6e;
        }
        
        .security-tips li {
            margin: 8px 0;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .password-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .password-form {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="password-container">
        <div class="password-header">
            <h1>🔐 修改密码</h1>
            <p>为了账户安全，请定期更换密码</p>
        </div>
        
        <div class="password-form">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
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
            
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">当前密码 *</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <small>请输入您当前使用的密码</small>
                </div>
                
                <div class="form-group">
                    <label for="new_password">新密码 *</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>密码至少需要<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                    <div id="password-strength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认新密码 *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <small>请再次输入新密码</small>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">🔒 修改密码</button>
            </form>
            
            <a href="index.php" class="btn-secondary">← 返回用户中心</a>
            
            <div class="security-tips">
                <h4>🛡️ 密码安全建议</h4>
                <ul>
                    <li>使用至少8个字符的密码</li>
                    <li>包含大小写字母、数字和特殊字符</li>
                    <li>不要使用生日、姓名等个人信息</li>
                    <li>定期更换密码，建议3-6个月更换一次</li>
                    <li>不要在多个网站使用相同密码</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('password-strength');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('passwordForm');
            
            // 密码强度检测
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                
                if (password.length > 0) {
                    strengthDiv.style.display = 'block';
                    strengthDiv.className = 'password-strength ' + strength.class;
                    strengthDiv.textContent = strength.text;
                } else {
                    strengthDiv.style.display = 'none';
                }
                
                validateForm();
            });
            
            // 确认密码验证
            confirmPasswordInput.addEventListener('input', validateForm);
            
            function checkPasswordStrength(password) {
                if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                    return { class: 'strength-weak', text: '密码太短' };
                }
                
                let score = 0;
                if (password.length >= 8) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                if (score < 3) {
                    return { class: 'strength-weak', text: '密码强度：弱' };
                } else if (score < 4) {
                    return { class: 'strength-medium', text: '密码强度：中等' };
                } else {
                    return { class: 'strength-strong', text: '密码强度：强' };
                }
            }
            
            function validateForm() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (newPassword.length >= <?php echo PASSWORD_MIN_LENGTH; ?> && 
                    newPassword === confirmPassword) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // 表单提交验证
            form.addEventListener('submit', function(e) {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('两次输入的新密码不一致');
                    return false;
                }
                
                if (newPassword.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                    e.preventDefault();
                    alert('新密码至少需要<?php echo PASSWORD_MIN_LENGTH; ?>个字符');
                    return false;
                }
                
                return true;
            });
        });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
