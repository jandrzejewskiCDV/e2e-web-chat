<?php
session_start();

$userIdInSession = $_SESSION['userId'];

$data = json_decode(file_get_contents("php://input"), true);

$userId = $data['userId'] ?? '';
$targetId = $data['targetId'] ?? '';
$message = $data['message'] ?? '';
$iv = $data['iv'] ?? '';

error_log("got messages!");
error_log($userId);
error_log($targetId);
error_log($message);
error_log($iv);
error_log("end of messages");

if(!isset($userId) || !isset($targetId) || !isset($message) || !isset($iv)) {
    echo "{\"failure\": \"invalid params\"}";
    return;
}

if($userIdInSession !== $userId){
    echo "{\"failure\": \"userid mismatch\"}";
    return;
}

$conn = mysqli_connect("localhost", "cdv", "cdv", "cdv");

if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$userOne = min($userId, $targetId);
$userTwo = max($userId, $targetId);

$getGroupChatId = "select id from one_on_one_chat_id where user_one_id=? and user_two_id=?";
$stmt = $conn->prepare($getGroupChatId);
$stmt->bind_param("ii", $userOne, $userTwo);
$stmt->execute();
$result = $stmt->get_result();
$chatId = $result->fetch_assoc()['id'];

$insertMessage = "insert into one_on_one_messages(chat_id, message, sender, initialization_vector) values (?, ?, ?, ?)";
$stmt = $conn->prepare($insertMessage);
$stmt->bind_param("isis", $chatId, $message, $userId, $iv);
$stmt->execute();
$stmt->close();
$conn->close();

error_log("success");
echo "{\"success\": \"message sent\"}";