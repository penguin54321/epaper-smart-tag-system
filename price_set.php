<?php
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';
csrf_check();


// ✅ POST 才能來
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php?status=invalid_method');
    exit;
}

// 獲取並驗證數據
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$region = isset($_POST['region']) ? trim($_POST['region']) : '';
$price = isset($_POST['price']) ? round((float)$_POST['price'], 2) : 0.00;

if ($product_id <= 0 || empty($region) || $price <= 0) {
    header('Location: product_edit.php?id=' . $product_id . '&status=error&msg=' . urlencode('所有欄位皆為必填且價格需大於零。'));
    exit;
}

try {
    $pdo->beginTransaction();

    // 停用舊紀錄
    $sql_disable = "UPDATE product_price 
                    SET is_latest = 0 
                    WHERE product_id = :product_id AND region = :region AND is_latest = 1";
    $stmt_disable = $pdo->prepare($sql_disable);
    $stmt_disable->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_disable->bindParam(':region', $region);
    $stmt_disable->execute();

    // 建立新紀錄
    $sql_insert = "INSERT INTO product_price (product_id, region, price, is_latest) 
                   VALUES (:product_id, :region, :price, 1)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':region', $region);
    $stmt_insert->bindParam(':price', $price);
    $stmt_insert->execute();

    $pdo->commit();
    header('Location: product_edit.php?id=' . $product_id . '&status=success&msg=' . urlencode('區域價格已成功設定並建立新紀錄！'));
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error_msg = urlencode("價格設定失敗: " . $e->getMessage());
    header('Location: product_edit.php?id=' . $product_id . '&status=db_error&msg=' . $error_msg);
    exit;
}
