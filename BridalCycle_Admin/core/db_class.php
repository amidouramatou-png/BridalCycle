<?php
require_once __DIR__ . '/db_cred.php';

class Database
{
    // One shared connection for the whole page (opened only once)
    private static ?mysqli $shared = null;

    protected mysqli $conn;

    public function __construct()
    {
        if (self::$shared === null) {
            // Turn every MySQL error into an exception we can catch
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                self::$shared = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                self::$shared->set_charset('utf8mb4');
                // MySQL uses the SAME clock as PHP
                self::$shared->query("SET time_zone = '" . date('P') . "'");
            } catch (mysqli_sql_exception $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(503);
                die('BridalCycle is temporarily unavailable. Please try again in a few minutes.');
            }
        }
        $this->conn = self::$shared;
    }

    // Run a prepared statement. ALWAYS used when the query contains user input
    protected function run(string $sql, string $types = '', mixed ...$params): mysqli_stmt
    {
        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    protected function fetchOne(string $sql, string $types = '', mixed ...$params): ?array
    {
        $stmt = $this->run($sql, $types, ...$params);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    protected function fetchAll(string $sql, string $types = '', mixed ...$params): array
    {
        $stmt = $this->run($sql, $types, ...$params);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
