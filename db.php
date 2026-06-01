<?php
// Minimalist DB connection using PDO
$dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'];
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
