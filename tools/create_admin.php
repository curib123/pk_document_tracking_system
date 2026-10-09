<?php
if (PHP_SAPI !== 'cli') exit("CLI only\n");
if ($argc < 2) exit("Usage: php tools/create_admin.php username\n");
$username=(string)$argv[1];
if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$username)) exit("Invalid username\n");
echo "First name: ";$first=trim(fgets(STDIN));
echo "Last name: ";$last=trim(fgets(STDIN));
echo "Position: ";$position=trim(fgets(STDIN))?:'Administrator';
echo "Password (min 12 characters, input visible): ";$password=rtrim(fgets(STDIN),"\r\n");
if (!$first || !$last || strlen($password)<12) exit("Name and 12+ character password required\n");
$db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USERNAME')?:'root',
  getenv('DB_PASSWORD')?:'',getenv('DB_DATABASE')?:'pk_dts',(int)(getenv('DB_PORT')?:3306));
$role=$db->query("SELECT id FROM roles WHERE name='Administrator' AND active=1 LIMIT 1")->fetch_assoc();
if (!$role) exit("Seed roles first.\n");
$stmt=$db->prepare('INSERT INTO users (username,first_name,last_name,position_title,role_id,password_hash,require_password_change,active) VALUES (?,?,?,?,?,?,0,1)');
$hash=password_hash($password,PASSWORD_DEFAULT);$id=(int)$role['id'];
$stmt->bind_param('ssssis',$username,$first,$last,$position,$id,$hash);
if (!$stmt->execute()) exit("Could not create user.\n");
echo "Admin created.\n";
