<?php

// price_batch_set.php - 處理批次價格設定與派送
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';
csrf_check();


// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: price_batch.php?status=invalid_method&msg=' . urlencode('不支援的請求方法。'));
    exit;
}

// 1. 獲取並驗證 POST 數據
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

// 處理 region 字串，分割成陣列，並去除空白
$region_input = isset($_POST['region']) ? trim($_POST['region']) : '';
$regions = array_filter(array_map('trim', explode(',', $region_input)));

// 獲取選定的商品 ID 陣列
$product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];

// 簡單驗證
if ($price <= 0 || empty($regions) || empty($product_ids)) {
    header('Location: price_batch.php?status=error&msg=' . urlencode('請確保價格、區域和商品至少選擇一項。'));
    exit;
}

$success_count = 0;
$error_messages = [];

try {
    // 啟用事務 (Transaction)
    $pdo->beginTransaction();

    // 停用舊紀錄
    $sql_disable = "UPDATE product_price SET is_latest = 0 
                    WHERE product_id = :product_id AND region = :region AND is_latest = 1";
    $stmt_disable = $pdo->prepare($sql_disable);
    
    // 新增新紀錄
    $sql_insert = "INSERT INTO product_price (product_id, region, price, is_latest) 
                   VALUES (:product_id, :region, :price, 1)";
    $stmt_insert = $pdo->prepare($sql_insert);

    // 核心巢狀迴圈
    foreach ($product_ids as $product_id) {
        $product_id = (int)$product_id;
        
        foreach ($regions as $region) {
            $region = trim($region);

            // 停用舊紀錄
            $stmt_disable->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_disable->bindParam(':region', $region);
            $stmt_disable->execute();

            // 插入新紀錄
            $stmt_insert->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':region', $region);
            $stmt_insert->bindParam(':price', $price);
            $stmt_insert->execute();
            
            $success_count++;
        }
    }

    // 提交
    $pdo->commit();

    $msg = "批次派送成功！共更新 {$success_count} 筆價格紀錄。";
    header('Location: price_batch.php?status=success&msg=' . urlencode($msg));
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error_msg = urlencode("批次派送失敗: " . $e->getMessage());
    header('Location: price_batch.php?status=db_error&msg=' . $error_msg);
    exit;
}
?>
