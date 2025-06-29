/**
 * 增强的图片上传组件
 * 集成图片压缩、预览、进度显示等功能
 */
class EnhancedImageUpload {
    constructor(inputElement, options = {}) {
        this.input = typeof inputElement === 'string' ? 
            document.querySelector(inputElement) : inputElement;
        
        if (!this.input) {
            throw new Error('找不到指定的input元素');
        }

        this.options = {
            previewContainer: null,
            progressContainer: null,
            enableCompression: true,
            enablePreview: true,
            enableProgress: true,
            maxFiles: 1,
            acceptedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            onFileSelect: null,
            onCompressionStart: null,
            onCompressionComplete: null,
            onError: null,
            ...options
        };

        this.compressor = new ImageCompressor();
        this.selectedFiles = [];
        this.compressedFiles = [];

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.createPreviewContainer();
        this.createProgressContainer();
    }

    setupEventListeners() {
        this.input.addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files);
        });

        // 拖拽支持
        if (this.options.enableDragDrop !== false) {
            this.setupDragDrop();
        }
    }

    setupDragDrop() {
        const dropZone = this.input.parentElement;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drag-over');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFileSelect(files);
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    createPreviewContainer() {
        if (!this.options.enablePreview) return;

        let container = this.options.previewContainer;
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }

        if (!container) {
            container = document.createElement('div');
            container.className = 'image-preview-container';
            this.input.parentElement.appendChild(container);
        }

        this.previewContainer = container;
    }

    createProgressContainer() {
        if (!this.options.enableProgress) return;

        let container = this.options.progressContainer;
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }

        if (!container) {
            container = document.createElement('div');
            container.className = 'upload-progress-container';
            container.style.display = 'none';
            this.input.parentElement.appendChild(container);
        }

        this.progressContainer = container;
    }

    async handleFileSelect(files) {
        const fileArray = Array.from(files);
        
        // 验证文件
        const validFiles = this.validateFiles(fileArray);
        if (validFiles.length === 0) return;

        this.selectedFiles = validFiles;
        
        if (this.options.onFileSelect) {
            this.options.onFileSelect(validFiles);
        }

        // 显示预览
        if (this.options.enablePreview) {
            this.showPreviews(validFiles);
        }

        // 压缩图片
        if (this.options.enableCompression) {
            await this.compressFiles(validFiles);
        }
    }

    validateFiles(files) {
        const validFiles = [];
        
        for (const file of files) {
            if (!this.options.acceptedTypes.includes(file.type)) {
                this.showError(`不支持的文件类型: ${file.name}`);
                continue;
            }

            if (file.size > (window.MAX_FILE_SIZE || 5 * 1024 * 1024)) {
                this.showError(`文件太大: ${file.name}`);
                continue;
            }

            validFiles.push(file);
        }

        if (validFiles.length > this.options.maxFiles) {
            this.showError(`最多只能选择 ${this.options.maxFiles} 个文件`);
            return validFiles.slice(0, this.options.maxFiles);
        }

        return validFiles;
    }

    showPreviews(files) {
        if (!this.previewContainer) return;

        this.previewContainer.innerHTML = '';

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = this.createPreviewElement(e.target.result, file, index);
                this.previewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }

    createPreviewElement(src, file, index) {
        const div = document.createElement('div');
        div.className = 'image-preview-item';
        div.innerHTML = `
            <img src="${src}" alt="预览" class="preview-image">
            <div class="preview-info">
                <div class="file-name">${file.name}</div>
                <div class="file-size">${this.formatFileSize(file.size)}</div>
                <div class="compression-status" id="compression-status-${index}">
                    准备压缩...
                </div>
            </div>
            <button type="button" class="remove-preview" onclick="this.parentElement.remove()">
                ×
            </button>
        `;
        return div;
    }

    async compressFiles(files) {
        if (this.options.onCompressionStart) {
            this.options.onCompressionStart(files);
        }

        this.showProgress('开始压缩图片...');
        this.compressedFiles = [];

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const statusElement = document.getElementById(`compression-status-${i}`);
            
            try {
                if (statusElement) {
                    statusElement.textContent = '压缩中...';
                    statusElement.className = 'compression-status compressing';
                }

                const compressed = await this.compressor.compress(file);
                this.compressedFiles.push(compressed);

                const compressionRatio = Math.round((1 - compressed.size / file.size) * 100);
                
                if (statusElement) {
                    statusElement.textContent = `已压缩 ${compressionRatio}% (${this.formatFileSize(compressed.size)})`;
                    statusElement.className = 'compression-status compressed';
                }

            } catch (error) {
                console.error('压缩失败:', error);
                this.compressedFiles.push(file); // 使用原文件
                
                if (statusElement) {
                    statusElement.textContent = '压缩失败，使用原图';
                    statusElement.className = 'compression-status error';
                }
            }
        }

        this.hideProgress();

        if (this.options.onCompressionComplete) {
            this.options.onCompressionComplete(this.compressedFiles, this.selectedFiles);
        }
    }

    showProgress(message) {
        if (!this.progressContainer) return;
        
        this.progressContainer.innerHTML = `
            <div class="progress-message">${message}</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        `;
        this.progressContainer.style.display = 'block';
    }

    hideProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'none';
        }
    }

    showError(message) {
        if (this.options.onError) {
            this.options.onError(message);
        } else {
            alert(message);
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    getCompressedFiles() {
        return this.compressedFiles;
    }

    getOriginalFiles() {
        return this.selectedFiles;
    }

    reset() {
        this.selectedFiles = [];
        this.compressedFiles = [];
        if (this.previewContainer) {
            this.previewContainer.innerHTML = '';
        }
        this.hideProgress();
    }
}

// 全局注册
window.EnhancedImageUpload = EnhancedImageUpload;

// 自动初始化带有特定类名的input元素
document.addEventListener('DOMContentLoaded', function() {
    const enhancedInputs = document.querySelectorAll('.enhanced-image-upload');
    enhancedInputs.forEach(input => {
        new EnhancedImageUpload(input);
    });
});
