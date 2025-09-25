<?php
session_start();
require 'vendor/autoload.php'; // install with: composer require firebase/php-jwt

use Firebase\JWT\JWT;

// ✅ SECURITY: Allow only logged-in students
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// JaaS credentials
$appId     = "vpaas-magic-cookie-4fce89a7cf2f4b64a9f5e2575ee77873";
$apiKeyId  = "51ec31";
$privateKey = file_get_contents(__DIR__ . "/jaas-private.pem");

// ✅ SECURITY: Expire after 3 hours (class length + buffer)
$payload = [
    "aud"  => "jitsi",        // always "jitsi"
    "iss"  => $apiKeyId,      // API Key ID
    "sub"  => $appId,         // App ID
    "room" => "*",            // allow all rooms or specify one
    "exp"  => time() + (3 * 60 * 60) // valid for 3 hours
];

// Generate JWT
$jwt = JWT::encode($payload, $privateKey, "RS256");

// ✅ Optional logging (track who requested a token)
$log = sprintf("[%s] User %s got a token\n", date("Y-m-d H:i:s"), $_SESSION['user_id']);
file_put_contents(__DIR__ . "/token_log.txt", $log, FILE_APPEND);

// Return token as JSON
header('Content-Type: application/json');
echo json_encode(["token" => $jwt]);
