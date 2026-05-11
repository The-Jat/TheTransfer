<?php

namespace Altum\Controllers;

use Altum\Models\User;
use Altum\Logger;

defined('ALTUMCODE') || die();
session_start();

class AuthCallback extends Controller {

    public function index() {

        if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
            die('Invalid state');
        }

        unset($_SESSION['oauth_state']);

        if(!isset($_GET['code'])) {
            die('Missing code');
        }

        $code = $_GET['code'];

        /* Token request */
        $token_response = json_decode(file_get_contents(OAUTH_BASE_URL . OAUTH_TOKEN_ENDPOINT, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded",
                'content' => http_build_query([
                    'grant_type' => 'authorization_code',
                    'client_id' => OAUTH_CLIENT_ID,
                    'client_secret' => OAUTH_CLIENT_SECRET,
                    'redirect_uri' => url('auth-callback'),
                    'code' => $code,
                    'code_verifier' => $_SESSION['pkce_code_verifier'],
                ])
            ]
        ])), true);

        unset($_SESSION['pkce_code_verifier']);

        if(!isset($token_response['access_token'])) {
            die('Token failed');
        }

        $access_token = $token_response['access_token'];

        /* Get user */
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "Authorization: Bearer $access_token"
            ]
        ]);

        $user = json_decode(file_get_contents(OAUTH_BASE_URL . OAUTH_PROFILE_ENDPOINT, false, $context), true);

        if(!$user || !isset($user['email'])) {
            die('User fetch failed');
        }

        $db = database();

        $email = $db->real_escape_string($user['email']);
        $name = $db->real_escape_string($user['name']);

        $result = $db->query("SELECT * FROM users WHERE email = '$email'");

        if($result && $result->num_rows > 0) {
            $existing = $result->fetch_object();
            $user_id = $existing->user_id;

        } else {

            $password = bin2hex(random_bytes(16)); // ✅ plain password

            $plan_id = $user['plan_id'] ?? 'free';
            $plan_settings = json_encode(settings()->{"plan_$plan_id"}->settings ?? settings()->plan_free->settings);
            $plan_expiration_date = get_date();

            $user_model = new User();

            $registered_user = $user_model->create(
                $email,
                $password,
                $name,
                1,
                'oauth',
                null,
                null,
                0,
                $plan_id,
                $plan_settings,
                $plan_expiration_date,
                settings()->main->default_timezone
            );

            $user_id = $registered_user['user_id'];

            Logger::users($user_id, 'register.success');
        }

        $user_row = $db->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_object();

        session_set('user_id', $user_row->user_id);
        session_set('user_password_hash', md5($user_row->password));

        setcookie('user_id', $user_row->user_id, time() + 60*60*24*30, '/');
        setcookie('user_password_hash', md5($user_row->password), time() + 60*60*24*30, '/');

        (new \Altum\Models\User())->login_aftermath_update($user_row->user_id);

        Logger::users($user_row->user_id, 'login.success');

        header('Location: ' . url('dashboard'));
        exit;
    }
}