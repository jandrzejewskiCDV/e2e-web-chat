
# PHP Web Chat Application

A lightweight chat application built using **pure PHP, HTML, CSS, and JavaScript**, featuring **real-time WebSocket communication**, **end-to-end encrypted messaging**, and a **MySQL database backend**.

---

## 📁 Project Structure

```
/opt/chat-server/                  # WebSocket server files
  ├── websocket_server.php         # WebSocket server (run manually)

/var/www/html/                     # Web server root
  ├── index.php                    # Main entry point
  ├── login.php                    # Login handling
  ├── register.php                 # Registration page
  ├── get_users.php                # Fetch online/registered users
  ├── get_messages.php             # Fetch chat messages
  ├── insert_message.php           # Insert a new message
  ├── message_encryption.js        # Message encryption logic
  ├── key_wrapper.js               # Key wrapping logic
  ├── web_chat.php                 # Main chat UI
  ├── style.css                    # Global styles
  ├── web_chat_style.css           # Styles specific to chat UI
  ├── *.html, *.svg, *.js          # Miscellaneous assets
```

---

## 🔐 Encrypted Messaging

All messages in the chat are **end-to-end encrypted** in the browser using JavaScript before being transmitted.

- The server **never sees plaintext messages**
- Each user exchanges keys via a secure key wrapper
- Encryption handled client-side via `message_encryption.js`

> ⚠️ For secure key exchange, always serve the site over **HTTPS**

---

## 🛠 Requirements

- PHP 7.x or later
- MySQL or MariaDB
- Web server (Apache, Nginx, etc.)
- Ability to run PHP from CLI (for WebSocket server)

---

## 🧱 MySQL Setup

This application requires a **MySQL database** for:

- User authentication
- Chat message storage

You must:

1. Create a new MySQL database
2. Create the necessary tables
3. Configure database credentials in your PHP files (usually near the top of `login.php`, `insert_message.php`, etc.)

---

## 🚀 Getting Started

### 1. Deploy Web Files

Copy all files **except `websocket_server.php`** to your web server’s document root:

```bash
sudo cp -r /path/to/project/* /var/www/html/
```

Ensure your PHP environment is configured and running (e.g., `php-fpm`, `apache2`).

### 2. Start the WebSocket Server

Run the WebSocket server manually (outside of `/var/www/html/`):

```bash
php /opt/chat-server/websocket_server.php
```

To keep it running in the background, use:

- `screen`
- `tmux`
- or set up a `systemd` service (ask if you need help)

### 3. Access the Chat App

Open your browser:

```
https://your-server-ip/
```

You'll see the login/register UI and can begin chatting after authentication.

---

## 🧰 Technologies Used

- PHP (Backend logic)
- MySQL (Database)
- JavaScript (Client-side logic & encryption)
- WebSocket (Real-time communication)
- HTML/CSS (Frontend UI)

---
