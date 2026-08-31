<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'apartment_db');
define('DB_PORT', '3306');

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {

            die("<div style='font-family: sans-serif; padding: 25px; background: #fff1f2; border: 1px solid #fda4af; border-radius: 8px; max-width: 650px; margin: 50px auto;'>
                <h3 style='color: #9f1239; margin-top: 0;'>⚠️ Database Connection Error</h3>
                <p style='color: #4c0519; line-height: 1.6;'>Could not connect to the database <strong>" . DB_NAME . "</strong>. Please make sure MySQL is running in XAMPP and you have imported <code>database.sql</code>.</p>
                <p style='color: #881337; font-size: 13px;'><em>Details: " . htmlspecialchars($e->getMessage()) . "</em></p>
                <hr style='border: none; border-top: 1px solid #fecdd3; margin: 15px 0;'>
                <p style='color: #4c0519; font-size: 14px;'><strong>Quick Fix:</strong> Open phpMyAdmin (<code>http://localhost/phpmyadmin</code>) -> create database <code>apartment_db</code> -> Import <code>database.sql</code>.</p>
            </div>");
        }
    }

    return $pdo;
}
