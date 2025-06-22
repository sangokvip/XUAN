<?php
// 用户认证相关函数

/**
 * 检查用户是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 检查塔罗师是否已登录
 */
function isReaderLoggedIn() {
    return isset($_SESSION['reader_id']) && !empty($_SESSION['reader_id']);
}

/**
 * 检查管理员是否已登录
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * 用户登录（增强安全性）
 */
function loginUser($username, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 检查IP是否被阻止
    if (isIPBlocked($ip)) {
        return ['success' => false, 'message' => 'IP地址被限制访问'];
    }

    // 检查登录尝试次数
    if (checkLoginAttempts($username, $ip)) {
        return ['success' => false, 'message' => '登录尝试次数过多，请15分钟后再试'];
    }

    $db = Database::getInstance();
    $user = $db->fetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);

    if ($user && verifyPassword($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = 'user';
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();

        // 记录成功登录
        logLoginAttempt($username, true, $ip);

        return ['success' => true, 'user' => $user];
    }

    // 记录失败登录
    logLoginAttempt($username, false, $ip);

    return ['success' => false, 'message' => '用户名或密码错误'];
}

/**
 * 塔罗师登录（增强安全性）
 */
function loginReader($username, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (isIPBlocked($ip)) {
        return ['success' => false, 'message' => 'IP地址被限制访问'];
    }

    if (checkLoginAttempts($username, $ip)) {
        return ['success' => false, 'message' => '登录尝试次数过多，请15分钟后再试'];
    }

    $db = Database::getInstance();
    $reader = $db->fetchOne("SELECT * FROM readers WHERE username = ? AND is_active = 1", [$username]);

    if ($reader && verifyPassword($password, $reader['password_hash'])) {
        $_SESSION['reader_id'] = $reader['id'];
        $_SESSION['user_type'] = 'reader';
        $_SESSION['user_name'] = $reader['full_name'];
        $_SESSION['last_activity'] = time();

        logLoginAttempt($username, true, $ip);
        return ['success' => true, 'reader' => $reader];
    }

    logLoginAttempt($username, false, $ip);
    return ['success' => false, 'message' => '用户名或密码错误'];
}

/**
 * 管理员登录（增强安全性）
 */
function loginAdmin($username, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (isIPBlocked($ip)) {
        return ['success' => false, 'message' => 'IP地址被限制访问'];
    }

    if (checkLoginAttempts($username, $ip)) {
        return ['success' => false, 'message' => '登录尝试次数过多，请15分钟后再试'];
    }

    $db = Database::getInstance();
    $admin = $db->fetchOne("SELECT * FROM admins WHERE username = ? AND is_active = 1", [$username]);

    if ($admin && verifyPassword($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_name'] = $admin['full_name'];
        $_SESSION['last_activity'] = time();

        logLoginAttempt($username, true, $ip);
        return ['success' => true, 'admin' => $admin];
    }

    logLoginAttempt($username, false, $ip);
    return ['success' => false, 'message' => '用户名或密码错误'];
}

/**
 * 用户注册
 */
function registerUser($data) {
    $db = Database::getInstance();
    
    // 验证数据
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = '用户名不能为空';
    } elseif (strlen($data['username']) < 3) {
        $errors[] = '用户名至少3个字符';
    }
    
    if (empty($data['email']) || !isValidEmail($data['email'])) {
        $errors[] = '请输入有效的邮箱地址';
    }
    
    if (empty($data['password']) || !isValidPassword($data['password'])) {
        $errors[] = '密码至少' . PASSWORD_MIN_LENGTH . '个字符';
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = '两次输入的密码不一致';
    }
    
    if (empty($data['full_name'])) {
        $errors[] = '姓名不能为空';
    }

    if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female'])) {
        $errors[] = '请选择性别';
    }

    // 检查用户名和邮箱是否已存在
    $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$data['username'], $data['email']]);
    if ($existingUser) {
        $errors[] = '用户名或邮箱已存在';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // 创建用户
    try {
        // 根据性别设置默认头像
        $avatar = $data['gender'] === 'male' ? 'img/nm.jpg' : 'img/nf.jpg';

        $userId = $db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => hashPassword($data['password']),
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'],
            'avatar' => $avatar
        ]);
        
        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['注册失败，请稍后重试']];
    }
}

/**
 * 塔罗师注册
 */
function registerReader($data, $token) {
    $db = Database::getInstance();
    
    // 验证注册链接
    $link = $db->fetchOne(
        "SELECT * FROM reader_registration_links WHERE token = ? AND is_used = 0 AND expires_at > NOW()",
        [$token]
    );
    
    if (!$link) {
        return ['success' => false, 'errors' => ['注册链接无效或已过期']];
    }
    
    // 验证数据
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = '用户名不能为空';
    } elseif (strlen($data['username']) < 3) {
        $errors[] = '用户名至少3个字符';
    }
    
    if (empty($data['email']) || !isValidEmail($data['email'])) {
        $errors[] = '请输入有效的邮箱地址';
    }
    
    if (empty($data['password']) || !isValidPassword($data['password'])) {
        $errors[] = '密码至少' . PASSWORD_MIN_LENGTH . '个字符';
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = '两次输入的密码不一致';
    }
    
    if (empty($data['full_name'])) {
        $errors[] = '姓名不能为空';
    }
    
    if (empty($data['experience_years']) || !is_numeric($data['experience_years'])) {
        $errors[] = '请输入有效的从业年数';
    }

    if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female'])) {
        $errors[] = '请选择性别';
    }

    // 检查用户名和邮箱是否已存在
    $existingReader = $db->fetchOne("SELECT id FROM readers WHERE username = ? OR email = ?", [$data['username'], $data['email']]);
    if ($existingReader) {
        $errors[] = '用户名或邮箱已存在';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // 创建塔罗师
    try {
        // 如果没有上传照片，根据性别设置默认头像
        $photo = $data['photo'] ?? null;
        if (empty($photo)) {
            $photo = $data['gender'] === 'male' ? 'img/tm.jpg' : 'img/tf.jpg';
        }

        $readerId = $db->insert('readers', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => hashPassword($data['password']),
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'],
            'experience_years' => (int)$data['experience_years'],
            'specialties' => $data['specialties'] ?? null,
            'description' => $data['description'] ?? null,
            'photo' => $photo,
            'photo_circle' => $data['photo_circle'] ?? null
        ]);
        
        // 标记注册链接为已使用
        $db->update('reader_registration_links', [
            'is_used' => 1,
            'used_at' => date('Y-m-d H:i:s'),
            'used_by' => $readerId
        ], 'token = ?', [$token]);
        
        return ['success' => true, 'reader_id' => $readerId];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['注册失败，请稍后重试']];
    }
}

/**
 * 登出
 */
function logout() {
    session_destroy();
    session_start();
}

/**
 * 要求用户登录
 */
function requireLogin($redirectTo = 'auth/login.php') {
    if (!isLoggedIn()) {
        redirect($redirectTo);
    }
}

/**
 * 要求塔罗师登录
 */
function requireReaderLogin($redirectTo = 'auth/reader_login.php') {
    if (!isReaderLoggedIn()) {
        redirect($redirectTo);
    }
}

/**
 * 要求管理员登录
 */
function requireAdminLogin($redirectTo = 'auth/admin_login.php') {
    if (!isAdminLoggedIn()) {
        redirect($redirectTo);
    }
}
?>
