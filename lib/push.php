<?php
declare(strict_types=1);

/**
 * 純 PHP の Web Push 実装。
 * RFC 8291 (aes128gcm) と RFC 8292 (VAPID) を満たす最小限の実装。
 *
 * 必要拡張: openssl, curl, hash (PHP 7.3+ で openssl_pkey_derive が必要)
 *
 * 使い方:
 *   $push = new WebPush(VAPID_SUBJECT, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
 *   $result = $push->send([
 *       'endpoint' => '...',
 *       'p256dh'   => '...', // base64url
 *       'auth'     => '...', // base64url
 *   ], json_encode(['title' => '...', 'body' => '...']));
 *
 * VAPID 鍵を初回生成する場合:
 *   $keys = WebPush::generateVapidKeys();
 *   // $keys['publicKey'], $keys['privateKey'] を config.php に保存
 */
class WebPushException extends RuntimeException {}

class WebPush
{
    private string $subject;
    private string $publicKey;
    private string $privateKey;

    public function __construct(string $subject, string $publicKey, string $privateKey)
    {
        if ($subject === '' || $publicKey === '' || $privateKey === '') {
            throw new WebPushException('VAPID 設定が未入力です。config.php を確認してください。');
        }
        $this->subject    = $subject;
        $this->publicKey  = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * 1 件の購読に対してプッシュ通知を送信する。
     *
     * @return array{status:int, response:string}
     */
    public function send(array $subscription, string $payload, int $ttl = 86400): array
    {
        $endpoint     = (string)($subscription['endpoint'] ?? '');
        $clientPubRaw = self::b64dec((string)($subscription['p256dh'] ?? ''));
        $authSecret   = self::b64dec((string)($subscription['auth'] ?? ''));

        if ($endpoint === '' || strlen($clientPubRaw) !== 65 || strlen($authSecret) !== 16) {
            throw new WebPushException('購読情報が不正です。');
        }

        [$body, ] = $this->encryptPayload($payload, $clientPubRaw, $authSecret);

        $audience = $this->getAudience($endpoint);
        $jwt      = $this->buildJwt($audience);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ', k=' . $this->publicKey,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new WebPushException('curl error: ' . $error);
        }

        return ['status' => $status, 'response' => (string)$response];
    }

    /**
     * RFC 8291 aes128gcm 形式でペイロードを暗号化。
     *
     * 戻り値: [body, serverPubRaw]
     */
    private function encryptPayload(string $payload, string $clientPubRaw, string $authSecret): array
    {
        // サーバー側エフェメラル鍵の生成
        $serverKey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($serverKey === false) {
            throw new WebPushException('サーバー鍵の生成に失敗しました。');
        }
        $details = openssl_pkey_get_details($serverKey);
        if ($details === false || empty($details['ec'])) {
            throw new WebPushException('サーバー鍵の取り出しに失敗しました。');
        }
        $serverPubRaw = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        // クライアント公開鍵から ECDH 共有秘密を計算
        $clientPubKey = $this->rawPublicKeyToOpensslKey($clientPubRaw);
        $sharedSecret = openssl_pkey_derive($clientPubKey, $serverKey, 32);
        if ($sharedSecret === false) {
            throw new WebPushException('ECDH の派生に失敗しました。');
        }

        // 中間鍵 (RFC 8291)
        $authInfo = "WebPush: info\x00" . $clientPubRaw . $serverPubRaw;
        $ikm      = hash_hkdf('sha256', $sharedSecret, 32, $authInfo, $authSecret);

        $salt  = random_bytes(16);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00",     $salt);

        // 末尾に区切りバイト 0x02（最後のレコードを意味する）
        $padded = $payload . "\x02";

        $tag = '';
        $cipher = openssl_encrypt(
            $padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16
        );
        if ($cipher === false) {
            throw new WebPushException('AES-GCM 暗号化に失敗しました。');
        }

        // ヘッダ: salt(16) | rs(4 BE) | idlen(1) | keyid(idlen)
        $rs    = strlen($padded) + 16;
        $idlen = 65;
        $header = $salt . pack('N', $rs) . chr($idlen) . $serverPubRaw;

        return [$header . $cipher . $tag, $serverPubRaw];
    }

    private function rawPublicKeyToOpensslKey(string $rawPub)
    {
        // 65 byte uncompressed point を SubjectPublicKeyInfo (DER) に包んで PEM 化
        $spkiPrefix = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
        $der = $spkiPrefix . $rawPub;
        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new WebPushException('クライアント公開鍵の解釈に失敗しました。');
        }
        return $key;
    }

    /**
     * VAPID JWT (ES256) を生成する。
     */
    private function buildJwt(string $audience): string
    {
        $header  = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $this->subject,
        ];

        $signingInput = self::b64enc((string)json_encode($header))
                      . '.'
                      . self::b64enc((string)json_encode($payload));

        $privateRaw = self::b64dec($this->privateKey);
        $publicRaw  = self::b64dec($this->publicKey);
        $vapidKey   = $this->rawPrivateKeyToOpensslKey($privateRaw, $publicRaw);

        $derSig = '';
        if (!openssl_sign($signingInput, $derSig, $vapidKey, OPENSSL_ALGO_SHA256)) {
            throw new WebPushException('VAPID JWT 署名に失敗しました。');
        }
        $joseSig = $this->derSignatureToJose($derSig);

        return $signingInput . '.' . self::b64enc($joseSig);
    }

    private function rawPrivateKeyToOpensslKey(string $privateRaw, string $publicRaw)
    {
        // RFC 5915 ECPrivateKey
        $version    = "\x02\x01\x01";
        $privateKey = "\x04\x20" . $privateRaw;
        $params     = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $publicKey  = "\xa1\x44\x03\x42\x00" . $publicRaw;
        $body       = $version . $privateKey . $params . $publicKey;
        $der        = "\x30" . $this->derLength(strlen($body)) . $body;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new WebPushException('VAPID 秘密鍵の読み込みに失敗しました: ' . openssl_error_string());
        }
        return $key;
    }

    private function derLength(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $bytes = '';
        while ($len > 0) {
            $bytes = chr($len & 0xff) . $bytes;
            $len >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * DER エンコードされた ECDSA 署名を JOSE 形式 (r || s, 64 byte) に変換。
     */
    private function derSignatureToJose(string $der): string
    {
        if (strlen($der) < 8 || $der[0] !== "\x30") {
            throw new WebPushException('DER 署名のヘッダ不正');
        }
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $lenBytes = ord($der[1]) & 0x7f;
            $offset   = 2 + $lenBytes;
        }
        if ($der[$offset] !== "\x02") {
            throw new WebPushException('DER 署名 r が不正');
        }
        $rLen   = ord($der[$offset + 1]);
        $r      = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;
        if ($der[$offset] !== "\x02") {
            throw new WebPushException('DER 署名 s が不正');
        }
        $sLen = ord($der[$offset + 1]);
        $s    = substr($der, $offset + 2, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        if (strlen($r) > 32 || strlen($s) > 32) {
            throw new WebPushException('ECDSA 署名長が不正');
        }
        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function getAudience(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new WebPushException('endpoint の URL が不正です。');
        }
        return $parts['scheme'] . '://' . $parts['host'];
    }

    public static function b64enc(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64dec(string $b64): string
    {
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return (string)base64_decode($b64);
    }

    /**
     * VAPID 鍵ペアを新規生成し、base64url で返す。
     *
     * @return array{publicKey:string, privateKey:string}
     */
    public static function generateVapidKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            throw new WebPushException('VAPID 鍵の生成に失敗しました。');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false || empty($details['ec'])) {
            throw new WebPushException('VAPID 鍵の取り出しに失敗しました。');
        }
        $publicRaw = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $privateRaw = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
        return [
            'publicKey'  => self::b64enc($publicRaw),
            'privateKey' => self::b64enc($privateRaw),
        ];
    }
}
