/**
 * 前端图片压缩组件
 * 在上传前对图片进行压缩，减少文件大小和上传时间
 */
class ImageCompressor {
    constructor(options = {}) {
        this.options = {
            maxWidth: options.maxWidth || 1920,
            maxHeight: options.maxHeight || 1920,
            quality: options.quality || 0.65,
            maxFileSize: options.maxFileSize || 2 * 1024 * 1024, // 2MB
            outputFormat: options.outputFormat || 'image/jpeg',
            enableResize: options.enableResize !== false,
            enableCompress: options.enableCompress !== false,
            ...options
        };
    }

    /**
     * 压缩图片文件
     * @param {File} file - 原始图片文件
     * @param {Object} options - 压缩选项
     * @returns {Promise<Blob>} - 压缩后的图片Blob
     */
    async compress(file, options = {}) {
        const config = { ...this.options, ...options };
        
        return new Promise((resolve, reject) => {
            // 检查文件类型
            if (!file.type.startsWith('image/')) {
                reject(new Error('不是有效的图片文件'));
                return;
            }

            // 如果文件已经很小，可能不需要压缩
            if (file.size <= config.maxFileSize * 0.5 && !config.forceCompress) {
                // 但仍然需要检查尺寸
                this.checkAndResizeIfNeeded(file, config).then(resolve).catch(reject);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    try {
                        const canvas = this.createOptimizedCanvas(img, config);
                        canvas.toBlob(
                            (blob) => {
                                if (blob) {
                                    // 检查压缩后的文件大小
                                    if (blob.size > config.maxFileSize) {
                                        // 如果还是太大，降低质量再试一次
                                        const lowerQuality = Math.max(0.3, config.quality - 0.2);
                                        const newConfig = { ...config, quality: lowerQuality };
                                        const newCanvas = this.createOptimizedCanvas(img, newConfig);
                                        newCanvas.toBlob(resolve, config.outputFormat, lowerQuality);
                                    } else {
                                        resolve(blob);
                                    }
                                } else {
                                    reject(new Error('图片压缩失败'));
                                }
                            },
                            config.outputFormat,
                            config.quality
                        );
                    } catch (error) {
                        reject(error);
                    }
                };
                img.onerror = () => reject(new Error('图片加载失败'));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error('文件读取失败'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * 检查并在需要时调整尺寸
     */
    async checkAndResizeIfNeeded(file, config) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    if (img.width <= config.maxWidth && img.height <= config.maxHeight) {
                        // 尺寸合适，直接返回原文件
                        resolve(file);
                    } else {
                        // 需要调整尺寸
                        const canvas = this.createOptimizedCanvas(img, config);
                        canvas.toBlob(resolve, config.outputFormat, config.quality);
                    }
                };
                img.onerror = () => reject(new Error('图片加载失败'));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error('文件读取失败'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * 创建优化的Canvas
     */
    createOptimizedCanvas(img, config) {
        const { width, height } = this.calculateOptimalSize(
            img.width, 
            img.height, 
            config.maxWidth, 
            config.maxHeight
        );

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = width;
        canvas.height = height;

        // 设置高质量的图像渲染
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';

        // 绘制图片
        ctx.drawImage(img, 0, 0, width, height);

        return canvas;
    }

    /**
     * 计算最优尺寸
     */
    calculateOptimalSize(originalWidth, originalHeight, maxWidth, maxHeight) {
        if (originalWidth <= maxWidth && originalHeight <= maxHeight) {
            return { width: originalWidth, height: originalHeight };
        }

        const ratio = Math.min(maxWidth / originalWidth, maxHeight / originalHeight);
        return {
            width: Math.round(originalWidth * ratio),
            height: Math.round(originalHeight * ratio)
        };
    }

    /**
     * 获取文件信息
     */
    getFileInfo(file) {
        return {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified,
            sizeFormatted: this.formatFileSize(file.size)
        };
    }

    /**
     * 格式化文件大小
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * 批量压缩多个文件
     */
    async compressMultiple(files, options = {}) {
        const results = [];
        for (const file of files) {
            try {
                const compressed = await this.compress(file, options);
                results.push({
                    original: file,
                    compressed: compressed,
                    success: true,
                    originalSize: file.size,
                    compressedSize: compressed.size,
                    compressionRatio: Math.round((1 - compressed.size / file.size) * 100)
                });
            } catch (error) {
                results.push({
                    original: file,
                    compressed: null,
                    success: false,
                    error: error.message
                });
            }
        }
        return results;
    }
}

// 全局实例
window.ImageCompressor = ImageCompressor;

// 默认压缩器实例
window.defaultImageCompressor = new ImageCompressor({
    maxWidth: window.CLIENT_MAX_WIDTH || 1920,
    maxHeight: window.CLIENT_MAX_HEIGHT || 1920,
    quality: window.CLIENT_COMPRESSION_QUALITY || 0.65,
    maxFileSize: window.MAX_FILE_SIZE || 2 * 1024 * 1024
});

/**
 * 便捷函数：压缩单个图片
 */
window.compressImage = function(file, options = {}) {
    return window.defaultImageCompressor.compress(file, options);
};

/**
 * 便捷函数：压缩多个图片
 */
window.compressImages = function(files, options = {}) {
    return window.defaultImageCompressor.compressMultiple(files, options);
};
