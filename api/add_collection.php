<?php
session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'ログインしていません。';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'] ?? null;
    $characterId = $data['character_id'] ?? null;

    if ($userId !== $_SESSION['user_id'] || !is_numeric($characterId)) {
        $response['message'] = '不正なリクエストです。';
        echo json_encode($response);
        exit;
    }

    try {
        // キャラクターIDがcharactersテーブルに存在するか確認（念のため）
        $stmt_check_char = $pdo->prepare("SELECT id FROM characters WHERE id = ?");
        $stmt_check_char->execute([$characterId]);
        if (!$stmt_check_char->fetch()) {
            $response['message'] = '指定されたキャラクターは存在しません。';
            echo json_encode($response);
            exit;
        }

        // コレクションに追加
        $stmt = $pdo->prepare("INSERT INTO user_collections (user_id, character_id) VALUES (?, ?)");
        $stmt->execute([$userId, $characterId]);

        $response['success'] = true;
        $response['message'] = 'コレクションにキャラクターを追加しました。';
    } catch (PDOException $e) {
        // 同じキャラクターを複数回引くことを許容しない場合の重複エラーなど
        if ($e->getCode() === '23000') { // SQLSTATE for integrity constraint violation
            $response['message'] = 'このキャラクターは既にコレクションにあります。';
        } else {
            error_log("Collection add error: " . $e->getMessage());
            $response['message'] = 'データベースエラーが発生しました。';
        }
    }
} else {
    $response['message'] = '無効なリクエストメソッドです。';
}

echo json_encode($response);
?>