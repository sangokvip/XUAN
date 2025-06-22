<?php
// 简单的文件缓存系统

class SimpleCache {
    private $cacheDir;
    private $defaultTTL;
    
    public function __construct($cacheDir = 'cache', $defaultTTL = 3600) {
        $this->cacheDir = __DIR__ . '/../' . $cacheDir;
        $this->defaultTTL = $defaultTTL;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * 获取缓存
     */
    public function get($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        $cache = json_decode($data, true);
        
        if (!$cache || !isset($cache['expires']) || $cache['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $cache['data'];
    }
    
    /**
     * 设置缓存
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $filename = $this->getCacheFilename($key);
        
        $cache = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filename, json_encode($cache), LOCK_EX) !== false;
    }
    
    /**
     * 删除缓存
     */
    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * 清空所有缓存
     */
    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * 清理过期缓存
     */
    public function cleanup() {
        $files = glob($this->cacheDir . '/*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $cache = json_decode($data, true);
            
            if (!$cache || !isset($cache['expires']) || $cache['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取缓存文件名
     */
    private function getCacheFilename($key) {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
    
    /**
     * 检查缓存是否存在且有效
     */
    public function exists($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * 获取或设置缓存（如果不存在则执行回调函数）
     */
    public function remember($key, $callback, $ttl = null) {
        $data = $this->get($key);
        
        if ($data !== null) {
            return $data;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }
}

// 全局缓存实例
$cache = new SimpleCache();

/**
 * 缓存塔罗师列表
 */
function getCachedReaders($page = 1, $search = '', $featured = false) {
    global $cache;
    
    $cacheKey = "readers_list_{$page}_{$search}_" . ($featured ? 'featured' : 'all');
    
    return $cache->remember($cacheKey, function() use ($page, $search, $featured) {
        if ($featured) {
            return getFeaturedReaders();
        } else {
            return getAllReaders($page, READERS_PER_PAGE);
        }
    }, 300); // 5分钟缓存
}

/**
 * 缓存塔罗师详情
 */
function getCachedReader($id) {
    global $cache;
    
    $cacheKey = "reader_detail_{$id}";
    
    return $cache->remember($cacheKey, function() use ($id) {
        return getReaderById($id);
    }, 600); // 10分钟缓存
}

/**
 * 缓存系统设置
 */
function getCachedSetting($key, $default = null) {
    global $cache;
    
    $cacheKey = "setting_{$key}";
    
    return $cache->remember($cacheKey, function() use ($key, $default) {
        return getSetting($key, $default);
    }, 3600); // 1小时缓存
}

/**
 * 清除相关缓存
 */
function clearReaderCache($readerId = null) {
    global $cache;
    
    if ($readerId) {
        $cache->delete("reader_detail_{$readerId}");
    }
    
    // 清除列表缓存
    $files = glob($cache->cacheDir . '/readers_list_*.cache');
    foreach ($files as $file) {
        unlink($file);
    }
}

/**
 * 页面缓存中间件
 */
function startPageCache($key, $ttl = 300) {
    global $cache;
    
    $cachedContent = $cache->get("page_{$key}");
    
    if ($cachedContent !== null) {
        echo $cachedContent;
        exit;
    }
    
    ob_start();
    
    // 注册关闭函数来保存缓存
    register_shutdown_function(function() use ($cache, $key, $ttl) {
        $content = ob_get_contents();
        if ($content) {
            $cache->set("page_{$key}", $content, $ttl);
        }
    });
}

/**
 * 数据库查询缓存
 */
function cachedQuery($sql, $params = [], $ttl = 300) {
    global $cache;
    
    $cacheKey = 'query_' . md5($sql . serialize($params));
    
    return $cache->remember($cacheKey, function() use ($sql, $params) {
        $db = Database::getInstance();
        return $db->fetchAll($sql, $params);
    }, $ttl);
}

/**
 * 图片缩略图缓存
 */
function getCachedThumbnail($imagePath, $width = 300, $height = 300) {
    if (!file_exists($imagePath)) {
        return null;
    }
    
    $thumbnailDir = 'cache/thumbnails';
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    $filename = pathinfo($imagePath, PATHINFO_FILENAME);
    $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
    $thumbnailPath = "{$thumbnailDir}/{$filename}_{$width}x{$height}.{$extension}";
    
    // 如果缩略图已存在且比原图新，直接返回
    if (file_exists($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($imagePath)) {
        return $thumbnailPath;
    }
    
    // 生成缩略图
    if (createThumbnail($imagePath, $thumbnailPath, $width, $height)) {
        return $thumbnailPath;
    }
    
    return $imagePath; // 失败时返回原图
}

/**
 * 创建缩略图
 */
function createThumbnail($source, $destination, $width, $height) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // 创建源图像资源
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // 计算缩放比例
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $newWidth = (int)($sourceWidth * $ratio);
    $newHeight = (int)($sourceHeight * $ratio);
    
    // 创建目标图像
    $targetImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // 保持透明度
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefill($targetImage, 0, 0, $transparent);
    }
    
    // 缩放图像
    imagecopyresampled(
        $targetImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $sourceWidth, $sourceHeight
    );
    
    // 保存图像
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($targetImage, $destination, 85);
            break;
        case 'image/png':
            $result = imagepng($targetImage, $destination, 6);
            break;
        case 'image/gif':
            $result = imagegif($targetImage, $destination);
            break;
    }
    
    // 清理资源
    imagedestroy($sourceImage);
    imagedestroy($targetImage);
    
    return $result;
}

// 定期清理缓存（1%概率执行）
if (random_int(1, 100) === 1) {
    $cache->cleanup();
}
?>
