<?php
session_start();
require_once '../config/config.php';

$errors = [];
$success = '';
$inviteToken = $_GET['invite'] ?? '';

// 如果已登录，重定向到首页
if (isLoggedIn()) {
    redirect('../index.php');
}

// 验证邀请码（如果有）
$invitationValid = false;
if (!empty($inviteToken)) {
    require_once '../includes/InvitationManager.php';
    $invitationManager = new InvitationManager();
    $invitation = $invitationManager->getInvitationByToken($inviteToken);
    if ($invitation) {
        $invitationValid = true;
    } else {
        $errors[] = '邀请链接无效或已过期';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'gender' => $_POST['gender'] ?? ''
    ];
    
    $result = registerUser($data, $inviteToken);
    if ($result['success']) {
        $success = '注册成功！请登录您的账户。';
    } else {
        $errors = $result['errors'];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo getSiteName(); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* 增强的动态背景点阵效果 */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.25) 2px, transparent 2px),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.15) 1.5px, transparent 1.5px),
                radial-gradient(circle at 40% 80%, rgba(147, 51, 234, 0.2) 1px, transparent 1px),
                radial-gradient(circle at 60% 20%, rgba(124, 58, 237, 0.15) 1px, transparent 1px);
            background-size: 60px 60px, 40px 40px, 80px 80px, 100px 100px;
            animation: float 25s ease-in-out infinite, drift 30s linear infinite;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 10% 50%, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                radial-gradient(circle at 90% 50%, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
            background-size: 120px 120px, 90px 90px;
            animation: float 20s ease-in-out infinite reverse, pulse 15s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(2deg); }
        }

        @keyframes drift {
            0% { transform: translateX(0px); }
            50% { transform: translateX(20px); }
            100% { transform: translateX(0px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            padding: 20px;
            max-height: 95vh;
            overflow-y: auto;
        }

        .auth-card {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(30px) saturate(1.5);
            -webkit-backdrop-filter: blur(30px) saturate(1.5);
            border-radius: 24px;
            padding: 40px;
            box-shadow:
                0 32px 64px rgba(0, 0, 0, 0.5),
                0 16px 32px rgba(0, 0, 0, 0.3),
                0 8px 16px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2),
                inset 0 -1px 0 rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(147, 51, 234, 0.3) 20%,
                rgba(147, 51, 234, 1) 50%,
                rgba(124, 58, 237, 1) 50%,
                rgba(124, 58, 237, 0.3) 80%,
                transparent 100%);
            animation: shimmer 3s ease-in-out infinite;
        }

        .auth-card::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg,
                rgba(147, 51, 234, 0.1),
                rgba(124, 58, 237, 0.1),
                rgba(147, 51, 234, 0.1));
            border-radius: 26px;
            z-index: -1;
            animation: glow 4s ease-in-out infinite alternate;
        }

        @keyframes shimmer {
            0%, 100% { opacity: 0.6; transform: translateX(-100%); }
            50% { opacity: 1; transform: translateX(100%); }
        }

        @keyframes glow {
            0% { opacity: 0.3; }
            100% { opacity: 0.8; }
        }

        .star-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 8px 32px rgba(147, 51, 234, 0.4);
        }

        .star-icon::before {
            content: '✡';
            font-size: 30px;
            color: white;
            text-shadow:
                0 0 20px rgba(255, 255, 255, 0.8),
                0 0 40px rgba(255, 255, 255, 0.4),
                0 0 60px rgba(147, 51, 234, 0.3);
            animation: starGlow 3s ease-in-out infinite alternate;
        }

        @keyframes starGlow {
            0% {
                transform: scale(1) rotate(0deg);
                text-shadow:
                    0 0 20px rgba(255, 255, 255, 0.8),
                    0 0 40px rgba(255, 255, 255, 0.4),
                    0 0 60px rgba(147, 51, 234, 0.3);
            }
            100% {
                transform: scale(1.1) rotate(360deg);
                text-shadow:
                    0 0 30px rgba(255, 255, 255, 1),
                    0 0 60px rgba(255, 255, 255, 0.6),
                    0 0 90px rgba(147, 51, 234, 0.5);
            }
        }

        .auth-title {
            color: white;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: white;
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #9333ea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.2);
        }

        .form-select {
            cursor: pointer;
        }

        .form-select option {
            background: #1a1a1a;
            color: white;
        }

        .form-hint {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            margin-top: 4px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 12px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(147, 51, 234, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .auth-links {
            margin-top: 24px;
            text-align: center;
        }

        .auth-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 8px;
        }

        .auth-link:hover {
            color: #9333ea;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .error-message ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .error-message li {
            margin-bottom: 4px;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
        }

        .success-message a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
        }

        .success-message a:hover {
            text-decoration: underline;
        }

        /* 桌面端优化 */
        @media (min-width: 768px) {
            body {
                align-items: flex-start;
                padding-top: 5vh;
            }

            .auth-container {
                max-width: 520px;
                max-height: 90vh;
            }

            .auth-card {
                padding: 36px 40px;
            }

            .star-icon {
                width: 65px;
                height: 65px;
                margin-bottom: 20px;
            }

            .star-icon::before {
                font-size: 28px;
            }

            .auth-title {
                font-size: 22px;
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-input, .form-select {
                padding: 12px 16px;
                font-size: 14px;
            }

            .submit-btn {
                padding: 14px;
                font-size: 15px;
                margin-top: 8px;
            }

            .auth-links {
                margin-top: 20px;
            }
        }

        /* 响应式设计 - 移动端 */
        @media (max-width: 767px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .auth-container {
                padding: 16px;
                max-width: 420px;
            }

            .auth-card {
                padding: 32px 24px;
            }

            .auth-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="star-icon"></div>

            <h1 class="auth-title">用户注册</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo h($success); ?>
                    <p><a href="login.php">立即登录</a></p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <input type="text"
                               class="form-input"
                               id="username"
                               name="username"
                               placeholder="用户名"
                               value="<?php echo h($_POST['username'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <input type="email"
                               class="form-input"
                               id="email"
                               name="email"
                               placeholder="邮箱地址"
                               value="<?php echo h($_POST['email'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <input type="text"
                                   class="form-input"
                                   id="full_name"
                                   name="full_name"
                                   placeholder="姓名"
                                   value="<?php echo h($_POST['full_name'] ?? ''); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">请选择性别</option>
                                <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>男</option>
                                <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>女</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="tel"
                               class="form-input"
                               id="phone"
                               name="phone"
                               placeholder="手机号码（可选）"
                               value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <input type="password"
                                   class="form-input"
                                   id="password"
                                   name="password"
                                   placeholder="密码"
                                   required>
                            <div class="form-hint">至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</div>
                        </div>

                        <div class="form-group">
                            <input type="password"
                                   class="form-input"
                                   id="confirm_password"
                                   name="confirm_password"
                                   placeholder="确认密码"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        注册账户
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-links">
                <a href="login.php" class="auth-link">已有账户？立即登录</a>
                <a href="../index.php" class="auth-link">返回首页</a>
            </div>
        </div>
    </div>
</body>
</html>
