<?php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include "db_connect.php";

$allowedDevices = [
    "handheld_esp32",
    "python_processor",
    "esp32_cam"
];

$device = trim((string)($_REQUEST["device"] ?? ""));

if (!in_array($device, $allowedDevices, true)) {
    echo json_encode([
        "status" => "error",
        "message" => "Valid device required"
    ]);
    $conn->close();
    exit;
}


$checkStmt = $conn->prepare("SELECT device_name FROM device_heartbeat WHERE device_name = ? LIMIT 1");

if (!$checkStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$checkStmt->bind_param("s", $device);

if (!$checkStmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $checkStmt->error
    ]);
    $checkStmt->close();
    $conn->close();
    exit;
}

$checkResult = $checkStmt->get_result();

if ($checkResult && $checkResult->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE device_heartbeat SET last_seen = CURRENT_TIMESTAMP WHERE device_name = ?");
} else {
    $stmt = $conn->prepare("INSERT INTO device_heartbeat (device_name, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
}

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    $checkStmt->close();
    $conn->close();
    exit;
}

$stmt->bind_param("s", $device);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Heartbeat updated",
        "device" => $device
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
}

$checkStmt->close();
$stmt->close();
$conn->close();
?>