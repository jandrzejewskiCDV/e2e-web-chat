<?php
session_start()
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Web Chat</title>
    <link rel="stylesheet" href="web_chat_style.css"/>
    <script src="key_wrapper.js"></script>
    <script src="message_encryption.js"></script>
</head>
<body>
<div class="container">
    <div class="users-list">
        <h2>Users</h2>
        <h7 id="logged-in-as"></h7>
        <?php
        $userId = $_SESSION['userId'];

        $conn = mysqli_connect("localhost", "cdv", "cdv", "cdv");
        if (!$conn)
            die("Connection failed: " . mysqli_connect_error());
        $sql = "SELECT id, username FROM users";
        $result = $conn->execute_query($sql);
        $conn->close();

        foreach ($result as $user) {
            if ($user['id'] == $userId)
                continue;

            echo "<div class='user' onclick='loadChat({$user['id']}, \"{$user['username']}\")'>
                         <img src='account-icon.png' alt='User Avatar'> 
                         <span>{$user['username']}</span> 
                  </div>";
        }
        ?>
    </div>
    <div class="chat-area">
        <div class="chat-header">
            <h2 id="chat-name">Pick a user to chat with!</h2>
        </div>
        <div id="chat-messages" class="chat-messages">

        </div>
        <div class="chat-input">
            <textarea id="chat-message" placeholder="Type your message here..."></textarea>
            <button id="buttonsendmessage" onclick="sendMessage()" disabled>Send</button>
        </div>
    </div>

    <script>
        let user = JSON.parse(sessionStorage.getItem('user'));
        document.getElementById('logged-in-as').innerText = 'Logged in as ' + user.username + '.';
        document.getElementById('buttonsendmessage').disabled = true;

        const chatMessageInput = document.getElementById('chat-message');

        let currentTarget = null;
        let currentTargetName = null;
        let targetPublicKey = null;

        async function sendMessage(){
            let message = chatMessageInput.value.trim();
            chatMessageInput.value = "";

            let storedPrivateKey = sessionStorage.getItem("privateKey");
            let privateKey = await importPrivateKey(storedPrivateKey);
            let secret = await deriveSecretKey(privateKey, targetPublicKey);

            let iv = generateIV();
            let encryptedMessage = await encryptMessage(secret, iv, message);

            await postMessage({
                userId: user.id,
                targetId: currentTarget,
                message: arrayBufferToBase64(encryptedMessage),
                iv: arrayBufferToBase64(iv),
            });

            await loadChat(currentTarget, currentTargetName);
        }

        async function postMessage(data){
            const response = await fetch('insert_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if(!response.ok){
                console.error("Could not send message");
            }
        }

        async function loadChat(targetId, targetUsername) {
            document.getElementById('buttonsendmessage').disabled = true;
            document.getElementById('chat-name').innerText = "Chat with " + targetUsername;
            document.getElementById('chat-messages').innerHTML = "";

            currentTarget = targetId;

            currentTargetName = targetUsername;
            const data = await getMessages(targetId);

            targetPublicKey = await importPublicKey(data.publicKey);
            let storedPrivateKey = sessionStorage.getItem("privateKey");

            let privateKey = await importPrivateKey(storedPrivateKey);

            let secret = await deriveSecretKey(privateKey, targetPublicKey);

            const element = document.getElementById('chat-messages');
            for (let obj of data.messages) {
                let message = base64ToArrayBuffer(obj.message);
                let iv = base64ToArrayBuffer(obj.initialization_vector);
                let sender = obj.sender;
                let timestamp = obj.timestamp;

                let decrypted = await decryptMessage(secret, iv, message);

                const span = document.createElement('span');
                span.style.wordWrap = 'break-word';
                //span.style.whiteSpace = 'normal';
                span.innerText = " (" + timestamp + ") " + decrypted.trim() + " (" + (sender === user.id ? "You" : targetUsername) + ")";
                element.appendChild(span);
                element.appendChild(document.createElement("br"));
            }

            document.getElementById('buttonsendmessage').disabled = false;
        }

        async function getMessages(targetId){
            try{
                const body = await fetch(`get_messages.php?user=${user.id}&target=${targetId}`);
                return await body.json();
            }catch (e){
                console.error(e)
                return "{}";
            }
        }
    </script>
</div>
</body>
</html>