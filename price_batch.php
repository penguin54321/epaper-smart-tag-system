<?php
// price_batch.php - 批次價格設定與派送介面

require_once 'bootstrap.php';
require_once 'auth.php';
require_once 'db_config.php';

// require_once 'auth.php';          // 如需權限保護再開，並在下方加 require_role(['admin']);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// 若要只給 admin：
// require_role(['admin']);

// =============== MQTT 連線配置 ===============
$mqtt_server = 'your_mqtt_broker_ip';
$mqtt_port   = 1883;
$mqtt_user   = "your_mqtt_username";
$mqtt_pass   = "your_mqtt_passport";
$mqtt_topic_base = "epaper/display/";

// =============== 發佈函式 ===============
function publishMqttMessage($server, $port, $user, $pass, $topic, $payload) {
    try {
        $client = new MqttClient($server, $port, uniqid('php_pub_'));
        $connectionSettings = (new ConnectionSettings())
            ->setUsername($user)
            ->setPassword($pass)
            ->setKeepAliveInterval(60);
        $client->connect($connectionSettings, true);
        $client->publish($topic, $payload, 1, false);
        $client->disconnect();
        return true;
    } catch (\Exception $e) {
        error_log("MQTT 發佈失敗 (Topic: {$topic}): " . $e->getMessage());
        return false;
    }
}

$products = [];
$status_message = '';
$all_regions = ['A機台', 'B機台', '全機台'];
const TARGET_ID_LENGTH = 10;

try {
    // 取得可用商品
    $stmt = $pdo->prepare("SELECT product_id, name FROM Products WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =============== POST：批次派送邏輯 ===============
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();  // ✅ 第一行做 CSRF 驗證

        $selected_product_ids = (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) ? $_POST['product_ids'] : [];
        $selected_regions_raw = (isset($_POST['regions']) && is_array($_POST['regions'])) ? $_POST['regions'] : [];

        if (empty($selected_product_ids) || empty($selected_regions_raw)) {
            $status_message = '<div style="background-color: #ffc107; color: #333; padding: 10px; border-radius: 4px;">⚠️ 請至少選擇一個商品和一個區域。</div>';
        } else {
            // 只允許 A/B；若勾「全機台」就展開成 A+B
            $allowed = ['A機台', 'B機台'];
            $selected_regions = [];
            foreach ($selected_regions_raw as $r) {
                if ($r === '全機台') {
                    $selected_regions = $allowed; // 直接覆蓋成 A+B
                    break;
                }
                if (in_array($r, $allowed, true)) {
                    $selected_regions[] = $r;
                }
            }
            $selected_regions = array_values(array_unique($selected_regions));

            if (empty($selected_regions)) {
                $status_message = '<div style="background-color: #ffc107; color: #333; padding: 10px; border-radius: 4px;">⚠️ 目標區域不合法，僅支援 A機台 / B機台。</div>';
            } else {
                $pdo->beginTransaction();
                $dispatch_count = 0;
                $mqtt_success_count = 0;

                try {
                    // 查每個 (product, region) 的最新價格
                    $ph_products = implode(',', array_fill(0, count($selected_product_ids), '?'));
                    $ph_regions  = implode(',', array_fill(0, count($selected_regions), '?'));

                    $sql_data = "
                        SELECT p.product_id, p.name, pp.price, pp.region
                        FROM Products p
                        JOIN product_price pp 
                          ON p.product_id = pp.product_id
                        WHERE p.product_id IN ($ph_products)
                          AND pp.is_latest = 1
                          AND pp.region IN ($ph_regions)
                    ";
                    $all_params = array_merge($selected_product_ids, $selected_regions);
                    $stmt_data = $pdo->prepare($sql_data);
                    $stmt_data->execute($all_params);
                    $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($rows)) {
                        throw new Exception('所選組合沒有對應的最新價格紀錄，請先在單品頁設定價格。');
                    }

                    // 以 (product_id, region) 去重，避免重複派送
                    $seen = [];
                    foreach ($rows as $item) {
                        $key = $item['product_id'] . '|' . $item['region'];
                        if (isset($seen[$key])) continue;
                        $seen[$key] = true;

                        $padded_id = str_pad($item['product_id'], TARGET_ID_LENGTH, '0', STR_PAD_LEFT);
                        $topic = $mqtt_topic_base . $padded_id . '/' . $item['region'];

                        $payload = json_encode([
                            'price'   => number_format($item['price'], 2, '.', ''),
                            'region'  => $item['region'],
                            'product' => $item['name'],
                            'ts'      => time(),
                        ], JSON_UNESCAPED_UNICODE);

                        if (publishMqttMessage($mqtt_server, $mqtt_port, $mqtt_user, $mqtt_pass, $topic, $payload)) {
                            $mqtt_success_count++;
                        }
                        $dispatch_count++;
                    }

                    $pdo->commit();
                    $msg = "批次派送完成：共 {$dispatch_count} 筆，成功 {$mqtt_success_count} 筆。";
                    header('Location: price_batch.php?status=success&msg=' . urlencode($msg));
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $status_message = '<div style="background-color:#f8d7da;color:#721c24;padding:10px;border-radius:4px;">❌ 派送失敗：' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status_message = '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">❌ 資料庫操作失敗: ' 
        . htmlspecialchars($e->getMessage()) . '</div>';
}

$pdo = null;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>批次價格設定</title>
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
        form input, form select, form textarea { padding: 6px 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; width: auto; }
        .select-list-container { display: flex; gap: 30px; margin-bottom: 20px;}
        .select-list { max-height: 250px; width: 250px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 4px; background: #fff; }
        .select-list:nth-child(2) { width: 350px; }
        .select-list label { display: block; font-weight: normal; margin-bottom: 3px; font-size: 14px; cursor: pointer; }
        .select-list label:hover { background-color: #f0f0f0; }
        .dispatch-btn { padding: 10px 20px; background: #e67e22; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; font-weight: bold; }
        .dispatch-btn:hover { background: #d35400; }
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
        <a href="price_batch.php" style="color: #fff; font-weight: bold;">批次價格編輯</a>
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
        <?php echo $status_message; ?>

        <!-- 顯示 GET 參數的狀態訊息 -->
        <?php if (isset($_GET['status'])): ?>
            <?php 
                $status = $_GET['status'];
                $message = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
                if ($status == 'success'): ?>
                    <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; font-weight: bold;">
                        ✅ <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php elseif ($status == 'db_error' || $status == 'error' || $status == 'warning'): ?>
                    <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; font-weight: bold;">
                        ❌ 派送失敗：<?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; 
            ?>
        <?php endif; ?>

        <h1>智能批次價格派送</h1>

        <div class="card">
            <h3>選擇目標商品與區域</h3>
            <form action="price_batch.php" method="POST">
                <?php csrf_field(); ?>  <!-- ✅ token 要放在表單裡，而不是 PHP 程式邏輯區塊 -->

                <div class="select-list-container">
                    <!-- 區域選擇 -->
                    <div class="form-group-item">
                        <h4>目標區域 (Region):</h4>
                        <p>選擇要更新價格的地區。</p>
                        <div class="select-list">
                            <?php foreach ($all_regions as $region): ?>
                                <label>
                                    <input type="checkbox" name="regions[]" value="<?php echo $region; ?>">
                                    <?php echo htmlspecialchars($region); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 商品選擇 -->
                    <div class="form-group-item">
                        <h4>要派送的商品 (Product):</h4>
                        <p>選擇需要更新價格的品項。</p>
                        <div class="select-list">
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <label>
                                        <input type="checkbox" name="product_ids[]" value="<?php echo $product['product_id']; ?>">
                                        [ID:<?php echo $product['product_id']; ?>] <?php echo htmlspecialchars($product['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>目前沒有上架商品可供批次操作。</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <p>❗ 注意：系統將自動查找並派送所選商品在所選地區的 <b>最新價格</b>。</p>
                <button type="submit" class="dispatch-btn">執行智能批次派送</button>
            </form>
        </div>
    </div>
</body>
</html>
