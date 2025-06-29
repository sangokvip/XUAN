-- 联系留言和联系方式设置数据库更新

-- 创建联系留言表
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '留言者姓名',
    email VARCHAR(255) NOT NULL COMMENT '留言者邮箱',
    subject VARCHAR(255) NOT NULL COMMENT '留言主题',
    message TEXT NOT NULL COMMENT '留言内容',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT '留言者IP地址',
    user_agent TEXT DEFAULT NULL COMMENT '用户代理信息',
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread' COMMENT '状态：未读、已读、已回复',
    admin_reply TEXT DEFAULT NULL COMMENT '管理员回复',
    replied_by INT DEFAULT NULL COMMENT '回复的管理员ID',
    replied_at TIMESTAMP NULL DEFAULT NULL COMMENT '回复时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    FOREIGN KEY (replied_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系留言表';

-- 插入联系方式设置项
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('contact_email_1', 'info@example.com', '主要联系邮箱'),
('contact_email_2', 'support@example.com', '客服邮箱'),
('contact_wechat', 'mystical_service', '微信客服号'),
('contact_wechat_hours', '9:00-21:00', '微信客服工作时间'),
('contact_qq_group_1', '123456789', '官方交流QQ群'),
('contact_qq_group_2', '987654321', '新手学习QQ群'),
('contact_xiaohongshu', '@神秘学园', '小红书账号'),
('contact_xiaohongshu_desc', '每日分享占卜知识', '小红书描述'),
('contact_phone', '', '联系电话（可选）'),
('contact_address', '', '联系地址（可选）'),
('contact_business_hours', '周一至周日 9:00-21:00', '营业时间'),
('contact_notice', '我们会在24小时内回复您的留言', '联系页面提示信息');
