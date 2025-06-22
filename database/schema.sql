-- 塔罗师展示网站数据库结构
-- 创建数据库
CREATE DATABASE IF NOT EXISTS tarot_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tarot_platform;

-- 用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- 管理员表
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- 塔罗师注册链接表
CREATE TABLE reader_registration_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) UNIQUE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    used_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
);

-- 塔罗师表
CREATE TABLE readers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    photo VARCHAR(255),
    price_list_image VARCHAR(255),
    experience_years INT NOT NULL DEFAULT 0,
    specialties TEXT,
    description TEXT,
    contact_info TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    registration_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_token) REFERENCES reader_registration_links(token)
);

-- 用户查看塔罗师联系方式记录表
CREATE TABLE contact_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reader_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reader (user_id, reader_id)
);

-- 系统设置表
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 插入默认管理员账户 (密码: admin123)
INSERT INTO admins (username, email, password_hash, full_name) VALUES 
('admin', 'admin@tarot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员');

-- 登录尝试记录表
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- 插入默认设置
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', '塔罗师展示平台', '网站名称'),
('site_description', '专业塔罗师展示平台', '网站描述'),
('max_featured_readers', '6', '首页最大推荐塔罗师数量'),
('registration_link_hours', '24', '注册链接有效期（小时）');
