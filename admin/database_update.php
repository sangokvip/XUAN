<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];
$updateResults = [];

// 定义可用的数据库更新
$availableUpdates = [
    'add_circle_photo' => [
        'name' => '添加圆形头像字段',
        'description' => '为塔罗师表添加photo_circle字段，用于首页圆形头像展示',
        'sql' => "ALTER TABLE readers ADD COLUMN photo_circle VARCHAR(255) DEFAULT NULL COMMENT '圆形头像（用于首页展示）' AFTER photo;",
        'check_sql' => "SHOW COLUMNS FROM readers LIKE 'photo_circle'"
    ],
    'add_contact_fields' => [
        'name' => '添加联系方式字段',
        'description' => '为塔罗师表添加微信、QQ、小红书、抖音等联系方式字段',
        'sql' => "ALTER TABLE readers 
                  ADD COLUMN wechat VARCHAR(100) DEFAULT NULL COMMENT '微信号' AFTER phone,
                  ADD COLUMN qq VARCHAR(50) DEFAULT NULL COMMENT 'QQ号' AFTER wechat,
                  ADD COLUMN xiaohongshu VARCHAR(100) DEFAULT NULL COMMENT '小红书账号' AFTER qq,
                  ADD COLUMN douyin VARCHAR(100) DEFAULT NULL COMMENT '抖音账号' AFTER xiaohongshu,
                  ADD COLUMN other_contact TEXT DEFAULT NULL COMMENT '其他联系方式' AFTER douyin;",
        'check_sql' => "SHOW COLUMNS FROM readers LIKE 'wechat'"
    ],
    'add_certificates_field' => [
        'name' => '添加证书字段',
        'description' => '为塔罗师表添加certificates字段，用于存储证书图片',
        'sql' => "ALTER TABLE readers ADD COLUMN certificates TEXT DEFAULT NULL COMMENT '证书图片路径（JSON格式）' AFTER photo_circle;",
        'check_sql' => "SHOW COLUMNS FROM readers LIKE 'certificates'"
    ],
    'add_gender_fields' => [
        'name' => '添加性别和头像字段',
        'description' => '为用户表和塔罗师表添加性别字段，为用户表添加头像字段，并为现有用户随机分配性别和默认头像',
        'sql' => [
            "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女' AFTER phone",
            "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT '头像路径' AFTER gender",
            "ALTER TABLE readers ADD COLUMN gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女' AFTER phone",
            "UPDATE users SET gender = CASE WHEN RAND() > 0.5 THEN 'male' ELSE 'female' END WHERE gender IS NULL",
            "UPDATE readers SET gender = CASE WHEN RAND() > 0.5 THEN 'male' ELSE 'female' END WHERE gender IS NULL",
            "UPDATE users SET avatar = CASE WHEN gender = 'male' THEN 'img/nm.jpg' WHEN gender = 'female' THEN 'img/nf.jpg' ELSE 'img/nm.jpg' END WHERE avatar IS NULL",
            "UPDATE readers SET photo = CASE WHEN gender = 'male' THEN 'img/tm.jpg' WHEN gender = 'female' THEN 'img/tf.jpg' ELSE 'img/tm.jpg' END WHERE photo IS NULL OR photo = ''"
        ],
        'check_sql' => "SHOW COLUMNS FROM users LIKE 'gender'"
    ],
    'add_view_count_field' => [
        'name' => '添加查看次数字段',
        'description' => '为塔罗师表添加view_count字段，用于存储查看次数，并初始化现有塔罗师的查看次数',
        'sql' => [
            "ALTER TABLE readers ADD COLUMN view_count INT DEFAULT 0 COMMENT '查看次数' AFTER description"
        ],
        'check_sql' => "SHOW COLUMNS FROM readers LIKE 'view_count'"
    ],
    'clean_specialty_tags' => [
        'name' => '清理专长标签',
        'description' => '清理塔罗师专长中的异常标签，只保留系统标准标签和符合规范的自定义标签',
        'sql' => [
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%爱情塔罗%' OR specialties LIKE '%感情%' THEN REPLACE(REPLACE(specialties, '爱情塔罗', '感情'), '爱情', '感情')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%事业塔罗%' THEN REPLACE(specialties, '事业塔罗', '事业')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%财运塔罗%' THEN REPLACE(specialties, '财运塔罗', '财运')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%学业塔罗%' THEN REPLACE(specialties, '学业塔罗', '学业')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%健康塔罗%' THEN REPLACE(specialties, '健康塔罗', '运势')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%人际关系%' THEN REPLACE(specialties, '人际关系', '感情')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%心理咨询%' THEN REPLACE(specialties, '心理咨询', '感情')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%灵性指导%' THEN REPLACE(specialties, '灵性指导', '运势')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%占星术%' THEN REPLACE(specialties, '占星术', '运势')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%水晶疗愈%' THEN REPLACE(specialties, '水晶疗愈', '运势')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%冥想指导%' THEN REPLACE(specialties, '冥想指导', '运势')
                ELSE specialties
            END",
            "UPDATE readers SET specialties = CASE
                WHEN specialties LIKE '%能量疗愈%' THEN REPLACE(specialties, '能量疗愈', '运势')
                ELSE specialties
            END"
        ],
        'check_sql' => "SELECT COUNT(*) as count FROM readers WHERE specialties LIKE '%塔罗%' OR specialties LIKE '%疗愈%' OR specialties LIKE '%指导%'"
    ]
];

// 检查当前数据库状态
$currentStatus = [];
foreach ($availableUpdates as $key => $update) {
    $result = $db->fetchOne($update['check_sql']);
    $currentStatus[$key] = !empty($result);
}

// 处理更新请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_key'])) {
    $updateKey = $_POST['update_key'];
    
    if (isset($availableUpdates[$updateKey])) {
        $update = $availableUpdates[$updateKey];
        
        // 检查是否已经存在
        $exists = $db->fetchOne($update['check_sql']);
        if ($exists) {
            $errors[] = "更新 '{$update['name']}' 已经执行过了，无需重复执行";
        } else {
            try {
                // 执行SQL更新
                $sql = $update['sql'];
                if (is_array($sql)) {
                    // 如果是SQL数组，逐个执行
                    foreach ($sql as $statement) {
                        $db->query($statement);
                    }
                } else {
                    // 如果是单个SQL语句
                    $db->query($sql);
                }

                $success = "数据库更新 '{$update['name']}' 执行成功！";

                // 更新状态
                $currentStatus[$updateKey] = true;

                // 记录更新日志
                $updateResults[] = [
                    'time' => date('Y-m-d H:i:s'),
                    'update' => $update['name'],
                    'status' => 'success'
                ];

            } catch (Exception $e) {
                $errors[] = "执行更新 '{$update['name']}' 时发生错误: " . $e->getMessage();

                $updateResults[] = [
                    'time' => date('Y-m-d H:i:s'),
                    'update' => $update['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
    } else {
        $errors[] = '无效的更新请求';
    }
}

// 执行全部更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all'])) {
    $executedCount = 0;
    $skippedCount = 0;
    
    foreach ($availableUpdates as $key => $update) {
        if (!$currentStatus[$key]) {
            try {
                // 执行SQL更新
                $sql = $update['sql'];
                if (is_array($sql)) {
                    // 如果是SQL数组，逐个执行
                    foreach ($sql as $statement) {
                        $db->query($statement);
                    }
                } else {
                    // 如果是单个SQL语句
                    $db->query($sql);
                }

                $currentStatus[$key] = true;
                $executedCount++;

                $updateResults[] = [
                    'time' => date('Y-m-d H:i:s'),
                    'update' => $update['name'],
                    'status' => 'success'
                ];

            } catch (Exception $e) {
                $errors[] = "执行更新 '{$update['name']}' 时发生错误: " . $e->getMessage();

                $updateResults[] = [
                    'time' => date('Y-m-d H:i:s'),
                    'update' => $update['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        } else {
            $skippedCount++;
        }
    }
    
    if ($executedCount > 0) {
        $success = "成功执行了 {$executedCount} 个数据库更新";
        if ($skippedCount > 0) {
            $success .= "，跳过了 {$skippedCount} 个已存在的更新";
        }
    } else {
        $errors[] = "所有数据库更新都已经执行过了";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库更新 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .update-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .update-card.completed {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        
        .update-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .update-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .update-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .update-description {
            color: #6c757d;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .update-sql {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #495057;
            margin-bottom: 15px;
            overflow-x: auto;
        }
        
        .btn-update {
            background: #d4af37;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .btn-update:hover {
            background: #b8941f;
        }
        
        .btn-update:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .update-all-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .btn-update-all {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-update-all:hover {
            background: #218838;
        }
        
        .results-section {
            margin-top: 30px;
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .result-success {
            background: #d4edda;
            color: #155724;
        }
        
        .result-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .warning-box h4 {
            color: #856404;
            margin: 0 0 10px 0;
        }
        
        .warning-box p {
            color: #856404;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>数据库更新</h1>
                <div class="page-actions">
                    <a href="index.php" class="btn btn-secondary">返回首页</a>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo h($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="warning-box">
                <h4>⚠️ 重要提示</h4>
                <p>数据库更新操作会修改数据库结构，请在执行前确保已备份数据库。建议在测试环境中先验证更新的正确性。</p>
            </div>
            
            <!-- 一键更新所有 -->
            <div class="update-all-section">
                <h3>一键执行所有待更新项</h3>
                <p>点击下方按钮将自动执行所有尚未完成的数据库更新</p>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="update_all" class="btn-update-all" 
                            onclick="return confirm('确定要执行所有数据库更新吗？此操作不可撤销。')">
                        执行所有更新
                    </button>
                </form>
            </div>
            
            <!-- 单独更新项 -->
            <div class="updates-list">
                <h3>可用的数据库更新</h3>
                
                <?php foreach ($availableUpdates as $key => $update): ?>
                    <div class="update-card <?php echo $currentStatus[$key] ? 'completed' : ''; ?>">
                        <div class="update-header">
                            <h4 class="update-title"><?php echo h($update['name']); ?></h4>
                            <span class="update-status <?php echo $currentStatus[$key] ? 'status-completed' : 'status-pending'; ?>">
                                <?php echo $currentStatus[$key] ? '已完成' : '待执行'; ?>
                            </span>
                        </div>
                        
                        <div class="update-description">
                            <?php echo h($update['description']); ?>
                        </div>
                        
                        <div class="update-sql">
                            <?php
                            $sql = $update['sql'];
                            if (is_array($sql)) {
                                foreach ($sql as $i => $statement) {
                                    echo ($i + 1) . '. ' . h($statement) . ";\n";
                                }
                            } else {
                                echo h($sql);
                            }
                            ?>
                        </div>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="update_key" value="<?php echo $key; ?>">
                            <button type="submit" class="btn-update" 
                                    <?php echo $currentStatus[$key] ? 'disabled' : ''; ?>
                                    onclick="return confirm('确定要执行此数据库更新吗？')">
                                <?php echo $currentStatus[$key] ? '已完成' : '执行更新'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 更新结果 -->
            <?php if (!empty($updateResults)): ?>
                <div class="results-section">
                    <h3>更新结果</h3>
                    <?php foreach ($updateResults as $result): ?>
                        <div class="result-item <?php echo $result['status'] === 'success' ? 'result-success' : 'result-error'; ?>">
                            <span><?php echo h($result['update']); ?></span>
                            <span><?php echo h($result['time']); ?></span>
                        </div>
                        <?php if (isset($result['error'])): ?>
                            <div style="color: #721c24; font-size: 0.9rem; margin-left: 20px;">
                                错误: <?php echo h($result['error']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
