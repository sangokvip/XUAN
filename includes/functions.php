<?php
// 通用函数库

/**
 * 安全输出HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 重定向函数
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 验证邮箱格式
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 验证密码强度
 */
function isValidPassword($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

/**
 * 密码哈希
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 验证密码
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 获取用户信息
 */
function getUserById($id, $activeOnly = false) {
    $db = Database::getInstance();
    $sql = "SELECT * FROM users WHERE id = ?";
    if ($activeOnly) {
        $sql .= " AND is_active = 1";
    }
    return $db->fetchOne($sql, [$id]);
}

/**
 * 获取塔罗师信息
 */
function getReaderById($id) {
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM readers WHERE id = ? AND is_active = 1", [$id]);
}

/**
 * 获取推荐塔罗师（按查看次数排序）
 */
function getFeaturedReaders($limit = null) {
    if ($limit === null) {
        $limit = (int)getSetting('max_featured_readers', 6);
    }
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT r.*,
                COALESCE(r.view_count, (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id)) as view_count
         FROM readers r
         WHERE r.is_featured = 1 AND r.is_active = 1
         ORDER BY view_count DESC, r.created_at DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取所有塔罗师（分页）
 */
function getAllReaders($page = 1, $perPage = READERS_PER_PAGE) {
    $db = Database::getInstance();
    $offset = ($page - 1) * $perPage;
    
    $readers = $db->fetchAll(
        "SELECT * FROM readers WHERE is_active = 1 ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM readers WHERE is_active = 1")['count'];
    
    return [
        'readers' => $readers,
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'current_page' => $page
    ];
}

/**
 * 检查用户是否已查看过塔罗师联系方式
 */
function hasViewedContact($userId, $readerId) {
    $db = Database::getInstance();
    $result = $db->fetchOne(
        "SELECT id FROM contact_views WHERE user_id = ? AND reader_id = ?",
        [$userId, $readerId]
    );
    return $result !== false;
}

/**
 * 记录用户查看塔罗师联系方式
 */
function recordContactView($userId, $readerId) {
    $db = Database::getInstance();
    try {
        $db->insert('contact_views', [
            'user_id' => $userId,
            'reader_id' => $readerId
        ]);
        return true;
    } catch (Exception $e) {
        // 如果已存在记录，忽略错误
        return false;
    }
}

/**
 * 文件上传处理（增强安全性）
 */
function uploadFile($file, $uploadPath, $allowedTypes = ALLOWED_IMAGE_TYPES) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => '没有文件被上传'];
    }

    // 检查临时文件是否存在
    if (!file_exists($file['tmp_name'])) {
        return ['success' => false, 'message' => '临时文件不存在'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }

    // 检查文件错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传出错'];
    }

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);

    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }

    // 验证文件MIME类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];

    if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
        return ['success' => false, 'message' => '文件类型验证失败'];
    }

    // 检查是否为真实图片
    if (!getimagesize($file['tmp_name'])) {
        return ['success' => false, 'message' => '文件不是有效的图片'];
    }

    $fileName = generateRandomString() . '.' . $extension;
    $filePath = $uploadPath . $fileName;

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    // 尝试移动文件
    $moveSuccess = false;

    // 首先尝试 move_uploaded_file（用于HTTP POST上传的文件）
    if (is_uploaded_file($file['tmp_name'])) {
        $moveSuccess = move_uploaded_file($file['tmp_name'], $filePath);
    } else {
        // 如果不是HTTP POST上传的文件，使用copy
        $moveSuccess = copy($file['tmp_name'], $filePath);
    }

    if ($moveSuccess) {
        // 设置文件权限
        chmod($filePath, 0644);
        return ['success' => true, 'filename' => $fileName, 'path' => $filePath];
    } else {
        // 提供更详细的错误信息
        $error = '文件上传失败';
        if (!is_writable($uploadPath)) {
            $error .= ' - 目录不可写';
        }
        if (!file_exists($file['tmp_name'])) {
            $error .= ' - 源文件不存在';
        }
        return ['success' => false, 'message' => $error];
    }
}

/**
 * 删除文件
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

/**
 * 安全删除文件 - 防止路径遍历攻击
 */
function safeDeleteFile($relativePath, $baseDir = '../') {
    if (empty($relativePath)) {
        return true;
    }

    $fullPath = $baseDir . $relativePath;

    // 获取真实路径并检查是否在允许的目录内
    $realPath = realpath($fullPath);
    $realBaseDir = realpath($baseDir);

    if ($realPath && $realBaseDir && strpos($realPath, $realBaseDir) === 0 && file_exists($fullPath)) {
        return unlink($fullPath);
    }

    return true; // 如果文件不存在或路径不安全，返回true（视为删除成功）
}

/**
 * 获取系统设置
 */
function getSetting($key, $default = null) {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

/**
 * 设置系统设置
 */
function setSetting($key, $value) {
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);

    try {
        if ($existing) {
            $stmt = $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
            return $stmt->rowCount() > 0;
        } else {
            $insertId = $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
            return $insertId > 0;
        }
    } catch (Exception $e) {
        error_log("设置更新失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取网站名称（从数据库或默认值）
 */
function getSiteName() {
    return getSetting('site_name', defined('SITE_NAME_DEFAULT') ? SITE_NAME_DEFAULT : '塔罗师展示平台');
}

/**
 * 获取网站描述
 */
function getSiteDescription() {
    return getSetting('site_description', '专业塔罗师展示平台');
}

/**
 * 生成CSRF令牌
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 清理输入数据
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return trim(strip_tags($input));
}

/**
 * 验证邮箱域名
 */
function isValidEmailDomain($email) {
    $domain = substr(strrchr($email, "@"), 1);
    return checkdnsrr($domain, "MX");
}

/**
 * 检查IP是否被限制
 */
function isIPBlocked($ip) {
    // 这里可以实现IP黑名单检查
    $blockedIPs = ['127.0.0.2']; // 示例
    return in_array($ip, $blockedIPs);
}

/**
 * 记录登录尝试
 */
function logLoginAttempt($username, $success, $ip) {
    $db = Database::getInstance();
    try {
        $db->insert('login_attempts', [
            'username' => $username,
            'success' => $success ? 1 : 0,
            'ip_address' => $ip,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

/**
 * 检查登录尝试次数
 */
function checkLoginAttempts($username, $ip) {
    $db = Database::getInstance();

    // 检查过去15分钟内的失败尝试次数
    $attempts = $db->fetchOne(
        "SELECT COUNT(*) as count FROM login_attempts
         WHERE (username = ? OR ip_address = ?)
         AND success = 0
         AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$username, $ip]
    );

    return ($attempts['count'] ?? 0) >= 5; // 15分钟内超过5次失败则锁定
}

/**
 * 清理旧的登录尝试记录
 */
function cleanupLoginAttempts() {
    $db = Database::getInstance();
    try {
        $db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (Exception $e) {
        error_log("Failed to cleanup login attempts: " . $e->getMessage());
    }
}

/**
 * 验证用户会话
 */
function validateSession() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['reader_id']) && !isset($_SESSION['admin_id'])) {
        return true; // 未登录用户
    }

    // 检查会话超时
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 安全的重定向
 */
function safeRedirect($url) {
    // 只允许相对URL或同域名URL
    $parsedUrl = parse_url($url);

    if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $_SERVER['HTTP_HOST']) {
        $url = '/'; // 重定向到首页
    }

    redirect($url);
}

/**
 * 生成安全的文件名
 */
function generateSecureFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    // 只保留字母数字和点
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);

    return generateRandomString(32) . '.' . $extension;
}

/**
 * 清理和标准化照片路径
 * 确保路径格式一致，适用于前台显示
 */
function cleanPhotoPath($path) {
    if (empty($path)) {
        return '';
    }

    // 移除可能的../前缀
    $path = str_replace('../', '', $path);

    // 移除开头的斜杠
    $path = ltrim($path, '/');

    return $path;
}

/**
 * 获取占卜师照片URL
 * 处理照片路径，如果没有照片则返回默认头像
 */
function getReaderPhotoUrl($reader, $useCircle = false) {
    $photoSrc = '';

    if ($useCircle && !empty($reader['photo_circle'])) {
        $photoSrc = cleanPhotoPath($reader['photo_circle']);
    } elseif (!empty($reader['photo'])) {
        $photoSrc = cleanPhotoPath($reader['photo']);
    } else {
        // 根据性别使用默认头像
        $photoSrc = ($reader['gender'] === 'female') ? 'img/tf.jpg' : 'img/tm.jpg';
    }

    return $photoSrc;
}

/**
 * 优化上传图片文件
 * 包括压缩、生成多尺寸缩略图、WebP转换
 */
function uploadOptimizedImage($file, $uploadPath = PHOTO_PATH) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => '',
        'optimized' => [],
        'webp' => [],
        'original_size' => 0,
        'optimized_size' => 0
    ];

    try {
        // 基础验证
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('无效的上传文件');
        }

        // 检查文件大小
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('文件大小超过限制（最大 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB）');
        }

        // 检查文件类型
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('不是有效的图片文件');
        }

        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (!in_array($imageInfo[2], $allowedTypes)) {
            throw new Exception('不支持的图片格式，请使用 JPG、PNG 或 GIF');
        }

        // 生成安全的文件名
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = generateSecureFilename($file['name']);
        $uploadFile = $uploadPath . $filename;

        // 确保上传目录存在
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // 移动原始文件
        if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
            throw new Exception('文件上传失败');
        }

        $result['original_size'] = filesize($uploadFile);

        // 使用图片优化器处理
        require_once __DIR__ . '/ImageOptimizer.php';
        $optimizer = new ImageOptimizer($uploadPath);
        $optimizeResult = $optimizer->processUploadedImage($uploadFile, $filename);

        if (!$optimizeResult['success']) {
            // 如果优化失败，删除原文件并返回错误
            unlink($uploadFile);
            throw new Exception($optimizeResult['error'] ?: '图片优化失败');
        }

        // 计算优化后的总大小
        $optimizedSize = 0;
        foreach ($optimizeResult['optimized'] as $path) {
            $fullPath = $uploadPath . $path;
            if (file_exists($fullPath)) {
                $optimizedSize += filesize($fullPath);
            }
        }

        $result['success'] = true;
        $result['filename'] = $filename;
        $result['optimized'] = $optimizeResult['optimized'];
        $result['webp'] = $optimizeResult['webp'];
        $result['optimized_size'] = $optimizedSize;
        $result['message'] = '图片上传并优化成功';

        // 记录优化统计
        $compressionRatio = $result['original_size'] > 0 ?
            round((1 - $optimizedSize / $result['original_size']) * 100, 1) : 0;

        error_log("Image optimization: {$filename}, Original: {$result['original_size']} bytes, Optimized: {$optimizedSize} bytes, Compression: {$compressionRatio}%");

    } catch (Exception $e) {
        $result['message'] = $e->getMessage();

        // 清理可能已上传的文件
        if (!empty($filename) && file_exists($uploadPath . $filename)) {
            unlink($uploadPath . $filename);
        }
    }

    return $result;
}

/**
 * 获取优化后的图片URL
 */
function getOptimizedImageUrl($filename, $size = 'medium', $preferWebP = true) {
    if (empty($filename)) {
        return '';
    }

    require_once __DIR__ . '/ImageOptimizer.php';
    $optimizer = new ImageOptimizer();
    return $optimizer->getOptimizedImageUrl($filename, $size, $preferWebP);
}
?>
