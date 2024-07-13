<?php

// Konfiguration
const SITE_URL = 'https://vpn-form.pinneberg.freifunk.net';
const EMAIL_FROM = 'noc@pinneberg.freifunk.net';
const EMAIL_REPLY_TO = 'noc@pinneberg.freifunk.net';

class Database
{
    private $db_file = 'database.sqlite';
    private $db;

    public function __construct()
    {
        $this->db = new SQLite3($this->db_file);
    }

    public function getDb()
    {
        return $this->db;
    }
}

class Node
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function register($name, $vpn_key, $email)
    {
        if (empty($name) || empty($vpn_key) || empty($email)) {
            return "Alle Felder sind erforderlich.";
        }

        if (!preg_match('/^[^@\s\']+@[^@\s\']+$/', $email)) {
            return "Ungültige E-Mail-Adresse.";
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $vpn_key)) {
            return "Der VPN Key darf nur aus Zahlen und Buchstaben bestehen.";
        }

        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $name)) {
            return "Der Knotenname darf nur alphanumerische Zeichen, Bindestriche und Unterstriche enthalten.";
        }

        $secret = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare('INSERT INTO nodes (name, vpn_key, email, secret) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $name, SQLITE3_TEXT);
        $stmt->bindValue(2, $vpn_key, SQLITE3_TEXT);
        $stmt->bindValue(3, $email, SQLITE3_TEXT);
        $stmt->bindValue(4, $secret, SQLITE3_TEXT);

        if ($stmt->execute()) {
            $this->sendConfirmationEmail($email, $secret);
            return 'Bestätigungs-E-Mail wurde gesendet.';
        } else {
            return "VPN Key muss eindeutig sein.";
        }
    }

    private function sendConfirmationEmail($email, $secret)
    {
        $confirmation_link = SITE_URL . "/index.php?action=confirm&email=" . urlencode($email) . "&secret=" . urlencode($secret);
        $subject = 'Bitte bestätigen Sie Ihre E-Mail-Adresse';
        $message = "Bitte bestätigen Sie Ihre E-Mail-Adresse durch Klicken auf den folgenden Link: $confirmation_link";
        $headers = 'From: ' . EMAIL_FROM . "\r\n" .
            'Reply-To: ' . EMAIL_REPLY_TO . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        mail($email, $subject, $message, $headers);
    }

    public function confirmEmail($email, $secret)
    {
        $stmt = $this->db->prepare('UPDATE nodes SET confirmed = datetime("now") WHERE email = ? AND secret = ?');
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->bindValue(2, $secret, SQLITE3_TEXT);

        if ($stmt->execute() && $this->db->changes() > 0) {
            return "Ihre E-Mail-Adresse wurde erfolgreich bestätigt.";
        } else {
            return "Bestätigung fehlgeschlagen. Ungültige Daten.";
        }
    }
}

$db = (new Database())->getDb();
$node = new Node($db);

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'register' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $vpn_key = $_POST['vpn_key'];
    $email = $_POST['email'];
    $message = $node->register($name, $vpn_key, $email);
    echo $message;
} elseif ($action == 'confirm' && isset($_GET['email']) && isset($_GET['secret'])) {
    $email = $_GET['email'];
    $secret = $_GET['secret'];
    $message = $node->confirmEmail($email, $secret);
    echo $message;
} else {
    displayForm();
}

function displayForm()
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Freifunk Pinneberg - Knoten Registrierung</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <style>
            * {
                box-sizing: border-box;
                -webkit-box-sizing: border-box;
                -moz-box-sizing: border-box;
                transition: all 0.5s ease-in-out,
                border 1s ease-in-out;
            }

            html {
                margin: 0;
                padding: 0;
                max-width: 100%;
                min-height: 100%;
            }

            body {
                background-color: #f7f7f7;
                color: #666666;
                margin: 0;
                padding: 0;
                max-width: 100%;
                min-height: 100%;
                font-size: 20px;
            }

            img {
                max-width: 100%;
            }

            form {
                background-color: #ffffff;
                border: 1px solid #ebebeb;
                padding: 20px;
                margin: auto;
                border-radius: 5px;
                max-width: 600px;
            }

            a:link {
                color: #009ee0;
            }

            a:hover {
                text-decoration: none;
            }

            a:active,
            a:focus,
            a.active,
            a:visited.active,
            a:visited:active,
            a:visited:focus {
                color: #dc0067;
            }

            fieldset {
                border: none;
                padding-left: 0;
            }

            fieldset > fieldset {
                padding-left: 15px;
                border: 0 solid #666666;
                border-left-width: 2px;
            }

            fieldset > legend {
                font-family: "Roboto Condensed", "Roboto", "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif;
                color: #dc0067;
                font-weight: bold;
                font-size: 30px;
                margin: 0;
            }

            label {
                display: inline-block;
                padding-top: 15px;
                padding-bottom: 5px;
            }

            input,
            textarea,
            select {
                font-size: 20px;
                font-family: "Roboto Condensed", "Roboto", "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif;
                border-radius: 3px;
                padding-left: 5px;
                padding-right: 5px;
                vertical-align: middle;
                line-height: 50px;
                text-decoration: none;
                background-color: transparent;
                color: #666666;
                border: solid 2px #666666;
                box-shadow: none;
                width: 100%;
                max-width: 450px;
            }

            select {
                height: 56px;
            }

            input:active,
            textarea:active,
            select:active,
            fieldset:active,
            input:focus,
            textarea:focus,
            select:focus,
            fieldset:focus {
                border-color: #009ee0;
            }

            input:invalid,
            textarea:invalid,
            select:invalid {
                border-color: #dc0067;
                color: #dc0067;
            }

            input:invalid:active,
            textarea:invalid:active,
            select:invalid:active,
            input:invalid:focus,
            textarea:invalid:focus,
            select:invalid:focus {
                border-color: #ffb400;
                color: #ffb400;
            }

            input:required:valid,
            textarea:required:valid,
            select:required:valid {
                border-color: #0d7813;
            }

            input {
                height: 50px;
            }

            input[type=checkbox] {
                width: initial;
                height: initial;
                margin: 10px;
            }

            .control-label {
                display: inline-block;
                padding-top: 15px;
                padding-bottom: 5px;
            }

            a:link:visited,
            a:visited {
                color: rgba(0, 158, 224, 0.8);
            }

            a:link.button,
            a:visited.button,
            .button,
            button {
                display: inline-block;
                height: 50px;
                color: #ffffff;
                font-size: 20px;
                padding-left: 50px;
                padding-right: 50px;
                background-color: #dc0067;
                box-shadow: 0px 10px 20px 0px rgba(220, 0, 103, 0.2);
                font-family: "Roboto Condensed", "Roboto", "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif;
                border: none;
                border-radius: 3px;
                vertical-align: middle;
                line-height: 50px;
                text-decoration: none;
                margin-top: 15px:
            }

            a:link.button.light,
            a:visited.button.light,
            .button.light {
                background-color: transparent;
                color: #dc0067;
                border: solid 2px #dc0067;
                box-shadow: none;
            }

            a.button.light:hover {
                box-shadow: 0px 10px 20px 0px rgba(220, 0, 103, 0.2);
            }
        </style>
    </head>
    <body>
    <form action="index.php?action=register" method="POST">
        <div class="form-group">
            <label for="name">Knoten Name:</label>
            <div class="input">
                <input type="text" id="name" name="name" pattern="[a-zA-Z0-9\-_]+" required>
            </div>
        </div>

        <div class="form-group">
            <label for="vpn_key">VPN Key:</label>
            <div class="input">
                <input type="text" id="vpn_key" name="vpn_key" pattern="^[a-z0-9]{64}$" required>
            </div>
        </div>

        <div class="form-group">
            <label for="email">E-Mail:</label>
            <div class="input">
                <input type="email" id="email" name="email" required>
            </div>
        </div>

        <div class="form-group">
            <button class="btn btn-primary" type="submit">Registrieren</button>
        </div>
    </form>
    </body>
    </html>
    <?php
}
