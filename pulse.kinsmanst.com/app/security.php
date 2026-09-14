<?php
declare(strict_types=1);

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Compatible with existing inline scripts, videos and Turnstile.
    // This is a limited policy, not a complete XSS defense.
   header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline';
   img-src 'self' data: blob:; font-src 'self'; connect-src 'self' https://api.asaas.com; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"); 
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function security_rate_hit(string $directory, string $key, int $limit, int $window, ?int $now = null): int
{
    $now ??= time();
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Rate limit storage unavailable');
    }
    // Fixed number of shards; identifiers and IP addresses are never stored in plain text.
    $hash = hash('sha256', $key);
    $handle = @fopen($directory . '/' . substr($hash, 0, 2) . '.json', 'c+');
    if (!$handle) throw new RuntimeException('Rate limit storage unavailable');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Rate limit lock unavailable');
        $raw = stream_get_contents($handle);
        $buckets = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($buckets)) throw new RuntimeException('Rate limit storage invalid');
        foreach ($buckets as $id => $bucket) if ($bucket['until'] <= $now) unset($buckets[$id]);
        $bucket = $buckets[$hash] ?? ['count' => 0, 'until' => $now + $window];
        if ($bucket['count'] >= $limit) return max(1, $bucket['until'] - $now);
        $bucket['count']++;
        $buckets[$hash] = $bucket;
        $encoded = json_encode($buckets, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
            throw new RuntimeException('Rate limit write unavailable');
        }
        return 0;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function security_request_guard(): void
{
    foreach ($_POST as $value) {
        if (!is_string($value) || strlen($value) > 100000) {
            http_response_code(400); exit('Dados inválidos.');
        }
    }
    $action = $_POST['action'] ?? '';
    $limits = ['login' => [30, 900], 'forgot' => [10, 3600], 'reset' => [15, 900], 'register_professional' => [5, 3600]];
    if (!isset($limits[$action])) return;
    [$limit, $window] = $limits[$action];
    try {
        $retry = security_rate_hit(APP_ROOT . '/storage/security', $action . '|ip|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $limit, $window);
        if (!$retry && in_array($action, ['login', 'forgot'], true)) {
            $identifier = strtolower(trim($_POST['identifier'] ?? $_POST['email'] ?? ''));
            if (!str_contains($identifier, '@')) $identifier = preg_replace('/\D/', '', $identifier);
            $retry = security_rate_hit(APP_ROOT . '/storage/security', $action . '|account|' . $identifier, $action === 'login' ? 10 : 5, $window);
        }
    } catch (Throwable $error) {
        error_log('Pulse: security rate limiter unavailable');
        http_response_code(503); exit('Acesso temporariamente indisponível. Tente novamente mais tarde.');
    }
    if ($retry) {
        header('Retry-After: ' . $retry);
        http_response_code(429); exit('Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.');
    }
}

function security_photo_allowed(PDO $pdo, array $user, string $path): bool
{
    if (!in_array($user['role'] ?? '', ['student', 'professional'], true)) return false;
    $owner = $user['role'] === 'student' ? 's.user_id = ?' : 'p.user_id = ?';
    $statement = $pdo->prepare("SELECT pp.id FROM progress_photos pp JOIN students s ON s.id=pp.student_id JOIN professionals p ON p.id=s.professional_id WHERE pp.image_path=? AND s.tenant_id=? AND p.tenant_id=s.tenant_id AND $owner LIMIT 1");
    $statement->execute([$path, $user['tenant_id'], $user['id']]);
    return (bool)$statement->fetchColumn();
}

function security_video_url(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    $parts = parse_url($value);
    if (strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('Informe um endereço HTTP ou HTTPS válido para o vídeo.');
    }
    return $value;
}
