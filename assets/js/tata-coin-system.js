/**
 * Tata Coin 经济体系前端管理
 * 处理签到、浏览奖励、等级显示等功能
 */

class TataCoinSystem {
    constructor() {
        this.apiBase = window.SITE_URL || '';
        this.userId = window.USER_ID || null;
        this.userType = window.USER_TYPE || 'user';
        this.init();
    }

    init() {
        // 页面加载完成后初始化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.onPageLoad());
        } else {
            this.onPageLoad();
        }
    }

    onPageLoad() {
        // 如果用户已登录，执行相关功能
        if (this.userId) {
            this.handlePageBrowseReward();
            this.initCheckInButton();
            this.updateCoinDisplay();
        }
    }

    /**
     * 处理页面浏览奖励
     */
    async handlePageBrowseReward() {
        // 页面停留5秒后发放浏览奖励
        setTimeout(async () => {
            try {
                const response = await fetch(`${this.apiBase}/api/tata_coin_actions.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'browse_reward',
                        page_url: window.location.pathname
                    })
                });

                const result = await response.json();
                if (result.success && result.reward > 0) {
                    this.showRewardNotification(`浏览奖励 +${result.reward} Tata Coin`, 'success');
                    this.updateCoinDisplay();
                }
            } catch (error) {
                console.log('浏览奖励请求失败:', error);
            }
        }, 5000); // 5秒后执行
    }

    /**
     * 初始化签到按钮
     */
    initCheckInButton() {
        const checkInBtn = document.getElementById('daily-checkin-btn');
        if (checkInBtn) {
            checkInBtn.addEventListener('click', () => this.handleDailyCheckIn());
            this.updateCheckInStatus();
        }
    }

    /**
     * 处理每日签到
     */
    async handleDailyCheckIn() {
        const checkInBtn = document.getElementById('daily-checkin-btn');
        if (checkInBtn) {
            checkInBtn.disabled = true;
            checkInBtn.textContent = '签到中...';
        }

        try {
            const response = await fetch(`${this.apiBase}/api/tata_coin_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'daily_checkin'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showRewardNotification(result.message, 'success');
                this.updateCoinDisplay();
                this.updateCheckInStatus();
                
                // 显示连续签到信息
                if (result.consecutive_days > 1) {
                    this.showCheckInStreak(result.consecutive_days, result.reward);
                }
            } else {
                this.showRewardNotification(result.message, 'warning');
            }
        } catch (error) {
            this.showRewardNotification('签到失败，请稍后重试', 'error');
        } finally {
            if (checkInBtn) {
                checkInBtn.disabled = false;
                checkInBtn.textContent = '每日签到';
            }
        }
    }

    /**
     * 更新签到状态
     */
    async updateCheckInStatus() {
        try {
            const response = await fetch(`${this.apiBase}/api/tata_coin_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_checkin_status'
                })
            });

            const result = await response.json();
            const checkInBtn = document.getElementById('daily-checkin-btn');
            
            if (checkInBtn) {
                if (result.checked_in_today) {
                    checkInBtn.disabled = true;
                    checkInBtn.textContent = '今日已签到';
                    checkInBtn.classList.add('checked-in');
                } else {
                    checkInBtn.disabled = false;
                    checkInBtn.textContent = '每日签到';
                    checkInBtn.classList.remove('checked-in');
                }
            }

            // 更新连续签到天数显示
            const streakElement = document.getElementById('checkin-streak');
            if (streakElement && result.consecutive_days > 0) {
                streakElement.textContent = `连续签到 ${result.consecutive_days} 天`;
            }
        } catch (error) {
            console.log('获取签到状态失败:', error);
        }
    }

    /**
     * 更新Tata Coin余额显示
     */
    async updateCoinDisplay() {
        try {
            const response = await fetch(`${this.apiBase}/api/tata_coin_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_balance'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                // 更新所有显示余额的元素
                const balanceElements = document.querySelectorAll('.tata-coin-balance');
                balanceElements.forEach(element => {
                    element.textContent = result.balance;
                });

                // 更新用户等级信息
                if (result.level_info) {
                    this.updateLevelDisplay(result.level_info);
                }
            }
        } catch (error) {
            console.log('获取余额失败:', error);
        }
    }

    /**
     * 更新等级显示
     */
    updateLevelDisplay(levelInfo) {
        const levelElement = document.getElementById('user-level');
        const levelNameElement = document.getElementById('user-level-name');
        const discountElement = document.getElementById('user-discount');

        if (levelElement) levelElement.textContent = `Lv.${levelInfo.level}`;
        if (levelNameElement) levelNameElement.textContent = levelInfo.level_name;
        if (discountElement && levelInfo.discount_rate > 0) {
            discountElement.textContent = `享受${levelInfo.discount_rate}%折扣`;
            discountElement.style.display = 'inline';
        }
    }

    /**
     * 显示奖励通知
     */
    showRewardNotification(message, type = 'success') {
        // 创建通知元素
        const notification = document.createElement('div');
        notification.className = `tata-coin-notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${type === 'success' ? '🎉' : type === 'warning' ? '⚠️' : '❌'}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;

        // 添加样式
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#4CAF50' : type === 'warning' ? '#FF9800' : '#F44336'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
            max-width: 300px;
            font-size: 14px;
        `;

        // 添加到页面
        document.body.appendChild(notification);

        // 3秒后自动移除
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    /**
     * 显示连续签到奖励
     */
    showCheckInStreak(consecutiveDays, reward) {
        const modal = document.createElement('div');
        modal.className = 'checkin-streak-modal';
        modal.innerHTML = `
            <div class="modal-overlay">
                <div class="modal-content">
                    <h3>🎉 连续签到奖励</h3>
                    <div class="streak-info">
                        <div class="streak-days">连续签到 ${consecutiveDays} 天</div>
                        <div class="streak-reward">获得 ${reward} Tata Coin</div>
                    </div>
                    <button class="close-modal-btn">确定</button>
                </div>
            </div>
        `;

        // 添加样式
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10001;
        `;

        document.body.appendChild(modal);

        // 关闭按钮事件
        modal.querySelector('.close-modal-btn').addEventListener('click', () => {
            document.body.removeChild(modal);
        });

        // 点击遮罩关闭
        modal.querySelector('.modal-overlay').addEventListener('click', (e) => {
            if (e.target === modal.querySelector('.modal-overlay')) {
                document.body.removeChild(modal);
            }
        });
    }

    /**
     * 完善资料奖励检查
     */
    async checkProfileCompletionReward() {
        try {
            const response = await fetch(`${this.apiBase}/api/tata_coin_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'profile_completion_reward'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showRewardNotification(result.message, 'success');
                this.updateCoinDisplay();
            }
        } catch (error) {
            console.log('完善资料奖励检查失败:', error);
        }
    }
}

// 添加CSS动画
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .checkin-streak-modal .modal-overlay {
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    .checkin-streak-modal .modal-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        max-width: 400px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .checkin-streak-modal h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 24px;
    }
    
    .streak-days {
        font-size: 18px;
        color: #666;
        margin-bottom: 10px;
    }
    
    .streak-reward {
        font-size: 20px;
        color: #4CAF50;
        font-weight: bold;
        margin-bottom: 20px;
    }
    
    .close-modal-btn {
        background: #4CAF50;
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .close-modal-btn:hover {
        background: #45a049;
    }
    
    .tata-coin-notification .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-icon {
        font-size: 18px;
    }
    
    .notification-message {
        flex: 1;
    }
`;
document.head.appendChild(style);

// 自动初始化
window.TataCoinSystem = TataCoinSystem;
window.tataCoinSystem = new TataCoinSystem();
