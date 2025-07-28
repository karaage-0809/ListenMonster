<?php
session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'characters' => [], 'total_count' => 0];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'ログインしていません。';
    echo json_encode($response);
    exit;
}

$userId = $_GET['user_id'] ?? null;
$limit = $_GET['limit'] ?? null; // 例えば、ゲーム画面では最新5件だけ表示など

if ((int)$userId !== (int)$_SESSION['user_id']) { // userIdをintにキャストして比較
    $response['message'] = '不正なリクエストです。';
    echo json_encode($response);
    exit;
}

try {
    // まず総数を取得
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM user_collections WHERE user_id = ?");
    $stmt_count->execute([$userId]); // キャスト済みuserIdを使用
    $response['total_count'] = $stmt_count->fetchColumn();

    // キャラクターデータを取得
    $sql = "
        SELECT uc.character_id, c.name, c.rarity, c.class, c.image_path, uc.acquired_at
        FROM user_collections uc
        JOIN characters c ON uc.character_id = c.id
        WHERE uc.user_id = ?
        ORDER BY uc.acquired_at DESC
    ";
    if ($limit !== null && is_numeric($limit) && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]); // キャスト済みuserIdを使用
    $response['characters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['success'] = true;
    $response['message'] = 'コレクション取得成功。';

} catch (PDOException $e) {
    error_log("Get collection error: " . $e->getMessage());
    $response['message'] = 'データベースエラーが発生しました。';
}

echo json_encode($response);