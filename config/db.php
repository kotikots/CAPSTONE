<?php
/**
 * SECURITY MITIGATION: Security Misconfiguration
 * Hide raw PHP errors from the public to prevent path and code leakage.
 * Route all errors to a custom user-friendly page.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function showCustomErrorPage() {
    // Return JSON if it's an API call
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'], 'api_') !== false || strpos($_SERVER['REQUEST_URI'], 'get_') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Oops! May mali sa byahe natin ngayon.']);
        exit;
    }
    
    // Otherwise show the clean HTML page
    http_response_code(500);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Error | PARE</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    </head>
    <body style="font-family: \'Inter\', sans-serif; background: linear-gradient(135deg, #020617 0%, #0f172a 40%, #1e3a8a 100%); color: white; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0;">
        <div style="background: rgba(255,255,255,0.03); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; text-align: center; max-width: 450px; box-shadow: 0 10px 30px rgba(14, 165, 233, 0.2);">
            <div style="font-size: 72px; margin-bottom: 20px;">🚧</div>
            <h1 style="font-size: 24px; font-weight: 900; margin-bottom: 12px; line-height: 1.2;">Oops! May mali sa byahe natin ngayon</h1>
            <p style="color: rgba(255,255,255,0.6); font-size: 14px; margin-bottom: 30px;">The system encountered a technical difficulty. Our engineers have been notified and are fixing the route.</p>
            <a href="javascript:history.back()" style="display: inline-block; background: #0ea5e9; color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 900; font-size: 14px; transition: transform 0.1s ease;" onmousedown="this.style.transform=\'scale(0.95)\'" onmouseup="this.style.transform=\'scale(1)\'">Bumalik (Go Back)</a>
        </div>
    </body>
    </html>';
    exit;
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    showCustomErrorPage();
});

set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    showCustomErrorPage();
});

date_default_timezone_set('Asia/Manila');
$host = 'localhost';
$db   = 'pare'; // Assuming pare_db from the other project
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
