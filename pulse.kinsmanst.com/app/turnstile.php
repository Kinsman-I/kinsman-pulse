<?php
declare(strict_types=1);

const TURNSTILE_SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

function turnstile_is_enabled(): bool
{
    return trim((string)env('TURNSTILE_SITE_KEY', '')) !== ''
        && trim((string)env('TURNSTILE_SECRET_KEY', '')) !== '';
}

function turnstile_widget(string $action): string
{
    $siteKey = trim((string)env('TURNSTILE_SITE_KEY', ''));
    if ($siteKey === '') return '';

    return '<div class="turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . e($siteKey)
        . '" data-action="' . e($action) . '" data-theme="light" data-size="flexible"></div></div>';
}

function turnstile_script(): string
{
    if (trim((string)env('TURNSTILE_SITE_KEY', '')) === '') return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function verify_turnstile(string $expectedAction): void
{
    $siteKey = trim((string)env('TURNSTILE_SITE_KEY', ''));
    $secret = trim((string)env('TURNSTILE_SECRET_KEY', ''));
    if ($siteKey === '' && $secret === '') return;
    if ($siteKey === '' || $secret === '') {
        throw new RuntimeException('A proteção Cloudflare está incompleta. Configure as duas chaves do Turnstile.');
    }

    $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '') throw new RuntimeException('Confirme a verificação de segurança para continuar.');

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
    $raw = false;

    if (function_exists('curl_init')) {
        $curl = curl_init(TURNSTILE_SITEVERIFY_URL);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($curl);
        curl_close($curl);
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents(TURNSTILE_SITEVERIFY_URL, false, $context);
    }

    if (!is_string($raw) || $raw === '') throw new RuntimeException('Não foi possível validar a segurança agora. Tente novamente.');
    $result = json_decode($raw, true);
    $valid = is_array($result) && ($result['success'] ?? false) === true;
    $actionMatches = isset($result['action']) && hash_equals($expectedAction, (string)$result['action']);
    if (!$valid || !$actionMatches) throw new RuntimeException('A verificação de segurança expirou ou é inválida. Tente novamente.');
}
