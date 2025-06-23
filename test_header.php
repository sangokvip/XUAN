<?php
session_start();
require_once 'config/config.php';

// 模拟不同类型的登录状态进行测试
$testMode = $_GET['mode'] ?? 'user';

// 清除现有session
unset($_SESSION['user_id']);
unset($_SESSION['reader_id']);
unset($_SESSION['admin_id']);

switch ($testMode) {
    case 'user':
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = '测试用户';
        break;
    case 'reader':
        $_SESSION['reader_id'] = 1;
        break;
    case 'admin':
        $_SESSION['admin_id'] = 1;
        break;
    case 'guest':
    default:
        // 不设置任何session
        break;
}

$pageTitle = '头部菜单测试';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .test-modes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .test-mode {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .test-mode.active {
            border-color: #667eea;
            background: #f8fafc;
        }
        
        .test-mode a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .test-mode a:hover {
            color: #5a67d8;
        }
        
        .instructions {
            background: #f0fdf4;
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .instructions h3 {
            color: #065f46;
            margin: 0 0 15px 0;
        }
        
        .instructions ul {
            color: #047857;
            margin: 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
        }
        
        .current-status {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .current-status h3 {
            color: #92400e;
            margin: 0 0 10px 0;
        }
        
        .status-info {
            color: #92400e;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="test-container">
        <div class="test-header">
            <h1>🧪 头部菜单测试页面</h1>
            <p>测试用户下拉菜单的功能</p>
        </div>
        
        <div class="current-status">
            <h3>📊 当前状态</h3>
            <div class="status-info">
                <?php if (isLoggedIn()): ?>
                    ✅ 当前以普通用户身份登录
                <?php elseif (isReaderLoggedIn()): ?>
                    ✅ 当前以塔罗师身份登录
                <?php elseif (isAdminLoggedIn()): ?>
                    ✅ 当前以管理员身份登录
                <?php else: ?>
                    ❌ 当前未登录（访客状态）
                <?php endif; ?>
            </div>
        </div>
        
        <div class="instructions">
            <h3>🔧 测试说明</h3>
            <ul>
                <li><strong>鼠标悬停显示</strong>：将鼠标悬停在用户名区域上，下拉菜单应该立即显示</li>
                <li><strong>菜单稳定性</strong>：下拉菜单显示后，鼠标移动到菜单项上时，菜单应该保持显示</li>
                <li><strong>延迟消失</strong>：鼠标离开用户名区域后，菜单应该在3秒后自动消失</li>
                <li><strong>取消消失</strong>：如果在3秒内鼠标重新进入用户名区域，应该取消消失倒计时</li>
                <li><strong>点击用户名</strong>：直接点击用户名文字，应该跳转到对应的后台页面</li>
                <li><strong>点击外部关闭</strong>：点击页面其他地方，下拉菜单应该立即关闭</li>
                <li><strong>ESC键关闭</strong>：按ESC键，下拉菜单应该立即关闭</li>
            </ul>
        </div>
        
        <div class="test-modes">
            <div class="test-mode <?php echo $testMode === 'guest' ? 'active' : ''; ?>">
                <a href="?mode=guest">👤 访客模式</a>
                <p>未登录状态</p>
            </div>
            
            <div class="test-mode <?php echo $testMode === 'user' ? 'active' : ''; ?>">
                <a href="?mode=user">🙋‍♂️ 普通用户</a>
                <p>点击用户名应跳转到用户中心</p>
            </div>
            
            <div class="test-mode <?php echo $testMode === 'reader' ? 'active' : ''; ?>">
                <a href="?mode=reader">🔮 塔罗师</a>
                <p>点击用户名应跳转到塔罗师后台</p>
            </div>
            
            <div class="test-mode <?php echo $testMode === 'admin' ? 'active' : ''; ?>">
                <a href="?mode=admin">👨‍💼 管理员</a>
                <p>点击用户名应跳转到管理后台</p>
            </div>
        </div>
        
        <div style="height: 500px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #6b7280;">
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 20px;">🎯</div>
                <h3>测试区域</h3>
                <p>这里是用来测试点击外部关闭下拉菜单的区域</p>
            </div>
        </div>
    </div>
    
    <script>
        // 添加一些调试信息和倒计时显示
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🧪 头部菜单测试页面已加载');
            console.log('📊 当前测试模式:', '<?php echo $testMode; ?>');

            const userDropdown = document.querySelector('.user-dropdown');
            if (userDropdown) {
                console.log('✅ 找到用户下拉菜单');

                // 创建倒计时显示元素
                const countdownDiv = document.createElement('div');
                countdownDiv.id = 'countdown-display';
                countdownDiv.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 10px 15px;
                    border-radius: 8px;
                    font-family: monospace;
                    font-size: 14px;
                    z-index: 9999;
                    display: none;
                `;
                document.body.appendChild(countdownDiv);

                // 监听鼠标事件
                userDropdown.addEventListener('mouseenter', function() {
                    console.log('🖱️ 鼠标进入用户菜单区域');
                    countdownDiv.style.display = 'none';
                });

                userDropdown.addEventListener('mouseleave', function() {
                    console.log('🖱️ 鼠标离开用户菜单区域，开始3秒倒计时');
                    startCountdown();
                });

                const userName = userDropdown.querySelector('.user-name');
                if (userName) {
                    userName.addEventListener('click', function(e) {
                        console.log('🖱️ 用户名被点击');
                        console.log('🔗 跳转链接:', userDropdown.querySelector('.user-toggle').getAttribute('data-user-center'));
                    });
                }

                function startCountdown() {
                    let seconds = 3;
                    countdownDiv.style.display = 'block';
                    countdownDiv.textContent = `菜单将在 ${seconds} 秒后消失`;

                    const interval = setInterval(() => {
                        seconds--;
                        if (seconds > 0) {
                            countdownDiv.textContent = `菜单将在 ${seconds} 秒后消失`;
                        } else {
                            countdownDiv.textContent = '菜单已消失';
                            setTimeout(() => {
                                countdownDiv.style.display = 'none';
                            }, 1000);
                            clearInterval(interval);
                        }
                    }, 1000);

                    // 如果鼠标重新进入，清除倒计时
                    const clearCountdown = () => {
                        clearInterval(interval);
                        countdownDiv.style.display = 'none';
                        userDropdown.removeEventListener('mouseenter', clearCountdown);
                    };
                    userDropdown.addEventListener('mouseenter', clearCountdown);
                }
            } else {
                console.log('❌ 未找到用户下拉菜单（可能是访客模式）');
            }
        });
    </script>
</body>
</html>
