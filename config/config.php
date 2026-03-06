<?php
// config/config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'DREAMTEAM');
define('DB_NAME', 'pss_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>