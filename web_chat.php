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

        <script>
            let sessionId = sessionStorage.getItem('sessionId');
            let element = document.getElementsByClassName("users-list")[0];

            fetch('get_users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sessionId: sessionId,
                })
            }).then((response) =>{
                return response.json();
            }).then((data) =>{
                if(data.failure){
                    window.location = 'login.php';
                    return;
                }

                data.forEach((item) =>{
                    let div = document.createElement('div');
                    div.classList.add('user');
                    div.addEventListener('click', function(){
                        loadChat(item.id, item.username)
                    })

                    let img = document.createElement('img');
                    img.src = 'account-icon.png';
                    img.alt = 'User Avatar';
                    div.appendChild(img);

                    let span = document.createElement('span');
                    span.innerText = item.username;
                    div.appendChild(span);

                    element.appendChild(div);
                })
            });
        </script>
    </div>
    <div class="chat-area">
        <div class="chat-header">
            <h2 id="chat-name">Pick a user to chat with!</h2>
        </div>
        <div id="chat-messages" class="chat-messages">

        </div>
        <div class="chat-input">
            <textarea id="chat-message" placeholder="Type your message here..."></textarea>
			<button id="jump-to-bottom" style="display: none;" onclick="scrollToBottom()">⬇</button>
			<button id="buttonsendmessage" onclick="sendMessage()" disabled>Send</button>
        </div>
        <small id="byte-counter">0 / 2048 bytes</small>
    </div>

    <script>
        const sendButton = document.getElementById('buttonsendmessage');
        SetSendButtonActive(false)

        let user = JSON.parse(sessionStorage.getItem('user'));
        document.getElementById('logged-in-as').innerText = 'Logged in as ' + user.username + '.';

        const chatMessageInput = document.getElementById('chat-message');

        let currentTarget = null;
        let currentTargetName = null;
        let targetPublicKey = null;
        let chatLoaded = false

        function SetSendButtonActive(state){
            if(state && chatLoaded && socket != null && socket.readyState === WebSocket.OPEN){
                sendButton.disabled = false;
                return;
            }

            sendButton.disabled = true;
        }

        async function sendMessage(){
            if(socket == null || socket.readyState !== WebSocket.OPEN){
                console.error("Web socket not available, try again later");
                return;
            }

            let message = chatMessageInput.value.trim();
			
			if(!message)
				return;
			
            chatMessageInput.value = "";
            byteCounter.textContent = `0 / 2048 bytes`;

            let storedPrivateKey = sessionStorage.getItem("privateKey");
            let privateKey = await importPrivateKey(storedPrivateKey);
            let secret = await deriveSecretKey(privateKey, targetPublicKey);

            let iv = generateIV();
            let encryptedMessage = await encryptMessage(secret, iv, message);

            let data = {
                sessionId: sessionStorage.getItem('sessionId'),
                targetId: currentTarget,
                message: arrayBufferToBase64(encryptedMessage),
                iv: arrayBufferToBase64(iv),
            }

            await postMessage(data);
        }

        async function postMessage(data){
            const response = await fetch('insert_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const json = await response.json();
            console.log(json);

            if(!response.ok || json.failure){
                console.error("Could not send message");
                return;
            }

            sendSocketMessage(JSON.stringify(json));
        }

        function scrollToBottom() {
			const chatMessages = document.getElementById('chat-messages');
			chatMessages.scrollTop = chatMessages.scrollHeight;
			toggleJumpButton();
		}		

        function toggleJumpButton() {
            const el = document.getElementById('chat-messages');
            const button = document.getElementById('jump-to-bottom');

            if (el.scrollHeight - el.scrollTop - el.clientHeight > 100) {
                button.style.display = 'block';
            } else {
                button.style.display = 'none';
            }
        }

        document.getElementById('chat-messages').addEventListener('scroll', toggleJumpButton);

        async function loadChat(targetId, targetUsername) {
            if(targetId == null || targetUsername == null)
                return;

            chatLoaded = false
            SetSendButtonActive(false)
            document.getElementById('chat-name').innerText = "Chat with " + targetUsername;

            currentTarget = targetId;
            currentTargetName = targetUsername;

            const data = await getMessages(targetId);
            targetPublicKey = await importPublicKey(data.publicKey);

            let storedPrivateKey = sessionStorage.getItem("privateKey");
            let privateKey = await importPrivateKey(storedPrivateKey);
            let secret = await deriveSecretKey(privateKey, targetPublicKey);

            document.getElementById('chat-messages').innerHTML = ""; 

            const element = document.getElementById('chat-messages');
            for (let obj of data.messages) {
                let message = base64ToArrayBuffer(obj.message);
                let iv = base64ToArrayBuffer(obj.initialization_vector);
                let sender = obj.sender;
                let timestamp = obj.timestamp;

                let decrypted = await decryptMessage(secret, iv, message);

                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message'); 

                if (sender === user.id) {
                    messageDiv.classList.add('self'); 
                } else {
                    messageDiv.classList.add('other'); 
                }

                const senderSpan = document.createElement('span');
                senderSpan.classList.add('sender');
                senderSpan.innerText = sender === user.id ? "You" : targetUsername;

                const textSpan = document.createElement('span');
                textSpan.classList.add('text');
                textSpan.innerText = decrypted;

                const timeSpan = document.createElement('span');
                timeSpan.classList.add('timestamp');
                timeSpan.innerText = timestamp;

                messageDiv.appendChild(senderSpan);
                messageDiv.appendChild(textSpan);
                messageDiv.appendChild(timeSpan);

                element.appendChild(messageDiv);
                element.appendChild(document.createElement("br"));
            }
            
			requestAnimationFrame(() => {
				scrollToBottom();
				toggleJumpButton();
			});

            chatLoaded = true
            SetSendButtonActive(true)
        }

        async function addChatMessage(json){
            if(currentTarget == null || currentTargetName == null)
                return;

            let obj = JSON.parse(json);

            if( obj.senderId !== user.id && obj.senderId !== currentTarget)
                return;

            let storedPrivateKey = sessionStorage.getItem("privateKey");
            let privateKey = await importPrivateKey(storedPrivateKey);
            let secret = await deriveSecretKey(privateKey, targetPublicKey);

            let message = base64ToArrayBuffer(obj.message);
            let iv = base64ToArrayBuffer(obj.iv);
            let sender = obj.senderId;
            let timestamp = obj.timestamp;

            let decrypted = await decryptMessage(secret, iv, message);

            const element = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');

            if (sender === user.id) {
                messageDiv.classList.add('self');
            } else {
                messageDiv.classList.add('other');
            }

            const senderSpan = document.createElement('span');
            senderSpan.classList.add('sender');
            senderSpan.innerText = sender === user.id ? "You" : currentTargetName;

            const textSpan = document.createElement('span');
            textSpan.classList.add('text');
            textSpan.innerText = decrypted;

            const timeSpan = document.createElement('span');
            timeSpan.classList.add('timestamp');
            timeSpan.innerText = timestamp;

            messageDiv.appendChild(senderSpan);
            messageDiv.appendChild(textSpan);
            messageDiv.appendChild(timeSpan);

            element.appendChild(messageDiv);
            element.appendChild(document.createElement("br"));

            const chatMessages = document.getElementById('chat-messages');

            const jumpToBottom = document.getElementById('jump-to-bottom');
			
			if(jumpToBottom.style.display === 'none'){
				requestAnimationFrame(() => {
					scrollToBottom();
				});
			}
        }

        async function getMessages(targetId){
            try{
                const body = await fetch('get_messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({sessionId: sessionStorage.getItem('sessionId'), targetId: targetId})
                })

                return await body.json();
            }catch (e){
                console.error(e)
                return {};
            }
        }
        document.getElementById('chat-message').addEventListener('keydown', function(event) {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();

            const messageText = chatMessageInput.value.trim();
            if (messageText.length > 0) {
                sendMessage();
            }
        }
    });
    </script>

    <script>
        let retry = 0;
        let maxRetries = 2;
        let socket = null;

        function connectSocket(){
            socket = new WebSocket('https://jacek.website:8443', [
                'Authorization', sessionStorage.getItem('sessionId')
            ]);
            socket.addEventListener('open', onOpen);
            socket.addEventListener('close', onClose);
            socket.addEventListener('error', onError);
            socket.addEventListener('message', onMessage);
        }

        async function onOpen(event){
            SetSendButtonActive(true)
            console.log("Web socket connected!")
            retry = 0;

            await loadChat(currentTarget, currentTargetName);
        }

        function onClose(event){
            console.log("WebSocket closed with code:", event.code, "and reason:", event.reason);

            SetSendButtonActive(false)
            socket = null;

            if(retry === maxRetries){
                console.error("websocket connection failed!");
                window.location.href = 'login.php';
                return;
            }

            retry++;
            console.log("retry count: " + retry);
            connectSocket();
        }

        function onError(event){
            console.log("Error", event);
            console.log(event.code)
        }

        async function onMessage(event){
            if(user == null || currentTarget == null || currentTargetName == null || !chatLoaded)
                return;

            await addChatMessage(event.data);
        }

        function sendSocketMessage(json){
            if(socket == null || socket.readyState !== WebSocket.OPEN)
                return;

            socket.send(json);
        }

        connectSocket();
    </script>

    <script>
        const textarea = document.getElementById('chat-message');
        const byteCounter = document.getElementById('byte-counter');
        const encoder = new TextEncoder();

        textarea.addEventListener('input', () => {
            let value = textarea.value;
            let encoded = encoder.encode(value);

            if (encoded.length > 2048) {
                let i = value.length;
                while (i > 0 && encoder.encode(value.slice(0, i)).length > 2048) {
                    i--;
                }
                textarea.value = value.slice(0, i);
                encoded = encoder.encode(textarea.value);
            }

            byteCounter.textContent = `${encoded.length} / 2048 bytes`;
        });
    </script>
</div>
</body>
</html>