<?php
date_default_timezone_set('Europe/Warsaw');

function isDataInvalid($data): bool{
    $sessionId = $data['sessionId'] ?? '';
    $targetId = $data['targetId'] ?? '';
    $message = $data['message'] ?? '';
    $iv = $data['iv'] ?? '';

    return !isset($sessionId) || !isset($targetId) || !isset($message) || !isset($iv);
}

function isSessionInvalid($sessionId): bool{
    return !isset($_SESSION[$sessionId]);
}

session_start();

$data = json_decode(file_get_contents("php://input"), true);

if(isDataInvalid($data)) {
    echo "{\"failure\": \"invalid params\"}";
    error_log("invalid params");
    return;
}

$sessionId = $data['sessionId'] ?? '';
$targetId = $data['targetId'] ?? '';
$message = $data['message'] ?? '';
$iv = $data['iv'] ?? '';

if(isSessionInvalid($sessionId)){
    echo "{\"failure\": \"tab session invalid\"}";
    error_log("session invalid");
    return;
}

if($message === null){
	echo "{\"failure\": \"message cannot be null\"}";
    error_log("message cannot be null");
    return;
}

$message = trim($message);

if ($message === '') {
    echo "{\"failure\": \"message cannot be empty\"}";
    error_log("message cannot be empty");
    return;
}

if(strlen($message) > 2048) {
    echo "{\"failure\": \"message too long\"}";
    error_log("message too long");
    return;
}

$conn = mysqli_connect("localhost", "cdv", "cdv", "cdv");

if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$userId = $_SESSION[$sessionId]['userId'] ?? "";

if(empty($userId)){
    echo "{\"failure\": \"userid mismatch\"}";
    error_log("userid mismatch");
    return;
}

if($userId === $targetId){
    echo "{\"failure\": \"you cannot send a message to yourself\"}";
    error_log("userid mismatch");
    return;
}

$userOne = min($userId, $targetId);
$userTwo = max($userId, $targetId);

$getGroupChatId = "select id from one_on_one_chat_id where user_one_id=? and user_two_id=?";
$stmt = $conn->prepare($getGroupChatId);
$stmt->bind_param("ii", $userOne, $userTwo);
$stmt->execute();
$result = $stmt->get_result();
$chatId = $result->fetch_assoc()['id'];

$timestamp = date('Y-m-d H:i:s');

$insertMessage = "insert into one_on_one_messages(chat_id, message, sender, initialization_vector, timestamp) values (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertMessage);
$stmt->bind_param("isiss", $chatId, $message, $userId, $iv, $timestamp);
$stmt->execute();
$stmt->close();
$conn->close();

$response = [];
$response['success'] = true;
$response['message'] = $message;
$response['iv'] = $iv;
$response['timestamp'] = $timestamp;
$response['targetId'] = $targetId;
$response['senderId'] = $userId;

echo json_encode($response);