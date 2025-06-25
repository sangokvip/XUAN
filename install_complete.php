<?php
session_start();

// 检查是否已安装
if (file_exists('config/database_config.php') && !isset($_GET['force'])) {
    die('系统已安装！如需重新安装，请在URL后添加 ?force=1');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = '';

// 步骤1：环境检查
if ($step == 1) {
    $checks = [
        'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO MySQL扩展' => extension_loaded('pdo_mysql'),
        'GD扩展' => extension_loaded('gd'),
        'JSON扩展' => extension_loaded('json'),
        'config目录可写' => is_writable('config'),
        'uploads目录可写' => is_writable('uploads') || mkdir('uploads', 0755, true),
        'cache目录可写' => is_writable('cache') || mkdir('cache', 0755, true),
        'logs目录可写' => is_writable('logs') || mkdir('logs', 0755, true),
    ];
    
    $allPassed = true;
    foreach ($checks as $check => $result) {
        if (!$result) $allPassed = false;
    }
}

// 步骤2：数据库配置
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $createDb = isset($_POST['create_db']);
    
    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $errors[] = '请填写所有必填字段';
    } else {
        try {
            // 测试数据库连接
            if ($createDb) {
                // 先连接到MySQL服务器（不指定数据库）
                $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 创建数据库
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $success = "数据库 {$dbName} 创建成功！";
            }
            
            // 连接到指定数据库
            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 保存数据库配置
            $configContent = "<?php\n";
            $configContent .= "define('DB_HOST', '{$dbHost}');\n";
            $configContent .= "define('DB_NAME', '{$dbName}');\n";
            $configContent .= "define('DB_USER', '{$dbUser}');\n";
            $configContent .= "define('DB_PASS', '" . addslashes($dbPass) . "');\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n";
            
            if (!file_put_contents('config/database_config.php', $configContent)) {
                throw new Exception('无法写入数据库配置文件');
            }
            
            $_SESSION['install_db_config'] = [
                'host' => $dbHost,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass
            ];
            
            header('Location: install_complete.php?step=3');
            exit;
            
        } catch (Exception $e) {
            $errors[] = '数据库连接失败：' . $e->getMessage();
        }
    }
}

// 步骤3：创建数据库表
if ($step == 3) {
    if (!isset($_SESSION['install_db_config'])) {
        header('Location: install_complete.php?step=2');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $config = $_SESSION['install_db_config'];
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4", 
                          $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 执行数据库结构创建
            $sqlStatements = getCompleteDbStructure();
            
            foreach ($sqlStatements as $sql) {
                $sql = trim($sql);
                if (!empty($sql)) {
                    $pdo->exec($sql);
                }
            }
            
            header('Location: install_complete.php?step=4');
            exit;
            
        } catch (Exception $e) {
            $errors[] = '创建数据库表失败：' . $e->getMessage();
        }
    }
}

// 步骤4：管理员账户设置
if ($step == 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminName = trim($_POST['admin_name'] ?? '');
    
    if (empty($adminUser) || empty($adminEmail) || empty($adminPass) || empty($adminName)) {
        $errors[] = '请填写所有字段';
    } elseif (strlen($adminPass) < 6) {
        $errors[] = '密码长度至少6位';
    } else {
        try {
            $config = $_SESSION['install_db_config'];
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4", 
                          $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建管理员账户
            $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adminUser, $adminEmail, $passwordHash, $adminName]);
            
            header('Location: install_complete.php?step=5');
            exit;
            
        } catch (Exception $e) {
            $errors[] = '创建管理员账户失败：' . $e->getMessage();
        }
    }
}

// 步骤5：完成安装
if ($step == 5) {
    // 清理安装会话
    unset($_SESSION['install_db_config']);
    
    // 创建必要的目录
    $dirs = ['uploads/photos', 'uploads/price_lists', 'uploads/certificates', 'cache', 'logs'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // 创建.htaccess文件保护敏感目录
    $htaccessContent = "Order Deny,Allow\nDeny from all";
    file_put_contents('config/.htaccess', $htaccessContent);
    file_put_contents('logs/.htaccess', $htaccessContent);
}

function getCompleteDbStructure() {
    return [
        // 用户表
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女',
            avatar VARCHAR(255) DEFAULT NULL COMMENT '头像路径',
            tata_coin INT DEFAULT 0 COMMENT 'Tata Coin余额，通过系统发放',
            invited_by INT DEFAULT NULL COMMENT '邀请人ID',
            invited_by_type ENUM('user', 'reader') DEFAULT NULL COMMENT '邀请人类型',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 管理员表
        "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 塔罗师注册链接表
        "CREATE TABLE IF NOT EXISTS reader_registration_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(255) UNIQUE NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            used_at TIMESTAMP NULL,
            used_by INT NULL,
            FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 塔罗师表
        "CREATE TABLE IF NOT EXISTS readers (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 用户查看塔罗师联系方式记录表
        "CREATE TABLE IF NOT EXISTS contact_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reader_id INT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reader_id) REFERENCES readers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_reader (user_id, reader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 系统设置表
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 登录尝试记录表
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            success BOOLEAN NOT NULL DEFAULT FALSE,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username_time (username, attempted_at),
            INDEX idx_ip_time (ip_address, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Tata Coin交易记录表
        "CREATE TABLE IF NOT EXISTS tata_coin_transactions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tata Coin交易记录表'",

        // 用户浏览记录表
        "CREATE TABLE IF NOT EXISTS user_browse_history (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户浏览记录表'",

        // 塔罗师问答表
        "CREATE TABLE IF NOT EXISTS reader_questions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师问答表'",

        // 问答回答表
        "CREATE TABLE IF NOT EXISTS reader_question_answers (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='问答回答表'",

        // 塔罗师评价表
        "CREATE TABLE IF NOT EXISTS reader_reviews (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师评价表'",

        // 评价点赞表
        "CREATE TABLE IF NOT EXISTS reader_review_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_review_id (review_id),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (review_id) REFERENCES reader_reviews(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_review_like (user_id, review_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评价点赞表'",

        // 邀请链接表
        "CREATE TABLE IF NOT EXISTS invitation_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inviter_id INT NOT NULL,
            inviter_type ENUM('reader', 'user') NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_token (token),
            INDEX idx_inviter (inviter_id, inviter_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请链接表'",

        // 邀请关系表
        "CREATE TABLE IF NOT EXISTS invitation_relations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请关系表'",

        // 邀请返点记录表
        "CREATE TABLE IF NOT EXISTS invitation_commissions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请返点记录表'",

        // 管理员消息表
        "CREATE TABLE IF NOT EXISTS admin_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL COMMENT '消息标题',
            content TEXT NOT NULL COMMENT '消息内容',
            target_type ENUM('user', 'reader', 'all') NOT NULL COMMENT '目标类型：user-普通用户，reader-塔罗师，all-所有人',
            created_by INT NOT NULL COMMENT '创建者ID（管理员）',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_target_type (target_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员消息表'",

        // 消息阅读记录表
        "CREATE TABLE IF NOT EXISTS message_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            user_id INT NOT NULL,
            user_type ENUM('user', 'reader') NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_message_id (message_id),
            INDEX idx_user (user_id, user_type),
            FOREIGN KEY (message_id) REFERENCES admin_messages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_message_user (message_id, user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息阅读记录表'",

        // 每日签到记录表
        "CREATE TABLE IF NOT EXISTS daily_check_ins (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表'",

        // 页面浏览奖励记录表
        "CREATE TABLE IF NOT EXISTS page_browse_rewards (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面浏览奖励记录表'",

        // 用户等级表
        "CREATE TABLE IF NOT EXISTS user_levels (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户等级表'",

        // 插入默认设置
        "INSERT INTO settings (setting_key, setting_value, description) VALUES
        ('site_name', '塔罗师展示平台', '网站名称'),
        ('site_description', '专业塔罗师展示平台', '网站描述'),
        ('max_featured_readers', '6', '首页最大推荐塔罗师数量'),
        ('registration_link_hours', '24', '注册链接有效期（小时）'),
        ('daily_browse_limit', '10', '每日浏览奖励上限'),
        ('profile_completion_reward', '20', '完善资料奖励金额'),
        ('invitation_user_reward', '20', '邀请用户奖励'),
        ('invitation_reader_reward', '50', '邀请塔罗师奖励'),
        ('daily_earning_limit', '30', '每日非付费获取上限'),
        ('reader_commission_rate', '50', '塔罗师分成比例（%）'),
        ('featured_reader_cost', '30', '查看推荐塔罗师费用'),
        ('normal_reader_cost', '10', '查看普通塔罗师费用'),
        ('new_user_tata_coin', '100', '新用户注册赠送金额')
        ON DUPLICATE KEY UPDATE setting_key = setting_key"
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师展示平台 - 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: #f5f5f5; }
        .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { padding: 40px; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
        .step.active { background: #667eea; color: white; }
        .step.completed { background: #28a745; color: white; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus { border-color: #667eea; outline: none; }
        .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #5a67d8; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .check-list { list-style: none; }
        .check-list li { padding: 10px 0; border-bottom: 1px solid #eee; }
        .check-list .status { float: right; font-weight: bold; }
        .status.pass { color: #28a745; }
        .status.fail { color: #dc3545; }
        .checkbox-group { display: flex; align-items: center; margin-bottom: 15px; }
        .checkbox-group input { width: auto; margin-right: 10px; }
        .progress { background: #f0f0f0; border-radius: 10px; height: 20px; margin: 20px 0; }
        .progress-bar { background: #667eea; height: 100%; border-radius: 10px; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔮 塔罗师展示平台</h1>
            <p>安装向导</p>
        </div>

        <div class="content">
            <!-- 步骤指示器 -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">4</div>
                <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">5</div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <!-- 步骤1：环境检查 -->
                <h2>步骤 1: 环境检查</h2>
                <p>正在检查您的服务器环境是否满足安装要求...</p>

                <ul class="check-list">
                    <?php foreach ($checks as $check => $result): ?>
                        <li>
                            <?php echo $check; ?>
                            <span class="status <?php echo $result ? 'pass' : 'fail'; ?>">
                                <?php echo $result ? '✓ 通过' : '✗ 失败'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($allPassed): ?>
                    <div class="alert alert-success">
                        <p>✓ 所有环境检查都已通过！</p>
                    </div>
                    <a href="install_complete.php?step=2" class="btn">下一步：数据库配置</a>
                <?php else: ?>
                    <div class="alert alert-error">
                        <p>请解决上述问题后重新检查。</p>
                    </div>
                    <a href="install_complete.php?step=1" class="btn">重新检查</a>
                <?php endif; ?>

            <?php elseif ($step == 2): ?>
                <!-- 步骤2：数据库配置 -->
                <h2>步骤 2: 数据库配置</h2>
                <p>请填写您的数据库连接信息：</p>

                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">数据库主机 *</label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="db_name">数据库名称 *</label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'tarot_platform'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="db_user">数据库用户名 *</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="db_pass">数据库密码</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="create_db" name="create_db" <?php echo isset($_POST['create_db']) ? 'checked' : ''; ?>>
                        <label for="create_db">如果数据库不存在，自动创建</label>
                    </div>

                    <button type="submit" class="btn">测试连接并继续</button>
                </form>

            <?php elseif ($step == 3): ?>
                <!-- 步骤3：创建数据库表 -->
                <h2>步骤 3: 创建数据库表</h2>
                <p>即将创建所有必要的数据库表结构，包括：</p>

                <ul style="margin: 20px 0; padding-left: 20px;">
                    <li>用户管理系统（用户、管理员、塔罗师）</li>
                    <li>Tata Coin虚拟货币系统</li>
                    <li>评价和问答系统</li>
                    <li>邀请返点系统</li>
                    <li>消息通知系统</li>
                    <li>系统设置和日志</li>
                </ul>

                <div class="alert alert-success">
                    <p>✓ 数据库连接成功！准备创建表结构...</p>
                </div>

                <form method="POST">
                    <button type="submit" class="btn btn-success">创建数据库表</button>
                </form>

            <?php elseif ($step == 4): ?>
                <!-- 步骤4：管理员账户设置 -->
                <h2>步骤 4: 创建管理员账户</h2>
                <p>请设置您的管理员账户信息：</p>

                <form method="POST">
                    <div class="form-group">
                        <label for="admin_user">管理员用户名 *</label>
                        <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">管理员邮箱 *</label>
                        <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_name">管理员姓名 *</label>
                        <input type="text" id="admin_name" name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? '系统管理员'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_pass">管理员密码 *</label>
                        <input type="password" id="admin_pass" name="admin_pass" required>
                        <small style="color: #666;">密码长度至少6位</small>
                    </div>

                    <button type="submit" class="btn">创建管理员账户</button>
                </form>

            <?php elseif ($step == 5): ?>
                <!-- 步骤5：安装完成 -->
                <h2>🎉 安装完成！</h2>

                <div class="alert alert-success">
                    <p><strong>恭喜！塔罗师展示平台已成功安装。</strong></p>
                </div>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3>接下来您可以：</h3>
                    <ul style="margin: 15px 0; padding-left: 20px;">
                        <li><a href="admin/dashboard.php" target="_blank">登录管理后台</a> - 管理网站设置和用户</li>
                        <li><a href="index.php" target="_blank">访问网站首页</a> - 查看网站前台</li>
                        <li><a href="auth/register.php" target="_blank">注册普通用户</a> - 体验用户功能</li>
                    </ul>
                </div>

                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;">
                    <h4>🔒 安全提醒：</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>请删除或重命名 <code>install_complete.php</code> 文件</li>
                        <li>确保 <code>config/</code> 和 <code>logs/</code> 目录不能通过Web访问</li>
                        <li>定期备份数据库</li>
                        <li>及时更新系统</li>
                    </ul>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="admin/dashboard.php" class="btn btn-success">进入管理后台</a>
                    <a href="index.php" class="btn" style="margin-left: 10px;">访问网站首页</a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
?>
