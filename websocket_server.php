<?php
const MIN_MESSAGE_INTERVAL = 0.2;
const ALLOWED_ORIGINS = [
    "https://jacek.website",
    "https://www.jacek.website"
];

$server = null;
$context = stream_context_create([
    'ssl' => [
        'local_cert' => '/etc/letsencrypt/live/jacek.website/fullchain.pem',
        'local_pk' => '/etc/letsencrypt/live/jacek.website/privkey.pem',
        'verify_peer' => false,
        'allow_self_signed' => false
    ]
]);

$sockets = [];
$socket_2_client_id = [];
$client_id_2_sockets = [];
$socket_last_message = [];

function onClientSocketConnected($userId): void
{
    echo "{$userId} has connected\r\n";
}

function onClientSocketDisconnect($userId): void
{
    echo "{$userId} has disconnected\r\n";
}

function onClientSocketMessage($userId, $message): void
{
    $json = json_decode($message);

    if(!isset($json)){
        error_log("empty json");
        return;
    }

    $targetId = $json->targetId;
    if($targetId === $userId){
        error_log("user tried to send message to himself");
        return;
    }

    echo "$message\r\n";

    $frame = encode($message);
    sendMessageToClients($targetId, $frame);
    sendMessageToClients($userId, $frame);
}

function sendMessageToClients($userId, $frame) : void
{
    global $client_id_2_sockets;

    if(!isset($client_id_2_sockets[$userId])){
        error_log("User id has no sockets active");
        return;
    }

    $clientSockets = $client_id_2_sockets[$userId];
    foreach ($clientSockets as $socket_id) {
        sendMessageToClient($socket_id, $frame);
    }

    echo "messages sent to $userId\r\n";
}

function sendMessageToClient($socket_id, $frame) : void
{
    global $sockets;

    if(!isset($sockets[$socket_id])){
        error_log("Socket $socket_id not found");
        return;
    }

    try{
        @fwrite($sockets[$socket_id], $frame);
    }catch (Exception $e){
        error_log("Failed writing message to client", $e);
    }
}

function handleClientSocketHandshake($socket, $data): bool|int
{
    if (isOriginInvalid($data))
        return false;

    $phpSessionId = extractPhpSession($data);
    if (!$phpSessionId)
        return false;

    $tabSessionId = extractTabSessionId($data);
    if (!$tabSessionId)
        return false;

    $userId = extractUserId($phpSessionId, $tabSessionId);
    if (!$userId)
        return false;

    fillClientData($socket, $userId);
    sendClientResponse($socket, $data);

    return $userId;
}

function sendClientResponse($socket, $data): void
{
    preg_match("/Sec-WebSocket-Key: (.+)\r\n/", $data, $m);
    $accept = base64_encode(sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    $resp = "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: $accept\r\n"
        . "Sec-WebSocket-Protocol: Authorization\r\n"
        . "\r\n";
    fwrite($socket, $resp);
}

function fillClientData($socket, $userId): void
{
    global $socket_2_client_id, $client_id_2_sockets;
    $socket_id = (int)$socket;
    $socket_2_client_id[$socket_id] = $userId;

    if (!isset($client_id_2_sockets[$userId]))
        $client_id_2_sockets[$userId] = [];

    $client_id_2_sockets[$userId][] = $socket_id;
}

function isOriginInvalid($data): bool
{
    if (!preg_match("/Origin: (.+)\r\n/", $data, $o)) {
        error_log("No origin");
        return true;
    }
    $origin = trim($o[1]);
    if (!in_array($origin, ALLOWED_ORIGINS, true)) {
        error_log("Bad origin");
        return true;
    }

    return false;
}

function extractPhpSession($data): bool|string
{
    if (!preg_match("/Cookie: (.+)\r\n/", $data, $c)) {
        error_log("No cookies");
        return false;
    }
    parse_str(str_replace('; ', '&', $c[1]), $cookies);
    if (empty($cookies['PHPSESSID'])) {
        error_log("No php session");
        return false;
    }

    return $cookies['PHPSESSID'];
}

function extractTabSessionId($data): bool|string
{
    if (!preg_match("/Sec-WebSocket-Protocol: (.+)\r\n/", $data, $sp)) {
        error_log("No protocol");
        return false;
    }

    $tabSessionId = trim(str_replace("Authorization, ", "", $sp[1]));
    if (empty($tabSessionId)) {
        error_log("No tab session");
        return false;
    }

    return $tabSessionId;
}

function extractUserId($phpSessionId, $tabSessionId): bool|int
{
    $sessionData = read_php_session_from_disk($phpSessionId);
    if (!$sessionData)
        return false;

    if (!isset($sessionData[$tabSessionId])) {
        error_log("Invalid tab session id");
        return false;
    }

    if (!isset($sessionData[$tabSessionId]['userId'])) {
        error_log("No user id found in session");
        return false;
    }

    return $sessionData[$tabSessionId]['userId'];
}

function handleClientSocketDisconnect($socket): void
{
    global $sockets, $socket_last_message;

    $socket_id = (int)$socket;

    $userId = cleanClientData($socket_id);
    if ($userId > 0) onClientSocketDisconnect($userId);

    fclose($socket);
    unset($sockets[$socket_id], $socket_last_message[$socket_id]);
}

function cleanClientData($socket_id): int
{
    global $socket_2_client_id, $client_id_2_sockets;

    if (!isset($socket_2_client_id[$socket_id]))
        return -1;

    $client_id = $socket_2_client_id[$socket_id];
    unset($socket_2_client_id[$socket_id]);

    if (!isset($client_id_2_sockets[$client_id]))
        return $client_id;

    $sockets = $client_id_2_sockets[$client_id];
    $index = array_search($socket_id, $sockets);

    if ($index === false)
        return $client_id;

    unset($client_id_2_sockets[$client_id][$index]);

    if (count($client_id_2_sockets[$client_id]) != 0)
        return $client_id;

    unset($client_id_2_sockets[$client_id]);

    return $client_id;
}
function handleServerSocket(): void
{
    global $server, $sockets;

    $new_socket = stream_socket_accept($server, -1);
    if (!$new_socket)
        return;

    $socket_id = (int)$new_socket;
    $sockets[$socket_id] = $new_socket;
}

function handleClientSocket($socket): void
{
    $socket_id = (int)$socket;
    $dataInput = fread($socket, 2048);
    if (!$dataInput) {
        handleClientSocketDisconnect($socket);
        return;
    }

    if (str_contains($dataInput, 'Sec-WebSocket-Key:')) {
        $userId = handleClientSocketHandshake($socket, $dataInput);

        if (!$userId)
            handleClientSocketDisconnect($socket);
        else
            onClientSocketConnected($userId);

        return;
    }

    if(isClientRateLimited($socket_id)) {
        error_log("Client rate limit exceeded");
        return;
    }

    try{
        handleClientSocketMessage($socket, $dataInput);
    }catch (exception $e){
        error_log("Failed handling client socket message", $e);
    }
}

function handleClientSocketMessage($socket, $dataInput): void
{
    global $socket_2_client_id;
    $socket_id = (int) $socket;

    if(!isset($socket_2_client_id[$socket_id])) {
        error_log("No client id found for socket");
        return;
    }

    $userId = $socket_2_client_id[$socket_id];
    $message = decode($dataInput);
    onClientSocketMessage($userId, $message);
}

function isClientRateLimited($socket_id): bool
{
    global $socket_last_message;

    $time = microtime(true);

    if (isset($socket_last_message[$socket_id]) &&
        ($time - $socket_last_message[$socket_id] < MIN_MESSAGE_INTERVAL)) {
        return true;
    }

    $socket_last_message[$socket_id] = $time;
    return false;
}

function handleSocket($socket): void
{
    global $server;

    if ($socket === $server) {
        handleServerSocket();
        return;
    }

    handleClientSocket($socket);
}

function read(): void
{
    global $server, $sockets;

    while (true){
        $read = array_values($sockets);
        $read[] = $server;
        $write = null;
        $except = null;

        stream_select($read, $write, $except, 0, 200000);

        foreach ($read as $socket) {
            handleSocket($socket);
        }
    }
}

function listen(): void
{
    global $server, $context;

    $server = stream_socket_server(
        'tls://0.0.0.0:8443',
        $errno,
        $errstr,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        $context
    );

    if (!$server)
        die("Error: $errstr ($errno)\n");

    echo "WSS Server started on port 8443...\n";
}

function encode(string $payload): string
{
    $frame = chr(0x81);
    $len = strlen($payload);
    if ($len <= 125) {
        $frame .= chr($len);
    } elseif ($len <= 0xFFFF) {
        $frame .= chr(126) . pack('n', $len);
    } else {
        $frame .= chr(127) . pack('J', $len);
    }
    return $frame . $payload;
}

function decode(string $data): string
{
    $len = ord($data[1]) & 0x7F;
    if ($len === 126) {
        $masks = substr($data, 4, 4);
        $payload = substr($data, 8);
    } else {
        $masks = substr($data, 2, 4);
        $payload = substr($data, 6);
    }
    $text = '';
    for ($i = 0, $L = strlen($payload); $i < $L; ++$i) {
        $text .= $payload[$i] ^ $masks[$i % 4];
    }
    return $text;
}

function read_php_session_from_disk($phpSessionId): bool|array
{
    $sessionPath = ini_get('session.save_path');
    $sessionFile = "$sessionPath/sess_$phpSessionId";

    if (!file_exists($sessionFile)) {
        error_log("Invalid session file");
        return false;
    }

    $contents = file_get_contents($sessionFile);
    if (!$contents) {
        error_log("Failed getting contents of session file");
        return false;
    }

    return deserialize_php($contents);
}

function deserialize_php($session_data): bool|array
{
    $return_data = [];
    $offset = 0;
    while ($offset < strlen($session_data)) {
        if (!str_contains(substr($session_data, $offset), "|")) {
            error_log("deserialize failed, invalid data, remaining: " . substr($session_data, $offset));
            return false;
        }
        $pos = strpos($session_data, "|", $offset);
        $num = $pos - $offset;
        $varname = substr($session_data, $offset, $num);
        $offset += $num + 1;
        $data = unserialize(substr($session_data, $offset));
        $return_data[$varname] = $data;
        $offset += strlen(serialize($data));
    }
    return $return_data;
}

listen();
read();