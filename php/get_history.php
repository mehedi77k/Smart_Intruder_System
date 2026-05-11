<?php
header("Content-Type: application/json");
include "db_connect.php";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

if ($page < 1) {
    $page = 1;
}

if ($limit < 1) {
    $limit = 100;
}

if ($limit > 100) {
    $limit = 100;
}

$offset = ($page - 1) * $limit;

$countResult = $conn->query("SELECT COUNT(*) AS total FROM intruder_log");
$totalRows = 0;

if ($countResult && $countResult->num_rows > 0) {
    $countRow = $countResult->fetch_assoc();
    $totalRows = (int)$countRow['total'];
}

$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmt = $conn->prepare("SELECT serial_no, time, mode, detection FROM intruder_log ORDER BY serial_no DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "page" => $page,
    "limit" => $limit,
    "total_rows" => $totalRows,
    "total_pages" => $totalPages,
    "rows" => $rows
]);

$conn->close();
?>
