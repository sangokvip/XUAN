<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 处理发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $targetType = $_POST['target_type'] ?? '';
    
    if (empty($title)) {
        $errors[] = '消息标题不能为空';
    }
    
    if (empty($content)) {
        $errors[] = '消息内容不能为空';
    }
    
    if (!in_array($targetType, ['user', 'reader', 'all'])) {
        $errors[] = '请选择有效的目标类型';
    }
    
    if (empty($errors)) {
        try {
            $messageId = $db->insert('admin_messages', [
                'title' => $title,
                'content' => $content,
                'target_type' => $targetType,
                'created_by' => $_SESSION['admin_id']
            ]);
            
            if ($messageId) {
                $success = '消息发送成功！';
            } else {
                $errors[] = '消息发送失败，请重试';
            }
        } catch (Exception $e) {
            $errors[] = '发送失败：' . $e->getMessage();
        }
    }
}

// 获取消息列表
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$messages = $db->fetchAll(
    "SELECT m.*, 
            (SELECT COUNT(*) FROM message_reads mr WHERE mr.message_id = m.id) as read_count,
            (SELECT COUNT(*) FROM users WHERE 1=1) as total_users,
            (SELECT COUNT(*) FROM readers WHERE 1=1) as total_readers
     FROM admin_messages m 
     ORDER BY m.created_at DESC 
     LIMIT ? OFFSET ?",
    [$limit, $offset]
);

// 计算每条消息的目标用户数和已读率
foreach ($messages as &$message) {
    switch ($message['target_type']) {
        case 'user':
            $message['target_count'] = $message['total_users'];
            break;
        case 'reader':
            $message['target_count'] = $message['total_readers'];
            break;
        case 'all':
            $message['target_count'] = $message['total_users'] + $message['total_readers'];
            break;
    }
    $message['read_rate'] = $message['target_count'] > 0 ? round(($message['read_count'] / $message['target_count']) * 100, 1) : 0;
}

$totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_messages")['count'];
$totalPages = ceil($totalCount / $limit);

$pageTitle = '消息管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .messages-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .send-message-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }
        
        .messages-list-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .message-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .message-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .message-meta {
            text-align: right;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .message-content {
            color: #4b5563;
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .message-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f3f4f6;
        }
        
        .target-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .target-user {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .target-reader {
            background: #fef3c7;
            color: #92400e;
        }
        
        .target-all {
            background: #d1fae5;
            color: #065f46;
        }
        
        .read-stats {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .read-rate {
            font-weight: 600;
            color: #059669;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .message-meta {
                text-align: left;
                margin-top: 5px;
            }
            
            .message-stats {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="messages-container">
        <div class="page-header">
            <h1>📢 消息管理</h1>
            <p>向用户和塔罗师发送通知消息</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
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
        
        <div class="content-grid">
            <!-- 发送消息 -->
            <div class="send-message-card">
                <h3 style="margin-top: 0;">📝 发送新消息</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="title">消息标题 *</label>
                        <input type="text" id="title" name="title" required placeholder="输入消息标题">
                    </div>
                    
                    <div class="form-group">
                        <label for="target_type">发送对象 *</label>
                        <select id="target_type" name="target_type" required>
                            <option value="">请选择</option>
                            <option value="user">普通用户</option>
                            <option value="reader">塔罗师</option>
                            <option value="all">所有人</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">消息内容 *</label>
                        <textarea id="content" name="content" required placeholder="输入消息内容"></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn-primary">
                        📤 发送消息
                    </button>
                </form>
            </div>
            
            <!-- 消息列表 -->
            <div class="messages-list-card">
                <h3 style="margin-top: 0;">📋 消息记录</h3>
                
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📭</div>
                        <p>暂无消息记录</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <h4 class="message-title"><?php echo h($message['title']); ?></h4>
                                <div class="message-meta">
                                    <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="message-content">
                                <?php echo nl2br(h($message['content'])); ?>
                            </div>
                            
                            <div class="message-stats">
                                <div>
                                    <span class="target-type target-<?php echo $message['target_type']; ?>">
                                        <?php
                                        $targetNames = [
                                            'user' => '普通用户',
                                            'reader' => '塔罗师',
                                            'all' => '所有人'
                                        ];
                                        echo $targetNames[$message['target_type']];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="read-stats">
                                    已读：<span class="read-rate"><?php echo $message['read_count']; ?>/<?php echo $message['target_count']; ?></span>
                                    (<span class="read-rate"><?php echo $message['read_rate']; ?>%</span>)
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
