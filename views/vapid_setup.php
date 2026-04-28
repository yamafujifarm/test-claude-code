<?php
/**
 * VAPID 鍵の初回生成ページ。
 * 一度生成して config.php に貼り付ければ、もう使いません。
 *
 * @var PDO $pdo
 */

$keys = null;
if (is_post() && post('action') === 'generate') {
    $keys = WebPush::generateVapidKeys();
}

$pageTitle = 'VAPID 鍵の生成（初回のみ）';
require __DIR__ . '/_header.php';
?>
<section class="page-section">
    <h2 class="section-title">VAPID 鍵の生成</h2>
    <p class="muted">プッシュ通知用の暗号鍵を 1 度だけ生成し、<code>config.php</code> に貼り付けます。</p>

    <?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
        <div class="alert alert-info">
            既に <code>config.php</code> に VAPID 鍵が設定されています。<br>
            **再生成すると既存端末の通知購読がすべて無効になります。** 通常は再生成しないでください。
        </div>
    <?php endif; ?>

    <?php if ($keys === null): ?>
        <form method="post" action="<?= h(url('', ['p' => 'vapid_setup'])) ?>" class="form">
            <input type="hidden" name="action" value="generate">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">VAPID 鍵を生成する</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-success">鍵を生成しました。下の値を <code>config.php</code> にコピーしてください。</div>

        <div class="format-guide">
            <div class="format-guide__title">config.php に貼り付ける内容</div>
<pre class="format-guide__pre">const VAPID_SUBJECT     = 'mailto:あなたのメールアドレス@example.com';
const VAPID_PUBLIC_KEY  = '<?= h($keys['publicKey']) ?>';
const VAPID_PRIVATE_KEY = '<?= h($keys['privateKey']) ?>';</pre>
            <ul class="format-guide__list">
                <li><code>VAPID_SUBJECT</code> はあなた（管理者）の連絡先メールアドレスにしてください。プッシュサービスから問い合わせを受け取るための情報です。</li>
                <li>貼り付け後、ブラウザを再読み込みするとプッシュ通知機能が使えるようになります。</li>
                <li><strong>このページは鍵を保存していません。</strong>このタブを閉じる前にコピーしてください。</li>
            </ul>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
