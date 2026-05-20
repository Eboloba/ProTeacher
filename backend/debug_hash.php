<?php
// debug_hash.php — проверяем хеш пароля

$password = 'password'; // ← именно так, 8 букв, все строчные
$hash = '$2y$10$7Zf0M1I6Bf2G1j5AOEJ2ReYw0Q7MYX6fGQX9zY8qjAojwM8gHTMEO';

echo "=== Password Verify Test ===\n";
echo "Password: '$password' (length: " . strlen($password) . ")\n";
echo "Hash: '$hash' (length: " . strlen($hash) . ")\n";
echo "Verify result: " . (password_verify($password, $hash) ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Password algo info: " . print_r(password_get_info($hash), true) . "\n";
?>