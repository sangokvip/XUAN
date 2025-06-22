<?php
echo "<h1>文件检查工具</h1>";

$files = [
    'admin/users.php',
    'admin/statistics.php', 
    'admin/settings.php',
    'admin/dashboard.php',
    'admin/readers.php',
    'admin/generate_reader_link.php'
];

echo "<h2>文件存在性和权限检查</h2>";
echo "<table border='1'>";
echo "<tr><th>文件</th><th>存在</th><th>可读</th><th>大小</th><th>修改时间</th></tr>";

foreach ($files as $file) {
    echo "<tr>";
    echo "<td>{$file}</td>";
    echo "<td>" . (file_exists($file) ? '✓' : '✗') . "</td>";
    echo "<td>" . (is_readable($file) ? '✓' : '✗') . "</td>";
    echo "<td>" . (file_exists($file) ? filesize($file) . ' bytes' : '-') . "</td>";
    echo "<td>" . (file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : '-') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>目录检查</h2>";
$dirs = ['admin', 'config', 'includes', 'assets'];

foreach ($dirs as $dir) {
    echo "<p>{$dir}: ";
    if (is_dir($dir)) {
        echo "存在 | 可读: " . (is_readable($dir) ? '✓' : '✗');
        echo " | 文件数: " . count(scandir($dir)) - 2;
    } else {
        echo "不存在";
    }
    echo "</p>";
}

echo "<h2>测试链接</h2>";
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<a href='{$file}' target='_blank'>{$file}</a><br>";
    }
}
?>
