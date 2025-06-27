/**
 * 图片懒加载和优化显示
 */
class ImageOptimizer {
    constructor() {
        this.observer = null;
        this.init();
    }

    init() {
        // 检查浏览器支持
        if ('IntersectionObserver' in window) {
            this.setupIntersectionObserver();
        } else {
            // 降级处理：直接加载所有图片
            this.loadAllImages();
        }

        // 检测WebP支持
        this.detectWebPSupport();
    }

    /**
     * 设置交叉观察器（懒加载）
     */
    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, options);

        // 观察所有懒加载图片
        this.observeImages();
    }

    /**
     * 观察需要懒加载的图片
     */
    observeImages() {
        const lazyImages = document.querySelectorAll('img[data-src], picture[data-src]');
        lazyImages.forEach(img => {
            this.observer.observe(img);
        });
    }

    /**
     * 加载单个图片
     */
    loadImage(element) {
        if (element.tagName === 'IMG') {
            this.loadSingleImage(element);
        } else if (element.tagName === 'PICTURE') {
            this.loadPictureElement(element);
        }
    }

    /**
     * 加载单个img元素
     */
    loadSingleImage(img) {
        const src = img.dataset.src;
        if (src) {
            img.src = src;
            img.classList.add('loaded');
            
            // 移除data-src属性
            delete img.dataset.src;
            
            // 添加加载完成的淡入效果
            img.addEventListener('load', () => {
                img.classList.add('fade-in');
            });
        }
    }

    /**
     * 加载picture元素
     */
    loadPictureElement(picture) {
        const sources = picture.querySelectorAll('source[data-srcset]');
        const img = picture.querySelector('img[data-src]');

        // 加载source元素
        sources.forEach(source => {
            const srcset = source.dataset.srcset;
            if (srcset) {
                source.srcset = srcset;
                delete source.dataset.srcset;
            }
        });

        // 加载img元素
        if (img) {
            this.loadSingleImage(img);
        }

        picture.classList.add('loaded');
    }

    /**
     * 直接加载所有图片（降级处理）
     */
    loadAllImages() {
        const lazyImages = document.querySelectorAll('img[data-src], picture[data-src]');
        lazyImages.forEach(element => {
            this.loadImage(element);
        });
    }

    /**
     * 检测WebP支持
     */
    detectWebPSupport() {
        const webP = new Image();
        webP.onload = webP.onerror = () => {
            const isSupported = (webP.height === 2);
            document.documentElement.classList.toggle('webp-support', isSupported);
            document.documentElement.classList.toggle('no-webp-support', !isSupported);
        };
        webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    }

    /**
     * 预加载关键图片
     */
    preloadCriticalImages() {
        const criticalImages = document.querySelectorAll('.critical-image');
        criticalImages.forEach(img => {
            if (img.dataset.src) {
                this.loadImage(img);
            }
        });
    }

    /**
     * 添加图片到懒加载队列
     */
    addToLazyLoad(element) {
        if (this.observer) {
            this.observer.observe(element);
        } else {
            this.loadImage(element);
        }
    }
}

/**
 * 图片加载错误处理
 */
function handleImageError(img) {
    // 如果是WebP图片加载失败，尝试加载原格式
    if (img.src.includes('.webp')) {
        const fallbackSrc = img.src.replace('.webp', '.jpg');
        img.src = fallbackSrc;
        return;
    }

    // 如果是缩略图加载失败，尝试加载原图
    if (img.src.includes('_small') || img.src.includes('_medium') || img.src.includes('_large')) {
        const originalSrc = img.src.replace(/_small|_medium|_large/, '');
        img.src = originalSrc;
        return;
    }

    // 最后使用默认头像
    const isReader = img.closest('.reader-card, .reader-photo, .reader-profile');
    if (isReader) {
        img.src = 'img/m1.jpg'; // 默认占卜师头像
    } else {
        img.src = 'img/nm.jpg'; // 默认用户头像
    }
}

/**
 * 创建响应式图片HTML
 */
function createResponsiveImage(imagePath, alt, size = 'medium', className = '') {
    const pathInfo = getImagePathInfo(imagePath);
    if (!pathInfo) return '';

    const webpSupported = document.documentElement.classList.contains('webp-support');
    const thumbnailPath = `${pathInfo.dir}/${pathInfo.name}_${size}.${pathInfo.ext}`;
    const webpThumbnailPath = `${pathInfo.dir}/${pathInfo.name}_${size}.webp`;

    if (webpSupported) {
        return `
            <picture class="${className}">
                <source data-srcset="${webpThumbnailPath}" type="image/webp">
                <img data-src="${thumbnailPath}" alt="${alt}" class="lazy-image" onerror="handleImageError(this)">
            </picture>
        `;
    } else {
        return `<img data-src="${thumbnailPath}" alt="${alt}" class="lazy-image ${className}" onerror="handleImageError(this)">`;
    }
}

/**
 * 解析图片路径信息
 */
function getImagePathInfo(imagePath) {
    if (!imagePath) return null;
    
    const lastSlash = imagePath.lastIndexOf('/');
    const lastDot = imagePath.lastIndexOf('.');
    
    if (lastDot === -1) return null;
    
    return {
        dir: lastSlash === -1 ? '' : imagePath.substring(0, lastSlash),
        name: imagePath.substring(lastSlash + 1, lastDot),
        ext: imagePath.substring(lastDot + 1)
    };
}

// 初始化图片优化器
document.addEventListener('DOMContentLoaded', () => {
    const imageOptimizer = new ImageOptimizer();
    
    // 预加载关键图片
    imageOptimizer.preloadCriticalImages();
    
    // 全局暴露，供其他脚本使用
    window.imageOptimizer = imageOptimizer;
});

// CSS样式（内联）
const style = document.createElement('style');
style.textContent = `
    .lazy-image {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .lazy-image.loaded {
        opacity: 1;
    }
    
    .lazy-image.fade-in {
        opacity: 1;
    }
    
    /* 加载占位符 */
    .lazy-image:not(.loaded) {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    
    /* 响应式图片容器 */
    picture {
        display: block;
    }
    
    picture img {
        width: 100%;
        height: auto;
    }
`;
document.head.appendChild(style);
