<?php
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';
csrf_check();



// 確保請求有 ID 參數，且為數字
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: products.php?status=error&msg=' . urlencode('無效的商品ID或缺少參數。'));
    exit;
}

$product_id = (int)$_GET['id'];

// =======================================================
// 2. 執行資料庫刪除操作 (DELETE)
// =======================================================

try {
    // 準備 SQL 語句
    $sql = "DELETE FROM Products WHERE product_id = :product_id";
    $stmt = $pdo->prepare($sql);
    
    // 綁定參數
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    
    // 執行刪除
    $stmt->execute();
    
    // 檢查是否真的有資料被刪除
    if ($stmt->rowCount() > 0) {
        $msg = '商品 ID: ' . $product_id . ' 已成功刪除。';
        $status = 'success';
    } else {
        $msg = '刪除失敗：找不到 ID 為 ' . $product_id . ' 的商品。';
        $status = 'error';
    }

    // 導向回商品列表
    header('Location: products.php?status=' . $status . '&msg=' . urlencode($msg));
    exit;
    
} catch (PDOException $e) {
    // 處理刪除失敗的錯誤，特別是外鍵約束錯誤！
    $error_message = $e->getMessage();
    
    // 友好提示：如果商品仍被價格、訂單等其他表格引用，則不能刪除
    if (strpos($error_message, 'Cannot delete or update a parent row: a foreign key constraint fails') !== false) {
        $friendly_msg = '刪除失敗：此商品仍有相關的價格或訂單紀錄，請先清除相關紀錄後再刪除。';
    } else {
        $friendly_msg = '資料庫操作失敗: ' . $error_message;
    }
    
    header('Location: products.php?status=db_error&msg=' . urlencode($friendly_msg));
    exit;
}
?>