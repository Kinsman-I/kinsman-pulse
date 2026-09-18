<?php
declare(strict_types=1);

/** Endpoint público autenticado por token para eventos enviados pela Asaas. */
require __DIR__ . "/../app/bootstrap.php";
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false]);
    exit();
}
$received = (string) ($_SERVER["HTTP_ASAAS_ACCESS_TOKEN"] ?? "");
$expected = (string) env("ASAAS_WEBHOOK_TOKEN", "");
if ($expected === "" || !hash_equals($expected, $received)) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit();
}
try {
    $payload = json_decode(
        (string) file_get_contents("php://input"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    process_asaas_webhook($payload);
    echo json_encode(["ok" => true]);
} catch (Throwable $e) {
    error_log("Asaas webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["ok" => false]);
}
