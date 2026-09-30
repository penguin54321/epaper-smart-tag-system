<?php
require_once 'bootstrap.php';    // 啟動 session & CSRF
require_once 'auth.php';         // 需要登入
require_once 'db_config.php';    // 提供 $pdo
require_once 'vendor/autoload.php'; // PhpMqtt\Client

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// =================== MQTT 基本設定 ===================
$mqtt_server = 'your_mqtt_broker_ip';
$mqtt_port   = 1883;
$mqtt_user   = "your_mqtt_username";
$mqtt_pass   = "your_mqtt_password";
$mqtt_topic_base = "epaper/display/"; // epaper/display/{padded_id}/{region}

const TARGET_ID_LENGTH = 10;
const ALLOWED_REGIONS = ['A機台', 'B機台'];

function publishMqttMessage($server, $port, $user, $pass, $topic, $payload) {
    try {
        $client = new MqttClient($server, $port, uniqid('php_pub_'));
        $connectionSettings = (new ConnectionSettings())
            ->setUsername($user)
            ->setPassword($pass)
            ->setKeepAliveInterval(60);
        $client->connect($connectionSettings, true);
        // QOS=1, retain=false；若要裝置新上線也拿到最新價，可改成 true
        $client->publish($topic, $payload, 1, false);
        $client->disconnect();
        return true;
    } catch (\Exception $e) {
        error_log("MQTT 發佈失敗 (Topic: {$topic}): " . $e->getMessage());
        return false;
    }
}

function normalize_region($region) {
    return in_array($region, ALLOWED_REGIONS, true) ? $region : null;
}

$product = null;
$price_history = [];
$status_message = '';

// 取得商品 ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: products.php?status=error&msg=' . urlencode('無效的商品 ID。'));
    exit;
}

try {
    // 商品基本資料
    $stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        header('Location: products.php?status=error&msg=' . urlencode("找不到 ID:{$product_id} 的商品。"));
        exit;
    }

    // 歷史價格
    $stmt_history = $pdo->prepare("
        SELECT * FROM product_price
        WHERE product_id = :product_id
        ORDER BY created_at DESC, is_latest DESC
    ");
    $stmt_history->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_history->execute();
    $price_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    // ==== [A] 更新商品基本資料 ====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {

        csrf_check(); // ✅ CSRF 驗證（第一行）

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = (int)($_POST['is_active'] ?? 0);

        if ($name === '') {
            $status_message = '<div class="error-msg">商品名稱不可為空。</div>';
        } else {
            $sql = "UPDATE Products 
                    SET name = :name, description = :description, is_active = :is_active
                    WHERE product_id = :product_id";
            $stmt_update = $pdo->prepare($sql);
            $stmt_update->bindParam(':name', $name);
            $stmt_update->bindParam(':description', $description);
            $stmt_update->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $stmt_update->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_update->execute();

            // 更新頁面上顯示的值
            $product['name'] = $name;
            $product['description'] = $description;
            $product['is_active'] = $is_active;

            $status_message = '<div class="success-msg">商品基本資料更新成功！</div>';
        }
    }

    // ==== [B] 單一價格派送 ====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_price') {

        csrf_check(); // ✅ CSRF 驗證（第一行）

        $price  = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $region = trim($_POST['region'] ?? '');

        if ($price === false || $price < 0) {
            $status_message = '<div class="error-msg">價格格式不正確。</div>';
        } else {
            $pdo->beginTransaction();
            $mqtt_success = true;

            try {
                // 計算目標區域
                if ($region === 'ALL') {
                    $targets = ALLOWED_REGIONS;
                } else {
                    $norm = normalize_region($region);
                    if ($norm === null) {
                        throw new Exception('不支援的派送範圍：' . htmlspecialchars($region));
                    }
                    $targets = [$norm];
                }

                $payload = json_encode([
                    'price'    => number_format($price, 2, '.', ''),
                    'product'  => $product['name'],
                    'ts'       => time(),
                ], JSON_UNESCAPED_UNICODE);

                $padded_id = str_pad($product_id, TARGET_ID_LENGTH, '0', STR_PAD_LEFT);

                foreach ($targets as $r) {
                    // DB：清舊、寫新
                    $pdo->prepare("UPDATE product_price SET is_latest = 0 WHERE product_id = :pid AND region = :r")
                        ->execute([':pid' => $product_id, ':r' => $r]);

                    $pdo->prepare("INSERT INTO product_price (product_id, price, region, is_latest)
                                   VALUES (:pid, :price, :r, 1)")
                        ->execute([':pid' => $product_id, ':price' => $price, ':r' => $r]);

                    // MQTT：依區域派送
                    $topic = $mqtt_topic_base . $padded_id . '/' . $r;
                    if (!publishMqttMessage($mqtt_server, $mqtt_port, $mqtt_user, $mqtt_pass, $topic, $payload)) {
                        $mqtt_success = false;
                    }
                }

                $pdo->commit();

                $msg = ($region === 'ALL')
                    ? "✅ 已將「{$product['name']}」在所有機台（A、B）更新為 {$price} 元！"
                    : "✅ 已將「{$product['name']}」在 {$targets[0]} 更新為 {$price} 元！";
                $msg .= $mqtt_success ? " (MQTT 派送成功)" : " (⚠️ MQTT 發佈失敗)";

                header('Location: product_edit.php?id=' . $product_id . '&status=success&msg=' . urlencode($msg));
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $status_message = '<div class="error-msg">資料庫或派送失敗：'.htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status_message = '<div class="error-msg">資料庫操作失敗: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// 關閉連線（選擇性）
$pdo = null;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>編輯商品 - <?php echo htmlspecialchars($product['name']); ?></title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; height: 100vh; }
        .sidebar { width: 220px; background: #333; color: white; display: flex; flex-direction: column; padding: 20px 0; height: 100vh; position: fixed; left: 0; top: 0; }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { padding: 12px 20px; color: white; text-decoration: none; display: block; transition: background 0.3s; }
        .sidebar a:hover { background: #444; }
        .content { margin-left: 220px; flex: 1; padding: 30px 40px; background: #f9f9f9; overflow-y: auto; }
        h1 { font-size: 26px; margin-bottom: 25px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); padding: 20px 25px; margin-bottom: 25px; }
        .card h3 { margin-top: 0; margin-bottom: 15px; color: #2c3e50; font-size: 18px; }
        form label { display: inline-block; width: 100px; font-weight: bold; margin-bottom: 5px; }
        form input, form select, form textarea { padding: 6px 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; width: 300px; }
        form textarea { height: 80px; vertical-align: top; }
        form button { padding: 8px 16px; background: #007bff; color: white; border: 1px solid #0056b3; border-radius: 4px; cursor: pointer; margin-top: 5px;}
        form button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; font-size: 14px; }
        .success-msg { background-color: #d4edda; color: #155724; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; }
        .warning-msg { background-color: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; }
        .error-msg { background-color: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; }
        .current-price { background-color: #e6ffe6; font-weight: bold; }
        .back-btn { display: inline-block; padding: 10px 15px; background: #e9ecef; color: #495057; text-decoration: none; font-weight: bold; border-radius: 5px; margin-bottom: 25px; transition: background 0.2s; font-size: 16px; }
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
        <a href="products.php">商品管理</a>
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
        <a href="products.php" class="back-btn">← 返回商品管理列表</a>
        <h1>編輯商品：<?php echo htmlspecialchars($product['name']); ?> (ID: <?php echo $product_id; ?>)</h1>

        <?php 
        echo $status_message;
        if (isset($_GET['status'])): 
            $status_class = $_GET['status'] === 'success' ? 'success-msg' : ($_GET['status'] === 'warning' ? 'warning-msg' : 'error-msg');
            $status_icon = $_GET['status'] === 'success' ? '✅' : ($_GET['status'] === 'warning' ? '⚠️' : '❌');
        ?>
            <div class="<?php echo $status_class; ?>"><?php echo $status_icon; ?> <?php echo htmlspecialchars(urldecode($_GET['msg'])); ?></div>
        <?php endif; ?>

        <!-- 1. 商品基本資料 -->
        <div class="card">
            <h3>商品基本資料</h3>
            <form action="product_edit.php?id=<?php echo $product_id; ?>" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_product">
                <label>商品名稱：</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required><br>
                <label style="vertical-align: top;">描述：</label>
                <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea><br>
                <label>狀態：</label>
                <select name="is_active">
                    <option value="1" <?php echo $product['is_active'] == 1 ? 'selected' : ''; ?>>上架 (1)</option>
                    <option value="0" <?php echo $product['is_active'] == 0 ? 'selected' : ''; ?>>下架 (0)</option>
                </select><br>
                <button type="submit">更新基本資料</button>
            </form>
        </div>

        <!-- 2. 單一價格派送 -->
        <div class="card">
            <h3>單一價格派送 (MQTT 即時通知)</h3>
            <form action="product_edit.php?id=<?php echo $product_id; ?>" method="POST" class="price-form-group">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="set_price">
                <label>新價格 ($)：</label>
                <input type="number" name="price" step="0.01" min="0" required><br>
                <label>派送範圍：</label>
                <select name="region" required>
                    <option value="ALL">全機台</option>
                    <option value="A機台">A機台</option>
                    <option value="B機台">B機台</option>
                </select><br>
                <button type="submit" style="background: #e67e22; border-color: #d35400;">派送新價格</button>
                <span style="color: #e67e22;"> (此操作會更新資料庫並發佈 MQTT 訊息)</span>
            </form>
        </div>

        <!-- 3. 歷史紀錄 -->
        <div class="card">
            <h3>價格歷史紀錄</h3>
            <?php if (empty($price_history)): ?>
                <p>此商品目前沒有任何價格紀錄。</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>狀態</th>
                            <th>價格 ($)</th>
                            <th>區域代碼</th>
                            <th>派送時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($price_history as $history): ?>
                            <tr class="<?php echo $history['is_latest'] ? 'current-price' : ''; ?>">
                                <td><?php echo $history['is_latest'] ? '✨ 最新' : '歷史'; ?></td>
                                <td><?php echo number_format($history['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($history['region']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($history['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
