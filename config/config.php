<?php
// 网站基本配置
// SITE_NAME 将从数据库动态获取，这里只是默认值
define('SITE_NAME_DEFAULT', '塔罗师展示平台');
define('SITE_EMAIL', 'admin@tarot.com');

// 加载网站URL配置
if (file_exists(__DIR__ . '/site_config.php')) {
    require_once __DIR__ . '/site_config.php';
}

// 如果没有配置文件，使用默认值
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost');
}

// 环境配置
define('ENVIRONMENT', 'development'); // development 或 production
define('DEBUG', ENVIRONMENT === 'development');

// 文件上传配置
define('UPLOAD_PATH', 'uploads/');
define('PHOTO_PATH', UPLOAD_PATH . 'photos/');
define('PRICE_LIST_PATH', UPLOAD_PATH . 'price_lists/');
define('CERTIFICATES_PATH', UPLOAD_PATH . 'certificates/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_CERTIFICATES', 10); // 最多上传10个证书
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// 图片优化配置
define('IMAGE_OPTIMIZATION_ENABLED', true); // 是否启用图片优化
define('MAX_IMAGE_WIDTH', 800);             // 图片最大宽度
define('MAX_IMAGE_HEIGHT', 800);            // 图片最大高度
define('IMAGE_QUALITY', 85);                // JPEG压缩质量 (1-100)
define('WEBP_ENABLED', true);               // 是否启用WebP格式
define('THUMBNAIL_ENABLED', true);          // 是否生成缩略图

// 头像专用配置
define('AVATAR_MAX_WIDTH', 400);            // 头像最大宽度
define('AVATAR_MAX_HEIGHT', 400);           // 头像最大高度
define('AVATAR_QUALITY', 90);               // 头像压缩质量

// 安全配置
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_TIMEOUT', 3600); // 1小时
define('REGISTRATION_LINK_HOURS', 24); // 注册链接有效期24小时

// 分页配置
define('READERS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// 确保常量已定义
if (!defined('ADMIN_ITEMS_PER_PAGE')) {
    define('ADMIN_ITEMS_PER_PAGE', 20);
}

// 缓存配置
define('CACHE_ENABLED', true);
define('CACHE_DEFAULT_TTL', 300); // 5分钟

// 日志配置
define('LOG_ENABLED', true);
define('LOG_PAGE_ACCESS', DEBUG);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 自动加载函数
function autoload($className) {
    $file = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('autoload');

// 包含数据库配置
if (file_exists(__DIR__ . '/database_config.php')) {
    require_once __DIR__ . '/database_config.php';
} elseif (file_exists(__DIR__ . '/site_config.php')) {
    require_once __DIR__ . '/site_config.php';
}

// 包含必要文件
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/security.php';
?>
