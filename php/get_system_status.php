<?php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include "db_connect.php";

$offlineAfterSeconds = 30;

function seconds_since($datetimeString) {
    if (!$datetimeString) {
        return PHP_INT_MAX;
    }

    $time = strtotime($datetimeString);
    if ($time === false) {
        return PHP_INT_MAX;
    }

    return time() - $time;
}

function normalize_mode($mode) {
    $mode = strtoupper(trim((string)$mode));

    if ($mode === "AT_HOME" || $mode === "AT HOME" || $mode === "HOME") {
        return "AT_HOME";
    }

    if (
        $mode === "NOT_AT_HOME" ||
        $mode === "NOT AT HOME" ||
        $mode === "AWAY" ||
        $mode === "NOTHOME"
    ) {
        return "NOT_AT_HOME";
    }

    return "AT_HOME";
}


$currentMode = "AT_HOME";
$modeUpdatedAt = null;

$modeSql = "SELECT mode, updated_at FROM system_state WHERE id = 1 LIMIT 1";
$modeResult = $conn->query($modeSql);

if ($modeResult && $modeResult->num_rows > 0) {
    $modeRow = $modeResult->fetch_assoc();
    $currentMode = normalize_mode($modeRow["mode"] ?? "AT_HOME");
    $modeUpdatedAt = $modeRow["updated_at"] ?? null;
}


$latest = [
    "serial_no" => 0,
    "time" => null,
    "mode" => $currentMode,
    "detection" => 0
];

$latestSql = "SELECT serial_no, time, mode, detection FROM intruder_log ORDER BY serial_no DESC LIMIT 1";
$latestResult = $conn->query($latestSql);

if ($latestResult && $latestResult->num_rows > 0) {
    $latestRow = $latestResult->fetch_assoc();

    $latest["serial_no"] = isset($latestRow["serial_no"]) ? (int)$latestRow["serial_no"] : 0;
    $latest["time"] = $latestRow["time"] ?? null;
    $latest["mode"] = normalize_mode($latestRow["mode"] ?? $currentMode);
    $latest["detection"] = isset($latestRow["detection"]) ? (int)$latestRow["detection"] : 0;
}


$dataAgeSeconds = null;
$dataRecent = false;

if (!empty($latest["time"])) {
    $dataAgeSeconds = seconds_since($latest["time"]);
    $dataRecent = ($dataAgeSeconds <= $offlineAfterSeconds);
}


$heartbeats = [];

$hbSql = "SELECT device_name, last_seen FROM device_heartbeat";
$hbResult = $conn->query($hbSql);

if ($hbResult) {
    while ($row = $hbResult->fetch_assoc()) {
        $age = seconds_since($row["last_seen"]);

        $heartbeats[$row["device_name"]] = [
            "last_seen" => $row["last_seen"],
            "recent" => ($age <= $offlineAfterSeconds),
            "age_seconds" => ($age === PHP_INT_MAX ? null : $age)
        ];
    }
}

$handheldRecent = $heartbeats["handheld_esp32"]["recent"] ?? false;
$pythonRecent   = $heartbeats["python_processor"]["recent"] ?? false;
$esp32CamRecent = $heartbeats["esp32_cam"]["recent"] ?? false;


$systemOnline = $handheldRecent && $pythonRecent && $esp32CamRecent;


$reasons = [];

if (!$handheldRecent) {
    $reasons[] = "Handheld ESP32 heartbeat missing";
}
if (!$pythonRecent) {
    $reasons[] = "Python processor heartbeat missing";
}
if (!$esp32CamRecent) {
    $reasons[] = "ESP32-CAM heartbeat missing";
}

if (empty($reasons)) {
    $reasons[] = "All required devices are connected";
}

$warnings = [];
if (!$dataRecent) {
    $warnings[] = "No recent intruder_log data within {$offlineAfterSeconds} seconds";
}


echo json_encode([
    "status" => "success",
    "current_mode" => $currentMode,
    "mode_updated_at" => $modeUpdatedAt,
    "system_online" => $systemOnline,
    "offline_after_seconds" => $offlineAfterSeconds,
    "latest" => $latest,
    "latest_detection" => $latest["detection"],
    "latest_log_time" => $latest["time"],
    "latest_log_mode" => $latest["mode"],
    "data_recent" => $dataRecent,
    "data_age_seconds" => $dataAgeSeconds,
    "heartbeats" => $heartbeats,
    "devices" => [
        "handheld_esp32" => [
            "online" => $handheldRecent,
            "last_seen" => $heartbeats["handheld_esp32"]["last_seen"] ?? null,
            "age_seconds" => $heartbeats["handheld_esp32"]["age_seconds"] ?? null
        ],
        "python_processor" => [
            "online" => $pythonRecent,
            "last_seen" => $heartbeats["python_processor"]["last_seen"] ?? null,
            "age_seconds" => $heartbeats["python_processor"]["age_seconds"] ?? null
        ],
        "esp32_cam" => [
            "online" => $esp32CamRecent,
            "last_seen" => $heartbeats["esp32_cam"]["last_seen"] ?? null,
            "age_seconds" => $heartbeats["esp32_cam"]["age_seconds"] ?? null
        ]
    ],
    "reasons" => $reasons,
    "warnings" => $warnings
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$conn->close();
?>