<?php
/**
 * 测试分页功能
 */

echo "<h1>分页测试</h1>";

// 获取参数
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');

echo "<h2>当前参数:</h2>";
echo "<ul>";
echo "<li>页码: $page</li>";
echo "<li>搜索: '$search'</li>";
echo "<li>专长: '$specialty'</li>";
echo "</ul>";

echo "<h2>原始GET参数:</h2>";
echo "<pre>" . print_r($_GET, true) . "</pre>";

// 测试URL构建函数
function buildPaginationUrl($pageNum, $search = '', $specialty = '') {
    $params = ['page' => $pageNum];
    if (!empty($search)) {
        $params['search'] = $search;
    }
    if (!empty($specialty)) {
        $params['specialty'] = $specialty;
    }
    return '?' . http_build_query($params);
}

echo "<h2>测试URL构建:</h2>";
echo "<ul>";
echo "<li>第1页: <a href='" . buildPaginationUrl(1, $search, $specialty) . "'>第1页</a></li>";
echo "<li>第2页: <a href='" . buildPaginationUrl(2, $search, $specialty) . "'>第2页</a></li>";
echo "<li>第3页: <a href='" . buildPaginationUrl(3, $search, $specialty) . "'>第3页</a></li>";
echo "</ul>";

echo "<h2>测试专长链接:</h2>";
echo "<ul>";
echo "<li><a href='?specialty=" . urlencode('能量疗愈') . "'>能量疗愈</a></li>";
echo "<li><a href='?specialty=" . urlencode('情感咨询') . "'>情感咨询</a></li>";
echo "<li><a href='?specialty=" . urlencode('事业发展') . "'>事业发展</a></li>";
echo "</ul>";

echo "<h2>测试组合链接:</h2>";
echo "<ul>";
echo "<li><a href='?page=2&specialty=" . urlencode('能量疗愈') . "'>第2页 + 能量疗愈</a></li>";
echo "<li><a href='?page=3&search=test&specialty=" . urlencode('情感咨询') . "'>第3页 + 搜索test + 情感咨询</a></li>";
echo "</ul>";

echo "<h2>返回测试:</h2>";
echo "<a href='readers.php'>返回塔罗师列表</a>";
?>
