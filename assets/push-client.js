// プッシュ通知の購読/解除を担当するクライアント側 JS。
// views/settings.php から読み込まれ、データ属性経由で VAPID 公開鍵を受け取る。

(function () {
    'use strict';

    const root = document.getElementById('push-settings');
    if (!root) return;

    const vapidPublicKey = root.dataset.vapid || '';
    const subscribeUrl   = root.dataset.subscribeUrl || './index.php?p=api_subscribe';
    const unsubscribeUrl = root.dataset.unsubscribeUrl || './index.php?p=api_unsubscribe';
    const testUrl        = root.dataset.testUrl || './index.php?p=api_test_push';
    const swPath         = root.dataset.swPath || './sw.js';

    const elBtnEnable  = document.getElementById('btn-enable-push');
    const elBtnDisable = document.getElementById('btn-disable-push');
    const elBtnTest    = document.getElementById('btn-test-push');
    const elStatus     = document.getElementById('push-status');
    const elMessage    = document.getElementById('push-message');
    const elStaffSelect = document.getElementById('staff-id');

    function setMessage(text, type) {
        if (!elMessage) return;
        elMessage.textContent = text || '';
        elMessage.className = 'alert ' + (type === 'error' ? 'alert-error' : (type === 'success' ? 'alert-success' : 'alert-info'));
        elMessage.style.display = text ? 'block' : 'none';
    }

    function setStatus(state) {
        if (!elStatus) return;
        const labels = {
            unsupported:  '※ お使いのブラウザはプッシュ通知に対応していません',
            standalone_required: '※ ホーム画面に追加してから開いてください（iOS の制約）',
            disabled:    '🔕 通知 OFF（この端末では受信しません）',
            enabled:     '🔔 通知 ON（この端末で受信します）',
            denied:      '🚫 通知が拒否されています。設定アプリから許可してください',
        };
        elStatus.textContent = labels[state] || state;
        elStatus.className = 'push-status push-status--' + state;

        if (elBtnEnable && elBtnDisable) {
            const isEnabled = state === 'enabled';
            elBtnEnable.style.display  = isEnabled ? 'none' : 'inline-block';
            elBtnDisable.style.display = isEnabled ? 'inline-block' : 'none';
            if (elBtnTest) elBtnTest.style.display = isEnabled ? 'inline-block' : 'none';
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw     = window.atob(base64);
        const arr     = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    async function getRegistration() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
        return await navigator.serviceWorker.ready;
    }

    async function init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            setStatus('unsupported');
            return;
        }
        // iOS: standalone でないと動かない
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isIos && !isStandalone) {
            setStatus('standalone_required');
            return;
        }
        if (Notification.permission === 'denied') {
            setStatus('denied');
            return;
        }
        const reg = await getRegistration();
        const sub = await reg.pushManager.getSubscription();
        setStatus(sub ? 'enabled' : 'disabled');
    }

    async function enablePush() {
        setMessage('', 'info');
        try {
            if (!elStaffSelect || !elStaffSelect.value) {
                setMessage('先に「私は誰？」の担当者を選択してください。', 'error');
                return;
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                setStatus('denied');
                setMessage('通知が許可されませんでした。', 'error');
                return;
            }
            const reg = await navigator.serviceWorker.register(swPath);
            await navigator.serviceWorker.ready;

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });

            const res = await fetch(subscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subscription: sub.toJSON(),
                    staff_id: parseInt(elStaffSelect.value, 10),
                }),
            });
            if (!res.ok) throw new Error('subscribe API failed: ' + res.status);

            setStatus('enabled');
            setMessage('この端末で通知を受け取れるようになりました。', 'success');
        } catch (err) {
            setMessage('通知の登録に失敗しました: ' + err.message, 'error');
        }
    }

    async function disablePush() {
        setMessage('', 'info');
        try {
            const reg = await getRegistration();
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                const endpoint = sub.endpoint;
                await sub.unsubscribe();
                await fetch(unsubscribeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: endpoint }),
                });
            }
            setStatus('disabled');
            setMessage('この端末の通知を解除しました。', 'info');
        } catch (err) {
            setMessage('解除に失敗しました: ' + err.message, 'error');
        }
    }

    async function sendTestPush() {
        setMessage('', 'info');
        try {
            const res = await fetch(testUrl, { method: 'POST' });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.error || ('HTTP ' + res.status));
            setMessage('テスト通知を送信しました。数秒以内に届きます。', 'success');
        } catch (err) {
            setMessage('テスト通知の送信に失敗しました: ' + err.message, 'error');
        }
    }

    if (elBtnEnable)  elBtnEnable.addEventListener('click', enablePush);
    if (elBtnDisable) elBtnDisable.addEventListener('click', disablePush);
    if (elBtnTest)    elBtnTest.addEventListener('click', sendTestPush);

    init();
})();
