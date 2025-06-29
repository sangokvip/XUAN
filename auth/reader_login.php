<?php
session_start();
require_once '../config/config.php';

$error = '';
$success = '';

// 如果已登录，重定向到占卜师后台
if (isReaderLoggedIn()) {
    redirect('../reader/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请填写所有字段';
    } else {
        $result = loginReader($username, $password);
        if ($result['success']) {
            $redirectTo = $_GET['redirect'] ?? '../reader/dashboard.php';
            redirect($redirectTo);
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>占卜师登录 - <?php echo getSiteName(); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
                radial-gradient(circle at 40% 80%, rgba(245, 158, 11, 0.2) 1px, transparent 1px),
                radial-gradient(circle at 60% 20%, rgba(217, 119, 6, 0.15) 1px, transparent 1px);
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
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(30px) saturate(1.5);
            -webkit-backdrop-filter: blur(30px) saturate(1.5);
            border-radius: 24px;
            padding: 48px 40px;
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
                rgba(245, 158, 11, 0.3) 20%,
                rgba(245, 158, 11, 1) 50%,
                rgba(217, 119, 6, 1) 50%,
                rgba(217, 119, 6, 0.3) 80%,
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
                rgba(245, 158, 11, 0.1),
                rgba(217, 119, 6, 0.1),
                rgba(245, 158, 11, 0.1));
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
            width: 80px;
            height: 80px;
            margin: 0 auto 32px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 8px 32px rgba(245, 158, 11, 0.4);
        }

        .star-icon::before {
            content: '✡';
            font-size: 36px;
            color: white;
            text-shadow:
                0 0 20px rgba(255, 255, 255, 0.8),
                0 0 40px rgba(255, 255, 255, 0.4),
                0 0 60px rgba(245, 158, 11, 0.3);
            animation: starGlow 3s ease-in-out infinite alternate;
        }

        @keyframes starGlow {
            0% {
                transform: scale(1) rotate(0deg);
                text-shadow:
                    0 0 20px rgba(255, 255, 255, 0.8),
                    0 0 40px rgba(255, 255, 255, 0.4),
                    0 0 60px rgba(245, 158, 11, 0.3);
            }
            100% {
                transform: scale(1.1) rotate(360deg);
                text-shadow:
                    0 0 30px rgba(255, 255, 255, 1),
                    0 0 60px rgba(255, 255, 255, 0.6),
                    0 0 90px rgba(245, 158, 11, 0.5);
            }
        }

        .auth-title {
            color: white;
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 32px;
            letter-spacing: -0.5px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-input:focus {
            outline: none;
            border-color: #f59e0b;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
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
            box-shadow: 0 12px 24px rgba(245, 158, 11, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .auth-links {
            margin-top: 32px;
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
            color: #f59e0b;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
        }

        /* 登录类型选择器 */
        .login-type-selector {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 280px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .login-type-option {
            flex: 1;
            min-width: 120px;
            padding: 12px 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-type-option.active {
            background: linear-gradient(135deg, #d4af37, #f1c40f);
            color: #000;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4);
            font-weight: 600;
        }

        .login-type-option:not(.active) {
            color: rgba(255, 255, 255, 0.6);
        }

        .login-type-option:not(.active):hover {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.8);
        }

        .login-type-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .login-type-option:hover::before {
            left: 100%;
        }

        .login-type-icon {
            margin-right: 8px;
            font-size: 16px;
        }

        /* 响应式设计 */
        @media (max-width: 480px) {
            .auth-container {
                padding: 16px;
            }

            .auth-card {
                padding: 32px 24px;
            }

            .auth-title {
                font-size: 24px;
            }

            .login-type-option {
                padding: 10px 16px;
                font-size: 14px;
            }

            .login-type-icon {
                margin-right: 6px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="star-icon"></div>

            <!-- 登录类型选择器 -->
            <div class="login-type-selector">
                <a href="login.php" class="login-type-option">
                    <span class="login-type-icon">👤</span>
                    用户登录
                </a>
                <div class="login-type-option active">
                    <span class="login-type-icon">🔮</span>
                    占卜师登录
                </div>
            </div>

            <h1 class="auth-title">占卜师登录</h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo h($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo h($success); ?></div>
            <?php endif; ?>

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
                    <input type="password"
                           class="form-input"
                           id="password"
                           name="password"
                           placeholder="密码"
                           required>
                </div>

                <button type="submit" class="submit-btn">
                    Let's start
                </button>
            </form>

            <div class="auth-links">
                <a href="forgot_password.php?type=reader" class="auth-link">忘记密码？</a><br>
                <a href="../index.php" class="auth-link">返回首页</a>
            </div>
        </div>
    </div>
</body>
</html>
