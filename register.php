<?php
function handleRegistration(): void
{
    if(!isset($_POST['register'])) {
        $_SESSION["register-error-message"] = "";
        return;
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $repeatedPassword = $_POST['repeat-password'] ?? '';
    $publicKey = $_POST['public-key'] ?? '';
    $encryptedPrivateKey = $_POST['encrypted-private-key'] ?? '';
    $initializationVector = $_POST['initialization-vector'] ?? '';
    $salt = $_POST['salt'] ?? '';

    if (empty($username)) {
        $_SESSION['register-error-message'] = 'Username cannot be empty';
        return;
    }

    if (empty($password)) {
        $_SESSION['register-error-message'] = 'Password cannot be empty';
        return;
    }

    if ($password !== $repeatedPassword) {
        $_SESSION['register-error-message'] = 'Passwords do not match';
        return;
    }

    if (empty($encryptedPrivateKey) || empty($publicKey) || empty($initializationVector) || empty($salt)) {
        $_SESSION['register-error-message'] = 'Invalid encryption key';
        return;
    }

    $conn = new mysqli("localhost", "cdv", "cdv", "cdv");
    if ($conn->connect_errno) {
        $_SESSION['register-error-message'] = 'Database error: ' . $conn->connect_error;
        $conn->close();
        return;
    }

    $sql = "select id from users where username=?";
    $result = $conn->execute_query($sql, [$username]);

    if($result->num_rows != 0){
        $result->close();
        $conn->close();
        $_SESSION['register-error-message'] = 'Username already exists';
        return;
    }

    $_SESSION['register-error-message'] = '';

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "insert into users (username, password, public_key, encrypted_private_key, initialization_vector, salt) values (?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $username, $hashed_password, $publicKey, $encryptedPrivateKey, $initializationVector, $salt);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: login.php");
}

session_start();
handleRegistration();
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
    <div class="start-form">
        <h1><u>Register</u></h1>
        <form class="login-form" id="registration-form" method="post">
            <div class="input-box">
                <input id="username" type="text" name="username" placeholder="Username" required minlength="3" maxlength="16"/>
                <img src="bxs-user.svg" alt="User Icon">
            </div>
            <div class="input-box">
                <input id="password" type="password" name="password" placeholder="Password" minlength="8" required/>
                <img src="bxs-lock.svg" alt="User Icon">
            </div>
            <div class="input-box">
                <input id="repeat-password" type="password" name="repeat-password" minlength="8" placeholder="Repeat Password"
                       required/>
                <img src="bxs-lock.svg" alt="User Icon">
            </div>
            <input type="hidden" id="public-key" name="public-key">
            <input type="hidden" id="encrypted-private-key" name="encrypted-private-key">
            <input type="hidden" id="initialization-vector" name="initialization-vector">
            <input type="hidden" id="salt" name="salt" hidden>
            <span id="error-message" class="error-message"></span>
            <button id="register-button" type="submit" name="register">Register</button>
        </form>

        <?php
        if (isset($_SESSION['register-error-message'])) {
            $errorMessage = $_SESSION['register-error-message'];
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

        <script>
            const passwordField = document.getElementById('password');
            const repeatPasswordField = document.getElementById('repeat-password');
            const errorMessage = document.getElementById('error-message');
            const submitButton = document.getElementById('register-button');
            const registrationForm = document.getElementById('registration-form');
            const encryptedPrivateKey = document.getElementById('encrypted-private-key');
            const publicKeyField = document.getElementById('public-key');
            const initializationVector = document.getElementById('initialization-vector');
            const salt = document.getElementById('salt');
        </script>

        <script>
            async function generateKey() {
                const keyPair = await generateKeyPair();
                const exportedPublicKey = await exportPublicKey(keyPair.publicKey);
                const wrappedPrivateKey = await wrapKey(keyPair.privateKey, passwordField.value);

                publicKeyField.value = exportedPublicKey;
                encryptedPrivateKey.value = wrappedPrivateKey.wrappedKey;
                initializationVector.value = wrappedPrivateKey.iv;
                salt.value = wrappedPrivateKey.salt;
            }

            function checkRepeatedPassword(event) {
                let password = passwordField.value;
                let repeatPassword = repeatPasswordField.value;

                if (repeatPassword !== '' && password !== repeatPassword) {
                    errorMessage.textContent = 'Passwords don\'t match';
                    return;
                }

                errorMessage.textContent = '';
            }

            async function handleSubmit(event) {
                event.preventDefault();

                if (passwordField.value !== repeatPasswordField.value)
                    return;

                await generateKey();

                registrationForm.removeEventListener('submit', handleSubmit);
                registrationForm.requestSubmit(submitButton);
                registrationForm.addEventListener('submit', handleSubmit);
            }

            repeatPasswordField.addEventListener('keyup', checkRepeatedPassword);
            registrationForm.addEventListener('submit', handleSubmit);
        </script>
    </div>
</main>
</body>
</html>