<?php
require_once 'session_check.php'; // ログインチェックとユーザー情報取得
require_once 'db_connect.php';

// ユーザーの現在のポイントとコレクションをDBから読み込む
$user_points = 0;
$user_collection = [];
$all_characters = [];

try {
    // ユーザーのポイントを取得
    $stmt_points = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt_points->execute([$current_user_id]);
    $user_data = $stmt_points->fetch();
    if ($user_data) {
        $user_points = $user_data['points'];
    }

    // 全キャラクターデータを取得 (JavaScriptの`characters`配列の代わり)
    $stmt_chars = $pdo->query("SELECT id, name, rarity, class, weight, image_path FROM characters");
    $all_characters = $stmt_chars->fetchAll(PDO::FETCH_ASSOC);

    // ユーザーのコレクションを取得
    $stmt_collection = $pdo->prepare("
        SELECT uc.character_id, c.name, c.rarity, c.class, c.image_path
        FROM user_collections uc
        JOIN characters c ON uc.character_id = c.id
        WHERE uc.user_id = ?
        ORDER BY uc.acquired_at DESC
    ");
    $stmt_collection->execute([$current_user_id]);
    $user_collection = $stmt_collection->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in index.php: " . $e->getMessage());
    // エラーメッセージをユーザーに表示する代わりに、安全なメッセージを
    die("データの読み込み中にエラーが発生しました。");
}

// JavaScriptに渡すためのキャラクターデータとコレクションデータをJSON形式で準備
$js_all_characters = json_encode($all_characters);
$js_user_collection = json_encode($user_collection);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ListenMonsters</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <h1>ListenMonsters</h1>

  <div id="user-info">ログイン中: <?php echo htmlspecialchars($current_username); ?></div>
  <div class="button-container">
    <button id="btn-logout">ログアウト</button>
    <button id="btn-view-collection">コレクションを見る</button>
  </div>

  <div class="section">
    <div class="button-container">
      <button id="startBtn">🎙️ 音声入力スタート</button>
    </div>
    <div id="transcript">🎧 話した内容がここに表示されます</div>
    <div id="kanaCount">🔢 カウント：</div>
    <div id="points">💰 現在のポイント：<?php echo $user_points; ?></div>
  </div>

  <div class="section">
    <div class="button-container">
      <button id="gachaBtn" disabled>🎁 ガチャを引く（10pt）</button>
    </div>
    <div id="result"></div>
    <div id="gacha-single-result" class="gacha-single-result"></div>
  </div>

  <div class="section">
    <h2>📚 コレクション (最新数件)</h2>
    <div id="collection">
        <?php
        if (empty($user_collection)) {
            echo "まだキャラを獲得していません。";
        } else {
            foreach (array_slice($user_collection, 0, 5) as $char) {
                echo '<div class="character ' . htmlspecialchars($char['class']) . '">';
                echo '<img src="' . htmlspecialchars($char['image_path']) . '" alt="' . htmlspecialchars($char['name']) . '">';
                echo '<span>' . htmlspecialchars($char['name']) . '（' . htmlspecialchars($char['rarity']) . '）</span>';
                echo '</div>';
            }
            if (count($user_collection) > 5) {
                echo '<p><a href="collection.php">全コレクションを見る</a></p>';
            }
        }
        ?>
    </div>
  </div>


<script>
    // PHPから渡される初期データ
    const INITIAL_USER_DATA = {
        userId: <?php echo json_encode($current_user_id); ?>,
        username: <?php echo json_encode($current_username); ?>,
        points: <?php echo json_encode($user_points); ?>
    };
    const ALL_CHARACTERS = <?php echo $js_all_characters; ?>;

    let currentUser = INITIAL_USER_DATA;
    let points = INITIAL_USER_DATA.points;

    // HTMLエスケープ処理
    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // 日付フォーマット処理 (今回は使用しないかもしれませんが、念のため残します)
    function formatDate(isoString) {
        if (!isoString) return '';
        const formattedString = isoString.replace(' ', 'T');
        const date = new Date(formattedString);
        if (isNaN(date.getTime())) {
            console.error("Invalid date string provided to formatDate:", isoString);
            return isoString;
        }
        return date.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
      // DOM要素
      const userInfoDiv = document.getElementById("user-info");
      const btnLogout = document.getElementById("btn-logout");
      // ★★★ここを追加★★★
      const btnViewCollection = document.getElementById("btn-view-collection");
      const startBtn = document.getElementById("startBtn");
      const transcriptDiv = document.getElementById("transcript");
      const kanaCountDiv = document.getElementById("kanaCount");
      const pointsDiv = document.getElementById("points");
      const gachaBtn = document.getElementById("gachaBtn");
      const resultDiv = document.getElementById("result"); // これはガチャ結果のメッセージ用
      // ★★★ここを追加★★★
      const gachaSingleResultDiv = document.getElementById("gacha-single-result"); // 獲得キャラの一時表示エリア
      const collectionDiv = document.getElementById("collection");

      // 音声認識準備
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      const recognition = new SpeechRecognition();
      recognition.lang = "ja-JP";
      recognition.continuous = false;

      const totalWeight = ALL_CHARACTERS.reduce((sum, char) => sum + char.weight, 0);

      // UIの初期状態を設定
      startBtn.disabled = !currentUser;
      gachaBtn.disabled = points < 10;
      updateGachaButtonAnimation();

      // ログアウトボタン
      btnLogout.addEventListener("click", () => {
        location.href = 'logout.php';
      });

      // ★★★ここを追加★★★
      // コレクションを見るボタン
      btnViewCollection.addEventListener("click", () => {
        location.href = 'collection.php';
      });

      // ポイントをサーバーに保存する関数
      async function savePoints(newPoints) {
        if (!currentUser || !currentUser.userId) return;
        try {
            const response = await fetch('api/update_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUser.userId, points: newPoints })
            });
            const result = await response.json();
            if (result.success) {
                points = newPoints;
                pointsDiv.textContent = `💰 現在のポイント：${points}`;
                gachaBtn.disabled = points < 10;
                updateGachaButtonAnimation();
            } else {
                console.error("ポイントの保存に失敗しました:", result.message);
                alert("ポイントの保存に失敗しました: " + result.message);
            }
        } catch (e) {
            console.error("ポイント保存の通信エラー:", e);
            alert("ポイントの保存中にエラーが発生しました。");
        }
      }

      // コレクションをサーバーに保存する関数
      async function saveCollection(characterId) {
        if (!currentUser || !currentUser.userId) return;
        try {
            const response = await fetch('api/add_collection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUser.userId, character_id: characterId })
            });
            const result = await response.json();
            if (result.success) {
                console.log("コレクションにキャラクターを追加しました。");
                // このページではupdateLocalCollectionDisplayをガチャ後には呼ばない
                // なぜなら、獲得キャラはgacha-single-resultに表示し、既存コレクション表示は静的だから
            } else {
                console.error("コレクションの保存に失敗しました:", result.message);
                alert("コレクションの保存に失敗しました: " + result.message);
            }
        } catch (e) {
            console.error("コレクション保存の通信エラー:", e);
            alert("コレクションの保存中にエラーが発生しました。");
        }
      }

      // コレクション表示更新関数 (index.php の最新数件用)
      // ★★★この関数はページ読み込み時のみ使用し、ガチャ後は呼ばない★★★
      async function updateLocalCollectionDisplay() {
          if (!currentUser || !currentUser.userId) return;
          try {
              const response = await fetch(`api/get_collection.php?user_id=${currentUser.userId}&limit=5`);
              const data = await response.json();
              if (data.success && data.characters) {
                  if (data.characters.length === 0) {
                      collectionDiv.textContent = "まだキャラを獲得していません。";
                  } else {
                      collectionDiv.innerHTML = data.characters.map(c =>
                          `<div class="character ${escapeHTML(c.class)}">
                             <img src="${escapeHTML(c.image_path)}" alt="${escapeHTML(c.name)}">
                             <span>${escapeHTML(c.name)}（${escapeHTML(c.rarity)}）</span>
                           </div>`
                      ).join("");
                      if (data.total_count > 5) {
                          collectionDiv.innerHTML += '<p><a href="collection.php">全コレクションを見る</a></p>';
                      }
                  }
              } else {
                  console.error("コレクションデータの取得に失敗しました:", data.message);
              }
          } catch (e) {
              console.error("コレクション表示更新エラー:", e);
          }
      }

      // ガチャボタンのアニメーションを制御する関数
      function updateGachaButtonAnimation() {
        if (!gachaBtn.disabled) {
          gachaBtn.classList.add('shine-animation');
        } else {
          gachaBtn.classList.remove('shine-animation');
        }
      }

      // 音声認識開始ボタン
      startBtn.onclick = () => {
        resultDiv.textContent = "";
        kanaCountDiv.textContent = "🔢 カウント：";
        transcriptDiv.textContent = "🎧 話した内容がここに表示されます";
        // ★★★ガチャ一時表示エリアもクリアする★★★
        gachaSingleResultDiv.innerHTML = ""; 
        recognition.start();
      };

      // 音声認識結果取得
      recognition.onresult = async (event) => {
        const recognizedText = event.results[0][0].transcript;
        transcriptDiv.textContent = "📄 文字起こし：" + recognizedText;

        const kanaCounts = {};
        for (let ch of recognizedText) {
          kanaCounts[ch] = (kanaCounts[ch] || 0) + 1;
        }

        const display = Object.entries(kanaCounts)
          .filter(([_, count]) => count > 0)
          .map(([char, count]) => `${char}: ${count}`)
          .join(" / ");
        kanaCountDiv.textContent = "🔤 カウント: " + display;

        const addedPoints = Object.values(kanaCounts).reduce((a, b) => a + b, 0);
        const potentialNewPoints = points + addedPoints;

        if (potentialNewPoints > points) {
          await savePoints(potentialNewPoints);
        } else {
            transcriptDiv.textContent = "ポイントは増えませんでした。";
        }
      };

      recognition.onerror = (event) => {
          console.error("音声認識エラー:", event.error);
          transcriptDiv.textContent = `エラー: ${event.error}`;
      };

      recognition.onend = () => {
          console.log("音声認識終了。");
      };

      // ガチャボタン押下時
      gachaBtn.onclick = async () => {
        if (points < 10) {
          alert("ポイントが足りません！");
          return;
        }

        resultDiv.textContent = "ガチャを引いています..."; // ロード中のメッセージ
        gachaSingleResultDiv.innerHTML = ""; // 前回のガチャ結果をクリア

        const rand = Math.random() * totalWeight;
        let cumulativeWeight = 0;
        let resultChar = null;

        for (const char of ALL_CHARACTERS) {
            cumulativeWeight += char.weight;
            if (rand < cumulativeWeight) {
                resultChar = char;
                break;
            }
        }
        if (!resultChar) {
            resultChar = ALL_CHARACTERS[0];
        }

        // ポイントをサーバーから減算
        await savePoints(points - 10);

        // コレクションをサーバーに保存 (キャラクターIDを渡す)
        await saveCollection(resultChar.id);

        // ガチャ結果表示 (新しいエリアに表示)
        resultDiv.textContent = "🎉 キャラクターをゲットしました！"; // メッセージを更新
        gachaSingleResultDiv.innerHTML = `
            <div class="character ${escapeHTML(resultChar.class)}">
               <img src="${escapeHTML(resultChar.image_path)}" alt="${escapeHTML(resultChar.name)}">
               <span>${escapeHTML(resultChar.name)}（${escapeHTML(resultChar.rarity)}）</span>
             </div>
        `;
      };

      // 初期表示時のコレクション更新（ガチャ後には呼ばない）
      updateLocalCollectionDisplay();

    });
  </script>
  
</body>
</html>