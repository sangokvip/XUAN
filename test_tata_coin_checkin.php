<?php
session_start();
require_once 'config/config.php';
require_once 'includes/TataCoinManager.php';

// 模拟用户登录状态
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // 假设存在ID为1的用户
    $_SESSION['user_type'] = 'user';
}

if (!isset($_SESSION['reader_id'])) {
    $_SESSION['reader_id'] = 1; // 假设存在ID为1的占卜师
}

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Tata Coin签到测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; border: none; cursor: pointer; }
        .btn:hover { background: #005a8b; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .balance-card { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #007cba; }
        .balance-amount { font-size: 2rem; font-weight: bold; color: #007cba; }
        .balance-label { color: #666; margin-top: 5px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .user-section { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .user-section h3 { margin-top: 0; color: #007cba; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🪙 Tata Coin签到功能测试</h1>";

try {
    $db = Database::getInstance();
    $tataCoinManager = new TataCoinManager();
    
    // 获取用户和占卜师的当前余额
    $userId = $_SESSION['user_id'];
    $readerId = $_SESSION['reader_id'];
    
    $userBalance = $tataCoinManager->getBalance($userId, 'user');
    $readerBalance = $tataCoinManager->getBalance($readerId, 'reader');
    
    echo "<div class='info'>
        <strong>当前状态：</strong><br>
        用户ID: $userId<br>
        占卜师ID: $readerId<br>
        测试时间: " . date('Y-m-d H:i:s') . "
    </div>";
    
    echo "<div class='user-section'>
        <h3>👤 普通用户 (ID: $userId)</h3>
        <div class='balance-card'>
            <div class='balance-amount'>$userBalance</div>
            <div class='balance-label'>当前Tata Coin余额</div>
        </div>
        <button onclick='testUserCheckin()' class='btn'>测试用户签到</button>
        <button onclick='checkUserBalance()' class='btn'>刷新用户余额</button>
    </div>";
    
    echo "<div class='user-section'>
        <h3>🔮 占卜师 (ID: $readerId)</h3>
        <div class='balance-card'>
            <div class='balance-amount'>$readerBalance</div>
            <div class='balance-label'>当前Tata Coin余额</div>
        </div>
        <button onclick='testReaderCheckin()' class='btn'>测试占卜师签到</button>
        <button onclick='checkReaderBalance()' class='btn'>刷新占卜师余额</button>
    </div>";
    
    echo "<div id='results'></div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 初始化失败：" . $e->getMessage() . "</div>";
}

echo "
<script>
function showResult(title, content, type) {
    const results = document.getElementById('results');
    const div = document.createElement('div');
    div.className = 'result ' + (type || 'info');
    div.innerHTML = '<h3>' + title + '</h3><pre>' + content + '</pre>';
    results.appendChild(div);
}

function clearResults() {
    document.getElementById('results').innerHTML = '';
}

async function testUserCheckin() {
    showResult('用户签到测试', '正在测试用户签到...', 'info');
    
    try {
        const response = await fetch('api/checkin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Type': 'user'
            }
        });
        
        const responseText = await response.text();
        
        try {
            const result = JSON.parse(responseText);
            showResult('用户签到结果', JSON.stringify(result, null, 2), result.success ? 'success' : 'error');
            
            if (result.success) {
                setTimeout(checkUserBalance, 1000);
            }
        } catch (e) {
            showResult('用户签到 - JSON解析失败', responseText, 'error');
        }
    } catch (error) {
        showResult('用户签到失败', error.message, 'error');
    }
}

async function testReaderCheckin() {
    showResult('占卜师签到测试', '正在测试占卜师签到...', 'info');
    
    try {
        // 设置占卜师会话
        await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=set_reader_session'
        });
        
        const response = await fetch('api/checkin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const responseText = await response.text();
        
        try {
            const result = JSON.parse(responseText);
            showResult('占卜师签到结果', JSON.stringify(result, null, 2), result.success ? 'success' : 'error');
            
            if (result.success) {
                setTimeout(checkReaderBalance, 1000);
            }
        } catch (e) {
            showResult('占卜师签到 - JSON解析失败', responseText, 'error');
        }
    } catch (error) {
        showResult('占卜师签到失败', error.message, 'error');
    }
}

async function checkUserBalance() {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_user_balance'
        });
        
        const result = await response.json();
        showResult('用户余额查询', '当前余额: ' + result.balance + ' Tata Coin', 'info');
        
        // 更新页面显示
        const userBalanceElement = document.querySelector('.user-section .balance-amount');
        if (userBalanceElement) {
            userBalanceElement.textContent = result.balance;
        }
    } catch (error) {
        showResult('用户余额查询失败', error.message, 'error');
    }
}

async function checkReaderBalance() {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_reader_balance'
        });
        
        const result = await response.json();
        showResult('占卜师余额查询', '当前余额: ' + result.balance + ' Tata Coin', 'info');
        
        // 更新页面显示
        const readerBalanceElements = document.querySelectorAll('.user-section .balance-amount');
        if (readerBalanceElements[1]) {
            readerBalanceElements[1].textContent = result.balance;
        }
    } catch (error) {
        showResult('占卜师余额查询失败', error.message, 'error');
    }
}
</script>";

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $tataCoinManager = new TataCoinManager();
        
        switch ($_POST['action']) {
            case 'get_user_balance':
                $balance = $tataCoinManager->getBalance($_SESSION['user_id'], 'user');
                echo json_encode(['success' => true, 'balance' => $balance]);
                break;
                
            case 'get_reader_balance':
                $balance = $tataCoinManager->getBalance($_SESSION['reader_id'], 'reader');
                echo json_encode(['success' => true, 'balance' => $balance]);
                break;
                
            case 'set_reader_session':
                $_SESSION['user_id'] = $_SESSION['reader_id'];
                $_SESSION['user_type'] = 'reader';
                echo json_encode(['success' => true]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo "
        <hr>
        <p><a href='index.php' class='btn'>返回首页</a></p>
        <p><a href='user/index.php' class='btn'>用户中心</a></p>
        <p><a href='reader/dashboard.php' class='btn'>占卜师后台</a></p>
    </div>
</body>
</html>";
?>
