<?php
session_start();
require_once 'config/config.php';
require_once 'includes/TataCoinManager.php';

// 模拟占卜师登录状态
if (!isset($_SESSION['reader_id'])) {
    $_SESSION['reader_id'] = 1;
    $_SESSION['user_type'] = 'reader';
}

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'test_checkin') {
        // 直接测试签到逻辑
        try {
            $db = Database::getInstance();
            $tataCoinManager = new TataCoinManager();
            $today = date('Y-m-d');
            $userId = $_SESSION['reader_id'];
            $userType = 'reader';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            
            // 检查今天是否已签到
            $existingCheckin = $db->fetchOne(
                "SELECT id FROM daily_checkins WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
            
            if ($existingCheckin) {
                echo json_encode([
                    'success' => false,
                    'message' => '今日已签到',
                    'existing_id' => $existingCheckin['id']
                ]);
                exit;
            }
            
            // 插入签到记录
            $insertData = [
                'user_id' => null,
                'reader_id' => $userId,
                'user_type' => $userType,
                'checkin_date' => $today,
                'consecutive_days' => 1,
                'reward_amount' => 5,
                'ip_address' => $ipAddress
            ];
            
            $db->insert('daily_checkins', $insertData);

            // 给用户增加Tata coin奖励
            $rewardAmount = 5;
            $description = '每日签到奖励';
            $tataCoinManager->earn($userId, $userType, $rewardAmount, $description);

            echo json_encode([
                'success' => true,
                'message' => '签到成功！获得5个Tata币',
                'reward' => $rewardAmount,
                'consecutive_days' => 1
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => '签到失败：' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'test_api') {
        // 测试API调用
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/checkin.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: ' . $_SERVER['HTTP_COOKIE']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo json_encode([
            'success' => true,
            'api_response' => $response,
            'http_code' => $httpCode,
            'curl_error' => $error
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>简单签到测试</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 简单签到测试</h1>
        
        <div class="info">
            <strong>当前会话信息：</strong><br>
            占卜师ID: <?php echo $_SESSION['reader_id'] ?? 'null'; ?><br>
            用户类型: <?php echo $_SESSION['user_type'] ?? 'null'; ?><br>
            当前时间: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <button onclick="testDirectCheckin()" class="btn">测试直接签到</button>
        <button onclick="testApiCheckin()" class="btn">测试API签到</button>
        <button onclick="clearResults()" class="btn">清除结果</button>
        
        <div id="results"></div>
    </div>

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
        
        function testDirectCheckin() {
            const formData = new FormData();
            formData.append('action', 'test_checkin');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const result = JSON.parse(text);
                    showResult('直接签到测试结果', JSON.stringify(result, null, 2), result.success ? 'success' : 'error');
                } catch (e) {
                    showResult('直接签到测试 - JSON解析失败', text, 'error');
                }
            })
            .catch(error => {
                showResult('直接签到测试失败', error.message, 'error');
            });
        }
        
        function testApiCheckin() {
            const formData = new FormData();
            formData.append('action', 'test_api');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const result = JSON.parse(text);
                    showResult('API签到测试结果', 
                        'HTTP状态码: ' + result.http_code + '\n' +
                        'CURL错误: ' + (result.curl_error || '无') + '\n' +
                        'API响应: ' + result.api_response, 
                        'info');
                } catch (e) {
                    showResult('API签到测试 - JSON解析失败', text, 'error');
                }
            })
            .catch(error => {
                showResult('API签到测试失败', error.message, 'error');
            });
        }
    </script>
</body>
</html>
