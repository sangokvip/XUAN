<?php
/**
 * 测试专长数据
 */

require_once 'config/config.php';

echo "<h1>专长数据测试</h1>";

try {
    $db = Database::getInstance();
    
    // 获取所有塔罗师的专长
    $readers = $db->fetchAll("SELECT id, full_name, specialties FROM readers WHERE is_active = 1");
    
    echo "<h2>所有塔罗师的专长:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>姓名</th><th>专长</th></tr>";
    
    $allSpecialties = [];
    
    foreach ($readers as $reader) {
        echo "<tr>";
        echo "<td>" . $reader['id'] . "</td>";
        echo "<td>" . htmlspecialchars($reader['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($reader['specialties']) . "</td>";
        echo "</tr>";
        
        // 收集所有专长
        if (!empty($reader['specialties'])) {
            $specialties = explode('、', $reader['specialties']);
            foreach ($specialties as $specialty) {
                $specialty = trim($specialty);
                if (!empty($specialty)) {
                    $allSpecialties[] = $specialty;
                }
            }
        }
    }
    
    echo "</table>";
    
    // 统计专长
    $specialtyCount = array_count_values($allSpecialties);
    arsort($specialtyCount);
    
    echo "<h2>专长统计:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>专长</th><th>塔罗师数量</th><th>测试链接</th></tr>";
    
    foreach ($specialtyCount as $specialty => $count) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($specialty) . "</td>";
        echo "<td>" . $count . "</td>";
        echo "<td><a href='readers.php?specialty=" . urlencode($specialty) . "'>测试筛选</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // 测试特定查询
    echo "<h2>测试'能量疗愈'查询:</h2>";
    $testSpecialty = '能量疗愈';
    $testResults = $db->fetchAll(
        "SELECT id, full_name, specialties FROM readers WHERE is_active = 1 AND specialties LIKE ?",
        ['%' . $testSpecialty . '%']
    );
    
    echo "<p>查询条件: specialties LIKE '%能量疗愈%'</p>";
    echo "<p>结果数量: " . count($testResults) . "</p>";
    
    if (!empty($testResults)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>姓名</th><th>专长</th></tr>";
        foreach ($testResults as $reader) {
            echo "<tr>";
            echo "<td>" . $reader['id'] . "</td>";
            echo "<td>" . htmlspecialchars($reader['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($reader['specialties']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>返回测试:</h2>";
echo "<a href='readers.php'>返回塔罗师列表</a>";
?>
