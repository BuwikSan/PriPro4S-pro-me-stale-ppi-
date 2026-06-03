<?php
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'cryptodb';
$db_user = getenv('DB_USER') ?: 'crypto';
$db_pass = getenv('DB_PASS') ?: 'crypto_pass';

$dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name;

// Retry dokud DB není připravena (race condition: web startuje dřív než MariaDB)
$pdo = null;
for ($i = 0; $i < 10; $i++) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break;
    } catch (PDOException $e) {
        if ($i === 9) throw $e;  // po 10 pokusech vzdej a vyhoď výjimku
        sleep(3);
    }
}
