<?php

require_once __DIR__ . '/../models/Settings.php';

class MailService {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromName;
    private $settings;

    public function __construct() {
        $this->settings = new Settings();
        
        $this->host = $this->settings->get('smtp_host');
        $this->port = $this->settings->get('smtp_port');
        $this->username = $this->settings->get('smtp_username');
        $this->password = $this->settings->get('smtp_password');
        $this->fromName = $this->settings->get('smtp_from_name');
    }

    public function send($to, $subject, $body) {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
             throw new Exception("SMTP Configuration missing. Please configure Email Settings.");
        }

        $contextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $context = stream_context_create($contextOptions);
        
        $this->log("Connecting to {$this->host}:{$this->port}...");
        $socket = stream_socket_client("ssl://{$this->host}:{$this->port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            $this->log("Connection Failed: $errno - $errstr");
            throw new Exception("Could not connect to SMTP host: $errno - $errstr");
        }
        
        stream_set_timeout($socket, 10); // Set read timeout

        $this->log("Reading greeting...");
        $this->readResponse($socket); // Read initial 220 greeting
        
        $this->serverCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']); // Hello
        $this->serverCmd($socket, "AUTH LOGIN"); // Auth Request
        $this->serverCmd($socket, base64_encode($this->username)); // User
        $this->serverCmd($socket, base64_encode($this->password)); // Pass
        $this->serverCmd($socket, "MAIL FROM: <{$this->username}>"); // From
        $this->serverCmd($socket, "RCPT TO: <$to>"); // To
        $this->serverCmd($socket, "DATA"); // Data start

        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->username}>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        
        $this->log("Sending Body...");
        fwrite($socket, "$headers\r\n$body\r\n.\r\n");
        $this->readResponse($socket); // Read response causing DATA end

        $this->serverCmd($socket, "QUIT"); // Quit
        fclose($socket);
        
        $this->log("Email sent successfully to $to");
        return true;
    }

    private function serverCmd($socket, $msg) {
        $this->log("Client: $msg");
        fwrite($socket, $msg . "\r\n");
        
        $this->log("Waiting for response...");
        $response = $this->readResponse($socket);
        $this->log("Server: " . trim($response));
        return $response;
    }

    private function readResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            // SMTP multi-line response ends when line matches "XYZ <message>" 
            // (3 digits, space, message). If 4th char is '-', it continues.
            if (preg_match('/^\d{3} /', $str)) { break; }
        }
        return $response;
    }

    private function log($msg) {
        file_put_contents('/tmp/smtp_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }
}
