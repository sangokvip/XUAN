/**
 * 图片懒加载和WebP支持
 */
class LazyImageLoader {
    constructor() {
        this.observer = null;
        this.supportsWebP = false;
        this.init();
    }

    async init() {
        // 检测WebP支持
        this.supportsWebP = await this.checkWebPSupport();
        
        // 初始化Intersection Observer
        this.initObserver();
        
        // 处理现有图片
        this.processImages();
    }

    /**
     * 检测浏览器是否支持WebP
     */
    checkWebPSupport() {
        return new Promise((resolve) => {
            const webP = new Image();
            webP.onload = webP.onerror = () => {
                resolve(webP.height === 2);
            };
            webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        });
    }

    /**
     * 初始化Intersection Observer
     */
    initObserver() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
        }
    }

    /**
     * 处理页面中的图片
     */
    processImages() {
        const images = document.querySelectorAll('img[data-src], img[data-srcset]');
        
        images.forEach(img => {
            if (this.observer) {
                this.observer.observe(img);
            } else {
                // 降级处理：直接加载
                this.loadImage(img);
            }
        });
    }

    /**
     * 加载单个图片
     */
    loadImage(img) {
        // 创建新的图片元素用于预加载
        const imageLoader = new Image();
        
        imageLoader.onload = () => {
            // 图片加载成功后替换src
            if (img.dataset.srcset) {
                img.srcset = img.dataset.srcset;
            }
            if (img.dataset.src) {
                img.src = img.dataset.src;
            }
            
            // 添加加载完成的类
            img.classList.add('loaded');
            img.classList.remove('loading');
            
            // 移除data属性
            delete img.dataset.src;
            delete img.dataset.srcset;
        };

        imageLoader.onerror = () => {
            // 加载失败时的处理
            img.classList.add('error');
            img.classList.remove('loading');
            
            // 如果有fallback图片，使用fallback
            if (img.dataset.fallback) {
                img.src = img.dataset.fallback;
            }
        };

        // 开始加载
        img.classList.add('loading');
        
        // 优先使用WebP格式
        if (this.supportsWebP && img.dataset.webp) {
            imageLoader.src = img.dataset.webp;
        } else if (img.dataset.srcset) {
            imageLoader.srcset = img.dataset.srcset;
        } else if (img.dataset.src) {
            imageLoader.src = img.dataset.src;
        }
    }

    /**
     * 动态添加新图片到懒加载队列
     */
    addImage(img) {
        if (this.observer) {
            this.observer.observe(img);
        } else {
            this.loadImage(img);
        }
    }

    /**
     * 强制加载所有图片
     */
    loadAllImages() {
        const images = document.querySelectorAll('img[data-src], img[data-srcset]');
        images.forEach(img => this.loadImage(img));
    }
}

/**
 * 图片优化辅助函数
 */
window.ImageHelper = {
    /**
     * 生成响应式图片HTML
     */
    generateResponsiveImage(baseUrl, filename, alt = '', className = '', sizes = ['thumb', 'small', 'medium', 'large']) {
        const baseName = filename.replace(/\.[^/.]+$/, '');
        const supportsWebP = window.lazyLoader ? window.lazyLoader.supportsWebP : false;
        
        let srcset = [];
        let webpSrcset = [];
        
        sizes.forEach(size => {
            const width = this.getSizeWidth(size);
            srcset.push(`${baseUrl}/optimized/${size}/${baseName}.jpg ${width}w`);
            if (supportsWebP) {
                webpSrcset.push(`${baseUrl}/webp/${size}/${baseName}.webp ${width}w`);
            }
        });
        
        const defaultSrc = `${baseUrl}/optimized/medium/${baseName}.jpg`;
        const webpSrc = supportsWebP ? `${baseUrl}/webp/medium/${baseName}.webp` : null;
        
        return `
            <img 
                src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E"
                data-src="${defaultSrc}"
                data-srcset="${srcset.join(', ')}"
                ${webpSrc ? `data-webp="${webpSrc}"` : ''}
                alt="${alt}"
                class="lazy-image ${className}"
                loading="lazy"
            />
        `;
    },

    /**
     * 获取尺寸对应的宽度
     */
    getSizeWidth(size) {
        const widths = {
            'thumb': 150,
            'small': 300,
            'medium': 600,
            'large': 1200,
            'circle': 200
        };
        return widths[size] || 600;
    },

    /**
     * 生成圆形头像HTML
     */
    generateCircleAvatar(baseUrl, filename, alt = '', className = '') {
        const baseName = filename.replace(/\.[^/.]+$/, '');
        const supportsWebP = window.lazyLoader ? window.lazyLoader.supportsWebP : false;
        
        const defaultSrc = `${baseUrl}/optimized/circle/${baseName}.jpg`;
        const webpSrc = supportsWebP ? `${baseUrl}/webp/circle/${baseName}.webp` : null;
        
        return `
            <img 
                src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E"
                data-src="${defaultSrc}"
                ${webpSrc ? `data-webp="${webpSrc}"` : ''}
                alt="${alt}"
                class="lazy-image circle-avatar ${className}"
                loading="lazy"
            />
        `;
    }
};

// 初始化懒加载
document.addEventListener('DOMContentLoaded', () => {
    window.lazyLoader = new LazyImageLoader();
});

// 为动态内容提供重新扫描功能
window.addEventListener('load', () => {
    // 监听DOM变化，自动处理新添加的图片
    if ('MutationObserver' in window) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const images = node.querySelectorAll ? 
                            node.querySelectorAll('img[data-src], img[data-srcset]') : 
                            [];
                        
                        images.forEach(img => {
                            if (window.lazyLoader) {
                                window.lazyLoader.addImage(img);
                            }
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});
