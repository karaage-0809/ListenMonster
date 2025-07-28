<?php
session_start();
require_once '../db_connect.php'; // データベース接続

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// JSON形式のPOSTデータを取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$userId = $data['user_id'] ?? null;
$newPoints = $data['points'] ?? null;

// デバッグログ (PHPエラーログに出力されます)
error_log("update_points.php DEBUG: Session User ID = " . ($_SESSION['user_id'] ?? 'NULL'));
error_log("update_points.php DEBUG: Received User ID = " . ($userId ?? 'NULL'));
error_log("update_points.php DEBUG: Received New Points = " . ($newPoints ?? 'NULL'));


if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'ログインしていません。';
    echo json_encode($response);
    exit;
}

// ここが最も重要な変更点: === を == に変更
// あるいは、両方を int にキャストして比較する: (int)$userId === (int)$_SESSION['user_id']
if ($userId != $_SESSION['user_id'] || !is_numeric($newPoints)) {
    $response['message'] = '不正なリクエストです。ユーザーIDが一致しないか、ポイントが不正です。';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction(); // トランザクション開始

    // ユーザーのポイントを更新
    $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
    $stmt->execute([$newPoints, $userId]);

    // 更新された行数をログに出力
    error_log("update_points.php DEBUG: Rows affected by update = " . $stmt->rowCount());

    if ($stmt->rowCount() > 0) {
        $pdo->commit(); // コミット
        $response['success'] = true;
        $response['message'] = 'ポイントを更新しました。';
    } else {
        $pdo->rollBack(); // ロールバック
        $response['message'] = 'ポイントの更新に失敗しました。ユーザーが見つからないか、ポイントが変更されていません。';
    }

} catch (PDOException $e) {
    $pdo->rollBack(); // エラー時はロールバック
    error_log("Database error in update_points.php: " . $e->getMessage());
    $response['message'] = 'データベースエラーが発生しました。';
}

echo json_encode($response);