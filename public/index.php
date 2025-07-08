<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Konfiguration
const SITE_URL = 'https://knoten-registrierung.pinneberg.freifunk.net';
const EMAIL_FROM = 'noc@pinneberg.freifunk.net';
const EMAIL_REPLY_TO = 'noc@pinneberg.freifunk.net';

class Database
{
    private string $db_file = '../private/database.sqlite';
    private ?SQLite3 $db;

    public function __construct()
    {
        $this->db = new SQLite3($this->db_file);
    }

    public function getDb(): SQLite3
    {
        return $this->db;
    }
}

class Node
{
    private ?SQLite3 $db;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
    }

    public function register(string $name, string $vpn_key, string $email, string $node_id = ''): string
    {
        if (empty($name) || empty($vpn_key) || empty($email)) {
            return "Bitte alle Pflichtfelder ausfüllen.";
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

        if (!empty($node_id) && !preg_match('/^[0-9a-f]{12}$/', $node_id)) {
            return "Ungültige Knoten ID, die Knoten ID ist immer alphanumerisch mit exakt 12 zeichen.";
        }

        if (preg_match('/ffpi-([0-9a-f]{12})/', $name, $matches) && empty($node_id)) {
            $node_id = $matches[1];
        }

        if (empty($node_id)) {
            $node_id = null;
        }

        $secret = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare('INSERT INTO nodes (name, vpn_key, email, registered, secret, node_id) VALUES (?, ?, ?, "now", ?, ?)');
        $stmt->bindValue(1, $name, SQLITE3_TEXT);
        $stmt->bindValue(2, $vpn_key, SQLITE3_TEXT);
        $stmt->bindValue(3, $email, SQLITE3_TEXT);
        $stmt->bindValue(4, $secret, SQLITE3_TEXT);
        $stmt->bindValue(5, $node_id, SQLITE3_TEXT);

        if ($stmt->execute()) {
            if($this->sendConfirmationEmail($email, $secret)) {
                return 'Bestätigungs-E-Mail wurde gesendet.';
            } else {
                return 'Fehler beim senden der E-Mail. Deine Daten wurden gespeichert, aber auß unbekanntem Grund konnte die E-Mail nicht gesendet werden. Bitte melde diesen Fehler unter service@pinneberg.freifunk.net';
            }
        } else {
            return "Fehler beim registrieren. Sicher das alles Daten korrekt sind? Falls der Fehler bestehen bleibt sende deinen key an keys@pinneberg.freifunk.net";
        }
    }

    private function sendConfirmationEmail(string $email, string $secret): bool
    {
        $confirmation_link = SITE_URL . "/index.php?action=confirm&email=" . rawurlencode($email) . "&secret=" . rawurlencode($secret);
        $subject = mb_encode_mimeheader('Freifunk Pinneberg: Knoten registrierung bestätigen', 'UTF-8');
        $message = "Bitte bestätige Deine E-Mail-Adresse durch Klicken auf den folgenden Link: <$confirmation_link>. Falls sich der VPN Key ändert, oder der Router seinen Besitzer wechselt, schreibe bitte eine kurze mail an service@pinneberg.freifunk.net";
        $headers =
            'Mime-Version: 1.0' . "\r\n" .
            'Content-Type: text/plain; charset=utf-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n" .
            'From: ' . EMAIL_FROM . "\r\n" .
            'Reply-To: ' . EMAIL_REPLY_TO . "\r\n" .
            'X-Mailer: PHP';

        return mail($email, $subject, $message, $headers);
    }

    public function confirmEmail($email, $secret): string
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

$action = $_GET['action'] ?? '';

if ($action == 'register' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $vpn_key = $_POST['vpn_key'];
    $node_id = $_POST['node_id'] ?? '';
    $email = $_POST['email'];
    $message = $node->register($name, $vpn_key, $email, $node_id);
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
    <html lang="de">
    <head>
        <title>Freifunk Pinneberg - VPN-Key Registrieren</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/svg+xml" href="https://pinneberg.freifunk.net/favicon.svg" sizes="any">
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
                padding: 10px;
                max-width: 100%;
                min-height: 100%;
                font-size: 20px;
            }

            img {
                max-width: 100%;
            }

            .logo {
                width: 100%;
                max-width: 450px;
            }

            .intro {
                display: block;
                max-width: 450px;
                margin-left: auto;
                margin-right: auto;
            }

            form {
                background-color: #ffffff;
                border: 1px solid #ebebeb;
                padding: 20px;
                border-radius: 5px;
                max-width: 600px;
                margin: 0px auto;
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
                padding-bottom: 5px;
            }

            .form-group {
                margin-top: 15px;
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
            }

            input::placeholder {
                opacity: 0.2;
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
                margin-top: 15px;
                width: 100%;
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
        <div class="intro">
            <img src="favicon.svg" alt="Freifunk Pinneberg Logo" class="logo">
            <h1>VPN-Key Registrieren</h1>
        </div>
        <div class="form-group">
            <p>Hier kannst du deinen neuen Knoten für das Mesh-VPN registrieren.</p>
            <p>Bitte nutze das Formular nur für Router die erstmalig ins Pinneberger Mesh-VPN kommen. Bei Änderungen an bestehenden Knoten schicke bitte eine E-Mail an keys@pinneberg.freifunk.net</p>
        </div>
        <div class="form-group">
            <label for="name">Knoten Name:</label>
            <div class="input">
                <input type="text" id="name" name="name" pattern="[a-zA-Z0-9\-_]+" placeholder="HAL-9000" value="<?php echo htmlspecialchars($_GET['name'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="node_id">Knoten ID:</label>
            <div class="input">
                <input type="text" id="node_id" name="node_id" placeholder="48414c200100" pattern="^[0-9a-f]{12}$" value="<?php echo htmlspecialchars($_GET['node_id'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="vpn_key">VPN Key:</label>
            <div class="input">
                <input type="text" id="vpn_key" name="vpn_key" placeholder="d865f8b36f95b0efe0fb74c1dfe2e8b87651e778c4f4f2a6385861a384bffde" pattern="^[a-z0-9]{64}$" value="<?php echo htmlspecialchars($_GET['vpn_key'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="email">E-Mail:</label>
            <div class="input">
                <input type="email" id="email" placeholder="dave.bowman@ncao.gov" name="email" required>
            </div>
            <p>Wir senden dir eine E-Mail zur Bestätigung zu. Deine E-Mail-Adresse wird nicht veröffentlicht. Wir nutzen
                deine E-Mail-Adresse dazu dich in besonders <strong>wichtigen</strong> Fällen die diesen spezifischen
                Knoten betreffen zu kontaktieren.</p>
            <p>Mit der Registrierung verpflichtest du dich, die VPN-Verbindung ausschließlich zur Bereitstellung eines
                Freien Netzes im Sinne des <a
                    href="https://pinneberg.freifunk.net/rechtliches/pico-peering-agreement" target="_blank">Pico
                    Peering Agreement</a> zu verwenden.</p>
        </div>

        <div class="form-group">
            <button class="btn btn-primary" type="submit">Registrieren</button>
        </div>
    </form>
    </body>
    </html>
    <?php
}
