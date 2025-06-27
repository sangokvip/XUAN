/**
 * 页面浏览奖励系统
 * 监控页面浏览时间，自动发放Tata Coin奖励
 */
class BrowseRewardSystem {
    constructor() {
        this.startTime = Date.now();
        this.minBrowseTime = 5000; // 5秒
        this.checkInterval = 1000; // 1秒检查一次
        this.hasRewarded = false;
        this.isActive = true;
        this.pageUrl = window.location.href;
        this.pageTitle = document.title;
        
        this.init();
    }
    
    init() {
        // 检查用户是否登录
        if (!this.isUserLoggedIn()) {
            return;
        }
        
        // 开始监控
        this.startMonitoring();
        
        // 监听页面可见性变化
        this.handleVisibilityChange();
        
        // 监听页面卸载
        this.handlePageUnload();
    }
    
    isUserLoggedIn() {
        // 检查是否有登录相关的元素或cookie
        return document.querySelector('.user-info') !== null || 
               document.querySelector('.reader-header') !== null ||
               document.cookie.includes('user_logged_in') ||
               document.cookie.includes('reader_logged_in');
    }
    
    startMonitoring() {
        this.monitorInterval = setInterval(() => {
            if (!this.isActive || this.hasRewarded) {
                return;
            }
            
            const currentTime = Date.now();
            const browseTime = currentTime - this.startTime;
            
            if (browseTime >= this.minBrowseTime) {
                this.sendRewardRequest(Math.floor(browseTime / 1000));
            }
        }, this.checkInterval);
    }
    
    handleVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.isActive = false;
            } else {
                this.isActive = true;
                // 重新开始计时
                this.startTime = Date.now();
            }
        });
    }
    
    handlePageUnload() {
        window.addEventListener('beforeunload', () => {
            if (this.monitorInterval) {
                clearInterval(this.monitorInterval);
            }
        });
    }
    
    async sendRewardRequest(browseTime) {
        if (this.hasRewarded) {
            return;
        }
        
        try {
            // 动态获取API路径
            const siteUrl = window.SITE_URL || '';
            const apiUrl = siteUrl ? `${siteUrl}/api/browse_reward.php` : '/api/browse_reward.php';

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    page_url: this.pageUrl,
                    page_title: this.pageTitle,
                    browse_time: browseTime
                })
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const result = await response.json();
            
            if (result.success && result.reward > 0) {
                this.showRewardNotification(result.reward, result.stats);
            }
            
            // 标记已处理，避免重复请求
            this.hasRewarded = true;
            
            // 停止监控
            if (this.monitorInterval) {
                clearInterval(this.monitorInterval);
            }
            
        } catch (error) {
            console.log('Browse reward request failed:', error);
        }
    }
    
    showRewardNotification(reward, stats) {
        // 创建无感提示
        const notification = document.createElement('div');
        notification.className = 'browse-reward-notification';
        notification.innerHTML = `
            <div class="reward-content">
                <span class="reward-icon">💰</span>
                <span class="reward-text">+${reward} Tata Coin</span>
                <span class="reward-remaining">(今日剩余: ${stats.today_remaining})</span>
            </div>
        `;
        
        // 添加样式
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            pointer-events: none;
        `;
        
        document.body.appendChild(notification);
        
        // 显示动画
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // 自动隐藏
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', () => {
    // 延迟初始化，避免影响页面加载性能
    setTimeout(() => {
        new BrowseRewardSystem();
    }, 1000);
});

// 导出类供其他脚本使用
window.BrowseRewardSystem = BrowseRewardSystem;
