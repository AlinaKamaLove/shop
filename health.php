<?php
/**
 * Проверка после переноса на Beget. Откройте в браузере: https://ваш-сайт.ru/health.php
 * Убедитесь, что нет ошибок, затем удалите этот файл с сервера.
 */
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
echo "PHP: " . PHP_VERSION . "\n\n";

require_once __DIR__ . '/connect.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Подключение к MySQL: OK\n";
    echo "DB_HOST=" . DB_HOST . " DB_NAME=" . DB_NAME . " DB_USER=" . DB_USER . "\n\n";

    $tables = ['Users', 'Products', 'Orders'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
        $ok = $stmt && $stmt->fetch();
        echo ($ok ? '[OK] ' : '[НЕТ] ') . "таблица `{$t}`\n";
        if ($ok && $t === 'Products') {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM Products')->fetchColumn();
            echo "     записей в Products: {$n}\n";
        }
    }
} catch (Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

echo "\nФайлы в каталоге сайта:\n";
foreach (glob(__DIR__ . '/*.php') as $f) {
    echo ' - ' . basename($f) . "\n";
}

echo '</pre>';
