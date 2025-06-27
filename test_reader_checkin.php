<?php
session_start();
require_once 'config/config.php';

// 模拟占卜师登录状态
if (!isset($_SESSION['reader_id'])) {
    $_SESSION['reader_id'] = 1; // 假设存在ID为1的占卜师
    $_SESSION['user_type'] = 'reader';
}

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>占卜师签到测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; border: none; cursor: pointer; }
        .btn:hover { background: #005a8b; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .log { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 占卜师签到功能测试</h1>";

echo "<div class='info'>
    <strong>当前会话信息：</strong><br>
    占卜师ID: " . ($_SESSION['reader_id'] ?? 'null') . "<br>
    用户类型: " . ($_SESSION['user_type'] ?? 'null') . "
</div>";

echo "<button id='test-checkin-btn' class='btn'>测试签到API</button>";
echo "<button id='test-status-btn' class='btn'>检查签到状态</button>";

echo "<div id='log-area' class='log'>
    <strong>测试日志：</strong><br>
</div>";

echo "
<script>
function log(message) {
    const logArea = document.getElementById('log-area');
    const time = new Date().toLocaleTimeString();
    logArea.innerHTML += '[' + time + '] ' + message + '<br>';
    logArea.scrollTop = logArea.scrollHeight;
    console.log(message);
}

document.getElementById('test-checkin-btn').addEventListener('click', async function() {
    log('开始测试签到API...');
    
    try {
        const response = await fetch('api/checkin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        log('响应状态: ' + response.status);
        log('响应状态文本: ' + response.statusText);

        const responseText = await response.text();
        log('响应原始内容: ' + responseText);

        try {
            const result = JSON.parse(responseText);
            log('解析后的JSON: ' + JSON.stringify(result, null, 2));

            if (result.success) {
                log('✅ 签到成功！奖励: ' + result.reward + ' Tata币');
            } else {
                log('❌ 签到失败: ' + result.message);
            }
        } catch (parseError) {
            log('❌ JSON解析失败: ' + parseError.message);
        }
        
    } catch (error) {
        log('❌ 请求失败: ' + error.message);
    }
});

document.getElementById('test-status-btn').addEventListener('click', async function() {
    log('检查签到状态...');
    
    try {
        const response = await fetch('api/tata_coin_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_checkin_status'
            })
        });
        
        log('状态检查响应: ' + response.status);

        const responseText = await response.text();
        log('状态检查原始内容: ' + responseText);

        try {
            const result = JSON.parse(responseText);
            log('状态检查结果: ' + JSON.stringify(result, null, 2));
        } catch (parseError) {
            log('❌ 状态检查JSON解析失败: ' + parseError.message);
        }
        
    } catch (error) {
        log('❌ 状态检查失败: ' + error.message);
    }
});

log('页面加载完成，准备测试...');
</script>

        <hr>
        <p><a href='reader/dashboard.php' class='btn'>返回占卜师后台</a></p>
        <p><a href='index.php' class='btn'>返回首页</a></p>
    </div>
</body>
</html>";
?>
