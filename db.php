<?php
// Configure these environment variables in your local/server environment.
// Do not commit real database credentials to a public repository.
$host = getenv('DRIVEMART_DB_HOST') ?: 'localhost';
$dbname = getenv('DRIVEMART_DB_NAME') ?: '';
$username = getenv('DRIVEMART_DB_USER') ?: '';
$password = getenv('DRIVEMART_DB_PASSWORD') ?: '';

if ($dbname === '' || $username === '') {
    http_response_code(500);
    die('Database configuration is missing. Configure DRIVEMART_DB_NAME, DRIVEMART_DB_USER, and DRIVEMART_DB_PASSWORD.');
}

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log('DriveMart database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');
?>
