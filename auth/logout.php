<?php
session_start();
require_once '../config/config.php';

// 执行登出
logout();

// 重定向到首页
redirect('../index.php');
?>
