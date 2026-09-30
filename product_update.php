<?php
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';
csrf_check();


// ✅ 僅允許 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php?status=invalid_method');
    exit;
}


// 1. 獲取並驗證 POST 資料
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

// ✅ 基本檢查
if ($product_id <= 0 || $name === '') {
    header('Location: product_edit.php?id=' . $product_id . '&status=error&msg=' . urlencode('商品名稱不可為空。'));
    exit;
}

try {

    // 2. 更新資料庫
    $sql = "UPDATE Products 
            SET name = :name, description = :description, is_active = :is_active
            WHERE product_id = :product_id";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);

    $stmt->execute();

    // ✅ 成功 -> 回到編輯頁
    header('Location: product_edit.php?id=' . $product_id . '&status=success&msg=' . urlencode('商品資料更新成功！'));
    exit;

} catch (PDOException $e) {

    // 失敗回傳錯誤訊息
    $msg = urlencode("更新失敗：" . $e->getMessage());
    header("Location: product_edit.php?id={$product_id}&status=db_error&msg={$msg}");
    exit;
}
