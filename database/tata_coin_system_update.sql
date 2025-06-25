-- Tata Coin 经济体系升级 SQL
-- 添加签到系统、浏览奖励等新功能所需的数据表

-- 每日签到记录表
CREATE TABLE IF NOT EXISTS daily_check_ins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    consecutive_days INT NOT NULL DEFAULT 1,
    reward_coins INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, check_in_date),
    INDEX idx_date (check_in_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, check_in_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表';

-- 页面浏览奖励记录表
CREATE TABLE IF NOT EXISTS page_browse_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    reward_coins INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_ip_date (ip_address, created_at),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面浏览奖励记录表';

-- 用户等级表（可选，用于缓存等级信息）
CREATE TABLE IF NOT EXISTS user_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('user', 'reader') NOT NULL,
    level INT NOT NULL DEFAULT 1,
    level_name VARCHAR(50) NOT NULL,
    total_spent INT DEFAULT 0 COMMENT '累计消费（用户）',
    total_earned INT DEFAULT 0 COMMENT '累计收入（塔罗师）',
    discount_rate INT DEFAULT 0 COMMENT '折扣率（%）',
    priority_score INT DEFAULT 0 COMMENT '优先级分数（塔罗师）',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_level (level),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_level (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户等级表';

-- 更新 tata_coin_transactions 表，添加新的交易类型
ALTER TABLE tata_coin_transactions 
MODIFY COLUMN transaction_type ENUM(
    'earn', 'spend', 'admin_add', 'admin_subtract', 'transfer',
    'daily_checkin', 'browse_reward', 'profile_completion', 
    'invitation_reward', 'level_bonus'
) NOT NULL COMMENT '交易类型';

-- 插入新的系统设置
INSERT INTO settings (setting_key, setting_value, description) VALUES 
('daily_browse_limit', '10', '每日浏览奖励上限'),
('profile_completion_reward', '20', '完善资料奖励金额'),
('invitation_user_reward', '20', '邀请用户奖励'),
('invitation_reader_reward', '50', '邀请塔罗师奖励'),
('daily_earning_limit', '30', '每日非付费获取上限'),
('reader_commission_rate', '50', '塔罗师分成比例（%）'),
('featured_reader_cost', '30', '查看推荐塔罗师费用'),
('normal_reader_cost', '10', '查看普通塔罗师费用'),
('new_user_tata_coin', '100', '新用户注册赠送金额')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 创建触发器：自动更新用户等级（可选）
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS update_user_level_after_transaction
AFTER INSERT ON tata_coin_transactions
FOR EACH ROW
BEGIN
    DECLARE total_spent INT DEFAULT 0;
    DECLARE total_earned INT DEFAULT 0;
    DECLARE new_level INT DEFAULT 1;
    DECLARE new_level_name VARCHAR(50) DEFAULT '新手';
    DECLARE new_discount INT DEFAULT 0;
    DECLARE new_priority INT DEFAULT 0;
    
    IF NEW.user_type = 'user' THEN
        -- 计算用户累计消费
        SELECT COALESCE(SUM(ABS(amount)), 0) INTO total_spent
        FROM tata_coin_transactions 
        WHERE user_id = NEW.user_id AND user_type = 'user' AND amount < 0;
        
        -- 计算用户等级
        IF total_spent >= 1000 THEN
            SET new_level = 5, new_level_name = '大师', new_discount = 20;
        ELSEIF total_spent >= 500 THEN
            SET new_level = 4, new_level_name = '专家', new_discount = 15;
        ELSEIF total_spent >= 200 THEN
            SET new_level = 3, new_level_name = '熟练', new_discount = 10;
        ELSEIF total_spent >= 50 THEN
            SET new_level = 2, new_level_name = '进阶', new_discount = 5;
        END IF;
        
        -- 更新或插入用户等级
        INSERT INTO user_levels (user_id, user_type, level, level_name, total_spent, discount_rate)
        VALUES (NEW.user_id, 'user', new_level, new_level_name, total_spent, new_discount)
        ON DUPLICATE KEY UPDATE
            level = new_level,
            level_name = new_level_name,
            total_spent = total_spent,
            discount_rate = new_discount;
            
    ELSEIF NEW.user_type = 'reader' THEN
        -- 计算塔罗师累计收入
        SELECT COALESCE(SUM(amount), 0) INTO total_earned
        FROM tata_coin_transactions 
        WHERE user_id = NEW.user_id AND user_type = 'reader' AND amount > 0;
        
        -- 获取塔罗师评价信息
        SELECT COALESCE(average_rating, 0), COALESCE(total_reviews, 0)
        INTO @avg_rating, @total_reviews
        FROM readers WHERE id = NEW.user_id;
        
        -- 计算塔罗师等级
        IF total_earned >= 1000 AND @avg_rating >= 4.5 AND @total_reviews >= 50 THEN
            SET new_level = 5, new_level_name = '大师级塔罗师', new_priority = 100;
        ELSEIF total_earned >= 500 AND @avg_rating >= 4.0 AND @total_reviews >= 20 THEN
            SET new_level = 4, new_level_name = '专业塔罗师', new_priority = 80;
        ELSEIF total_earned >= 200 AND @avg_rating >= 3.5 AND @total_reviews >= 10 THEN
            SET new_level = 3, new_level_name = '资深塔罗师', new_priority = 60;
        ELSEIF total_earned >= 50 AND @avg_rating >= 3.0 AND @total_reviews >= 5 THEN
            SET new_level = 2, new_level_name = '认证塔罗师', new_priority = 40;
        ELSE
            SET new_level = 1, new_level_name = '新人塔罗师', new_priority = 0;
        END IF;
        
        -- 更新或插入塔罗师等级
        INSERT INTO user_levels (user_id, user_type, level, level_name, total_earned, priority_score)
        VALUES (NEW.user_id, 'reader', new_level, new_level_name, total_earned, new_priority)
        ON DUPLICATE KEY UPDATE
            level = new_level,
            level_name = new_level_name,
            total_earned = total_earned,
            priority_score = new_priority;
    END IF;
END$$

DELIMITER ;

-- 创建清理过期数据的存储过程
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS CleanupOldRewards()
BEGIN
    -- 清理30天前的浏览奖励记录
    DELETE FROM page_browse_rewards 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- 清理90天前的签到记录
    DELETE FROM daily_check_ins 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- 清理180天前的交易记录（保留重要交易）
    DELETE FROM tata_coin_transactions 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
    AND transaction_type IN ('browse_reward', 'daily_checkin');
END$$

DELIMITER ;

-- 创建获取用户统计信息的视图
CREATE OR REPLACE VIEW user_coin_stats AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.tata_coin as current_balance,
    COALESCE(ul.level, 1) as user_level,
    COALESCE(ul.level_name, '新手') as level_name,
    COALESCE(ul.discount_rate, 0) as discount_rate,
    COALESCE(spent.total_spent, 0) as total_spent,
    COALESCE(earned.total_earned, 0) as total_earned,
    COALESCE(checkins.consecutive_days, 0) as current_checkin_streak,
    COALESCE(checkins.last_checkin, NULL) as last_checkin_date
FROM users u
LEFT JOIN user_levels ul ON u.id = ul.user_id AND ul.user_type = 'user'
LEFT JOIN (
    SELECT user_id, SUM(ABS(amount)) as total_spent
    FROM tata_coin_transactions 
    WHERE user_type = 'user' AND amount < 0
    GROUP BY user_id
) spent ON u.id = spent.user_id
LEFT JOIN (
    SELECT user_id, SUM(amount) as total_earned
    FROM tata_coin_transactions 
    WHERE user_type = 'user' AND amount > 0
    GROUP BY user_id
) earned ON u.id = earned.user_id
LEFT JOIN (
    SELECT user_id, consecutive_days, check_in_date as last_checkin
    FROM daily_check_ins dc1
    WHERE dc1.check_in_date = (
        SELECT MAX(dc2.check_in_date) 
        FROM daily_check_ins dc2 
        WHERE dc2.user_id = dc1.user_id
    )
) checkins ON u.id = checkins.user_id;

-- 创建塔罗师统计信息的视图
CREATE OR REPLACE VIEW reader_coin_stats AS
SELECT 
    r.id,
    r.username,
    r.full_name,
    r.tata_coin as current_balance,
    COALESCE(ul.level, 1) as reader_level,
    COALESCE(ul.level_name, '新人塔罗师') as level_name,
    COALESCE(ul.priority_score, 0) as priority_score,
    COALESCE(earned.total_earned, 0) as total_earned,
    COALESCE(earned.monthly_earned, 0) as monthly_earned,
    r.average_rating,
    r.total_reviews,
    r.view_count
FROM readers r
LEFT JOIN user_levels ul ON r.id = ul.user_id AND ul.user_type = 'reader'
LEFT JOIN (
    SELECT 
        user_id, 
        SUM(amount) as total_earned,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as monthly_earned
    FROM tata_coin_transactions 
    WHERE user_type = 'reader' AND amount > 0
    GROUP BY user_id
) earned ON r.id = earned.user_id;
