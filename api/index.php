<?php
// 1. Define the correct path to the autoloader (go up one level from /api to root)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

// 2. Check if the file actually exists
if (!file_exists($autoloadPath)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        "error" => "Autoload not found.",
        "debug_path" => $autoloadPath,
        "suggestion" => "Check if composer.json is in the root and Vercel build succeeded."
    ]);
    exit;
}

// 3. Include the autoloader
require_once $autoloadPath;
// 2. Load Config
// Assuming config.php is in the root directory
$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    $c = require_once $configPath;
} else {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["error" => "Config file not found at root."]);
    exit;
}

// 3. Initialize Logger
// setupLogger must be defined in helpers.php (which is loaded via composer autoload)
$log = setupLogger('php://stderr', 'API');

// 4. Connect to Supabase (Postgres)
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

// 5. Routing Logic
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header("Content-Type: application/json");

// Clean the path
$path = str_replace('/api', '', $path);
if ($path === '' || $path === '/') {
    $path = '/index'; // Default route
}

switch ($path) {
    case '/availability':
        $domain = $_GET['domain'] ?? null;
        if (!$domain) {
            echo json_encode(["status" => "error", "message" => "Missing domain parameter"]);
            break;
        }
        // Example Postgres Query
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
            echo json_encode(["error" => "Query failed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint $path not found"]);
        break;
}