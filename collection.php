<?php
require_once 'session_check.php'; // ログインチェックとユーザー情報取得
require_once 'db_connect.php';

$user_collection = [];

try {
    $stmt_collection = $pdo->prepare("
        SELECT uc.character_id, c.name, c.rarity, c.class, c.image_path, uc.acquired_at
        FROM user_collections uc
        JOIN characters c ON uc.character_id = c.id
        WHERE uc.user_id = ?
        ORDER BY uc.acquired_at DESC
    ");
    $stmt_collection->execute([$current_user_id]);
    $user_collection = $stmt_collection->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in collection.php: " . $e->getMessage());
    die("コレクションの読み込み中にエラーが発生しました。");
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>コレクション - ListenMonsters</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>📚 コレクション</h1>
    <div id="user-info">ログイン中: <?php echo htmlspecialchars($current_username); ?></div>
    <div class="button-container">
        <button onclick="location.href='index.php'">ゲームに戻る</button>
        <button id="btn-logout">ログアウト</button>
    </div>

    <div class="section">
        <?php if (empty($user_collection)): ?>
            <p>まだキャラを獲得していません。</p>
        <?php else: ?>
            <div class="collection-grid"> <?php foreach ($user_collection as $char): ?>
                    <div class="character <?php echo htmlspecialchars($char['class']); ?>">
                        <img src="<?php echo htmlspecialchars($char['image_path']); ?>" alt="<?php echo htmlspecialchars($char['name']); ?>">
                        <span><?php echo htmlspecialchars($char['name']); ?>（<?php echo htmlspecialchars($char['rarity']); ?>）</span>
                        <small>獲得日: <?php echo date('Y/m/d H:i', strtotime($char['acquired_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('btn-logout').addEventListener('click', () => {
            location.href = 'logout.php';
        });
        // CSS for collection-grid (style.cssに追加することを推奨)
        // .collection-grid {
        //     display: grid;
        //     grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        //     gap: 15px;
        //     padding: 10px;
        // }
        // .collection-grid .character {
        //     flex-direction: column;
        //     align-items: center;
        //     text-align: center;
        // }
        // .collection-grid .character img {
        //     margin-right: 0;
        //     margin-bottom: 10px;
        // }
    </script>
</body>
</html>