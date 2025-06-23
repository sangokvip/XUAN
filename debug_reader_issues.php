<?php
require_once 'config/config.php';
require_once 'includes/ReviewManager.php';

try {
    $db = Database::getInstance();
    $readerId = (int)($_GET['id'] ?? 1);
    
    echo "<h2>调试塔罗师页面问题</h2>";
    echo "<p>塔罗师ID: {$readerId}</p>";
    
    // 1. 检查塔罗师基本信息
    echo "<h3>1. 塔罗师基本信息</h3>";
    $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
    if ($reader) {
        echo "<p>✅ 塔罗师存在</p>";
        echo "<p>姓名: " . htmlspecialchars($reader['full_name']) . "</p>";
        echo "<p>注册时间: " . $reader['created_at'] . "</p>";
        echo "<p>从业年数: " . $reader['experience_years'] . "</p>";
        echo "<p>平均评分: " . ($reader['average_rating'] ?? '未设置') . "</p>";
        echo "<p>总评价数: " . ($reader['total_reviews'] ?? '未设置') . "</p>";
    } else {
        echo "<p>❌ 塔罗师不存在</p>";
        exit;
    }
    
    // 2. 检查评价系统
    echo "<h3>2. 评价系统检查</h3>";
    $reviewManager = new ReviewManager();
    
    if ($reviewManager->isInstalled()) {
        echo "<p>✅ 评价系统已安装</p>";
        
        // 检查评价统计
        $reviewStats = $reviewManager->getReviewStats($readerId);
        echo "<p>评价统计:</p>";
        echo "<ul>";
        echo "<li>总评价数: " . $reviewStats['total_reviews'] . "</li>";
        echo "<li>平均评分: " . number_format($reviewStats['average_rating'], 2) . "</li>";
        echo "<li>5星: " . $reviewStats['rating_5'] . "</li>";
        echo "<li>4星: " . $reviewStats['rating_4'] . "</li>";
        echo "<li>3星: " . $reviewStats['rating_3'] . "</li>";
        echo "<li>2星: " . $reviewStats['rating_2'] . "</li>";
        echo "<li>1星: " . $reviewStats['rating_1'] . "</li>";
        echo "</ul>";
        
        // 检查评价列表
        $reviews = $reviewManager->getReviews($readerId, 10, 0);
        echo "<p>评价列表: " . count($reviews) . " 条</p>";
        
        // 检查问题列表
        $questions = $reviewManager->getQuestions($readerId, 10, 0);
        echo "<p>问题列表: " . count($questions) . " 条</p>";
        
    } else {
        echo "<p>❌ 评价系统未安装</p>";
    }
    
    // 3. 检查数据库表
    echo "<h3>3. 数据库表检查</h3>";
    
    $tables = ['reader_reviews', 'reader_questions', 'reader_question_answers'];
    foreach ($tables as $table) {
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}")['count'];
            echo "<p>✅ {$table}: {$count} 条记录</p>";
        } catch (Exception $e) {
            echo "<p>❌ {$table}: 表不存在或错误 - " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. 检查该塔罗师的具体数据
    echo "<h3>4. 该塔罗师的具体数据</h3>";
    
    try {
        $readerReviews = $db->fetchAll("SELECT * FROM reader_reviews WHERE reader_id = ?", [$readerId]);
        echo "<p>该塔罗师的评价: " . count($readerReviews) . " 条</p>";
        
        $readerQuestions = $db->fetchAll("SELECT * FROM reader_questions WHERE reader_id = ?", [$readerId]);
        echo "<p>该塔罗师的问题: " . count($readerQuestions) . " 条</p>";
        
        if (!empty($readerQuestions)) {
            echo "<h4>问题详情:</h4>";
            foreach ($readerQuestions as $q) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px 0;'>";
                echo "<p><strong>问题ID:</strong> " . $q['id'] . "</p>";
                echo "<p><strong>用户ID:</strong> " . $q['user_id'] . "</p>";
                echo "<p><strong>问题:</strong> " . htmlspecialchars($q['question']) . "</p>";
                echo "<p><strong>创建时间:</strong> " . $q['created_at'] . "</p>";
                
                // 检查该问题的回答
                $answers = $db->fetchAll("SELECT * FROM reader_question_answers WHERE question_id = ?", [$q['id']]);
                echo "<p><strong>回答数:</strong> " . count($answers) . "</p>";
                
                if (!empty($answers)) {
                    echo "<div style='margin-left: 20px;'>";
                    foreach ($answers as $a) {
                        echo "<div style='border-left: 2px solid #007bff; padding-left: 10px; margin: 5px 0;'>";
                        echo "<p><strong>回答ID:</strong> " . $a['id'] . "</p>";
                        echo "<p><strong>用户ID:</strong> " . $a['user_id'] . "</p>";
                        echo "<p><strong>回答:</strong> " . htmlspecialchars($a['answer']) . "</p>";
                        echo "<p><strong>创建时间:</strong> " . $a['created_at'] . "</p>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p>❌ 查询数据时出错: " . $e->getMessage() . "</p>";
    }
    
    // 5. 检查重复数据
    echo "<h3>5. 检查重复数据</h3>";
    
    try {
        $duplicateQuestions = $db->fetchAll("
            SELECT question, COUNT(*) as count 
            FROM reader_questions 
            WHERE reader_id = ? 
            GROUP BY question 
            HAVING COUNT(*) > 1
        ", [$readerId]);
        
        if (!empty($duplicateQuestions)) {
            echo "<p>❌ 发现重复问题:</p>";
            foreach ($duplicateQuestions as $dup) {
                echo "<p>问题: " . htmlspecialchars($dup['question']) . " (重复 " . $dup['count'] . " 次)</p>";
            }
        } else {
            echo "<p>✅ 没有重复问题</p>";
        }
        
        $duplicateAnswers = $db->fetchAll("
            SELECT answer, question_id, COUNT(*) as count 
            FROM reader_question_answers 
            WHERE question_id IN (SELECT id FROM reader_questions WHERE reader_id = ?)
            GROUP BY answer, question_id 
            HAVING COUNT(*) > 1
        ", [$readerId]);
        
        if (!empty($duplicateAnswers)) {
            echo "<p>❌ 发现重复回答:</p>";
            foreach ($duplicateAnswers as $dup) {
                echo "<p>回答: " . htmlspecialchars($dup['answer']) . " (重复 " . $dup['count'] . " 次)</p>";
            }
        } else {
            echo "<p>✅ 没有重复回答</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ 检查重复数据时出错: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='reader.php?id={$readerId}'>返回塔罗师页面</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
