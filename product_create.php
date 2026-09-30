<?php
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';
csrf_check();


// ✅ 僅允許 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php?status=invalid_method');
    exit;
}

// =======================================================
// 1. 取得並驗證表單資料
// =======================================================
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

// ✅ 必填檢查
if ($name === '') {
    header('Location: product_new.php?status=error&msg=' . urlencode('商品名稱不可為空'));
    exit;
}

try {
    // =======================================================
    // 2. 寫入資料庫（防 SQL Injection）
    // =======================================================
    $sql = "INSERT INTO Products (name, description, is_active)
            VALUES (:name, :description, :is_active)";
    
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

    $stmt->execute();

    // ✅ 成功後導回
    header('Location: products.php?status=success&msg=' . urlencode('商品新增成功！'));
    exit;

} catch (PDOException $e) {

    $msg = urlencode("新增失敗：" . $e->getMessage());
    header("Location: product_new.php?status=db_error&msg={$msg}");
    exit;
}
