<?php

class Database
{
    private $db_file = 'database.sqlite';
    private $db;

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
                confirmed DATETIME DEFAULT NULL,
                secret TEXT NOT NULL
            )");
            echo "Database initialized.";
        } else {
            echo "Database already exists. No changes made.";
        }
    }
}

new Database();
