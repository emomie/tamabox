<?php
namespace App\Controller;


use App\Controller\AppController;
use \potibm\Bluesky\BlueskyApi;
use \potibm\Bluesky\BlueskyPostService;
use \potibm\Bluesky\Feed\Post;
use Cake\Http\Cookie\Cookie;
use Cake\Controller\Component\CookieComponent;
use Cake\Core\Configure;
// session_start(); // CakePHPのセッション管理に任せるためコメントアウト推奨だが、既存動作への影響を避けるため一旦そのままにするか、あるいは既存コードが依存しているなら残す。
// しかし、クラスファイル直下の session_start() は推奨されない。
// 既存コードにあるので、そのままにしておく。
session_start();


class LoginController extends AppController
{


	public function initialize(): void
	{

		$this->loadComponent('IsLogin');
		$this->loadComponent('BskyApi');
		$this->loadComponent('BlueskyOauth');


	} 

    public function oauthLogin()
    {
        try {
            // 1. PKCE (Proof Key for Code Exchange) の生成
            $pkce = $this->BlueskyOauth->generatePkce();
            $_SESSION['pkce_verifier'] = $pkce['verifier'];

            // 2. State (CSRF対策) の生成
            $state = $this->BlueskyOauth->generateRandomString();
            $_SESSION['oauth_state'] = $state;

            // 3. PAR (Pushed Authorization Request) の実行
            $par_response = $this->BlueskyOauth->executeParRequest($pkce['challenge'], $state);
            
            $request_uri = $par_response['request_uri'];

            // 4. 認可エンドポイントへリダイレクト
            $auth_url = Configure::read('Bluesky.auth_endpoint') . '?client_id=' . urlencode(Configure::read('Bluesky.metadata_url')) . '&request_uri=' . urlencode($request_uri);
            
            return $this->redirect($auth_url);

        } catch (\Exception $e) {
            $this->Flash->error('エラーが発生しました: ' . $e->getMessage());
            return $this->redirect(['controller' => 'Error', 'action' => 'login']);
        }
    }

    public function callback()
    {
        // エラーチェック
        if ($this->request->getQuery('error')) {
            $this->Flash->error('Error: ' . $this->request->getQuery('error'));
            return $this->redirect(['controller' => 'Error', 'action' => 'login']);
        }

        // Stateチェック (CSRF対策)
        $state = $this->request->getQuery('state');
        if (!$state || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
            $this->Flash->error('Invalid state parameter.');
            return $this->redirect(['controller' => 'Error', 'action' => 'login']);
        }

        // Authorization Codeの取得
        $code = $this->request->getQuery('code');
        if ($code) {
            try {
                // アクセストークンと交換
                $token_response = $this->BlueskyOauth->exchangeCodeForToken($code, $_SESSION['pkce_verifier']);
                
                // ユーザー情報の取得 (DIDからPDSを特定)
                $did = $token_response['sub'];
                $pds_endpoint = $this->BlueskyOauth->resolveDidToPds($did);
                
                // セッションに保存
                $_SESSION['is_login'] = true;
                $_SESSION['did'] = $did;
                $_SESSION['access_token'] = $token_response['access_token'];
                $_SESSION['refresh_token'] = $token_response['refresh_token'];
                $_SESSION['pds_endpoint'] = $pds_endpoint;
                
                // 既存のロジックとの互換性のため、user_name (handle) も取得しておくと良いが、
                // ここではAPIコールが必要。一旦スキップして、必要なら後で追加。
                // ただし、mypageなどで user_name を表示しているなら必要。
                
                // プロフィール取得してハンドル名を保存
                try {
                    $profile_endpoint = rtrim($pds_endpoint, '/') . '/xrpc/app.bsky.actor.getProfile';
                    $profile = $this->BlueskyOauth->callApi($profile_endpoint, $token_response['access_token'], 'GET', ['actor' => $did]);
                    $_SESSION['user_name'] = $profile['handle'];
                    // アバターなども保存可能
                } catch (\Exception $e) {
                    // プロフィール取得失敗してもログインは続行させるか？
                    // user_name が必須ならエラーにする
                    $this->Flash->error('プロフィール取得に失敗しました: ' . $e->getMessage());
                }

                return $this->redirect('/mypage');
                
            } catch (\Exception $e) {
                $this->Flash->error('トークン取得エラー: ' . $e->getMessage());
                return $this->redirect(['controller' => 'Error', 'action' => 'login']);
            }
        } else {
            $this->Flash->error('コードが見つかりません。');
            return $this->redirect(['controller' => 'Error', 'action' => 'login']);
        }
    } 


	public function index()
	{


		//COOKIE無しでPOSTもない場合はエラー
		$this->redirect('/error');
		return;    

	}


	public function oauth()
	{


		//POSTがない場合はエラー
		if(!isset($_POST['name']) OR !isset($_POST['app_pass']))
		{


			//COOKIE無しでPOSTもない場合はエラー
			$this->redirect('/error/login');
			return;                      

		 
		}else{

			$user_name = $_POST['name'];
			$app_pass = $_POST['app_pass'];            
		}






		//パスワードとユーザの一致を確認
		$is_user_exist = $this->BskyApi->is_user_exist($user_name,$app_pass);
		//var_dump($is_user_exist);

		if($is_user_exist == false)
		{
			$this->redirect('/error/login');
			return;               

		}



		//cookieにチェックが入っていたらここでcookieに格納
		if(isset($_POST) AND $_POST['chk_box'] == "true")
		{

			setcookie(
				"user_name",   // Cookieの名前
				$user_name,    // Cookieの値
				time() + 60*60*24*365,  // 有効期限
				'/',           // パス
				'',            // ドメインを空文字列に変更
				false,         // セキュア
				true,          // HttpOnly
			);


			setcookie(
				"app_pass",   // Cookieの名前
				$app_pass,  // Cookieの値
				time() + 60*60*24*365,  // 有効期限
				'/',           // パス
				'',            // ドメインを空文字列に変更
				false,         // セキュア
				true,          // HttpOnly
			);            
		}

		//cookieにis_first_loginがあるかどうか確認する
		if(!isset($_COOKIE['is_first_login'])){
			//最初のログインの場合はフラグを立てる
			setcookie("is_first_login", "true", time() + 60*60*24*365, '/', '', false, true);
		}else{
			//is_first_loginがあった場合は二回目以降のログイン
			setcookie("is_first_login", "false", time() + 60*60*24*365, '/', '', false, true);
		}


		//print_r($_COOKIE);

		//セッションを有効化
		$_SESSION['is_login'] = true;
		$_SESSION['user_name'] = $user_name;
		$_SESSION['app_pass'] = $app_pass;

		$this->redirect('/mypage');
		return;   

	}











}   