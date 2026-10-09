<?php
// Create the first administrator from a CLI without storing a sample password.
if (PHP_SAPI !== 'cli') exit("CLI only\n");
if ($argc < 3) exit("Usage: php tools/create_admin.php username email\n");
$root = dirname(__DIR__);
require $root . '/application/bootstrap.php';
$mysqli = new mysqli('127.0.0.1', 'root', '', 'pk_dts', 3306);
if ($mysqli->connect_errno) exit("Database connection failed.\n");
fwrite(STDOUT, "Full name: ");
$name = trim(fgets(STDIN));
fwrite(STDOUT, "Password (min 12 chars; input is visible): ");
$password = rtrim(fgets(STDIN), "\r\n");
if (!$name || strlen($password) < 12) exit("Name and 12+ character password required.\n");
$role = $mysqli->query("SELECT id FROM roles WHERE name='super_admin'")->fetch_assoc();
if (!$role) exit("Run database/seed.sql first.\n");
$stmt = $mysqli->prepare('INSERT INTO users (role_id,name,username,email,password_hash) VALUES (?,?,?,?,?)');
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt->bind_param('issss', $role['id'], $name, $argv[1], $argv[2], $hash);
if (!$stmt->execute()) exit("Could not create account (check unique username/email).\n");
echo "Administrator created.\n";
