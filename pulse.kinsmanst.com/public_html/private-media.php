<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
function media_not_found(): never { http_response_code(404); exit('Arquivo não disponível.'); }
$name = $_GET['file'] ?? '';
if (!is_string($name) || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $name)) media_not_found();
$user = current_user();
if (!$user || !security_photo_allowed(db(), $user, 'uploads/progress/' . $name)) media_not_found();
if ($user['role'] === 'professional' && professional_access_blocked($user)) media_not_found();
$root = realpath(__DIR__ . '/uploads/progress');
$path = realpath(__DIR__ . '/uploads/progress/' . $name);
if (!$root || !$path || dirname($path) !== $root || !is_file($path)) media_not_found();
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) media_not_found();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
session_write_close();
readfile($path);
