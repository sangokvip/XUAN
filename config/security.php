<?php
// 安全配置文件

// 启动会话安全设置
if (session_status() === PHP_SESSION_NONE) {
    // 设置安全的会话参数
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // HTTPS环境下设为1
    ini_set('session.cookie_samesite', 'Strict');

    // 设置会话名称
    session_name('TAROT_SESSION');

    // 启动会话
    session_start();

    // 验证会话（只对已登录用户进行验证）
    if ((isset($_SESSION['user_id']) || isset($_SESSION['reader_id']) || isset($_SESSION['admin_id'])) && !validateSession()) {
        session_destroy();
        session_start(); // 重新启动一个新的会话
    }
}

// 设置安全头部
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 内容安全策略
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
       "style-src 'self' 'unsafe-inline' fonts.googleapis.com; " .
       "img-src 'self' data: blob:; " .
       "font-src 'self' data: *.alicdn.com fonts.gstatic.com chrome-extension:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none';";
header("Content-Security-Policy: $csp");

// 清理全局变量
function sanitizeGlobals() {
    if (isset($_GET)) {
        $_GET = array_map('sanitizeInput', $_GET);
    }
    if (isset($_POST)) {
        $_POST = array_map('sanitizeInput', $_POST);
    }
    if (isset($_COOKIE)) {
        $_COOKIE = array_map('sanitizeInput', $_COOKIE);
    }
}

// 检查请求方法
function validateRequestMethod($allowedMethods = ['GET', 'POST']) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowedMethods)) {
        http_response_code(405);
        die('Method Not Allowed');
    }
}

// 验证Referer头部
function validateReferer() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($referer) || parse_url($referer, PHP_URL_HOST) !== $host) {
            http_response_code(403);
            die('Invalid request origin');
        }
    }
}

// 限制请求频率
function rateLimitCheck($key, $maxRequests = 60, $timeWindow = 60) {
    $cacheKey = 'rate_limit_' . md5($key);
    
    // 这里可以使用Redis或文件缓存
    // 简单实现使用会话存储
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    $windowStart = $now - $timeWindow;
    
    // 清理过期记录
    if (isset($_SESSION['rate_limits'][$cacheKey])) {
        $_SESSION['rate_limits'][$cacheKey] = array_filter(
            $_SESSION['rate_limits'][$cacheKey],
            function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );
    } else {
        $_SESSION['rate_limits'][$cacheKey] = [];
    }
    
    // 检查请求数量
    if (count($_SESSION['rate_limits'][$cacheKey]) >= $maxRequests) {
        http_response_code(429);
        die('Too Many Requests');
    }
    
    // 记录当前请求
    $_SESSION['rate_limits'][$cacheKey][] = $now;
}

// 验证文件上传
function validateFileUpload($file) {
    // 检查文件大小
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }
    
    // 检查文件内容
    if (!getimagesize($file['tmp_name'])) {
        return false;
    }
    
    return true;
}

// 生成安全的随机密码
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charLength - 1)];
    }
    
    return $password;
}

// 记录安全事件
function logSecurityEvent($event, $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}

// 清理敏感数据
function clearSensitiveData() {
    // 清理POST数据中的密码字段
    if (isset($_POST['password'])) {
        $_POST['password'] = str_repeat('*', strlen($_POST['password']));
    }
    if (isset($_POST['confirm_password'])) {
        $_POST['confirm_password'] = str_repeat('*', strlen($_POST['confirm_password']));
    }
}

// 执行基本安全检查
sanitizeGlobals();

// 定期清理登录尝试记录
if (random_int(1, 100) === 1) { // 1%的概率执行清理
    cleanupLoginAttempts();
}

// 记录页面访问（可选）
if (defined('LOG_PAGE_ACCESS') && LOG_PAGE_ACCESS) {
    logSecurityEvent('page_access', [
        'page' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ]);
}
?>
