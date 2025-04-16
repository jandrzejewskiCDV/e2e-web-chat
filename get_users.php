<?php
session_start();

$data = json_decode(file_get_contents("php://input"), true);
if(empty($data)) {
    echo "{\"failure\": \"empty json\"}";
    return;
}

$sessionId = $data['sessionId'] ?? '';
if(empty($sessionId) || !isset($_SESSION[$sessionId])) {
    echo "{\"failure\": \"invalid sessionId\"}";
    return;
}

$userId = $_SESSION[$sessionId]['userId'] ?? '';
if(empty($userId)) {
    echo "{\"failure\": \"empty user\"}";
    return;
}

$conn = new mysqli("localhost", "cdv", "cdv", "cdv");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$users = $conn
    ->execute_query("select id,username from users where id != ?", [$userId])
    ->fetch_all(MYSQLI_ASSOC);

echo json_encode($users);
