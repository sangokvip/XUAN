<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$error = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $readerId = (int)($_POST['reader_id'] ?? 0);
    
    if ($action === 'toggle_featured' && $readerId) {
        $reader = $db->fetchOne("SELECT is_featured FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            $newStatus = $reader['is_featured'] ? 0 : 1;
            $db->update('readers', ['is_featured' => $newStatus], 'id = ?', [$readerId]);
            $success = $newStatus ? '已设为推荐塔罗师' : '已取消推荐';
        }
    }
    
    elseif ($action === 'toggle_active' && $readerId) {
        $reader = $db->fetchOne("SELECT is_active FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            $newStatus = $reader['is_active'] ? 0 : 1;
            $db->update('readers', ['is_active' => $newStatus], 'id = ?', [$readerId]);
            $success = $newStatus ? '已激活塔罗师' : '已禁用塔罗师';
        }
    }

    elseif ($action === 'delete_reader' && $readerId) {
        // 获取塔罗师信息
        $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
        if ($reader) {
            // 删除相关文件
            $filesToDelete = [];
            if (!empty($reader['photo']) && file_exists('../' . $reader['photo'])) {
                $filesToDelete[] = '../' . $reader['photo'];
            }
            if (!empty($reader['price_list_image']) && file_exists('../' . $reader['price_list_image'])) {
                $filesToDelete[] = '../' . $reader['price_list_image'];
            }

            // 删除证书文件
            if (!empty($reader['certificates'])) {
                $certificates = json_decode($reader['certificates'], true);
                if (is_array($certificates)) {
                    foreach ($certificates as $cert) {
                        if (file_exists('../' . $cert)) {
                            $filesToDelete[] = '../' . $cert;
                        }
                    }
                }
            }

            // 删除数据库记录
            $db->delete('readers', 'id = ?', [$readerId]);

            // 删除文件
            foreach ($filesToDelete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $success = '塔罗师已删除';
        }
    }
}

// 获取筛选参数
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$featured = $_GET['featured'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// 构建查询条件
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (full_name LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if ($status === 'active') {
    $whereClause .= " AND is_active = 1";
} elseif ($status === 'inactive') {
    $whereClause .= " AND is_active = 0";
}

if ($featured === 'yes') {
    $whereClause .= " AND is_featured = 1";
} elseif ($featured === 'no') {
    $whereClause .= " AND is_featured = 0";
}

// 获取塔罗师数据
$offset = ($page - 1) * ADMIN_ITEMS_PER_PAGE;

$readers = $db->fetchAll(
    "SELECT r.*, 
            (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id) as view_count
     FROM readers r 
     {$whereClause} 
     ORDER BY r.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [ADMIN_ITEMS_PER_PAGE, $offset])
);

// 获取总数
$totalResult = $db->fetchOne(
    "SELECT COUNT(*) as total FROM readers r {$whereClause}",
    $params
);
$total = $totalResult['total'];
$totalPages = ceil($total / ADMIN_ITEMS_PER_PAGE);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师管理 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>塔罗师管理</h1>
                <div class="page-actions">
                    <a href="reader_add.php" class="btn btn-primary">添加塔罗师</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <!-- 筛选器 -->
            <div class="readers-filters">
                <form method="GET">
                    <div class="filters-row">
                        <div class="form-group">
                            <label for="search">搜索</label>
                            <input type="text" id="search" name="search"
                                   placeholder="姓名或邮箱"
                                   value="<?php echo h($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">状态</label>
                            <select id="status" name="status">
                                <option value="">全部状态</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>已激活</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>已禁用</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="featured">推荐状态</label>
                            <select id="featured" name="featured">
                                <option value="">全部</option>
                                <option value="yes" <?php echo $featured === 'yes' ? 'selected' : ''; ?>>已推荐</option>
                                <option value="no" <?php echo $featured === 'no' ? 'selected' : ''; ?>>未推荐</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">筛选</button>
                            <a href="readers.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 塔罗师列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>塔罗师列表 (共 <?php echo $total; ?> 位)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($readers)): ?>
                        <p class="no-data">没有找到塔罗师</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>姓名</th>
                                        <th>邮箱</th>
                                        <th>从业年数</th>
                                        <th>查看次数</th>
                                        <th>状态</th>
                                        <th>推荐</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($readers as $reader): ?>
                                        <tr>
                                            <td><?php echo h($reader['full_name']); ?></td>
                                            <td><?php echo h($reader['email']); ?></td>
                                            <td><?php echo h($reader['experience_years']); ?>年</td>
                                            <td><?php echo h($reader['view_count']); ?></td>
                                            <td>
                                                <?php if ($reader['is_active']): ?>
                                                    <span class="status-active">已激活</span>
                                                <?php else: ?>
                                                    <span class="status-expired">已禁用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reader['is_featured']): ?>
                                                    <span class="status-active">已推荐</span>
                                                <?php else: ?>
                                                    <span class="status-used">未推荐</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($reader['created_at'])); ?></td>
                                            <td>
                                                <div class="reader-actions">
                                                    <a href="reader_edit.php?id=<?php echo $reader['id']; ?>" class="btn btn-sm btn-primary">编辑</a>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_featured">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $reader['is_featured'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                                            <?php echo $reader['is_featured'] ? '取消推荐' : '设为推荐'; ?>
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $reader['is_active'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                                            <?php echo $reader['is_active'] ? '禁用' : '激活'; ?>
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这位塔罗师吗？此操作不可恢复！');">
                                                        <input type="hidden" name="action" value="delete_reader">
                                                        <input type="hidden" name="reader_id" value="<?php echo $reader['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
                                       class="btn btn-secondary">上一页</a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
                                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&featured=<?php echo urlencode($featured); ?>" 
                                       class="btn btn-secondary">下一页</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
