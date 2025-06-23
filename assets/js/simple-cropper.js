/**
 * 简化版图片剪裁工具
 * 用于创建圆形头像的备用方案
 */
class SimpleCropper {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.image = null;
    }
    
    /**
     * 将图片剪裁为圆形
     * @param {File} file 图片文件
     * @param {number} size 输出尺寸，默认300
     * @returns {Promise<Blob>} 剪裁后的圆形图片
     */
    cropToCircle(file, size = 300) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const img = new Image();
                
                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        canvas.width = size;
                        canvas.height = size;
                        
                        // 计算图片的缩放和位置
                        const scale = Math.max(size / img.width, size / img.height);
                        const scaledWidth = img.width * scale;
                        const scaledHeight = img.height * scale;
                        const x = (size - scaledWidth) / 2;
                        const y = (size - scaledHeight) / 2;
                        
                        // 创建圆形剪裁路径
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
                        ctx.clip();
                        
                        // 绘制图片
                        ctx.drawImage(img, x, y, scaledWidth, scaledHeight);
                        ctx.restore();
                        
                        // 转换为Blob
                        canvas.toBlob((blob) => {
                            if (blob) {
                                resolve(blob);
                            } else {
                                reject(new Error('无法生成图片'));
                            }
                        }, 'image/jpeg', 0.8);
                        
                    } catch (error) {
                        reject(error);
                    }
                };
                
                img.onerror = () => {
                    reject(new Error('图片加载失败'));
                };
                
                img.src = e.target.result;
            };
            
            reader.onerror = () => {
                reject(new Error('文件读取失败'));
            };
            
            reader.readAsDataURL(file);
        });
    }
    
    /**
     * 显示剪裁对话框（简化版）
     * @param {File} file 图片文件
     * @returns {Promise<Blob>} 剪裁后的图片
     */
    show(file) {
        return new Promise((resolve, reject) => {
            // 创建简单的确认对话框
            const confirmed = confirm('是否将图片剪裁为圆形头像？\n\n点击"确定"自动剪裁，点击"取消"跳过剪裁。');
            
            if (confirmed) {
                this.cropToCircle(file)
                    .then(resolve)
                    .catch(reject);
            } else {
                reject('cancelled');
            }
        });
    }
}

// 创建全局实例作为备用
window.simpleCropper = new SimpleCropper();

// 如果主要的图片剪裁工具没有加载，使用简化版本
if (!window.imageCropper) {
    console.log('使用简化版图片剪裁工具');
    window.imageCropper = window.simpleCropper;
}
