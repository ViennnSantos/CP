<?php
if (!defined('DEBUG')) { define('DEBUG', false); }

class Database
{
    // 🔹 NEW: singleton holder
    private static $instance = null;

    private $host = "localhost";
    private $database_name = "rads_tooling";
    private $username = "root";
    private $password = "";
    private $conn = null;

    // 🔹 NEW: global accessor (works alongside "new Database()")
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self(); // uses current class
        }
        return self::$instance;
    }

    // (optional) prevent cloning/unserialize of the singleton
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public function getConnection()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database_name};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new \PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());

            if (defined('DEBUG') && DEBUG) {
                throw new \Exception("Database connection failed: " . $e->getMessage());
            } else {
                throw new \Exception("Database connection failed. Please try again later.");
            }
        }
    }

    public function testConnection()
    {
        try {
            $conn = $this->getConnection();
            $conn->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

try {
    $dbInstance = Database::getInstance();
    $conn = $dbInstance->getConnection();
    $pdo = $conn; // ensure $pdo available for legacy code
} catch (Exception $e) {
    error_log('database.php: failed to create $conn/$pdo - ' . $e->getMessage());
    $conn = null;
    $pdo = null;
}
