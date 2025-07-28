<?php
session_start();
session_unset(); // 全てのセッション変数を削除
session_destroy(); // セッションを破棄
header('Location: login.php'); // ログインページへリダイレクト
exit;
?>