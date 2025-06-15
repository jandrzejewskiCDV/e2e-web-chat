<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register</title>
    <link rel="stylesheet" href="style.css"/>
    <script src="message_encryption.js"></script>
    <script src="key_wrapper.js"></script>
	
<?php
function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function handleLogin(): void
{
    if (!isset($_POST['login'])) {
        $_SESSION['login-error-message'] = '';
        return;
    }

    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $_SESSION['login-error-message'] = 'Username cannot be empty';
        return;
    }

    if (empty($password)) {
        $_SESSION['login-error-message'] = 'Password cannot be empty';
        return;
    }

    $conn = mysqli_connect("localhost", "cdv", "cdv", "cdv");
    if ($conn->connect_error) {
        $_SESSION['login-error-message'] = 'Database error: ' . $conn->connect_error;
        $conn->close();
        return;
    }

    $sql = "select id, username, password, public_key, encrypted_private_key, initialization_vector, salt from users where username=?";
    $user = $conn->execute_query($sql, [$username])->fetch_assoc();
    $conn->close();

    if (!password_verify($password, $user['password'])) {
        $_SESSION['login-error-message'] = 'Invalid username or password';
        return;
    }

    unset($user['password']);
    $_SESSION['login-error-message'] = '';

    $uuid = generateUuidV4();
    $sessionData = array();
    $sessionData['userId'] = $user['id'];

    $_SESSION[$uuid] = $sessionData;

    echo '<script> document.addEventListener("DOMContentLoaded", async function(){
            let user = '. json_encode($user) .';
            let password = "'. htmlspecialchars($password) . '";
            let sessionId = "' . $uuid . '";
            
            let encryptedData = {
                wrappedKey: user.encrypted_private_key,
                salt: user.salt,
                iv: user.initialization_vector,
            }
            
            let privateKey = await unwrapKey(encryptedData, password);
            let exportedPrivateKey = await exportPrivateKey(privateKey);
            
            let userDto = {
                id: user.id,
                username: user.username,
            }
            
            sessionStorage.clear();
            sessionStorage.setItem("user", JSON.stringify(userDto));
            sessionStorage.setItem("privateKey", exportedPrivateKey);
            sessionStorage.setItem("sessionId", sessionId);
                        
            window.location="web_chat.php"
        }) 
        </script>';
}

session_start();
handleLogin();
?>
	
</head>
<body>
<main>
    <div class="start-form">
        <h1><u>Login</u></h1>
        <form class="login-form" id="login-form" method="post">
            <div class="input-box">
                <input id="username" type="text" name="username" placeholder="Username" required minlength="3" maxlength="16"/>
                <img src="bxs-user.svg" alt="User Icon">
            </div>
            <div class="input-box">
                <input id="password" type="password" name="password" placeholder="Password" minlength="8" required/>
                <img src="bxs-lock.svg" alt="Lock Icon">
            </div>
            <span id="error-message" class="error-message"></span>
            <button id="login-button" type="submit" name="login">Log in</button>
        </form>
    </div>
    <?php
    if (isset($_SESSION['login-error-message'])) {
        $errorMessage = $_SESSION['login-error-message'];
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const errorElement = document.getElementById("error-message");
            if (errorElement) {
                errorElement.textContent = "' . $errorMessage . '";
            }
        });
    </script>';
    }
    ?>
</main>
</body>
</html>