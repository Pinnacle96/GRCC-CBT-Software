<?php
/**
 * Database Connection File
 * Establishes a PDO connection to the MySQL database
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'grcc_cbt';
    private $username = 'root';
    private $password = ''; // Default WAMP password is empty
    private $conn;
    
    /**
     * Get the database connection
     * @return PDO connection object
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    return $pdo;
}