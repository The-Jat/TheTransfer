<?php

namespace Altum\Controllers;

defined('ALTUMCODE') || die();
session_start();

class AuthRedirect extends Controller {

    public function index() {

        // CSRF protection
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        // PKCE verifier
        $code_verifier = bin2hex(random_bytes(32));

        $_SESSION['pkce_code_verifier'] = $code_verifier;

        // PKCE challenge
        $code_challenge = rtrim(
            strtr(
                base64_encode(
                    hash('sha256', $code_verifier, true)
                ),
                '+/',
                '-_'
            ),
            '='
        );

        $query = http_build_query([
            'client_id' => OAUTH_CLIENT_ID,
            'redirect_uri' => url('auth-callback'),
            // 'response_type' => 'code',
            // 'scope' => 'profile email',
            'state' => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        ]);

        header('Location: ' . OAUTH_BASE_URL . OAUTH_AUTHORIZE_ENDPOINT .'?' . $query);
        exit;
    }
}