<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "smart_intruder";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    header("Content-Type: application/json");
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");
?>
