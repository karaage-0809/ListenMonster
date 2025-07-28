<?php
session_start();
require_once 'db_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = 'ユーザー名とパスワードを入力してください。';
    } else {
        // パスワードをハッシュ化
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // ユーザー名をユニークにするため、まず存在チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'このユーザー名は既に使用されています。';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$username, $password_hash]);
                $message = '登録が完了しました！ログインしてください。';
                // 登録成功後、ログインページへリダイレクト
                header('Location: login.php?registered=true');
                exit;
            }
        } catch (PDOException $e) {
            $message = '登録中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録 - ListenMonsters</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>ListenMonsters</h1>
    <div class="section form-container">
        <h2>ユーザー登録</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <input type="text" name="username" placeholder="ユーザー名" required>
            <input type="password" name="password" placeholder="パスワード" required>
            <button type="submit">登録</button>
        </form>
        <p class="link-text">既にアカウントをお持ちですか？ <a href="login.php">ログインはこちら</a></p>
    </div>
</body>
</html>