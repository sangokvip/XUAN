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
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB (降低文件大小限制)
define('MAX_CERTIFICATES', 10); // 最多上传10个证书
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 图片优化配置
define('IMAGE_OPTIMIZATION_ENABLED', true);
define('AVATAR_MAX_WIDTH', 800); // 头像最大宽度
define('AVATAR_MAX_HEIGHT', 800); // 头像最大高度
define('AVATAR_QUALITY', 75); // 压缩质量 (降低到75%)
define('WEBP_ENABLED', true); // 启用WebP格式
define('THUMBNAIL_ENABLED', true); // 启用缩略图生成
define('PROGRESSIVE_JPEG', true); // 启用渐进式JPEG
define('CLIENT_SIDE_COMPRESSION', true); // 启用客户端压缩
define('CLIENT_COMPRESSION_QUALITY', 0.65); // 客户端压缩质量
define('CLIENT_MAX_WIDTH', 1920); // 客户端最大宽度
define('CLIENT_MAX_HEIGHT', 1920); // 客户端最大高度

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
