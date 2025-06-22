<?php
// 错误处理和日志系统

class ErrorHandler {
    private $logDir;
    private $isProduction;
    
    public function __construct($logDir = 'logs', $isProduction = false) {
        $this->logDir = __DIR__ . '/../' . $logDir;
        $this->isProduction = $isProduction;
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $this->setupErrorHandling();
    }
    
    /**
     * 设置错误处理
     */
    private function setupErrorHandling() {
        // 设置错误报告级别
        if ($this->isProduction) {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        
        // 设置自定义错误处理器
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    /**
     * 处理一般错误
     */
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = $this->getErrorType($severity);
        $this->logError($errorType, $message, $file, $line);
        
        if (!$this->isProduction) {
            $this->displayError($errorType, $message, $file, $line);
        }
        
        return true;
    }
    
    /**
     * 处理异常
     */
    public function handleException($exception) {
        $this->logError(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        if ($this->isProduction) {
            $this->showErrorPage();
        } else {
            $this->displayException($exception);
        }
    }
    
    /**
     * 处理致命错误
     */
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logError(
                'FATAL',
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            if ($this->isProduction) {
                $this->showErrorPage();
            }
        }
    }
    
    /**
     * 记录错误到日志文件
     */
    private function logError($type, $message, $file, $line, $trace = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_uri' => $requestUri
        ];
        
        if ($trace) {
            $logEntry['trace'] = $trace;
        }
        
        // 记录到日志文件
        $logFile = $this->logDir . '/error_' . date('Y-m-d') . '.log';
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // 如果是严重错误，发送邮件通知（可选）
        if (in_array($type, ['FATAL', 'EXCEPTION']) && defined('ADMIN_EMAIL')) {
            $this->sendErrorEmail($logEntry);
        }
    }
    
    /**
     * 获取错误类型名称
     */
    private function getErrorType($severity) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $errorTypes[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * 显示错误信息（开发模式）
     */
    private function displayError($type, $message, $file, $line) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>{$type}:</strong> {$message}<br>";
        echo "<strong>File:</strong> {$file}<br>";
        echo "<strong>Line:</strong> {$line}";
        echo "</div>";
    }
    
    /**
     * 显示异常信息（开发模式）
     */
    private function displayException($exception) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<strong>Message:</strong> " . $exception->getMessage() . "<br>";
        echo "<strong>File:</strong> " . $exception->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $exception->getLine() . "<br>";
        echo "<strong>Trace:</strong><br>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    
    /**
     * 显示用户友好的错误页面（生产模式）
     */
    private function showErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        
        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统错误</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f8f8; margin: 0; padding: 50px; }
        .error-container { max-width: 600px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #d4af37; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>系统暂时不可用</h1>
        <p>抱歉，系统遇到了一个错误。我们已经记录了这个问题，并会尽快修复。</p>
        <p>请稍后再试，或者联系网站管理员。</p>
        <a href="/" class="btn">返回首页</a>
    </div>
</body>
</html>';
        exit;
    }
    
    /**
     * 发送错误邮件通知
     */
    private function sendErrorEmail($logEntry) {
        if (!defined('ADMIN_EMAIL') || !function_exists('mail')) {
            return;
        }
        
        $subject = '网站错误通知 - ' . $logEntry['type'];
        $message = "网站发生了一个错误：\n\n";
        $message .= "时间：{$logEntry['timestamp']}\n";
        $message .= "类型：{$logEntry['type']}\n";
        $message .= "消息：{$logEntry['message']}\n";
        $message .= "文件：{$logEntry['file']}\n";
        $message .= "行号：{$logEntry['line']}\n";
        $message .= "IP：{$logEntry['ip']}\n";
        $message .= "请求：{$logEntry['request_uri']}\n";
        
        if (isset($logEntry['trace'])) {
            $message .= "\n堆栈跟踪：\n{$logEntry['trace']}\n";
        }
        
        $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        @mail(ADMIN_EMAIL, $subject, $message, $headers);
    }
    
    /**
     * 清理旧日志文件
     */
    public function cleanupLogs($daysToKeep = 30) {
        $files = glob($this->logDir . '/error_*.log');
        $cutoffTime = time() - ($daysToKeep * 24 * 3600);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * 获取错误统计
     */
    public function getErrorStats($days = 7) {
        $stats = [];
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $logFile = $this->logDir . "/error_{$date}.log";
            
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                $stats[$date] = count($lines);
            } else {
                $stats[$date] = 0;
            }
        }
        
        return array_reverse($stats, true);
    }
}

// 初始化错误处理器
$isProduction = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
$errorHandler = new ErrorHandler('logs', $isProduction);

// 定期清理日志（1%概率执行）
if (random_int(1, 100) === 1) {
    $errorHandler->cleanupLogs();
}

/**
 * 记录自定义日志
 */
function logMessage($level, $message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    $logLine = json_encode($logEntry) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * 便捷的日志函数
 */
function logInfo($message, $context = []) {
    logMessage('info', $message, $context);
}

function logWarning($message, $context = []) {
    logMessage('warning', $message, $context);
}

function logError($message, $context = []) {
    logMessage('error', $message, $context);
}

function logDebug($message, $context = []) {
    if (!defined('DEBUG') || !DEBUG) {
        return;
    }
    logMessage('debug', $message, $context);
}
?>
