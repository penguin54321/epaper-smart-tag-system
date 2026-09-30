<?php
require_once 'bootstrap.php';
require_once 'db_config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $name  = trim($_POST['name'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || $name==='') {
    $err = '資料格式不正確（Email/密碼至少8碼/姓名必填）。';
  } else {
    // 檢查重複
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e");
    $stmt->execute([':e' => $email]);
    if ($stmt->fetch()) {
      $err = '此 Email 已被註冊。';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (:e,:h,:n)");
      $stmt->execute([':e'=>$email, ':h'=>$hash, ':n'=>$name]);
      header('Location: login.php?msg='.urlencode('註冊成功，請登入')); exit;
    }
  }
}
?>
<!-- 簡易表單 -->
<form method="post">
  <input name="name" placeholder="姓名" required>
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="密碼(>=8)" required>
  <button>註冊</button>
  <div style="color:red"><?= $err ?? '' ?></div>
</form>
