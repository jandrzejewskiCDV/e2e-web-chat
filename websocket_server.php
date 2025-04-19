<?php

define('MAX_PAYLOAD', 1024);
define('MIN_MESSAGE_INTERVAL', 0.2);

$allowed_origins = [
    'https://jacek.website',
    'https://www.jacek.website'
];

$context = stream_context_create([
    'ssl' => [
        'local_cert'        => '/etc/letsencrypt/live/jacek.website/fullchain.pem',
        'local_pk'          => '/etc/letsencrypt/live/jacek.website/privkey.pem',
        'verify_peer'       => false,
        'allow_self_signed' => false
    ]
]);

$server = stream_socket_server(
    'tls://0.0.0.0:8443',
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if (!$server) {
    die("Error: $errstr ($errno)\n");
}
echo "WSS Server started on port 8443...\n";

$clients       = [];
$client_users  = [];
$users_client  = [];
$last_message_time = [];

while (true) {
    $read = array_values($clients);
    $read[] = $server;
    $write = $except = null;

    stream_select($read, $write, $except, 0, 200000);

    foreach ($read as $sock) {
        if ($sock === $server) {
            $new = stream_socket_accept($server, -1);
            if ($new) {
                $sid = (int)$new;
                $clients[$sid] = $new;
            }
            continue;
        }

        $sid = (int)$sock;
        $data = fread($sock, 2048);
        if (!$data) {
            handleDisconnect($sid);
            continue;
        }

        if (str_contains($data, 'Sec-WebSocket-Key:')) {
            if (!handleHandshake($sock, $data)) {
                fclose($sock);
                unset($clients[$sid]);
            }
            continue;
        }

        $msg = decode($data);

        if (isset($last_message_time[$sid]) &&
            (microtime(true) - $last_message_time[$sid] < MIN_MESSAGE_INTERVAL)) {
            continue;
        }
        $last_message_time[$sid] = microtime(true);
        if (strlen($msg) > MAX_PAYLOAD) {
            continue;
        }

        if (!isset($client_users[$sid])) {
            continue;
        }
        $fromUserId = $client_users[$sid];
        onMessage($fromUserId, $msg);
    }
}

// ─── ENCODING ──────────────────────────────────────────────────────────────

function encode(string $payload): string {
    $frame = chr(0x81);
    $len   = strlen($payload);
    if ($len <= 125) {
        $frame .= chr($len);
    } elseif ($len <= 0xFFFF) {
        $frame .= chr(126) . pack('n', $len);
    } else {
        $frame .= chr(127) . pack('J', $len);
    }
    return $frame . $payload;
}

function decode(string $data): string {
    $len = ord($data[1]) & 0x7F;
    if ($len === 126) {
        $masks   = substr($data, 4, 4);
        $payload = substr($data, 8);
    } else {
        $masks   = substr($data, 2, 4);
        $payload = substr($data, 6);
    }
    $text = '';
    for ($i = 0, $L = strlen($payload); $i < $L; ++$i) {
        $text .= $payload[$i] ^ $masks[$i % 4];
    }
    return $text;
}

// ─── CUSTOMIZABLE HOOKS ─────────────────────────────────────────────────────

function onHandshake(string $phpsessId, string $sessionKey): int {
    $data = read_php_session($phpsessId);

    if(empty($data)){
        error_log("Invalid php session");
        return -1;
    }

    if(!isset($data[$sessionKey])){
        error_log("Invalid session key");
        return -1;
    }

    $userId = $data[$sessionKey]['userId'];
    if(!$userId){
        error_log("Empty user id");
        return -1;
    }

    return $userId;
}

function onMessage(int $fromUserId, string $jsonData): void {
    global $users_client, $clients;

    $data = json_decode($jsonData);
    if (!$data || empty($data->targetId) || !isset($users_client[$data->targetId])) {
        error_log("no data or no target id or users client not sent");
        return;
    }

    $targetSid = $users_client[$data->targetId];
    if (!isset($clients[$targetSid])) {
        error_log("no target id");
        return;
    }

    echo $fromUserId . " to " . $data->targetId . "\r\n";

    $frame = encode($fromUserId);
    @fwrite($clients[$targetSid], $frame);
}

function onDisconnect(string $userId): void {
    echo "$userId disconnected\r\n";
}

// ─── HANDSHAKE HANDLER ─────────────────────────────────────────────────────

function handleHandshake($sock, string $data): bool {
    global $allowed_origins, $client_users, $users_client;

    if (!preg_match("/Origin: (.+)\r\n/", $data, $o)) {
        error_log("no origin");
        return false;
    }
    $origin = trim($o[1]);
    if (!in_array($origin, $allowed_origins, true)) {
        error_log("bad origin");
        return false;
    }

    if (!preg_match("/Cookie: (.+)\r\n/", $data, $c)) {
        error_log("no cookies");
        return false;
    }
    parse_str(str_replace('; ', '&', $c[1]), $cookies);
    if (empty($cookies['PHPSESSID'])) {
        error_log("no php");
        return false;
    }
    $phpSessionId = $cookies['PHPSESSID'];

    if (!preg_match("/Sec-WebSocket-Protocol: (.+)\r\n/", $data, $sp)) {
        error_log("no protocol");
        return false;
    }

    $tabSessionId = trim(str_replace("Authorization, ", "", $sp[1]));
    if (empty($tabSessionId)) {
        error_log("empty tab session");
        return false;
    }

    $userId = onHandshake($phpSessionId, $tabSessionId);
    if ($userId < 0) {
        error_log("invalid user");
        return false;
    }

    $sid = (int)$sock;
    $client_users[$sid] = $userId;
    $users_client[$userId] = $sid;

    preg_match("/Sec-WebSocket-Key: (.+)\r\n/", $data, $m);
    $accept = base64_encode(sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    $resp = "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: $accept\r\n"
        . "Sec-WebSocket-Protocol: $tabSessionId\r\n"
        . "\r\n";
    fwrite($sock, $resp);

    echo $userId . " has connected \r\n";

    return true;
}

// ─── DISCONNECT HANDLER ─────────────────────────────────────────────────────

function handleDisconnect(int $sid): void {
    global $clients, $client_users, $users_client, $last_message_time;
    if (isset($client_users[$sid])) {
        onDisconnect($client_users[$sid]);
        unset($users_client[$client_users[$sid]]);
        unset($client_users[$sid]);
    }
    fclose($clients[$sid]);
    unset($clients[$sid], $last_message_time[$sid]);
}

function read_php_session($sessionId) {
    $sessionPath = ini_get("session.save_path");
    $sessionFile = "$sessionPath/sess_$sessionId";

    if (!file_exists($sessionFile)) return null;

    $contents = file_get_contents($sessionFile);

    return unserialize_php($contents);
}

function unserialize_php($session_data) {
    $return_data = [];
    $offset = 0;
    while ($offset < strlen($session_data)) {
        if (!str_contains(substr($session_data, $offset), "|")) {
            error_log("invalid data, remaining: " . substr($session_data, $offset));
            return [];
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