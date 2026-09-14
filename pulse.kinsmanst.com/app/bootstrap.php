<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

function load_env(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo .env nao encontrado. Consulte docs/INSTALACAO-CPANEL.md.');
    }
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $values;
}

$env = load_env(APP_ROOT . '/.env');
function env(string $key, mixed $default = null): mixed { global $env; return $env[$key] ?? $default; }

date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));
ini_set('display_errors', env('APP_DEBUG', 'false') === 'true' ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/security.php';
security_headers();
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name((string) env('SESSION_NAME', 'kinsman_pulse_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => str_starts_with((string) env('APP_URL', ''), 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST'), env('DB_PORT', '3306'), env('DB_NAME'));
    $pdo = new PDO($dsn, (string) env('DB_USER'), (string) env('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $page = 'dashboard', array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function back_url(): string
{
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $parts = parse_url($referer);
    if ($parts && isset($parts['host']) && hash_equals((string)($_SERVER['HTTP_HOST'] ?? ''), (string)$parts['host'])) {
        $path = $parts['path'] ?? '/index.php';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) return url();
        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
    return url();
}
function csrf_token(): string { $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Sessao expirada. Atualize a pagina e tente novamente.'); } }
function db_table_exists(string $table): bool { $stmt=db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);return (int)$stmt->fetchColumn()>0; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $user;
    if ($user !== null) return $user ?: null;
    $stmt = db()->prepare("SELECT u.*,
      COALESCE(ownp.brand_name, studentp.brand_name, t.name) tenant_name,
      COALESCE(ownp.logo_path, studentp.logo_path, t.logo_path) logo_path,
      COALESCE(ownp.primary_color, studentp.primary_color, t.primary_color) primary_color,
      COALESCE(ownp.whatsapp, studentp.whatsapp) brand_whatsapp,
      COALESCE(ownp.instagram, studentp.instagram) brand_instagram,
      COALESCE(ownp.welcome_message, studentp.welcome_message) welcome_message,
      COALESCE(ownp.footer_text, studentp.footer_text, 'Tecnologia Kinsman') footer_text
      ,COALESCE(stu.service_type, ownp.service_type, 'complete') access_service_type
      FROM users u JOIN tenants t ON t.id=u.tenant_id
      LEFT JOIN professionals ownp ON ownp.user_id=u.id
      LEFT JOIN students stu ON stu.user_id=u.id
      LEFT JOIN professionals studentp ON studentp.id=stu.professional_id
      WHERE u.id=? AND u.active=1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: false;
    if ($user) {
        $fingerprint = hash('sha256', (string)$user['password_hash']);
        if (!isset($_SESSION['auth_fingerprint']) || !hash_equals($_SESSION['auth_fingerprint'], $fingerprint)) {
            $_SESSION = [];
            $user = false;
        }
    }
    return $user ?: null;
}

function require_auth(): array { $user = current_user(); if (!$user) redirect(url('login')); return $user; }
function require_role(array|string $roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], (array)$roles, true)) { http_response_code(403); exit('Acesso nao autorizado.'); }
    return $user;
}
function logout(): void { $_SESSION = []; if (ini_get('session.use_cookies')) setcookie(session_name(), '', time()-42000, '/'); session_destroy(); }

function audit(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
{
    $u = current_user();
    $stmt = db()->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$u['tenant_id'] ?? null, $u['id'] ?? null, $action, $entity, $entityId, json_encode($details), $_SERVER['REMOTE_ADDR'] ?? null]);
}

function upload_image(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]['tmp_name'])) return null;
    if (!preg_match('/\A[a-z]+\z/', $folder)) throw new RuntimeException('Pasta inválida.');
    $file = $_FILES[$field];
    if (!is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Upload inválido.');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao enviar a imagem.');
    $max = ((int) env('UPLOAD_MAX_MB', 5)) * 1024 * 1024;
    if ($file['size'] > $max) throw new RuntimeException('A imagem ultrapassa o tamanho permitido.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Use uma imagem JPG, PNG ou WebP.');
    $dimensions = @getimagesize($file['tmp_name']);
    if (!$dimensions || $dimensions[0] > 12000 || $dimensions[1] > 12000 || $dimensions[0] * $dimensions[1] > 40000000) throw new RuntimeException('Dimensões da imagem inválidas.');
    $relative = 'uploads/' . $folder;
    $dir = __DIR__ . '/../public_html/' . $relative;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Nao foi possivel criar a pasta de upload.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Nao foi possivel salvar a imagem.');
    return $relative . '/' . $name;
}

function student_scope_sql(array $user, string $alias = 's'): array
{
    if ($user['role'] === 'admin') return ["$alias.tenant_id = ?", [$user['tenant_id']]];
    if ($user['role'] === 'professional') return ["$alias.professional_id = (SELECT id FROM professionals WHERE user_id=?)", [$user['id']]];
    return ["$alias.user_id = ?", [$user['id']]];
}

require_once __DIR__ . '/views.php';
require_once __DIR__ . '/turnstile.php';
require_once __DIR__ . '/asaas.php';
require_once __DIR__ . '/exports.php';
require_once __DIR__ . '/actions.php';
