<?php
/**
 * 数据库更新脚本 - 添加占卜师表缺失字段
 * 用于添加nationality、divination_types、primary_identity、identity_category字段
 */

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>占卜师表字段更新</h2>\n";
    
    // 检查并添加nationality字段
    $result = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'nationality'");
    if (!$result) {
        $sql = "ALTER TABLE readers ADD COLUMN nationality VARCHAR(10) DEFAULT 'CN' COMMENT '国籍代码' AFTER gender";
        $db->query($sql);
        echo "✓ 添加nationality字段成功<br>\n";
    } else {
        echo "✓ nationality字段已存在<br>\n";
    }

    // 检查并添加divination_types字段
    $result = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'divination_types'");
    if (!$result) {
        $sql = "ALTER TABLE readers ADD COLUMN divination_types TEXT DEFAULT NULL COMMENT '占卜类型（JSON格式）' AFTER custom_specialties";
        $db->query($sql);
        echo "✓ 添加divination_types字段成功<br>\n";
    } else {
        echo "✓ divination_types字段已存在<br>\n";
    }

    // 检查并添加primary_identity字段
    $result = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'primary_identity'");
    if (!$result) {
        $sql = "ALTER TABLE readers ADD COLUMN primary_identity VARCHAR(50) DEFAULT NULL COMMENT '主要身份标签' AFTER divination_types";
        $db->query($sql);
        echo "✓ 添加primary_identity字段成功<br>\n";
    } else {
        echo "✓ primary_identity字段已存在<br>\n";
    }

    // 检查并添加identity_category字段
    $result = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'identity_category'");
    if (!$result) {
        $sql = "ALTER TABLE readers ADD COLUMN identity_category ENUM('western', 'eastern') DEFAULT NULL COMMENT '身份类别：western-西玄，eastern-东玄' AFTER primary_identity";
        $db->query($sql);
        echo "✓ 添加identity_category字段成功<br>\n";
    } else {
        echo "✓ identity_category字段已存在<br>\n";
    }
    
    echo "<br><strong>数据库更新完成！</strong><br>\n";
    echo "<a href='admin/readers.php'>返回占卜师管理</a><br>\n";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>错误：" . $e->getMessage() . "</div>\n";
}
?>
