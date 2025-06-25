<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取当前塔罗师ID
$currentReaderId = (int)($_POST['reader_id'] ?? 0);

if ($currentReaderId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的塔罗师ID']);
    exit;
}

try {
    $db = Database::getInstance();

    // 获取随机的其他塔罗师（排除当前塔罗师）
    $relatedReaders = $db->fetchAll(
        "SELECT * FROM readers
         WHERE id != ? AND is_active = 1
         ORDER BY RAND()
         LIMIT 3",
        [$currentReaderId]
    );

    if (empty($relatedReaders)) {
        echo json_encode(['success' => false, 'message' => '暂无其他塔罗师']);
        exit;
    }

    // 调试信息
    error_log("API: 找到 " . count($relatedReaders) . " 个塔罗师");
    
    // 生成HTML内容
    $html = '';
    foreach ($relatedReaders as $relatedReader) {
        $html .= '<div class="reader-card">';
        $html .= '<div class="reader-photo">';
        $html .= '<a href="' . (defined('SITE_URL') ? SITE_URL : '') . '/reader.php?id=' . $relatedReader['id'] . '" class="reader-photo-link">';
        
        if (!empty($relatedReader['photo'])) {
            $html .= '<img src="' . htmlspecialchars($relatedReader['photo']) . '" alt="' . htmlspecialchars($relatedReader['full_name']) . '">';
        } else {
            $html .= '<div class="default-photo"><i class="icon-user"></i></div>';
        }
        
        $html .= '</a>';
        $html .= '</div>';
        
        $html .= '<div class="reader-info">';
        $html .= '<h3>' . htmlspecialchars($relatedReader['full_name']) . '</h3>';
        $html .= '<p>从业 ' . htmlspecialchars($relatedReader['experience_years']) . ' 年</p>';
        
        // 添加专长标签
        if (!empty($relatedReader['specialties'])) {
            $html .= '<div class="specialties">';
            $html .= '<strong>擅长：</strong>';
            
            $systemSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
            $specialties = explode('、', $relatedReader['specialties']);
            
            foreach ($specialties as $specialtyItem) {
                $specialtyItem = trim($specialtyItem);
                if (!empty($specialtyItem) && in_array($specialtyItem, $systemSpecialties)) {
                    $html .= '<a href="readers.php?specialty=' . urlencode($specialtyItem) . '" class="specialty-tag specialty-' . htmlspecialchars($specialtyItem) . '">' . htmlspecialchars($specialtyItem) . '</a>';
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '<a href="' . (defined('SITE_URL') ? SITE_URL : '') . '/reader.php?id=' . $relatedReader['id'] . '" class="btn btn-primary">查看详情</a>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log("API错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '获取数据失败：' . $e->getMessage()
    ]);
}
?>
