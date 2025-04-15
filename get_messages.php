<?php

$user = $_GET['user'] ?? "";
$target = $_GET['target'] ?? "";

if(!isset($user) || !isset($target)){
    echo "{}";
    return;
}

$conn = mysqli_connect("localhost", "cdv", "cdv", "cdv");
if($conn -> connect_error){
    die("Connection failed: " . $conn->connect_error);
}

$userOne = min($user, $target);
$userTwo = max($user, $target);

$selectGroupIdQuery = "select id from one_on_one_chat_id where user_one_id=? and user_two_id=?";
$result = $conn->execute_query($selectGroupIdQuery, [$userOne, $userTwo]);

if($result->num_rows == 0){
    $insertGroupChat = "insert into one_on_one_chat_id (user_one_id, user_two_id) values (?, ?)";
    $stmt = $conn->prepare($insertGroupChat);
    $stmt->bind_param("ii", $userOne, $userTwo);
    $stmt->execute();

    $result = $conn->execute_query($selectGroupIdQuery, [$userOne, $userTwo]);
}

$chatId = $result->fetch_assoc()["id"];

$selectMessages =
    "select message, sender, initialization_vector, timestamp from one_on_one_messages where chat_id=?";
$result = $conn->execute_query($selectMessages, [$chatId])->fetch_all(MYSQLI_ASSOC);

$selectPublicKey = "select public_key from users where id=?";
$publicKey = $conn->execute_query($selectPublicKey, [$target])->fetch_assoc()['public_key'];

$conn->close();

$data = array();
$data['publicKey'] = $publicKey;
$data['messages'] = $result;

echo json_encode($data, JSON_UNESCAPED_UNICODE);
