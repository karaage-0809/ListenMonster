<?php
session_start();
require_once 'db_connect.php';

$message = '';

// 既にログインしている場合はゲームページへリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = 'ユーザー名とパスワードを入力してください。';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, points FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            // ログイン成功後、ゲームページへリダイレクト
            header('Location: index.php');
            exit;
        } else {
            $message = 'ユーザー名またはパスワードが間違っています。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - ListenMonsters</title>
    <link rel="stylesheet" href="style.css">
    </style>
</head>
<body>
    <h1>ListenMonsters</h1>
    <div class="section form-container">
        <h2>ログイン</h2>
        <?php if (isset($_GET['registered'])): ?>
            <p style="color: green; font-weight: bold;">登録が完了しました！ログインしてください。</p>
        <?php endif; ?>
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <input type="text" name="username" placeholder="ユーザー名" required>
            <input type="password" name="password" placeholder="パスワード" required>
            <button type="submit">ログイン</button>
        </form>
        <p class="link-text">アカウントをお持ちでないですか？ <a href="register.php">登録はこちら</a></p>
    </div>
</body>
</html>