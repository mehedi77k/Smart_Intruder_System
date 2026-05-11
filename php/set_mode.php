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

$rawMode = $_REQUEST["mode"] ?? "";
$source  = trim((string)($_REQUEST["source"] ?? "unknown"));

$mode = normalize_mode($rawMode);

if ($mode === null) {
    echo json_encode([
        "status" => "error",
        "message" => "Valid mode required: AT_HOME or NOT_AT_HOME"
    ]);
    $conn->close();
    exit;
}


$checkSql = "SELECT id FROM system_state WHERE id = 1 LIMIT 1";
$checkResult = $conn->query($checkSql);

if (!$checkResult) {
    echo json_encode([
        "status" => "error",
        "message" => "Check query failed: " . $conn->error
    ]);
    $conn->close();
    exit;
}

if ($checkResult->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE system_state SET mode = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
} else {
    $stmt = $conn->prepare("INSERT INTO system_state (id, mode, updated_at) VALUES (1, ?, CURRENT_TIMESTAMP)");
}

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("s", $mode);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

$result = $conn->query("SELECT id, mode, updated_at FROM system_state WHERE id = 1 LIMIT 1");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "message" => "Mode updated successfully",
        "current_mode" => $row["mode"],
        "mode_updated_at" => $row["updated_at"],
        "source" => $source
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "Mode updated, but fetch failed",
        "current_mode" => $mode,
        "mode_updated_at" => null,
        "source" => $source
    ]);
}

$conn->close();
?>