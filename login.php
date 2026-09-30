<?php
require_once 'bootstrap.php';
require_once 'db_config.php';

// 處理登入 POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash']) || $user['is_active'] != 1) {
        $error = "帳號或密碼錯誤，或帳號未啟用。";
    } else {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ];
        header("Location: products.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>電子紙後台登入</title>
<style>
body {
    font-family: Arial, sans-serif;
    background:#f5f5f5;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.login-box {
    background:white;
    padding:35px 40px;
    border-radius:8px;
    box-shadow:0 3px 8px rgba(0,0,0,0.12);
    width:350px;
}
h2{
    text-align:center;
    margin-bottom:25px;
    color:#333;
}
label{
    font-weight:bold;
    display:block;
    margin-top:10px;
    margin-bottom:3px;
}
input {
    width:100%;
    padding:8px;
    border:1px solid #ccc;
    border-radius:4px;
}
button {
    width:100%;
    margin-top:18px;
    padding:10px;
    background:#007bff;
    color:white;
    border:none;
    border-radius:4px;
    cursor:pointer;
    font-weight:bold;
}
button:hover{
    background:#0056b3;
}
.error{
    background:#f8d7da;
    color:#721c24;
    padding:10px;
    margin-top:10px;
    border-radius:4px;
}
</style>
</head>
<body>
<div class="login-box">
<h2>電子紙後台登入</h2>
<form method="POST" action="login.php">
    <?php csrf_field(); ?>
    <label>Email</label>
    <input type="email" name="email" required>
    <label>密碼</label>
    <input type="password" name="password" required>
    <button type="submit">登入</button>
</form>
<?php if (!empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
</div>
</body>
</html>

