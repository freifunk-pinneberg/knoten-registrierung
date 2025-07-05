<?php

class DatabaseInitialization
{
    private string $db_file = 'database.sqlite';
    private ?SQLite3 $db;

    public function __construct()
    {
        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
        if (!file_exists($this->db_file)) {
            $this->db = new SQLite3($this->db_file);
            $this->db->exec("CREATE TABLE IF NOT EXISTS nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                vpn_key TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL,
                registered DATETIME DEFAULT NULL,
                confirmed DATETIME DEFAULT NULL,
                banned DATETIME DEFAULT NULL,
                secret TEXT NOT NULL,
                node_id TEXT DEFAULT NULL UNIQUE
            )");
            echo "Database initialized.";
        } else {
            echo "Database already exists. No changes made.";
        }
    }
}

new DatabaseInitialization();
