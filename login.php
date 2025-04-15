<?php
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
    $_SESSION['userId'] = $user['id'];

    echo '<script> document.addEventListener("DOMContentLoaded", async function(){
            let user = '. json_encode($user) .';
            let password = "'. htmlspecialchars($password) . '";
            
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
                        
            window.location="web_chat.php"
        }) 
        </script>';
}

session_start();
handleLogin();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register</title>
    <link rel="stylesheet" href="style.css"/>
    <script src="message_encryption.js"></script>
    <script src="key_wrapper.js"></script>
</head>
<body>
<main>
    <form id="login-form" method="post">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" placeholder="Username" required minlength="3" maxlength="16"/>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" placeholder="Password" minlength="8" required/>
        <span id="error-message" class="error-message"></span>
        <button id="login-button" type="submit" name="login">Log in</button>
    </form>

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