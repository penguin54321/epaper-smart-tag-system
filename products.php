<?php
// products.php - 商品列表
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';


$products = [];
try {
    // 💡 修正後的 SQL 查詢：新增獲取【最後派送時間】的子查詢
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            (
                SELECT MIN(pp.price) 
                FROM product_price pp 
                WHERE pp.product_id = p.product_id 
                AND pp.is_latest = 1
            ) AS latest_min_price,
            (
                SELECT MAX(pp.created_at)
                FROM product_price pp
                WHERE pp.product_id = p.product_id
            ) AS last_dispatch_time -- 💡 新增的欄位
        FROM Products p
        ORDER BY p.product_id DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // ... (錯誤處理保持不變) ...
}

// 保持 $pdo = null; 關閉連線
$pdo = null; 
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>電子紙後台管理系統</title>
    <style>
        /* 由於 CSS 內容較長，這裡省略。請保留您原本的 <style> 內容 */
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar { width: 220px; background: #333; color: white; display: flex; flex-direction: column; padding: 20px 0; height: 100vh; position: fixed; left: 0; top: 0; }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { padding: 12px 20px; color: white; text-decoration: none; display: block; transition: background 0.3s; }
        .sidebar a:hover { background: #444; }
        .content { margin-left: 220px; flex: 1; padding: 30px 40px; background: #f9f9f9; overflow-y: auto; }
        .page { display: none; }
        .page.active { display: block; }
        h1 { font-size: 26px; margin-bottom: 25px; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); padding: 20px 25px; margin-bottom: 25px; }
        .card h3 { margin-top: 0; margin-bottom: 15px; color: #2c3e50; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px 12px; text-align: left; font-size: 14px; }
        .status { font-weight: bold; }
        .status.ok { color: green; }
        .status.fail { color: red; }
        .status.warn { color: orange; }
        form label { display: inline-block; width: 80px; font-weight: bold; margin-bottom: 5px; }
        form input, form select { padding: 6px 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; }
        form button { padding: 8px 16px; background: #007bff; color: white; border: 1px solid #0056b3; border-radius: 4px; cursor: pointer; margin-top: 5px;}
        form button:hover { background: #0056b3; }
        .toast { visibility: hidden; min-width: 200px; background: #333; color: #fff; text-align: center; border-radius: 8px; padding: 8px; position: fixed; bottom: 30px; right: 30px; z-index: 1000; opacity: 0; transition: opacity 0.5s, visibility 0.5s; }
        .toast.show { visibility: visible; opacity: 1; }
        .user-info-bar {
            position: fixed;
            top: 10px;
            right: 20px;
            background: #fff;
            border: 1px solid #ddd;
            padding: 8px 14px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-size: 14px;
            z-index: 999;
        }
        .user-info-name {
            font-weight: bold;
            color: #333;
        }
        .user-info-role {
            font-size: 13px;
            color: #888;
        }
        .logout-link {
            margin-left: 10px;
            color: #e74c3c;
            text-decoration: none;
            font-weight: bold;
        }
        .logout-link:hover {
            text-decoration: underline;
        }

                
    </style>
    </head>

<body>

   <div class="sidebar">
        <h2>電子紙後台</h2>
        <a href="products.php" style="color: #fff; font-weight: bold;">商品管理</a>
        <a href="price_overview.php">實時價格總覽</a> 
        <a href="price_batch.php">批次價格編輯</a>
        <a href="#" onclick="alert('系統狀態功能正在規劃中...')">系統狀態</a>
    </div>
    <div class="user-info-bar">
        👤 <span class="user-info-name">
            <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
        </span>
        <span class="user-info-role">
            (<?php echo htmlspecialchars($_SESSION['user']['role']); ?>)
        </span>
        <a href="logout.php" class="logout-link" onclick="return confirm('您確定要登出嗎？')">登出</a>

    </div>


    <div class="content">
        <?php if (isset($db_error)): ?>
            <div style="background-color: #fdd; border: 1px solid #f00; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>資料庫錯誤！</strong> <?php echo $db_error; ?>
            </div>
        <?php endif; ?>

        <div id="dashboard" class="page active">
            <h1>📊 儀表板</h1>
            <div class="card">
    <h3>現有商品列表</h3>
    <a href="product_add.php" style="
        display: inline-block; 
        padding: 8px 16px; 
        background: #194779ff; 
        color: white; 
        border: 1px solid #052242ff; 
        border-radius: 4px; 
        text-decoration: none;
        vertical-align: top;
        margin-botton: 5px; /* 稍微對齊標題 */
    ">＋ 新增商品</a>
    <table id="productsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>商品名稱</th>
                <th>描述</th>
                <th>狀態</th>
                <th>最新價格 (最低)</th> 
                <th>最後派送時間</th> 
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['product_id']; ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($product['description'], 0, 30)) . '...'; ?></td>
                    <td><?php echo $product['is_active'] ? '上架' : '下架'; ?></td>
                    
                <td>
                    <?php 
                        if (!is_null($product['last_dispatch_time'])) { // 檢查是否為 NULL 即可
                            // 格式化時間，只顯示年月日和小時
                            echo date('Y-m-d H:i', strtotime($product['last_dispatch_time']));
                        } else {
                            echo '無紀錄'; // 如果從未設定過價格
                        }
                    ?>
                </td>
                    
                    <td>
                        <?php 
                            if ($product['last_dispatch_time'] !== null) {
                                // 格式化時間，只顯示年月日和小時
                                echo date('Y-m-d H:i', strtotime($product['last_dispatch_time']));
                            } else {
                                echo '無紀錄'; // 如果從未設定過價格
                            }
                        ?>
                    </td>
                    
                     <td>
                        <a href="product_edit.php?id=<?php echo $product['product_id']; ?>">編輯/價格</a>
                        
                        <a href="product_delete.php?id=<?php echo $product['product_id']; ?>" 
                        style="color: #dc3545; margin-left: 10px;"
                        onclick="return confirm('❗ 警告：確定要刪除此商品嗎？所有價格紀錄也將被【永久移除】！')">
                        | 刪除
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    

        

        <div id="prices" class="page">
            </div>
        <div id="system" class="page">
            </div>
    </div>

    <div id="toast" class="toast">✅ 派送成功！</div>

    
</body>
</html>