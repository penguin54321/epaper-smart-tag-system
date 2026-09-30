<?php
// price_overview.php - 實時價格總覽頁面（只顯示合法機台版）
require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';

// 允許的合法區域（與後台派送一致）
const ALLOWED_REGIONS = ['A機台', 'B機台'];

$latest_prices_by_region = [];
$status_message = '';

try {
    // 只抓「最新」且「合法區域」的紀錄，並固定區域排序：A機台 -> B機台，再依商品名排序
    $placeholders = implode(',', array_fill(0, count(ALLOWED_REGIONS), '?')); // ?, ?
    $sql = "
        SELECT
            p.product_id,
            p.name,
            pp.price,
            pp.region,
            pp.created_at
        FROM Products p
        JOIN product_price pp ON p.product_id = pp.product_id
        WHERE pp.is_latest = 1
          AND pp.region IN ($placeholders)
        ORDER BY FIELD(pp.region, " . str_repeat('?,', count(ALLOWED_REGIONS)-1) . "?) ASC, p.name ASC
    ";

    // 綁定兩次（一次給 IN，一次給 ORDER BY FIELD）
    $params = array_merge(ALLOWED_REGIONS, ALLOWED_REGIONS);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_latest_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 依區域分組
    foreach ($all_latest_prices as $row) {
        $region = $row['region'];
        $latest_prices_by_region[$region][] = $row;
    }

} catch (PDOException $e) {
    $status_message = '<div style="color:red; background-color:#f8d7da; padding:10px; border-radius:5px;">資料庫查詢失敗: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$pdo = null;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>實時價格總覽</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar { width: 220px; background: #333; color: white; display: flex; flex-direction: column; padding: 20px 0; height: 100vh; position: fixed; left: 0; top: 0; }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { padding: 12px 20px; color: white; text-decoration: none; display: block; transition: background 0.3s; }
        .sidebar a:hover { background: #444; }
        .content { margin-left: 220px; flex: 1; padding: 30px 40px; background: #f9f9f9; overflow-y: auto; }
        h1 { font-size: 26px; margin-bottom: 25px; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); padding: 20px 25px; margin-bottom: 25px; }
        .card h3 { margin-top: 0; margin-bottom: 15px; color: #2c3e50; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px 12px; text-align: left; font-size: 14px; }
        form button { padding: 8px 16px; background: #007bff; color: white; border: 1px solid #0056b3; border-radius: 4px; cursor: pointer; margin-top: 5px;}
        form button:hover { background: #0056b3; }
        .back-btn {
            display: inline-block;
            padding: 10px 15px;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background 0.2s;
            font-size: 16px;
        }
        .back-btn:hover { background: #dee2e6; color: #212529; }
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
        .user-info-name { font-weight: bold; color: #333; }
        .user-info-role { font-size: 13px; color: #888; }
        .logout-link { margin-left: 10px; color: #e74c3c; text-decoration: none; font-weight: bold; }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>電子紙後台</h2>
        <a href="products.php">商品管理</a>
        <a href="price_overview.php" style="color: #fff; font-weight: bold;">實時價格總覽</a>
        <a href="price_batch.php">批次價格編輯</a>
        <a href="#" onclick="alert('系統狀態功能正在規劃中...')">系統狀態</a>
    </div>

    <div class="user-info-bar">
        👤 <span class="user-info-name">
            <?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?>
        </span>
        <span class="user-info-role">
            (<?php echo htmlspecialchars($_SESSION['user']['role'] ?? ''); ?>)
        </span>
        <a href="logout.php" class="logout-link" onclick="return confirm('您確定要登出嗎？')">登出</a>
    </div>

    <div class="content">
        <a href="products.php" class="back-btn">← 返回</a>
        <h1>實時價格總覽 (按區域)</h1>

        <?php echo $status_message; ?>

        <?php if (empty($latest_prices_by_region)): ?>
            <div class="card"><p>目前沒有任何「合法區域」(A機台 / B機台) 的最新價格紀錄。</p></div>
        <?php else: ?>
            <?php foreach (ALLOWED_REGIONS as $region): ?>
                <?php if (!empty($latest_prices_by_region[$region])): ?>
                    <div class="card">
                        <h3>區域代碼：<?php echo htmlspecialchars($region); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>商品 ID</th>
                                    <th>商品名稱</th>
                                    <th>最新價格 ($)</th>
                                    <th>派送時間</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_prices_by_region[$region] as $row): ?>
                                    <tr>
                                        <td><?php echo $row['product_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>$<?php echo number_format($row['price'], 2); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                        <td><a href="product_edit.php?id=<?php echo $row['product_id']; ?>">編輯/歷史</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
