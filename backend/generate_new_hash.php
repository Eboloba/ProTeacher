<?php
// generate_new_hash.php — создаёт рабочий хеш для пароля "password"

$password = 'password'; // ← пароль для входа
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "=== NEW HASH FOR PASSWORD 'password' ===\n";
echo "Password: $password\n";
echo "New hash: $hash\n";
echo "Length: " . strlen($hash) . "\n";
echo "Verify test: " . (password_verify($password, $hash) ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "\n=== SQL UPDATE QUERY ===\n";
echo "UPDATE users SET password_hash = '$hash' WHERE email = 'admin@example.com';\n";
?>