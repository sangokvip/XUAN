-- 塔罗师展示平台完整数据库结构
-- 适用于任何MySQL数据库，请先创建数据库再导入此文件

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女',
    avatar VARCHAR(255) DEFAULT NULL COMMENT '头像路径',
    tata_coin INT DEFAULT 100 COMMENT 'Tata Coin余额，新用户默认100',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理员表
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 塔罗师注册链接表
CREATE TABLE IF NOT EXISTS reader_registration_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    used_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 塔罗师表
CREATE TABLE IF NOT EXISTS readers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) COMMENT '电话号码',
    gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女',
    photo VARCHAR(255),
    photo_circle VARCHAR(255) DEFAULT NULL COMMENT '圆形头像（用于首页展示）',
    certificates TEXT DEFAULT NULL COMMENT '证书图片路径（JSON格式）',
    price_list_image VARCHAR(255),
    experience_years INT NOT NULL DEFAULT 0,
    specialties TEXT,
    custom_specialties VARCHAR(500) DEFAULT NULL COMMENT '自定义专长标签（最多3个，每个最多4字符）',
    description TEXT,
    contact_info TEXT COMMENT '联系信息描述',
    wechat VARCHAR(100) DEFAULT NULL COMMENT '微信号',
    qq VARCHAR(50) DEFAULT NULL COMMENT 'QQ号',
    xiaohongshu VARCHAR(100) DEFAULT NULL COMMENT '小红书账号',
    douyin VARCHAR(100) DEFAULT NULL COMMENT '抖音账号',
    other_contact TEXT DEFAULT NULL COMMENT '其他联系方式',
    view_count INT DEFAULT 0 COMMENT '查看次数',
    average_rating DECIMAL(3,2) DEFAULT 0.00 COMMENT '平均评分',
    total_reviews INT DEFAULT 0 COMMENT '总评价数',
    tata_coin INT DEFAULT 0 COMMENT 'Tata Coin余额，塔罗师默认0',
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    registration_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户查看塔罗师联系方式记录表
CREATE TABLE IF NOT EXISTS contact_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reader_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reader (user_id, reader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系统设置表
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 登录尝试记录表
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tata Coin交易记录表
CREATE TABLE IF NOT EXISTS tata_coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型：user-普通用户，reader-塔罗师',
    transaction_type ENUM('earn', 'spend', 'admin_add', 'admin_subtract', 'transfer') NOT NULL COMMENT '交易类型',
    amount INT NOT NULL COMMENT '金额（正数为收入，负数为支出）',
    balance_after INT NOT NULL COMMENT '交易后余额',
    description TEXT COMMENT '交易描述',
    related_user_id INT DEFAULT NULL COMMENT '关联用户ID（如转账对象）',
    related_user_type ENUM('user', 'reader') DEFAULT NULL COMMENT '关联用户类型',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_type (transaction_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tata Coin交易记录表';

-- 用户浏览记录表
CREATE TABLE IF NOT EXISTS user_browse_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    reader_id INT NOT NULL COMMENT '塔罗师ID',
    browse_type ENUM('free', 'paid') NOT NULL COMMENT '浏览类型：free-免费浏览，paid-付费查看联系方式',
    cost INT DEFAULT 0 COMMENT '花费的Tata Coin数量',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_reader (user_id, reader_id),
    INDEX idx_browse_type (browse_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户浏览记录表';

-- 塔罗师问答表
CREATE TABLE IF NOT EXISTS reader_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reader_id INT NOT NULL,
    user_id INT NOT NULL,
    question TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reader_id (reader_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师问答表';

-- 问答回答表
CREATE TABLE IF NOT EXISTS reader_question_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    answer TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question_id (question_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (question_id) REFERENCES reader_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='问答回答表';

-- 塔罗师评价表
CREATE TABLE IF NOT EXISTS reader_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reader_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    like_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reader_id (reader_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reader_review (user_id, reader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师评价表';

-- 评价点赞表
CREATE TABLE IF NOT EXISTS reader_review_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_review_id (review_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (review_id) REFERENCES reader_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_review_like (user_id, review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评价点赞表';

-- 邀请链接表
CREATE TABLE IF NOT EXISTS invitation_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inviter_id INT NOT NULL,
    inviter_type ENUM('reader', 'user') NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_token (token),
    INDEX idx_inviter (inviter_id, inviter_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请链接表';

-- 邀请关系表
CREATE TABLE IF NOT EXISTS invitation_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inviter_id INT NOT NULL,
    inviter_type ENUM('reader', 'user') NOT NULL,
    invitee_id INT NOT NULL,
    invitee_type ENUM('reader', 'user') NOT NULL,
    invitation_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inviter (inviter_id, inviter_type),
    INDEX idx_invitee (invitee_id, invitee_type),
    INDEX idx_token (invitation_token),
    UNIQUE KEY unique_invitee (invitee_id, invitee_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请关系表';

-- 邀请返点记录表
CREATE TABLE IF NOT EXISTS invitation_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inviter_id INT NOT NULL,
    inviter_type ENUM('reader', 'user') NOT NULL,
    invitee_id INT NOT NULL,
    invitee_type ENUM('reader', 'user') NOT NULL,
    transaction_id INT NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    original_amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inviter (inviter_id, inviter_type),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请返点记录表';

-- 管理员消息表
CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT '消息标题',
    content TEXT NOT NULL COMMENT '消息内容',
    target_type ENUM('user', 'reader', 'all') NOT NULL COMMENT '目标类型：user-普通用户，reader-塔罗师，all-所有人',
    created_by INT NOT NULL COMMENT '创建者ID（管理员）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_target_type (target_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员消息表';

-- 消息阅读记录表
CREATE TABLE IF NOT EXISTS message_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    user_type ENUM('user', 'reader') NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_id (message_id),
    INDEX idx_user (user_id, user_type),
    FOREIGN KEY (message_id) REFERENCES admin_messages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_message_user (message_id, user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息阅读记录表';

-- 插入默认设置
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', '塔罗师展示平台', '网站名称'),
('site_description', '专业塔罗师展示平台', '网站描述'),
('max_featured_readers', '6', '首页最大推荐塔罗师数量'),
('registration_link_hours', '24', '注册链接有效期（小时）')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
