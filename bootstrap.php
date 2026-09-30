<?php
// bootstrap.php（所有頁面共用）
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',           // 同網域預設
  'secure' => isset($_SERVER['HTTPS']), // https 下才用 secure
  'httponly' => true,
  'samesite' => 'Strict'
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">';
}

function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit("Invalid CSRF token");
    }
}

