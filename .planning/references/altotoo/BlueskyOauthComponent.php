<?php
namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Exception;

class BlueskyOauthComponent extends Component
{
    /**
     * URLセーフなBase64エンコード
     */
    private function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * ランダムな文字列生成
     */
    public function generateRandomString($length = 32) {
        return $this->base64urlEncode(random_bytes($length));
    }

    /**
     * PKCE用のVerifierとChallengeを生成
     */
    public function generatePkce() {
        $verifier = $this->generateRandomString(64);
        $challenge = $this->base64urlEncode(hash('sha256', $verifier, true));
        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * DER形式の署名をRaw形式(R+S)に変換
     * (OpenSSLのECDSA署名はDER形式だが、JWTはRaw形式を要求するため)
     */
    private function derToRawSignature($der) {
        $len = strlen($der);
        if ($len < 8) return false;
        
        // DER構造の解析 (簡易版)
        // Sequenceタグ(0x30) + 全長 + Integerタグ(0x02) + R長 + R + Integerタグ(0x02) + S長 + S
        
        $pos = 2; // Skip Sequence tag and length
        
        // R
        if (ord($der[$pos]) !== 0x02) return false;
        $pos++;
        $r_len = ord($der[$pos]);
        $pos++;
        $r = substr($der, $pos, $r_len);
        $pos += $r_len;
        
        // S
        if (ord($der[$pos]) !== 0x02) return false;
        $pos++;
        $s_len = ord($der[$pos]);
        $pos++;
        $s = substr($der, $pos, $s_len);
        
        // 先頭の0x00を取り除く（もしあれば）
        if (strlen($r) > 32 && ord($r[0]) === 0) $r = substr($r, 1);
        if (strlen($s) > 32 && ord($s[0]) === 0) $s = substr($s, 1);
        
        // 32バイトにパディング
        $r = str_pad($r, 32, chr(0), STR_PAD_LEFT);
        $s = str_pad($s, 32, chr(0), STR_PAD_LEFT);
        
        return $r . $s;
    }

    /**
     * JWT (ES256) の生成
     */
    private function createJwt($payload, $header_extra = []) {
        $private_key_path = Configure::read('Bluesky.private_key_path');
        $private_key = file_get_contents($private_key_path);
        if (!$private_key) throw new Exception("Private key not found at: " . $private_key_path);

        $header = array_merge(['typ' => 'JWT', 'alg' => 'ES256'], $header_extra);
        
        $segments = [];
        $segments[] = $this->base64urlEncode(json_encode($header));
        $segments[] = $this->base64urlEncode(json_encode($payload));
        $signing_input = implode('.', $segments);
        
        $signature = '';
        if (!openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Signing failed");
        }
        
        // DER署名をRaw署名に変換
        $raw_signature = $this->derToRawSignature($signature);
        $segments[] = $this->base64urlEncode($raw_signature);
        
        return implode('.', $segments);
    }

    /**
     * Client Assertion (Private Key JWT) の生成
     */
    private function createClientAssertion() {
        $now = time();
        $payload = [
            'iss' => Configure::read('Bluesky.metadata_url'),
            'sub' => Configure::read('Bluesky.metadata_url'),
            'aud' => Configure::read('Bluesky.issuer'),
            'jti' => $this->generateRandomString(),
            'iat' => $now,
            'exp' => $now + 60, // 1分間有効
        ];
        
        // kidを追加 (setup_keys.phpで生成したkidと一致させる必要があります)
        return $this->createJwt($payload, ['kid' => 'key-1']);
    }

    /**
     * DPoP Proof の生成
     */
    private function createDpopProof($htm, $htu, $access_token = null, $nonce = null) {
        $private_key_path = Configure::read('Bluesky.private_key_path');
        $private_key = file_get_contents($private_key_path);
        $res = openssl_pkey_get_private($private_key);
        $details = openssl_pkey_get_details($res);
        
        // 公開鍵をJWK形式で取得
        $x = $this->base64urlEncode($details['ec']['x']);
        $y = $this->base64urlEncode($details['ec']['y']);
        
        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
            'use' => 'sig'
        ];
        
        $now = time();
        $payload = [
            'htm' => $htm,
            'htu' => $htu,
            'iat' => $now,
            'exp' => $now + 60,
            'jti' => $this->generateRandomString(),
        ];
        
        if ($access_token) {
            $payload['ath'] = $this->base64urlEncode(hash('sha256', $access_token, true));
        }

        if ($nonce) {
            $payload['nonce'] = $nonce;
        }
        
        return $this->createJwt($payload, ['typ' => 'dpop+jwt', 'jwk' => $jwk]);
    }

    /**
     * APIリクエストの実行 (DPoP対応)
     */
    public function callApi($endpoint, $access_token, $method = 'GET', $params = [], $content_type = 'application/json') {
        // 内部関数: 実際にリクエストを送る処理
        $send_request = function($nonce = null) use ($endpoint, $access_token, $method, $params, $content_type) {
            $dpop_proof = $this->createDpopProof($method, $endpoint, $access_token, $nonce);
            
            $url = $endpoint;
            if ($method === 'GET' && !empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            $ch = curl_init($url);
            
            $headers = [
                'Authorization: DPoP ' . $access_token,
                'DPoP: ' . $dpop_proof
            ];
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($content_type === 'application/json') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                }
                $headers[] = 'Content-Type: ' . $content_type;
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // ヘッダーも取得
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response_raw = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            $header_text = substr($response_raw, 0, $header_size);
            $body = substr($response_raw, $header_size);
            
            return ['code' => $http_code, 'header' => $header_text, 'body' => $body];
        };

        // 1回目のリクエスト
        $result = $send_request();

        // DPoP Nonceエラーの場合の再試行ロジック
        if ($result['code'] === 401) {
            $body_json = json_decode($result['body'], true);
            if (isset($body_json['error']) && $body_json['error'] === 'use_dpop_nonce') {
                // ヘッダーから DPoP-Nonce を抽出
                if (preg_match('/^DPoP-Nonce:\s*(.+)$/im', $result['header'], $matches)) {
                    $nonce = trim($matches[1]);
                    // Nonceを使って再試行
                    $result = $send_request($nonce);
                }
            }
        }
        
        if ($result['code'] !== 200) {
            // エラー時もレスポンスを返す（例外を投げるのではなく、呼び出し元で判断させる方が柔軟かもだが、元のコードに合わせて例外にするか検討）
            // ここでは元のコードに合わせて例外を投げる
            throw new Exception("API request failed: " . $result['body']);
        }
        
        return json_decode($result['body'], true);
    }

    public function post($text, $embed, $access_token, $did) {
        if (empty($text)) {
            return ['status' => 'no content'];
        }

        $embed_type = 'none';
        if (isset($embed['type'])){
            $embed_type = $embed['type'];
            unset($embed['type']);
        }

        $body = $this->createPostRequest($text, $did);

        switch ($embed_type) {
            case 'link':
                $body = $this->addLink($body, $embed, $access_token);
                break;
            case 'image':
                $body = $this->addImage($body, $embed, $access_token);
                break;
        }

        $service_path = 'xrpc/com.atproto.repo.createRecord';
        $endpoint = Configure::read('Bluesky.issuer') . '/' . $service_path;
        
        try {
            $response = $this->callApi($endpoint, $access_token, 'POST', $body);
            return [
                'status' => 'success',
                'debug'  => $response,
                'post_fields'  => json_encode($body)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * OAuthトークンはPDS専用のため、$pds_endpoint を必ず渡すこと。
     */
    public function postWithImages($text, $images, $access_token, $did, $pds_endpoint = null) {
        $baseUrl = $pds_endpoint ? rtrim($pds_endpoint, '/') : Configure::read('Bluesky.issuer');
        $body = $this->createPostRequest($text, $did);

        if (!empty($images)) {
            $body['record']['embed'] = array(
                '$type'  => 'app.bsky.embed.images',
                'images' => []
            );

            foreach ($images as $image_info) {
                $blob = $this->uploadImage(['uri' => $image_info['path']], $access_token, $pds_endpoint);
                
                if (isset($blob['error'])) {
                    // エラーログを残すか、例外を投げるか
                    // ここではとりあえずスキップするが、実運用では通知が必要かも
                    continue;
                }

                // createRecord では blob 形式（ref.$link）を使う。Legacy の cid 形式は不可
                $blobObj = $blob['blob'];
                $img = array(
                    'alt' => $image_info['alt'],
                    'image' => array(
                        '$type' => 'blob',
                        'ref' => array('$link' => $blobObj['ref']['$link']),
                        'mimeType' => $blobObj['mimeType'],
                        'size' => (int) ($blobObj['size'] ?? 0),
                    )
                );
                // aspectRatio を指定しないとクライアントが正方形等で表示し上下に帯が出るため、実寸比を渡す
                $w = (int) ($image_info['width'] ?? 0);
                $h = (int) ($image_info['height'] ?? 0);
                if ($w >= 1 && $h >= 1) {
                    $img['aspectRatio'] = array('width' => $w, 'height' => $h);
                }
                $body['record']['embed']['images'][] = $img;
            }
        }

        $service_path = 'xrpc/com.atproto.repo.createRecord';
        $endpoint = $baseUrl . '/' . $service_path;
        
        try {
            $response = $this->callApi($endpoint, $access_token, 'POST', $body);
            return [
                'status' => 'success',
                'debug'  => $response
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failure',
                'error' => $e->getMessage()
            ];
        }
    }

    private function addLink($body, $embed, $access_token) {
        if (isset($embed['thumb'])) {
            $thumb = '' . $embed['thumb'];
            $blob = $this->uploadImage(['uri' => $thumb], $access_token);
            unset($embed['thumb']);

            if (isset($blob['blob']['ref'])) {
                $ref_link = $blob['blob']['ref']['$link'];
                $embed['thumb'] = array(
                    "mimeType" => $blob['blob']['mimeType'],
                    "cid"      => $ref_link
                );
            }
        }

        $body['record']['embed'] = array(
            '$type'    => 'app.bsky.embed.external',
            'external' => $embed['embed']
        );
        return $body;
    }

    private function addImage($body, $embed, $access_token) {
        $blob = $this->uploadImage($embed, $access_token);

        if (isset($blob['error'])) {
            return array( 
                'status' => 'did not upload image',
                'debug'  => $blob
            );
        }

        $blobObj = $blob['blob'];
        $body['record']['embed'] = array(
            '$type'  => 'app.bsky.embed.images',
            'images' => []
        );

        $img = array(
            'alt' => $embed['alt'],
            'image' => array(
                '$type' => 'blob',
                'ref' => array('$link' => $blobObj['ref']['$link']),
                'mimeType' => $blobObj['mimeType'],
                'size' => (int) ($blobObj['size'] ?? 0),
            )
        );

        $body['record']['embed']['images'][] = $img;

        return $body;
    }

    private function createPostRequest($text, $did) {
        $text = strip_tags($text);
        $facets = array();

        preg_match_all('/\[(.*?)\]\((.*?)\)/', $text, $elements, PREG_OFFSET_CAPTURE);
        array_shift($elements);

        if (!empty($elements)) {
            $text_plain = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function ($matches) {
                return $matches[1];
            }, $text);

            for ($i=0; $i<count($elements[0]); $i++) {
                $label = $elements[0][$i][0];
                $link  = $elements[1][$i][0];
                $pos_start = strpos($text_plain, $label);
                $pos_end   = $pos_start + strlen($label);

                $facet = array(
                    'features' =>[],
                    'index' => array(
                        'byteStart' => $pos_start,
                        'byteEnd'   => $pos_end
                    )
                );

                $facet['features'][] = array(
                    'uri'   => $link,
                    '$type' => 'app.bsky.richtext.facet#link'
                );

                $facets[] = $facet;
            }

            if (!empty($facets)) {
                $text = $text_plain;
            }
        } else {
            $url_pattern = '/(((http|https)\:\/\/)|(www\.))[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\:[0-9]+)?(\/\S*)?/';
            preg_match_all($url_pattern, $text, $elements, PREG_OFFSET_CAPTURE);

            if (isset($elements[0]) && !empty($elements[0])) {
                $el = $elements[0];
                for ($i=0; $i<count($el); $i++) {
                    $link      = $el[$i][0];
                    $pos_start = strpos($text, $link);
                    $pos_end   = $pos_start + strlen($link);

                    $facet = array(
                        'features' =>[],
                        'index' => array(
                            'byteStart' => $pos_start,
                            'byteEnd'   => $pos_end
                        )
                    );
                    $facet['features'][] = array(
                        'uri'   => $link,
                        '$type' => 'app.bsky.richtext.facet#link'
                    );

                    $facets[] = $facet;
                }
            }
        }

        $body = array(
            'collection' => 'app.bsky.feed.post',
            'did'        => $did,
            'repo'       => $did,
            'record'     => array(
                '$type'     => 'app.bsky.feed.post',
                'text'      => $text,
                'createdAt' => gmdate( 'c' )
            ),
        );

        if (!empty($facets)) {
            $body['record']['facets'] = $facets;
        }

        return $body;
    }

    /**
     * @param string|null $pds_endpoint OAuthの場合はユーザーのPDS URLを渡すこと（必須）
     */
    public function uploadImage($image, $access_token, $pds_endpoint = null) {
        $service_path = 'xrpc/com.atproto.repo.uploadBlob';
        $baseUrl = $pds_endpoint ? rtrim($pds_endpoint, '/') : Configure::read('Bluesky.issuer');
        $endpoint = $baseUrl . '/' . $service_path;
        
        $ext = pathinfo($image['uri'], PATHINFO_EXTENSION);
        $content_type = 'application/octet-stream';
        switch($ext) {
            case 'jpg':
            case 'jpeg':
                $content_type = 'image/jpeg';
                break;			
            case 'png':
                $content_type = 'image/png';
                break;
        }

        $data = file_get_contents($image['uri']);
        
        try {
            $blob = $this->callApi($endpoint, $access_token, 'POST', $data, $content_type);
            if (isset($blob['blob']) && !isset($blob['blob']['size']) && file_exists($image['uri'])) {
                $blob['blob']['size'] = filesize($image['uri']);
            }
            return $blob;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * DIDからPDSのエンドポイントを特定する
     */
    public function resolveDidToPds($did) {
        // PLC DirectoryからDIDドキュメントを取得
        $url = "https://plc.directory/" . $did;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $doc = json_decode($response, true);
        if (!$doc || !isset($doc['service'])) {
            throw new Exception("DID Document not found or invalid");
        }
        
        foreach ($doc['service'] as $service) {
            if ($service['type'] === 'AtprotoPersonalDataServer') {
                return $service['serviceEndpoint'];
            }
        }
        
        throw new Exception("PDS endpoint not found");
    }

    public function getProfile($did, $access_token) {
        try {
            $pds_endpoint = $this->resolveDidToPds($did);
            $profile_endpoint = rtrim($pds_endpoint, '/') . '/xrpc/app.bsky.actor.getProfile';
            
            return $this->callApi($profile_endpoint, $access_token, 'GET', ['actor' => $did]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function exchangeCodeForToken($code, $pkce_verifier) {
        $token_endpoint = Configure::read('Bluesky.token_endpoint');
        
        // 内部関数: 実際にリクエストを送る処理
        $send_request = function($nonce = null) use ($code, $pkce_verifier, $token_endpoint) {
            $client_assertion = $this->createClientAssertion();
            $dpop_proof = $this->createDpopProof('POST', $token_endpoint, null, $nonce);
            
            $params = [
                'client_id' => Configure::read('Bluesky.metadata_url'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => Configure::read('Bluesky.redirect_uri'),
                'code_verifier' => $pkce_verifier,
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $client_assertion
            ];
            
            $ch = curl_init($token_endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // ヘッダーも取得する
            
            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'DPoP: ' . $dpop_proof
            ];
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response_raw = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            $header_text = substr($response_raw, 0, $header_size);
            $body = substr($response_raw, $header_size);
            
            return ['code' => $http_code, 'header' => $header_text, 'body' => $body];
        };

        // 1回目のリクエスト
        $result = $send_request();

        // DPoP Nonceエラーの場合の再試行ロジック
        if ($result['code'] === 400 || $result['code'] === 401) {
            $body_json = json_decode($result['body'], true);
            if (isset($body_json['error']) && $body_json['error'] === 'use_dpop_nonce') {
                // ヘッダーから DPoP-Nonce を抽出
                if (preg_match('/^DPoP-Nonce:\s*(.+)$/im', $result['header'], $matches)) {
                    $nonce = trim($matches[1]);
                    // Nonceを使って再試行
                    $result = $send_request($nonce);
                }
            }
        }
        
        if ($result['code'] !== 200) {
            throw new Exception("Token exchange failed: " . $result['body']);
        }
        
        return json_decode($result['body'], true);
    }

    public function executeParRequest($code_challenge, $state) {
        $par_endpoint = Configure::read('Bluesky.par_endpoint');
        
        // 内部関数: 実際にリクエストを送る処理
        $send_request = function($nonce = null) use ($code_challenge, $state, $par_endpoint) {
            $client_assertion = $this->createClientAssertion();
            $dpop_proof = $this->createDpopProof('POST', $par_endpoint, null, $nonce);
            
            $params = [
                'client_id' => Configure::read('Bluesky.metadata_url'),
                'response_type' => 'code',
                'code_challenge' => $code_challenge,
                'code_challenge_method' => 'S256',
                'redirect_uri' => Configure::read('Bluesky.redirect_uri'),
                'state' => $state,
                'scope' => 'atproto transition:generic',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $client_assertion
            ];
            
            $ch = curl_init($par_endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // ヘッダーも取得する
            
            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'DPoP: ' . $dpop_proof
            ];
            
            // Nonceがある場合はDPoP-Nonceヘッダーは不要（Proofの中に入れるだけでよいが、一部実装ではヘッダーにも求められることがあるため念のため）
            // Blueskyの仕様ではProof内のnonceクレームが重要。
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response_raw = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            $header_text = substr($response_raw, 0, $header_size);
            $body = substr($response_raw, $header_size);
            
            return ['code' => $http_code, 'header' => $header_text, 'body' => $body];
        };

        // 1回目のリクエスト
        $result = $send_request();

        // DPoP Nonceエラーの場合の再試行ロジック
        if ($result['code'] === 400 || $result['code'] === 401) {
            $body_json = json_decode($result['body'], true);
            if (isset($body_json['error']) && $body_json['error'] === 'use_dpop_nonce') {
                // ヘッダーから DPoP-Nonce を抽出
                if (preg_match('/^DPoP-Nonce:\s*(.+)$/im', $result['header'], $matches)) {
                    $nonce = trim($matches[1]);
                    // Nonceを使って再試行
                    $result = $send_request($nonce);
                }
            }
        }
        
        if ($result['code'] !== 201) {
            throw new Exception("PAR request failed: " . $result['body']);
        }
        
        return json_decode($result['body'], true);
    }
}
