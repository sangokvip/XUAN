<?php
/**
 * 图片优化处理类
 * 支持自动压缩、多尺寸缩略图、WebP格式转换
 */
class ImageOptimizer {
    
    // 预定义的图片尺寸 (优化后，减少不必要的尺寸)
    const SIZES = [
        'thumb' => ['width' => 120, 'height' => 120, 'quality' => 70],     // 缩略图
        'small' => ['width' => 240, 'height' => 240, 'quality' => 75],     // 小图
        'medium' => ['width' => 400, 'height' => 400, 'quality' => 80],    // 中图
        'circle' => ['width' => 150, 'height' => 150, 'quality' => 75]     // 圆形头像
    ];
    
    private $uploadPath;
    private $quality;
    private $webpSupported;
    
    public function __construct($uploadPath = 'uploads/photos/', $quality = 75) {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
        $this->quality = defined('AVATAR_QUALITY') ? AVATAR_QUALITY : $quality;
        $this->webpSupported = function_exists('imagewebp') && (defined('WEBP_ENABLED') ? WEBP_ENABLED : true);

        // 创建优化图片目录
        $this->createOptimizedDirectories();
    }
    
    /**
     * 创建优化图片存储目录
     */
    private function createOptimizedDirectories() {
        $dirs = ['optimized', 'webp'];
        foreach ($dirs as $dir) {
            $path = $this->uploadPath . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            
            // 为每个尺寸创建子目录
            foreach (array_keys(self::SIZES) as $size) {
                $sizePath = $path . '/' . $size;
                if (!is_dir($sizePath)) {
                    mkdir($sizePath, 0755, true);
                }
            }
        }
    }
    
    /**
     * 处理上传的图片
     */
    public function processUploadedImage($sourceFile, $filename) {
        $results = [
            'original' => $filename,
            'optimized' => [],
            'webp' => [],
            'success' => false,
            'error' => null
        ];
        
        try {
            // 获取图片信息
            $imageInfo = getimagesize($sourceFile);
            if (!$imageInfo) {
                throw new Exception('无效的图片文件');
            }
            
            $imageType = $imageInfo[2];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // 创建图片资源
            $sourceImage = $this->createImageResource($sourceFile, $imageType);
            if (!$sourceImage) {
                throw new Exception('无法创建图片资源');
            }
            
            // 生成各种尺寸的图片
            foreach (self::SIZES as $sizeName => $dimensions) {
                $this->generateOptimizedImage(
                    $sourceImage,
                    $filename,
                    $sizeName,
                    $dimensions,
                    $originalWidth,
                    $originalHeight,
                    $results
                );
            }

            // 生成原图的优化版本
            $this->generateOriginalOptimized($sourceImage, $filename, $originalWidth, $originalHeight, $results);
            
            // 清理资源
            imagedestroy($sourceImage);
            
            $results['success'] = true;
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 创建图片资源
     */
    private function createImageResource($file, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($file);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($file);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($file);
            default:
                return false;
        }
    }
    
    /**
     * 生成优化后的图片
     */
    private function generateOptimizedImage($sourceImage, $filename, $sizeName, $dimensions, $originalWidth, $originalHeight, &$results) {
        // 计算新尺寸（保持比例）
        $newDimensions = $this->calculateDimensions(
            $originalWidth, 
            $originalHeight, 
            $dimensions['width'], 
            $dimensions['height'],
            $sizeName === 'circle' // 圆形头像使用裁剪模式
        );
        
        // 创建新图片
        $newImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
        
        // 保持透明度（PNG）
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
        
        // 重新采样
        imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, $newDimensions['src_x'], $newDimensions['src_y'],
            $newDimensions['width'], $newDimensions['height'],
            $newDimensions['src_width'], $newDimensions['src_height']
        );
        
        // 如果是圆形头像，应用圆形遮罩
        if ($sizeName === 'circle') {
            $newImage = $this->applyCircleMask($newImage, $newDimensions['width']);
        }
        
        // 使用尺寸特定的质量设置
        $quality = isset($dimensions['quality']) ? $dimensions['quality'] : $this->quality;

        // 保存JPEG版本（渐进式）
        $jpegPath = $this->uploadPath . 'optimized/' . $sizeName . '/' . $this->changeExtension($filename, 'jpg');
        if ($this->saveProgressiveJpeg($newImage, $jpegPath, $quality)) {
            $results['optimized'][$sizeName] = 'optimized/' . $sizeName . '/' . $this->changeExtension($filename, 'jpg');
        }

        // 保存WebP版本（如果支持）
        if ($this->webpSupported) {
            $webpPath = $this->uploadPath . 'webp/' . $sizeName . '/' . $this->changeExtension($filename, 'webp');
            if (imagewebp($newImage, $webpPath, $quality)) {
                $results['webp'][$sizeName] = 'webp/' . $sizeName . '/' . $this->changeExtension($filename, 'webp');
            }
        }
        
        // 清理资源
        imagedestroy($newImage);
    }
    
    /**
     * 计算新图片尺寸
     */
    private function calculateDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight, $crop = false) {
        if ($crop) {
            // 裁剪模式：填满目标尺寸
            $scale = max($targetWidth / $originalWidth, $targetHeight / $originalHeight);
            $newWidth = $targetWidth;
            $newHeight = $targetHeight;
            $srcWidth = $targetWidth / $scale;
            $srcHeight = $targetHeight / $scale;
            $srcX = ($originalWidth - $srcWidth) / 2;
            $srcY = ($originalHeight - $srcHeight) / 2;
        } else {
            // 缩放模式：保持比例
            $scale = min($targetWidth / $originalWidth, $targetHeight / $originalHeight);
            $newWidth = $originalWidth * $scale;
            $newHeight = $originalHeight * $scale;
            $srcWidth = $originalWidth;
            $srcHeight = $originalHeight;
            $srcX = 0;
            $srcY = 0;
        }
        
        return [
            'width' => round($newWidth),
            'height' => round($newHeight),
            'src_x' => round($srcX),
            'src_y' => round($srcY),
            'src_width' => round($srcWidth),
            'src_height' => round($srcHeight)
        ];
    }
    
    /**
     * 应用圆形遮罩
     */
    private function applyCircleMask($image, $size) {
        $mask = imagecreatetruecolor($size, $size);
        $transparent = imagecolorallocatealpha($mask, 255, 255, 255, 127);
        imagefill($mask, 0, 0, $transparent);
        
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefilledellipse($mask, $size/2, $size/2, $size, $size, $white);
        
        $result = imagecreatetruecolor($size, $size);
        imagealphablending($result, false);
        imagesavealpha($result, true);
        $transparent = imagecolorallocatealpha($result, 255, 255, 255, 127);
        imagefill($result, 0, 0, $transparent);
        
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $maskColor = imagecolorat($mask, $x, $y);
                if (($maskColor & 0xFF) > 0) {
                    $imageColor = imagecolorat($image, $x, $y);
                    imagesetpixel($result, $x, $y, $imageColor);
                }
            }
        }
        
        imagedestroy($mask);
        return $result;
    }
    
    /**
     * 更改文件扩展名
     */
    private function changeExtension($filename, $newExtension) {
        $pathInfo = pathinfo($filename);
        return $pathInfo['filename'] . '.' . $newExtension;
    }
    
    /**
     * 获取优化后的图片URL
     */
    public function getOptimizedImageUrl($originalFilename, $size = 'medium', $preferWebP = true) {
        if (!isset(self::SIZES[$size])) {
            $size = 'medium';
        }
        
        $baseFilename = $this->changeExtension($originalFilename, '');
        
        // 优先返回WebP格式（如果支持且存在）
        if ($preferWebP && $this->webpSupported) {
            $webpPath = $this->uploadPath . 'webp/' . $size . '/' . $baseFilename . 'webp';
            if (file_exists($webpPath)) {
                return 'uploads/photos/webp/' . $size . '/' . $baseFilename . 'webp';
            }
        }
        
        // 返回JPEG格式
        $jpegPath = $this->uploadPath . 'optimized/' . $size . '/' . $baseFilename . 'jpg';
        if (file_exists($jpegPath)) {
            return 'uploads/photos/optimized/' . $size . '/' . $baseFilename . 'jpg';
        }
        
        // 如果优化版本不存在，返回原图
        return 'uploads/photos/' . $originalFilename;
    }
    
    /**
     * 生成原图的优化版本
     */
    private function generateOriginalOptimized($sourceImage, $filename, $originalWidth, $originalHeight, &$results) {
        // 如果原图太大，生成一个优化的原图版本
        $maxWidth = defined('AVATAR_MAX_WIDTH') ? AVATAR_MAX_WIDTH : 800;
        $maxHeight = defined('AVATAR_MAX_HEIGHT') ? AVATAR_MAX_HEIGHT : 800;

        if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
            $newDimensions = $this->calculateDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight, false);

            $newImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);

            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0, 0, 0,
                $newDimensions['width'], $newDimensions['height'],
                $originalWidth, $originalHeight
            );

            // 保存优化的原图
            $optimizedOriginalPath = $this->uploadPath . 'optimized/' . $this->changeExtension($filename, 'jpg');
            if ($this->saveProgressiveJpeg($newImage, $optimizedOriginalPath, $this->quality)) {
                $results['optimized']['original'] = 'optimized/' . $this->changeExtension($filename, 'jpg');
            }

            // WebP版本
            if ($this->webpSupported) {
                $webpOriginalPath = $this->uploadPath . 'webp/' . $this->changeExtension($filename, 'webp');
                if (imagewebp($newImage, $webpOriginalPath, $this->quality)) {
                    $results['webp']['original'] = 'webp/' . $this->changeExtension($filename, 'webp');
                }
            }

            imagedestroy($newImage);
        }
    }

    /**
     * 保存渐进式JPEG
     */
    private function saveProgressiveJpeg($image, $path, $quality) {
        if (defined('PROGRESSIVE_JPEG') && PROGRESSIVE_JPEG && function_exists('imageinterlace')) {
            imageinterlace($image, 1); // 启用渐进式
        }
        return imagejpeg($image, $path, $quality);
    }

    /**
     * 清理旧的优化图片
     */
    public function cleanupOptimizedImages($originalFilename) {
        $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);

        foreach (array_keys(self::SIZES) as $size) {
            // 删除JPEG版本
            $jpegPath = $this->uploadPath . 'optimized/' . $size . '/' . $baseFilename . '.jpg';
            if (file_exists($jpegPath)) {
                unlink($jpegPath);
            }

            // 删除WebP版本
            $webpPath = $this->uploadPath . 'webp/' . $size . '/' . $baseFilename . '.webp';
            if (file_exists($webpPath)) {
                unlink($webpPath);
            }
        }

        // 删除优化的原图
        $optimizedOriginalJpeg = $this->uploadPath . 'optimized/' . $baseFilename . '.jpg';
        if (file_exists($optimizedOriginalJpeg)) {
            unlink($optimizedOriginalJpeg);
        }

        $optimizedOriginalWebp = $this->uploadPath . 'webp/' . $baseFilename . '.webp';
        if (file_exists($optimizedOriginalWebp)) {
            unlink($optimizedOriginalWebp);
        }
    }

    /**
     * 获取文件大小信息
     */
    public function getFileSizeInfo($originalFilename) {
        $info = [
            'original' => 0,
            'optimized' => 0,
            'webp' => 0,
            'total_saved' => 0,
            'compression_ratio' => 0
        ];

        // 原文件大小
        $originalPath = $this->uploadPath . $originalFilename;
        if (file_exists($originalPath)) {
            $info['original'] = filesize($originalPath);
        }

        // 优化文件大小
        $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
        foreach (array_keys(self::SIZES) as $size) {
            $jpegPath = $this->uploadPath . 'optimized/' . $size . '/' . $baseFilename . '.jpg';
            if (file_exists($jpegPath)) {
                $info['optimized'] += filesize($jpegPath);
            }

            $webpPath = $this->uploadPath . 'webp/' . $size . '/' . $baseFilename . '.webp';
            if (file_exists($webpPath)) {
                $info['webp'] += filesize($webpPath);
            }
        }

        // 计算节省的空间
        $totalOptimized = $info['optimized'] + $info['webp'];
        if ($info['original'] > 0) {
            $info['total_saved'] = $info['original'] - $totalOptimized;
            $info['compression_ratio'] = round((1 - $totalOptimized / $info['original']) * 100, 1);
        }

        return $info;
    }
}
?>
