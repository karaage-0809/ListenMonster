<?php
// session_check.php
session_start();

// ログインしていない場合はログインページへリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 現在ログインしているユーザーの情報を取得
$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];