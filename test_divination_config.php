<?php
require_once 'includes/DivinationConfig.php';

echo "<h1>占卜类型配置测试</h1>";

echo "<h2>所有占卜类型：</h2>";
$allTypes = DivinationConfig::getAllDivinationTypes();
foreach ($allTypes as $categoryKey => $category) {
    echo "<h3>{$category['name']} ({$category['color']})</h3>";
    echo "<ul>";
    foreach ($category['types'] as $typeKey => $typeName) {
        echo "<li>{$typeKey} => {$typeName}</li>";
    }
    echo "</ul>";
}

echo "<h2>测试功能：</h2>";

// 测试获取类型名称
echo "<p>塔罗类型名称：" . DivinationConfig::getDivinationTypeName('tarot') . "</p>";
echo "<p>八字类型名称：" . DivinationConfig::getDivinationTypeName('bazi') . "</p>";

// 测试获取分类
echo "<p>塔罗分类：" . DivinationConfig::getDivinationCategory('tarot') . "</p>";
echo "<p>八字分类：" . DivinationConfig::getDivinationCategory('bazi') . "</p>";

// 测试标签样式类
echo "<p>塔罗标签类：" . DivinationConfig::getDivinationTagClass('tarot') . "</p>";
echo "<p>八字标签类：" . DivinationConfig::getDivinationTagClass('bazi') . "</p>";

// 测试验证功能
$testTypes = ['tarot', 'astrology', 'bazi'];
$testPrimary = 'tarot';
$validation = DivinationConfig::validateDivinationSelection($testTypes, $testPrimary);
echo "<h3>验证测试：</h3>";
echo "<p>选择类型：" . implode(', ', $testTypes) . "</p>";
echo "<p>主要类型：{$testPrimary}</p>";
echo "<p>验证结果：" . ($validation['valid'] ? '有效' : '无效') . "</p>";
if (!$validation['valid']) {
    echo "<p>错误信息：" . implode(', ', $validation['errors']) . "</p>";
}

// 测试生成标签HTML
echo "<h3>标签HTML测试：</h3>";
echo "<p>塔罗标签：" . DivinationConfig::generateDivinationTag('tarot', true) . "</p>";
echo "<p>八字标签：" . DivinationConfig::generateDivinationTag('bazi', false) . "</p>";
?>
