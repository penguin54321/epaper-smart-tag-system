<?php
// 在所有需要登入的頁面最上方 include 'auth.php';
require_once 'bootstrap.php';

if (!isset($_SESSION['user'])) {
  header('Location: login.php?msg='.urlencode('請先登入')); exit;
}

// 若要檢查角色（例如只允許 admin）
function require_role($roles = ['admin']) {
  if (!isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], (array)$roles, true)) {
    http_response_code(403);
    echo 'Forbidden: 權限不足'; exit;
  }
}
