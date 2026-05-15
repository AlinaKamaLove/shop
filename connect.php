<?php

define('DB_HOST', 'localhost');

define('DB_NAME', 'nairak4l_shop');
define('DB_USER', 'nairak4l_shop');
define('DB_PASS', 'Nara2006');


function ensure_schema_column(PDO $pdo, $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '1060') !== false
            || stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'already exists') !== false) {
            return;
        }
        throw $e;
    }
}

function ensure_products_extra_columns(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_schema_column($pdo, 'ALTER TABLE Products ADD COLUMN gallery TEXT NULL DEFAULT NULL');
    ensure_schema_column($pdo, "ALTER TABLE Products ADD COLUMN sizes VARCHAR(512) NOT NULL DEFAULT ''");
    ensure_schema_column($pdo, "ALTER TABLE Products ADD COLUMN colors VARCHAR(512) NOT NULL DEFAULT ''");
    ensure_schema_column($pdo, "ALTER TABLE Products ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'accessories'");
}

/**
 * Декодирует поле gallery (JSON-массив путей; допускается двойное кодирование).
 *
 * @return string[]
 */
function product_catalog_decode_gallery($raw) {
    $out = array();
    if ($raw === null || $raw === '') {
        return $out;
    }
    if (!is_string($raw)) {
        return $out;
    }
    $galRaw = $raw;
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $decoded = json_decode($galRaw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $out[] = $item;
                }
            }
            return $out;
        }
        if (is_string($decoded) && trim($decoded) !== '' && $decoded !== $galRaw) {
            $galRaw = $decoded;
            continue;
        }
        break;
    }
    return $out;
}

function product_catalog_normalize_img_key($path) {
    $s = trim(str_replace('\\', '/', (string) $path));
    if ($s === '') {
        return '';
    }
    $s = preg_replace('#^(?:\./)+#', '', $s);
    $s = ltrim($s, '/');
    return strtolower($s);
}

/**
 * Кадры для карусели: сначала обложка (photo), затем галерея, без дубликатов по нормализованному URL/пути.
 *
 * @return string[]
 */
function product_catalog_slides(array $p) {
    $gal = product_catalog_decode_gallery(isset($p['gallery']) ? $p['gallery'] : null);
    $seen = array();
    $slides = array();
    $add = function ($path) use (&$slides, &$seen) {
        $path = trim((string) $path);
        if ($path === '') {
            return;
        }
        $k = product_catalog_normalize_img_key($path);
        if ($k === '') {
            $k = md5($path);
        }
        if (isset($seen[$k])) {
            return;
        }
        $seen[$k] = true;
        $slides[] = $path;
    };
    $main = trim((string) (isset($p['photo']) ? $p['photo'] : ''));
    if ($main !== '') {
        $add($main);
    }
    foreach ($gal as $g) {
        $add($g);
    }
    return $slides;
}

// Функция для подключения к БД
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        try {
            ensure_products_extra_columns($pdo);
        } catch (Throwable $schemaErr) {
            // На части хостингов ALTER без прав или схема уже полная — страницы не должны падать целиком.
        }
        return $pdo;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '1045') !== false) {
            die(
                'Ошибка MySQL 1045: доступ запрещён. В файле connect.php задайте реальные '
                . 'DB_NAME, DB_USER и DB_PASS из панели Beget (раздел MySQL). '
                . 'Сейчас указан пользователь &quot;' . htmlspecialchars(DB_USER, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '&quot;. '
                . 'Технически: ' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }
        die('Ошибка подключения: ' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}

/**
 * Корзина: список позиций [ ['product_id'=>int, 'size'=>string, 'color'=>string, 'quantity'=>int], ... ].
 * Старый формат из куки { "id": qty } преобразуется автоматически.
 *
 * @param array|null $cart
 */
function cart_session_normalize(&$cart) {
    if (!is_array($cart)) {
        $cart = [];
        return;
    }
    if ($cart === []) {
        return;
    }
    $first = reset($cart);
    if (is_array($first) && isset($first['product_id'])) {
        $out = [];
        foreach ($cart as $row) {
            if (!is_array($row) || !isset($row['product_id'])) {
                continue;
            }
            $out[] = [
                'product_id' => (int) $row['product_id'],
                'size' => isset($row['size']) ? (string) $row['size'] : '',
                'color' => isset($row['color']) ? (string) $row['color'] : '',
                'quantity' => isset($row['quantity']) ? max(1, (int) $row['quantity']) : 1,
            ];
        }
        $cart = $out;
        return;
    }
    $out = [];
    foreach ($cart as $pid => $qty) {
        if (is_array($qty)) {
            continue;
        }
        $pid = (int) $pid;
        if ($pid <= 0) {
            continue;
        }
        $q = (int) $qty;
        if ($q < 1) {
            continue;
        }
        $out[] = [
            'product_id' => $pid,
            'size' => '',
            'color' => '',
            'quantity' => $q,
        ];
    }
    $cart = $out;
}

function cart_line_signature($productId, $size, $color) {
    return (int) $productId . "\x1e" . (string) $size . "\x1e" . (string) $color;
}

function cart_total_items(array $cart) {
    $n = 0;
    foreach ($cart as $row) {
        if (is_array($row) && isset($row['quantity'])) {
            $n += (int) $row['quantity'];
        }
    }
    return $n;
}

function cart_quantity_for_product(array $cart, $productId) {
    $pid = (int) $productId;
    $n = 0;
    foreach ($cart as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int) (isset($row['product_id']) ? $row['product_id'] : 0) === $pid) {
            $n += (int) (isset($row['quantity']) ? $row['quantity'] : 0);
        }
    }
    return $n;
}

function cart_add_or_merge(array &$cart, $productId, $addQty, $size, $color, $stockLimit) {
    cart_session_normalize($cart);
    $productId = (int) $productId;
    $addQty = max(1, (int) $addQty);
    $size = trim((string) $size);
    $color = trim((string) $color);
    $stockLimit = (int) $stockLimit;
    if ($productId <= 0 || $stockLimit < 1 || $addQty < 1) {
        return false;
    }
    $curTotal = cart_quantity_for_product($cart, $productId);
    if ($curTotal + $addQty > $stockLimit) {
        return false;
    }
    $sig = cart_line_signature($productId, $size, $color);
    foreach ($cart as $k => $row) {
        if (!is_array($row)) {
            continue;
        }
        $rs = isset($row['size']) ? (string) $row['size'] : '';
        $rc = isset($row['color']) ? (string) $row['color'] : '';
        if (cart_line_signature(isset($row['product_id']) ? $row['product_id'] : 0, $rs, $rc) === $sig) {
            $cart[$k]['quantity'] = (int) (isset($row['quantity']) ? $row['quantity'] : 0) + $addQty;
            return true;
        }
    }
    $cart[] = [
        'product_id' => $productId,
        'size' => $size,
        'color' => $color,
        'quantity' => $addQty,
    ];
    return true;
}

/**
 * @param string[] $allowedSizes
 * @param string[] $allowedColors
 */
function cart_variant_allowed($allowedSizes, $allowedColors, $size, $color) {
    $size = trim((string) $size);
    $color = trim((string) $color);
    if ($allowedSizes !== []) {
        if ($size === '' || !in_array($size, $allowedSizes, true)) {
            return null;
        }
    } else {
        $size = '';
    }
    if ($allowedColors !== []) {
        if ($color === '' || !in_array($color, $allowedColors, true)) {
            return null;
        }
    } else {
        $color = '';
    }
    return [$size, $color];
}

function cart_persist_cookie(array $cart) {
    setcookie('cart', json_encode($cart, JSON_UNESCAPED_UNICODE), time() + 2592000, '/');
}
?>