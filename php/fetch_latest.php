<?php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include "db_connect.php";

$sql = "SELECT serial_no, time, mode, detection 
        FROM intruder_log 
        ORDER BY serial_no DESC 
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    echo json_encode([
        "serial_no" => isset($row["serial_no"]) ? (int)$row["serial_no"] : 0,
        "time"      => $row["time"] ?? null,
        "mode"      => $row["mode"] ?? "AT_HOME",
        "detection" => isset($row["detection"]) ? (int)$row["detection"] : 0
    ]);
} else {
    echo json_encode([
        "serial_no" => 0,
        "time"      => null,
        "mode"      => "AT_HOME",
        "detection" => 0
    ]);
}

$conn->close();
?>