<?php
// api/index.php

// 1. Load Dependencies
require_once __DIR__ . '/../vendor/autoload.php';
$c = require_once __DIR__ . '/../config.php';
// helpers.php is loaded via composer autoload or require_once

$log = setupLogger('php://stderr', 'API');

// 2. Connect to Supabase (Postgres)
try {
    $dsn = "pgsql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_database']}";
    $pdo = new PDO($dsn, $c['db_username'], $c['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $log->error("Database connection failed: " . $e->getMessage());
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection error"]);
    exit;
}

// 3. Routing Logic
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header("Content-Type: application/json");

// Clean the path (removes /api prefix if Vercel includes it)
$path = str_replace('/api', '', $path);

switch ($path) {
    case '/availability':
        $domain = $_GET['domain'] ?? null;
        // ... include your domain logic from start_api.php here ...
        // Use $pdo->prepare() and echo json_encode()
        echo json_encode(["status" => "success", "domain" => $domain]);
        break;

    case '/registrars':
        $stmt = $pdo->query("SELECT name, url, abuse_email, iana_id FROM registrar");
        echo json_encode($stmt->fetchAll());
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint $path not found"]);
        break;
}