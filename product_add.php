<?php
require_once 'bootstrap.php';
require_once 'auth.php';


?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>新增商品</title>
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
        .back-btn:hover {
            background: #dee2e6;
            color: #212529;
        }
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
        <a href="products.php" style="color: #e0f7fa; font-weight: bold;">商品管理</a>
        <a href="price_batch.php">批次價格編輯</a>
        <a href="#" onclick="alert('系統狀態功能正在規劃中...')">系統狀態 (待定)</a>
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

        <a href="products.php" class="back-btn">← 返回</a>
        <h1>新增商品</h1>

        <div class="card">
            <h3>商品基本資料</h3>

            <form action="product_create.php" method="POST">
                <?php csrf_field(); ?>   <!-- ✅ CSRF token -->

                <label>商品名稱：</label>
                <input type="text" name="name" required><br>

                <label style="vertical-align: top;">描述：</label>
                <textarea name="description"></textarea><br>

                <label>狀態：</label>
                <select name="is_active">
                    <option value="1" selected>上架 (1)</option>
                    <option value="0">下架 (0)</option>
                </select><br>

                <button type="submit">確認新增商品</button>
            </form>
        </div>

    </div>
</body>
</html>
