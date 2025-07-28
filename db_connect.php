<?php
// db_connect.php (ローカル & さくらサーバー両対応版)

// ------------------------------------------------------------
// ★★★ 環境ごとのデータベース接続設定 ★★★
// ------------------------------------------------------------

// ローカル環境 (XAMPPなど) の設定
// ※通常、XAMPPのMySQLはパスワードなし、ユーザー名root、ホスト名localhostです。
$local_db_config = [
    'host'    => 'localhost',
    'db'      => 'listenmonsters_db', // あなたのローカルDB名
    'user'    => 'root',              // あなたのローカルDBユーザー名
    'pass'    => '',                  // あなたのローカルDBパスワード
];

// さくらサーバー環境の設定
// ※ここをあなたのさくらサーバーの設定に書き換えてください
$sakura_db_config = [
    'host'    => '', // 例: mysqlXXX.db.sakura.ne.jp
    'db'      => '',        // 例: your_db_name
    'user'    => '',  // 例: your_user_name
    'pass'    => '',  // 例: your_password
];

// ------------------------------------------------------------
// ★★★ 上記の設定を必ずあなたの環境に合わせて書き換えてください ★★★
// ------------------------------------------------------------


// 現在の環境を判定
// 一般的なさくらサーバーでは環境変数 'SERVER_NAME' や 'HTTP_HOST' に
// サーバー固有のドメイン名が含まれることが多いです。
// より確実に判定するには、さくらサーバーの特定ディレクトリに
// 環境変数や特定のファイルを作成するなどの方法もありますが、
// ここでは簡易的にホスト名で判定します。

$is_sakura_server = false;
if (isset($_SERVER['HTTP_HOST'])) {
    // 例: さくらサーバーのドメイン名の一部が含まれているかチェック
    // より厳密に判定する場合は、例えば 'mysql' が含まれるホスト名かどうかなどで判断
    if (strpos($_SERVER['HTTP_HOST'], '.sakura.ne.jp') !== false || strpos($_SERVER['HTTP_HOST'], 's*.xrea.com') !== false /* その他のさくら系ドメイン */) {
        $is_sakura_server = true;
    }
}
// または、よりシンプルに、ローカルホスト以外をリモートと見なす
// if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
//     $is_sakura_server = true;
// }


// どちらのDB設定を使用するか決定
if ($is_sakura_server) {
    $current_db_config = $sakura_db_config;
} else {
    $current_db_config = $local_db_config;
}

$host = $current_db_config['host'];
$db   = $current_db_config['db'];
$user = $current_db_config['user'];
$pass = $current_db_config['pass'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // エラー時に例外を投げる
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 結果を連想配列で取得
    PDO::ATTR_EMULATE_PREPARES   => false,                  // プリペアドステートメントのエミュレーションを無効にする
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // データベース接続失敗時のエラーハンドリング
    error_log("Database connection error: " . $e->getMessage());

    // 環境によって表示メッセージを変えることも可能
    if ($is_sakura_server) {
        die("申し訳ありません。現在サービスをご利用いただけません。しばらくしてから再度お試しください。");
    } else {
        die("ローカルデータベース接続エラー: " . $e->getMessage() . "<br>db_connect.php の設定を確認してください。");
    }
}