<?php
header("Content-Type: application/json");
include "db_connect.php";

$result = $conn->query("SELECT id, mode, updated_at FROM system_state WHERE id = 1 LIMIT 1");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "mode" => $row['mode'],
        "updated_at" => $row['updated_at']
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "mode" => "AT_HOME",
        "updated_at" => null
    ]);
}

$conn->close();
?>
