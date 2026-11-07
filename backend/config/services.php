<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Fonte dos jogos da NBA (app/Integrations/Nba): espn (API pública do site da ESPN, não
    // oficial), cdn (JSONs de cdn.nba.com) ou demo (simulação local, sem internet).
    'nba' => [
        'provider' => env('NBA_PROVIDER', 'espn'),
        // Pacote de certificados (CA) para o HTTPS da fonte, quando o PHP local não tem um
        // configurado (curl.cainfo). Nunca desligar a verificação.
        'ca_bundle' => env('NBA_CA_BUNDLE'),
    ],

    // Entrar com Google. Sem GOOGLE_CLIENT_ID o botão nem aparece. No Google Cloud, a URL de
    // retorno autorizada é FRONTEND_URL/api/auth/google/callback (o Next repassa para cá).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/api/auth/google/callback',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
