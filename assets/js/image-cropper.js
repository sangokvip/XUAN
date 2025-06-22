/**
 * 图片剪裁工具
 * 用于创建圆形头像
 */
class ImageCropper {
    constructor(options = {}) {
        this.options = {
            containerSelector: '#image-cropper-modal',
            previewSelector: '#cropper-preview',
            canvasSelector: '#cropper-canvas',
            cropSize: 300,
            quality: 0.8,
            ...options
        };
        
        this.canvas = null;
        this.ctx = null;
        this.image = null;
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.offsetX = 0;
        this.offsetY = 0;
        this.scale = 1;
        this.minScale = 0.1;
        this.maxScale = 3;
        
        this.init();
    }
    
    init() {
        this.createModal();
        this.bindEvents();
    }
    
    createModal() {
        const modalHTML = `
            <div id="image-cropper-modal" class="cropper-modal" style="display: none;">
                <div class="cropper-overlay"></div>
                <div class="cropper-container">
                    <div class="cropper-header">
                        <h3>剪裁头像</h3>
                        <button type="button" class="cropper-close">&times;</button>
                    </div>
                    <div class="cropper-body">
                        <div class="cropper-canvas-container">
                            <canvas id="cropper-canvas"></canvas>
                            <div class="cropper-circle-overlay"></div>
                        </div>
                        <div class="cropper-controls">
                            <div class="cropper-zoom">
                                <label>缩放:</label>
                                <input type="range" id="cropper-zoom" min="0.1" max="3" step="0.1" value="1">
                            </div>
                            <div class="cropper-preview-container">
                                <div class="cropper-preview-label">预览:</div>
                                <div id="cropper-preview" class="cropper-preview"></div>
                            </div>
                        </div>
                    </div>
                    <div class="cropper-footer">
                        <button type="button" class="btn btn-secondary cropper-cancel">取消</button>
                        <button type="button" class="btn btn-primary cropper-confirm">确认剪裁</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        this.modal = document.getElementById('image-cropper-modal');
        this.canvas = document.getElementById('cropper-canvas');
        this.ctx = this.canvas.getContext('2d');
        this.preview = document.getElementById('cropper-preview');
        this.zoomSlider = document.getElementById('cropper-zoom');
        
        // 设置画布大小
        this.canvas.width = this.options.cropSize;
        this.canvas.height = this.options.cropSize;
    }
    
    bindEvents() {
        // 关闭模态框
        this.modal.querySelector('.cropper-close').addEventListener('click', () => this.hide());
        this.modal.querySelector('.cropper-cancel').addEventListener('click', () => this.hide());
        this.modal.querySelector('.cropper-overlay').addEventListener('click', () => this.hide());
        
        // 确认剪裁
        this.modal.querySelector('.cropper-confirm').addEventListener('click', () => this.crop());
        
        // 缩放控制
        this.zoomSlider.addEventListener('input', (e) => {
            this.scale = parseFloat(e.target.value);
            this.draw();
            this.updatePreview();
        });
        
        // 鼠标拖拽
        this.canvas.addEventListener('mousedown', (e) => this.startDrag(e));
        this.canvas.addEventListener('mousemove', (e) => this.drag(e));
        this.canvas.addEventListener('mouseup', () => this.endDrag());
        this.canvas.addEventListener('mouseleave', () => this.endDrag());
        
        // 触摸事件
        this.canvas.addEventListener('touchstart', (e) => this.startDrag(e.touches[0]));
        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            this.drag(e.touches[0]);
        });
        this.canvas.addEventListener('touchend', () => this.endDrag());
        
        // 鼠标滚轮缩放
        this.canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            this.scale = Math.max(this.minScale, Math.min(this.maxScale, this.scale + delta));
            this.zoomSlider.value = this.scale;
            this.draw();
            this.updatePreview();
        });
    }
    
    show(file) {
        return new Promise((resolve, reject) => {
            this.resolve = resolve;
            this.reject = reject;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                this.image = new Image();
                this.image.onload = () => {
                    this.resetPosition();
                    this.draw();
                    this.updatePreview();
                    this.modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                };
                this.image.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
    
    hide() {
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
        if (this.reject) {
            this.reject('cancelled');
        }
    }
    
    resetPosition() {
        this.scale = 1;
        this.zoomSlider.value = 1;
        
        // 计算初始位置，使图片居中
        const canvasSize = this.options.cropSize;
        const imgAspect = this.image.width / this.image.height;
        
        if (imgAspect > 1) {
            // 横向图片
            this.scale = canvasSize / this.image.height;
            this.offsetX = (canvasSize - this.image.width * this.scale) / 2;
            this.offsetY = 0;
        } else {
            // 纵向图片
            this.scale = canvasSize / this.image.width;
            this.offsetX = 0;
            this.offsetY = (canvasSize - this.image.height * this.scale) / 2;
        }
        
        this.zoomSlider.value = this.scale;
    }
    
    startDrag(e) {
        this.isDragging = true;
        const rect = this.canvas.getBoundingClientRect();
        this.startX = (e.clientX || e.pageX) - rect.left - this.offsetX;
        this.startY = (e.clientY || e.pageY) - rect.top - this.offsetY;
        this.canvas.style.cursor = 'grabbing';
    }
    
    drag(e) {
        if (!this.isDragging) return;
        
        const rect = this.canvas.getBoundingClientRect();
        this.offsetX = (e.clientX || e.pageX) - rect.left - this.startX;
        this.offsetY = (e.clientY || e.pageY) - rect.top - this.startY;
        
        this.draw();
        this.updatePreview();
    }
    
    endDrag() {
        this.isDragging = false;
        this.canvas.style.cursor = 'grab';
    }
    
    draw() {
        if (!this.image) return;
        
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // 绘制图片
        this.ctx.drawImage(
            this.image,
            this.offsetX,
            this.offsetY,
            this.image.width * this.scale,
            this.image.height * this.scale
        );
    }
    
    updatePreview() {
        if (!this.image) return;
        
        // 创建预览画布
        const previewCanvas = document.createElement('canvas');
        const previewCtx = previewCanvas.getContext('2d');
        const previewSize = 100;
        
        previewCanvas.width = previewSize;
        previewCanvas.height = previewSize;
        
        // 绘制圆形剪裁预览
        previewCtx.save();
        previewCtx.beginPath();
        previewCtx.arc(previewSize / 2, previewSize / 2, previewSize / 2, 0, Math.PI * 2);
        previewCtx.clip();
        
        // 计算缩放比例
        const scale = previewSize / this.options.cropSize;
        
        previewCtx.drawImage(
            this.image,
            this.offsetX * scale,
            this.offsetY * scale,
            this.image.width * this.scale * scale,
            this.image.height * this.scale * scale
        );
        
        previewCtx.restore();
        
        // 更新预览
        this.preview.innerHTML = '';
        this.preview.appendChild(previewCanvas);
    }
    
    crop() {
        if (!this.image) return;
        
        // 创建最终的圆形头像
        const finalCanvas = document.createElement('canvas');
        const finalCtx = finalCanvas.getContext('2d');
        const size = this.options.cropSize;
        
        finalCanvas.width = size;
        finalCanvas.height = size;
        
        // 绘制圆形剪裁
        finalCtx.save();
        finalCtx.beginPath();
        finalCtx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
        finalCtx.clip();
        
        finalCtx.drawImage(
            this.image,
            this.offsetX,
            this.offsetY,
            this.image.width * this.scale,
            this.image.height * this.scale
        );
        
        finalCtx.restore();
        
        // 转换为Blob
        finalCanvas.toBlob((blob) => {
            this.hide();
            if (this.resolve) {
                this.resolve(blob);
            }
        }, 'image/jpeg', this.options.quality);
    }
}

// 全局实例
window.imageCropper = new ImageCropper();
