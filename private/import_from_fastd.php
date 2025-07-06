#!/usr/bin/env php
<?php
declare(strict_types=1);

/* --- Einstellungen ----------------------------------------------------- */
const DB_PATH = __DIR__ . '/database.sqlite';   // bestehende DB
const PEER_DIR = '/var/www/ffpi-node-register/Test/Gate04Peers';        // fastd-Peers

/* --- Hilfsfunktionen ---------------------------------------------------- */
function firstKeyIn(string $text): ?string
{
    foreach (explode("\n", $text) as $l) {
        if (preg_match('/^\s*key\s+"([0-9a-f]{64})"/i', $l, $m)) {
            return strtolower($m[1]);            // erste nicht-auskommentierte Key-Zeile
        }
    }
    return null;
}

function getName(string $file): string
{
    return basename($file);
}

function getNodeID(string $file): ?string
{
    $name = getName($file);
    if (preg_match('/ffpi-([0-9a-f]{12})/', $name, $matches)) {
        $node_id = $matches[1];
    }
    return $node_id ?? null;
}

function randomSecret(): string
{
    return bin2hex(random_bytes(16));
}

function oldestStatTime(string $path): int
{
    $st = stat($path);                 // numeric + associative keys
    $t = [$st['mtime'], $st['ctime']];

    // ext4 mit statx liefert 'birthtime' / index 10 – nur verwenden, wenn >0
    if (isset($st['birthtime']) && $st['birthtime'] > 0) {
        $t[] = $st['birthtime'];
    }
    return min($t);
}

/* --- DB-Verbindung ------------------------------------------------------ */
$db = new SQLite3(DB_PATH, SQLITE3_OPEN_READWRITE);
$db->enableExceptions(true);

/* --- INSERT-Statement (verändert keine bestehenden Zeilen) ------------- */
$ins = $db->prepare(
    'INSERT OR IGNORE INTO nodes
       (name, vpn_key, registered, secret, node_id, import_date)
     VALUES
       (:name, :key, :reg, :sec, :nodeid, :importdate)'
);

/* --- Durch alle Peer-Dateien laufen ------------------------------------ */
$new = $skipped = 0;

foreach (glob(PEER_DIR . '/*') ?: [] as $file) {
    if (!is_file($file)) continue;

    $key = firstKeyIn(file_get_contents($file));
    if (!$key) {
        fprintf(STDERR, "WARN: kein Key in %s\n", $file);
        continue;
    }

    $ins->reset();
    $ins->bindValue(':name', getName($file), SQLITE3_TEXT);
    $ins->bindValue(':key', $key, SQLITE3_TEXT);
    $ins->bindValue(':reg', oldestStatTime($file), SQLITE3_INTEGER); // Unix-Timestamp
    $ins->bindValue(':sec', randomSecret(), SQLITE3_TEXT);
    $ins->bindValue(':nodeid', getNodeID($file), SQLITE3_TEXT);
    $ins->bindValue(':importdate', time(), SQLITE3_INTEGER);
    echo $ins->getSQL(true) . "\n";
    $ins->execute();


    $db->changes() ? $new++ : $skipped++;
}

printf("Fertig: %d neue Knoten importiert, %d übersprungen.\n", $new, $skipped);
