<?php
// 1. Paths
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$configPath   = __DIR__ . '/../config.php';

// 2. Load Autoloader First
if (!file_exists($autoloadPath)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        "error" => "Autoload not found.",
        "debug_path" => $autoloadPath
    ]);
    exit;
}
require_once $autoloadPath;

// 3. Load Config (Local file OR Environment Variables)
if (file_exists($configPath)) {
    $c = require_once $configPath;
} else {
    $c = [
        'db_host'     => getenv('DB_HOST'),
        'db_port'     => getenv('DB_PORT') ?: '5432',
        'db_database' => getenv('DB_DATABASE'),
        'db_username' => getenv('DB_USERNAME'),
        'db_password' => getenv('DB_PASSWORD'),
    ];
}

// Validate config exists
if (empty($c['db_host']) || empty($c['db_password'])) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["error" => "Database configuration missing (no config.php or ENV vars)."]);
    exit;
}

// 4. Initialize Logger (Must be defined in helpers.php)
$log = setupLogger('php://stderr', 'API');

// 5. Connect to Supabase
try {
    $dsn = "pgsql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_database']}";
    $pdo = new PDO($dsn, $c['db_username'], $c['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    if (isset($log)) {
        $log->error("Database connection failed: " . $e->getMessage());
    }
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection error"]);
    exit;
}

// 6. Routing Logic
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header("Content-Type: application/json");

// Clean path: removes '/api' if present
$path = str_replace('/api', '', $path);
if ($path === '' || $path === '/') {
    $path = '/index';
}

switch ($path) {
    case '/availability':
        $domain = $_GET['domain'] ?? null;
        if (!$domain) {
            echo json_encode(["status" => "error", "message" => "Missing domain parameter"]);
            break;
        }
        $stmt = $pdo->prepare("SELECT name FROM domain WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => $domain]);
        $exists = $stmt->fetch();
        
        echo json_encode([
            "status" => "success", 
            "domain" => $domain,
            "available" => $exists ? false : true
        ]);
        break;

    case '/registrars':
        try {
            $stmt = $pdo->query("SELECT name, url, abuse_email, iana_id FROM registrar");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "Query failed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint $path not found"]);
        break;
}