<?php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include "db_connect.php";

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

    return null;
}

function get_current_mode($conn) {
    $mode = "AT_HOME";

    $result = $conn->query("SELECT mode FROM system_state WHERE id = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (!empty($row["mode"])) {
            $normalized = normalize_mode($row["mode"]);
            if ($normalized !== null) {
                $mode = $normalized;
            }
        }
    }

    return $mode;
}

$detectionRaw = $_REQUEST["detection"] ?? null;
$requestedMode = $_REQUEST["mode"] ?? null;

if ($detectionRaw === null || $detectionRaw === "") {
    echo json_encode([
        "status" => "error",
        "message" => "detection parameter required"
    ]);
    $conn->close();
    exit;
}

$detection = (int)$detectionRaw;
if ($detection < 0) {
    $detection = 0;
}

$normalizedRequestedMode = normalize_mode($requestedMode);
$modeToInsert = $normalizedRequestedMode ?: get_current_mode($conn);

$stmt = $conn->prepare("INSERT INTO intruder_log (mode, detection) VALUES (?, ?)");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("si", $modeToInsert, $detection);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Data inserted successfully",
        "inserted_id" => $stmt->insert_id,
        "mode" => $modeToInsert,
        "detection" => $detection
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>