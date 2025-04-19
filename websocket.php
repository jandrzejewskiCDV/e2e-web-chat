<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body>
<h1>WebSocket Test</h1>
<input id="message" placeholder="Type a message...">
<button onclick="sendMessage()">Send</button>
<ul id="log"></ul>


<script>
    function getCookie(cname) {
        let name = cname + "=";
        let decodedCookie = decodeURIComponent(document.cookie);
        let ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }

    const sessionId = getCookie('PHPSESSID');
    console.log(sessionId)
    const socket = new WebSocket("wss://jacek.website:8443");

    socket.onopen = () => {
        log("Connected to WebSocket server.");
    };

    socket.onmessage = (event) => {
        log("Received: " + event.data);
    };

    function sendMessage() {
        const msg = document.getElementById("message").value;
        socket.send(msg);
        log("Sent: " + msg);
    }

    function log(message) {
        const li = document.createElement("li");
        li.textContent = message;
        document.getElementById("log").appendChild(li);
    }
</script>
</body>
</html>
