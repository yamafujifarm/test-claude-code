<?php
/**
 * この端末のプッシュ通知設定。
 *
 * @var PDO $pdo
 */

$staff = $pdo->query('SELECT * FROM staff ORDER BY name ASC')->fetchAll();

$vapidPublic = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
$vapidReady  = $vapidPublic !== '';
$totalSubs   = (int)$pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();

$pageTitle = '通知設定';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">この端末の通知設定</h2>

    <?php if (!$vapidReady): ?>
        <div class="alert alert-error">
            VAPID 鍵が未設定のため、通知機能を利用できません。<br>
            管理者は <a href="<?= h(url('', ['p' => 'vapid_setup'])) ?>">こちらから VAPID 鍵を生成</a>し、<code>config.php</code> に貼り付けてください。
        </div>
    <?php elseif (empty($staff)): ?>
        <div class="alert alert-info">
            まず担当者を登録してください。
            <a href="<?= h(url('', ['p' => 'staff'])) ?>">担当者管理へ</a>
        </div>
    <?php else: ?>
        <p class="muted">他の担当者が注文を記録した時、この端末に通知を表示します。</p>

        <div id="push-settings"
             data-vapid="<?= h($vapidPublic) ?>"
             data-subscribe-url="<?= h(url('', ['p' => 'api_subscribe'])) ?>"
             data-unsubscribe-url="<?= h(url('', ['p' => 'api_unsubscribe'])) ?>"
             data-test-url="<?= h(url('', ['p' => 'api_test_push'])) ?>"
             data-sw-path="./sw.js">

            <div class="form-row">
                <label class="form-label" for="staff-id">この端末を使うのは？</label>
                <select id="staff-id" class="form-input">
                    <option value="">-- 担当者を選択 --</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= h((string)$s['id']) ?>"><?= h($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted" style="font-size:12px;">この端末から記録した注文には自動でこの担当者が紐づきます。</p>
            </div>

            <div id="push-status" class="push-status">確認中...</div>

            <div id="push-message" class="alert" style="display:none;"></div>

            <div class="form-actions" style="margin-top:8px;">
                <button id="btn-enable-push"  class="btn btn-primary btn-block" style="display:none;">この端末で通知を有効にする</button>
                <button id="btn-disable-push" class="btn btn-secondary btn-block" style="display:none;">この端末の通知を解除する</button>
                <button id="btn-test-push"    class="btn btn-link" style="display:none;">テスト通知を送る</button>
            </div>
        </div>

        <div class="setup-guide">
            <details>
                <summary>iPhone で通知を受け取るための準備</summary>
                <ol>
                    <li>Safari でこのアプリを開きます</li>
                    <li>共有ボタン（□↑）をタップ</li>
                    <li>「ホーム画面に追加」をタップ</li>
                    <li>ホーム画面に追加されたアイコンから起動する</li>
                    <li>このページを開き、上の「通知を有効にする」ボタンをタップ</li>
                    <li>表示される通知許可ダイアログを許可</li>
                </ol>
                <p class="muted" style="font-size:12px;">
                    ⚠️ Safari のタブで開いている状態では通知を受け取れません（iOS 16.4 以降の Web Push の制約）。
                </p>
            </details>
        </div>

        <p class="muted" style="font-size:12px; margin-top:14px;">
            現在 <strong><?= $totalSubs ?></strong> 台の端末が通知を購読中です。
        </p>
    <?php endif; ?>

    <div class="page-actions" style="margin-top:16px;">
        <a class="btn btn-link" href="<?= h(url('', ['p' => 'staff'])) ?>">担当者マスタを編集</a>
    </div>
</section>

<?php if ($vapidReady && !empty($staff)): ?>
    <script src="assets/push-client.js?v=1"></script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
