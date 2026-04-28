// Service Worker for やまふじ農園 お米 注文予測
// プッシュ通知の受信と、PWA としての最低限の動作を担当する。

const CACHE_VERSION = 'rice-app-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// プッシュ通知受信
self.addEventListener('push', (event) => {
    let data = { title: 'やまふじ農園', body: '通知があります' };
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { title: 'やまふじ農園', body: event.data.text() };
        }
    }

    const options = {
        body:  data.body || '',
        icon:  data.icon  || './assets/icon-192.png',
        badge: data.badge || './assets/badge-96.png',
        tag:   data.tag   || 'rice-order',
        renotify: true,
        data: {
            url: data.url || './index.php?p=history'
        },
        // iOS でも音・バイブが鳴るように
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'やまふじ農園', options)
    );
});

// 通知をタップしたとき
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || './';

    event.waitUntil(
        (async () => {
            const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            // 既に開いているウィンドウがあればフォーカス
            for (const client of allClients) {
                if ('focus' in client) {
                    client.navigate(targetUrl).catch(() => {});
                    return client.focus();
                }
            }
            // なければ新規ウィンドウで開く
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })()
    );
});

// 購読期限切れ時の更新
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const newSubscription = await self.registration.pushManager.subscribe(
                    event.oldSubscription ? event.oldSubscription.options : {}
                );
                await fetch('./index.php?p=api_subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: newSubscription.toJSON(),
                        renew: true
                    })
                });
            } catch (e) {
                // 失敗時は次回の subscribe 操作で復旧
            }
        })()
    );
});
