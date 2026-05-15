<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();


function getValue($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

function admin_safe_image_relpath($path) {
    if ($path === '' || $path === null) {
        return null;
    }
    if (strpos($path, '..') !== false) {
        return null;
    }
    if (strncmp($path, 'images/', 7) !== 0) {
        return null;
    }
    return $path;
}

function admin_image_fs_path($relPath) {
    $p = admin_safe_image_relpath($relPath);
    if ($p === null) {
        return null;
    }
    return __DIR__ . '/' . $p;
}

function admin_ensure_images_directory() {
    $dir = __DIR__ . '/images';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function admin_unlink_image_safe($path) {
    $full = admin_image_fs_path($path);
    if ($full !== null && is_file($full)) {
        unlink($full);
    }
}

function admin_normalize_upload_field_array($key) {
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
        return;
    }
    if (!isset($_FILES[$key]['name'])) {
        return;
    }
    if (!is_array($_FILES[$key]['name'])) {
        $_FILES[$key] = array(
            'name' => array($_FILES[$key]['name']),
            'type' => array(isset($_FILES[$key]['type']) ? $_FILES[$key]['type'] : ''),
            'tmp_name' => array($_FILES[$key]['tmp_name']),
            'error' => array((int)$_FILES[$key]['error']),
            'size' => array(isset($_FILES[$key]['size']) ? (int)$_FILES[$key]['size'] : 0),
        );
    }
}

function admin_gallery_upload_err_text($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Галерея: файл слишком большой (лимит PHP upload_max_filesize / post_max_size)';
        case UPLOAD_ERR_PARTIAL:
            return 'Галерея: загрузка прервана';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Галерея: нет временной папки на сервере';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Галерея: не удалось записать на диск';
        case UPLOAD_ERR_EXTENSION:
            return 'Галерея: загрузка остановлена расширением PHP';
        default:
            return 'Галерея: ошибка загрузки файла';
    }
}

function admin_detect_image_mime($tmp, $fallbackType) {
    if (is_string($tmp) && $tmp !== '' && is_file($tmp)) {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($tmp);
            if ($m !== false && $m !== '') {
                return $m;
            }
        }
        if (class_exists('finfo')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $m = @finfo_file($f, $tmp);
                finfo_close($f);
                if ($m !== false && $m !== '') {
                    return $m;
                }
            }
        }
    }
    return is_string($fallbackType) ? $fallbackType : '';
}

function admin_decode_gallery($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $d)));
}

function admin_gallery_json(array $paths) {
    return json_encode(array_values($paths), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function admin_map_gallery_files_upload_key() {
    if (!isset($_FILES['gallery_files']) && isset($_FILES['gallery_files[]'])) {
        $_FILES['gallery_files'] = $_FILES['gallery_files[]'];
        unset($_FILES['gallery_files[]']);
    }
}

function admin_gallery_count_nonempty_slots($fieldName) {
    admin_map_gallery_files_upload_key();
    admin_normalize_upload_field_array($fieldName);
    if (!isset($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['name'])) {
        return 0;
    }
    $names = $_FILES[$fieldName]['name'];
    if (!is_array($names)) {
        return trim((string) $names) !== '' ? 1 : 0;
    }
    $c = 0;
    foreach ($names as $nm) {
        if (trim((string) $nm) !== '') {
            $c++;
        }
    }
    return $c;
}

function admin_process_gallery_uploads($fieldName, &$errors, $prefix = 'g_') {
    admin_map_gallery_files_upload_key();
    admin_normalize_upload_field_array($fieldName);
    $submitted = admin_gallery_count_nonempty_slots($fieldName);
    $out = array();
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['name'])) {
        return $out;
    }
    admin_ensure_images_directory();
    $baseDir = __DIR__ . '/images';
    if (!is_dir($baseDir) || !is_writable($baseDir)) {
        $errors['gallery'] = 'Галерея: папка images недоступна для записи';
        return $out;
    }
    $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg', 'image/x-png');
    $n = count($_FILES[$fieldName]['name']);
    for ($i = 0; $i < $n; $i++) {
        $err = isset($_FILES[$fieldName]['error'][$i]) ? (int)$_FILES[$fieldName]['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors['gallery'] = admin_gallery_upload_err_text($err);
            continue;
        }
        $tmp = $_FILES[$fieldName]['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) {
            $errors['gallery'] = 'Галерея: файл не прошёл проверку is_uploaded_file()';
            continue;
        }
        $fallbackType = isset($_FILES[$fieldName]['type'][$i]) ? $_FILES[$fieldName]['type'][$i] : '';
        $file_type = admin_detect_image_mime($tmp, $fallbackType);
        if ($file_type === 'image/jpg') {
            $file_type = 'image/jpeg';
        }
        if (!in_array($file_type, $allowed, true)) {
            $errors['gallery'] = 'Галерея: допустимы JPG, PNG, GIF, WebP';
            continue;
        }
        $sz = isset($_FILES[$fieldName]['size'][$i]) ? (int)$_FILES[$fieldName]['size'][$i] : 0;
        if ($sz > 5 * 1024 * 1024) {
            $errors['gallery'] = 'Галерея: файл не более 5 МБ';
            continue;
        }
        $extension = pathinfo($_FILES[$fieldName]['name'][$i], PATHINFO_EXTENSION);
        $relPath = 'images/' . $prefix . uniqid('', true) . '.' . $extension;
        $dest = __DIR__ . '/' . $relPath;
        if (move_uploaded_file($tmp, $dest)) {
            $out[] = $relPath;
        } else {
            $errors['gallery'] = 'Галерея: не удалось сохранить файл в папку images';
        }
    }
    if ($submitted > 0 && count($out) === 0 && !isset($errors['gallery'])) {
        $errors['gallery'] = 'Галерея: выбранные файлы не сохранились. Проверьте формат (JPG, PNG, GIF, WebP), размер до 5 МБ и лимиты PHP: max_file_uploads=' . ini_get('max_file_uploads') . ', post_max_size=' . ini_get('post_max_size');
    }
    return $out;
}

function admin_product_category_labels() {
    return array(
        'clothing' => 'Одежда',
        'shoes' => 'Обувь',
        'accessories' => 'Аксессуары',
    );
}

function admin_normalize_product_category($postVal) {
    $labels = admin_product_category_labels();
    $v = is_string($postVal) ? trim($postVal) : '';
    if ($v !== '' && array_key_exists($v, $labels)) {
        return $v;
    }
    return 'accessories';
}

// Проверка прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    die("Недостаточно прав для доступа к этой странице");
}

require_once __DIR__ . '/connect.php';
$pdo = getDB();

admin_ensure_images_directory();

$errors = array();
$success = '';

if (!empty($_SESSION['admin_flash_success'])) {
    $success = $_SESSION['admin_flash_success'];
    unset($_SESSION['admin_flash_success']);
}


if (isset($_POST['change_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];
    
    try {
        $stmt = $pdo->prepare("UPDATE Users SET user_role = ? WHERE user_id = ?");
        $stmt->execute(array($newRole, $userId));
        echo json_encode(array('success' => true));
    } catch (PDOException $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    exit();
}

// AJAX-удаление товара (остаёмся на вкладке «Товары» без полной перезагрузки)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_product'])) {
    header('Content-Type: application/json; charset=utf-8');
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($productId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Неверный товар'));
        exit();
    }
    try {
        $stmt = $pdo->prepare("SELECT photo, gallery FROM Products WHERE product_id = ?");
        $stmt->execute(array($productId));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            echo json_encode(array('success' => false, 'message' => 'Товар не найден'));
            exit();
        }
        $stmt = $pdo->prepare("DELETE FROM Products WHERE product_id = ?");
        $stmt->execute(array($productId));
        if (!empty($product['photo'])) {
            admin_unlink_image_safe($product['photo']);
        }
        foreach (admin_decode_gallery(isset($product['gallery']) ? $product['gallery'] : null) as $gp) {
            admin_unlink_image_safe($gp);
        }
        echo json_encode(array('success' => true, 'message' => 'Товар успешно удалён'));
    } catch (PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Ошибка при удалении: ' . $e->getMessage()));
    }
    exit();
}

// Обработка добавления нового товара
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $sizes = trim($_POST['sizes'] ?? '');
    $colors = trim($_POST['colors'] ?? '');
    $category = admin_normalize_product_category($_POST['category'] ?? '');
    
    // Валидация данных
    if (empty($name)) $errors["name"] = "Название товара обязательно";
    if (empty($price) || $price <= 0) $errors['price'] = "Укажите корректную цену";
    if ($stock < 0) $errors['stock'] = "Укажите корректное количество";
    
    $keepGallery = admin_decode_gallery($_POST['old_gallery'] ?? '[]');
    $galleryUploaded = array();
    $galleryJson = admin_gallery_json(array());
    
    if (empty($errors)) {
        $galleryUploaded = admin_process_gallery_uploads('gallery_files', $errors);
    }
    
    // Обложка (отдельное поле)
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        
        // Проверяем тип файла
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg', 'image/x-png');
        $file_type = mime_content_type($file['tmp_name']);
        if ($file_type === 'image/jpg') {
            $file_type = 'image/jpeg';
        }
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['photo'] = "Допустимы только изображения JPG, PNG или GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors['photo'] = "Файл слишком большой (максимум 5MB)";
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $upload_path = 'images/' . $filename;
            $dest = __DIR__ . '/' . $upload_path;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $photo = $upload_path;
            } else {
                $errors['photo'] = "Ошибка при загрузке файла";
            }
        }
    }
    
    // Нет обложки, но галерея успешно загрузилась — первый кадр становится обложкой
    if ($photo === '' && !empty($galleryUploaded) && empty($errors)) {
        $photo = $galleryUploaded[0];
        $galleryUploaded = array_values(array_slice($galleryUploaded, 1));
    }
    
    if ($photo === '') {
        $errors['photo'] = "Загрузите обложку или добавьте хотя бы одно фото в галерею";
    }
    
    if (empty($errors)) {
        $galleryJson = admin_gallery_json(array_merge($keepGallery, $galleryUploaded));
    }
    
    if (!empty($errors)) {
        if (!empty($photo)) {
            admin_unlink_image_safe($photo);
        }
        foreach ($galleryUploaded as $gp) {
            admin_unlink_image_safe($gp);
        }
    }
    
    if (empty($errors)) {
        try {   
            $stmt = $pdo->prepare("INSERT INTO Products (name, description, price, stock, photo, category, sizes, colors, gallery, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute(array($name, $description, $price, $stock, $photo, $category, $sizes, $colors, $galleryJson));
        
            $_SESSION['admin_flash_success'] = 'Товар успешно добавлен!';
            header('Location: admin.php?tab=add-product');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = "Ошибка при добавлении товара: " . $e->getMessage();
        
            if (!empty($photo)) {
                admin_unlink_image_safe($photo);
            }
            foreach ($galleryUploaded as $gp) {
                admin_unlink_image_safe($gp);
            }
        }    
    }
}

// Обработка обновления товара
if (isset($_POST['update_product'])) {
    $productId = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $sizes = trim($_POST['sizes'] ?? '');
    $colors = trim($_POST['colors'] ?? '');
    $category = admin_normalize_product_category($_POST['category'] ?? '');
    $oldPhoto = trim($_POST['old_photo']);
    
    if (empty($name)) $errors["name"] = "Название товара обязательно";
    if (empty($price) || $price <= 0) $errors['price'] = "Укажите корректную цену";
    if ($stock < 0) $errors['stock'] = "Укажите корректное количество";
    
    $photo = $oldPhoto;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['photo'] = "Допустимы только изображения JPG, PNG или GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors['photo'] = "Файл слишком большой (максимум 5MB)";
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $upload_path = 'images/' . $filename;
            $dest = __DIR__ . '/' . $upload_path;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                if (!empty($oldPhoto)) {
                    admin_unlink_image_safe($oldPhoto);
                }
                $photo = $upload_path;
            } else {
                $errors['photo'] = "Ошибка при загрузке файла";
            }
        }
    }
    
    $galleryUploaded = [];
    $galleryJson = admin_gallery_json([]);
    $toRemoveGal = [];
    
    if (empty($errors)) {
        $stmtGal = $pdo->prepare("SELECT gallery FROM Products WHERE product_id = ?");
        $stmtGal->execute([$productId]);
        $prevG = admin_decode_gallery($stmtGal->fetchColumn());
        $keep = admin_decode_gallery($_POST['old_gallery'] ?? '[]');
        $toRemoveGal = array_values(array_diff($prevG, $keep));
        $galleryUploaded = admin_process_gallery_uploads('gallery_files', $errors);
        if (empty($errors)) {
            $galleryJson = admin_gallery_json(array_merge($keep, $galleryUploaded));
        }
    }
    
    if (!empty($errors)) {
        foreach ($galleryUploaded as $gp) {
            admin_unlink_image_safe($gp);
        }
    }
    
    if (empty($errors)) {
        try {   
            $stmt = $pdo->prepare("UPDATE Products SET name = ?, description = ?, price = ?, stock = ?, photo = ?, category = ?, sizes = ?, colors = ?, gallery = ? WHERE product_id = ?");
            $stmt->execute(array($name, $description, $price, $stock, $photo, $category, $sizes, $colors, $galleryJson, $productId));
            foreach ($toRemoveGal as $rm) {
                admin_unlink_image_safe($rm);
            }
            $_SESSION['admin_flash_success'] = 'Товар успешно обновлен!';
            header('Location: admin.php?tab=products');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = "Ошибка при обновлении товара: " . $e->getMessage();
            foreach ($galleryUploaded as $gp) {
                admin_unlink_image_safe($gp);
            }
        }    
    }
}

// Обработка удаления товара
if (isset($_GET['delete_product'])) {
    $productId = (int)$_GET['delete_product'];
    
    try {
        $stmt = $pdo->prepare("SELECT photo, gallery FROM Products WHERE product_id = ?");
        $stmt->execute(array($productId));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM Products WHERE product_id = ?");
        $stmt->execute(array($productId));
        
        if (!empty($product['photo'])) {
            admin_unlink_image_safe($product['photo']);
        }
        foreach (admin_decode_gallery($product['gallery'] ?? null) as $gp) {
            admin_unlink_image_safe($gp);
        }
        
        $_SESSION['message'] = 'Товар успешно удален';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Ошибка при удалении товара: ' . $e->getMessage();
    }
    
    header('Location: admin.php?tab=products');
    exit();
}

// Получение списка пользователей
try {
    $stmt = $pdo->query("SELECT user_id, username, email, user_role, created_at FROM Users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении пользователей: " . $e->getMessage());
}

// Получение списка товаров
try {
    $stmt = $pdo->query("SELECT * FROM Products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении товаров: " . $e->getMessage());
}

// Подсчет товаров в корзине
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    cart_session_normalize($_SESSION['cart']);
}
$cart_count = cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []);

$adminTabs = array('users', 'products', 'add-product');
$activeTab = isset($_GET['tab']) ? (string) $_GET['tab'] : null;
if (!in_array($activeTab, $adminTabs, true)) {
    $activeTab = null;
}
if ($activeTab === null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_product']) || isset($_POST['update_product']))) {
        $activeTab = 'add-product';
    } else {
        $activeTab = 'products';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | VELURA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1a1a1d;
            --card: #232329;
            --soft: #2b2b31;
            --text: #e8e8e8;
            --muted: #a7a7a7;
            --accent: #c8a27a;
            --border: rgba(255,255,255,0.08);
            --transition: all .3s ease;
            --primary-color: #c8a27a;
            --secondary-color: #2b2b31;
            --dark-color: #0d0d0f;
            --light-color: #1a1a1d;
            --accent-color: #d4b896;
            --text-color: #e8e8e8;
            --text-light: #a7a7a7;
            --err: #e85d5d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        
        body {
            background-color: var(--light-color);
            color: var(--text-color);
            line-height: 1.7;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        header {
            background: rgba(26,26,29,0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            padding: 1.1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-color);
            text-decoration: none;
            letter-spacing: 3px;
            font-family: 'Playfair Display', serif;
            text-transform: uppercase;
            transition: var(--transition);
        }
        
        .logo:hover {
            opacity: 0.85;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
        }
        
        nav ul li {
            margin-left: 1.8rem;
            position: relative;
        }
        
        nav ul li a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            padding: 0.35rem 0;
            position: relative;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        nav ul li a:hover {
            color: var(--text-color);
            background: transparent;
        }
        
        .cart-count {
            position: absolute;
            top: -6px;
            right: -12px;
            background-color: var(--accent);
            color: #000;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }
        
        .admin-section {
            padding: 4rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-family: 'Playfair Display', serif;
            color: var(--text-color);
        }
        
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .tab-button {
            padding: 0.8rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-light);
            transition: var(--transition);
            position: relative;
        }
        
        .tab-button.active {
            color: var(--primary-color);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-content h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            color: var(--text-color);
            margin-bottom: 1.25rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            background-color: var(--card);
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background-color: var(--soft);
            font-weight: 600;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        tr:hover {
            background-color: rgba(255,255,255,0.03);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--accent);
            color: #111;
            padding: 0.6rem 1.2rem;
            border: 1px solid var(--accent);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .btn:hover {
            filter: brightness(1.08);
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .admin-actions-cell {
            vertical-align: middle;
            min-width: 148px;
        }
        .admin-row-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: stretch;
        }
        .btn-row-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            font-family: inherit;
            transition: var(--transition);
            border: 1px solid transparent;
            box-sizing: border-box;
        }
        .btn-row-action i {
            font-size: 12px;
            opacity: 0.9;
        }
        .btn-edit-row {
            background: linear-gradient(165deg, rgba(200, 162, 122, 0.2) 0%, rgba(200, 162, 122, 0.08) 100%);
            border-color: rgba(200, 162, 122, 0.5);
            color: #e8d4bc;
        }
        .btn-edit-row:hover {
            background: rgba(200, 162, 122, 0.28);
            border-color: var(--accent);
            color: #fff5e8;
            box-shadow: 0 6px 20px rgba(200, 162, 122, 0.18);
        }
        .btn-delete-row {
            background: rgba(232, 93, 93, 0.12);
            border-color: rgba(232, 93, 93, 0.4);
            color: #f5b0b0;
        }
        .btn-delete-row:hover {
            background: rgba(232, 93, 93, 0.22);
            border-color: #e85d5d;
            color: #ffe0e0;
            box-shadow: 0 6px 20px rgba(232, 93, 93, 0.18);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-field-heading {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--soft);
            color: var(--text-color);
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(200, 162, 122, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            background-color: var(--soft);
            color: var(--accent);
            text-align: center;
            font-weight: 600;
            border: 1px solid var(--border);
        }
        
        .message.error {
            background-color: rgba(232, 93, 93, 0.12);
            color: #f0a0a0;
            border-color: rgba(232, 93, 93, 0.3);
        }
        
        .message.success {
            background-color: rgba(107, 191, 138, 0.12);
            color: #8fd4a8;
            border-color: rgba(107, 191, 138, 0.3);
        }
       footer {
    background: #111;
    color: #aaa;
    padding: 70px 0 40px;
    margin-top: 70px;
}

/* GRID */
.footer-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;

    text-align: center;
    justify-items: center;
    align-items: start;
}

/* TITLES */
.footer-grid h3 {
    font-family: 'Playfair Display', serif;
    color: #fff;
    margin-bottom: 14px;
    font-size: 15px;
    letter-spacing: 1px;
}

/* LINKS */
.footer-grid a {
    color: #aaa;
    text-decoration: none;
    font-size: 13px;
    display: block;
    margin-bottom: 8px;
    transition: 0.3s ease;
}

.footer-grid a:hover {
    color: #fff;
}

/* FOOTER BOTTOM */
.footer-bottom {
    text-align: center;
    margin-top: 40px;
    font-size: 12px;
    color: #777;
}

        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #777;
            font-size: 0.9rem;
        }
        
        #image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .visually-hidden-input {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }
        
        .file-dropzone {
            position: relative;
            border: 2px dashed var(--border);
            border-radius: 14px;
            padding: 28px 20px;
            text-align: center;
            background: var(--soft);
            transition: var(--transition);
            margin-top: 8px;
        }
        
        .file-dropzone:hover,
        .file-dropzone:focus-within {
            border-color: var(--accent);
            background: rgba(200, 162, 122, 0.06);
        }
        
        .file-dropzone-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: var(--muted);
            font-size: 13px;
        }
        
        .file-dropzone-label i {
            font-size: 28px;
            color: var(--accent);
        }
        
        .file-dropzone-label small {
            font-size: 11px;
            opacity: 0.85;
        }
        
        .file-dropzone input[type="file"].file-dropzone-file {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            min-height: 120px;
            margin: 0;
            padding: 0;
            opacity: 0.01;
            cursor: pointer;
            z-index: 12;
            font-size: 16px;
        }
        
        .file-dropzone .file-dropzone-label {
            pointer-events: none;
            position: relative;
            z-index: 1;
            cursor: default;
        }
        
        label.file-dropzone-gallery-hit {
            display: block;
            position: relative;
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 120px;
            cursor: pointer;
        }
        
        .gallery-thumb-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        
        .gallery-thumb {
            position: relative;
            width: 72px;
            height: 72px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-thumb .rm {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border: none;
            border-radius: 50%;
            background: rgba(0,0,0,0.65);
            color: #fff;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
        .gallery-thumb .rm:hover {
            background: var(--err);
        }
        
        .preview-container {
            margin-top: 10px;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 1.5rem;
                justify-content: center;
            }
            
            nav ul li {
                margin: 0 0.8rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">VELURA</a>
                <nav>
                    <ul>
                        <li><a href="index.php">Главная</a></li>
                        <li><a href="profile.php">Профиль</a></li>
                        <li><a href="products.php">Каталог</a></li>
                        <li><a href="admin.php?tab=products">Админ</a></li>
                        <li>
                            <a href="cart.php">Корзина</a>
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </li>
                        <li><a href="logout.php">Выход</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="admin-section">
            <div class="section-title">
                <h2>Админ-панель</h2>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors['database'])): ?>
                <div class="message error"><?php echo htmlspecialchars($errors['database']); ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button type="button" class="tab-button<?php echo $activeTab === 'users' ? ' active' : ''; ?>" data-tab="users">Пользователи</button>
                <button type="button" class="tab-button<?php echo $activeTab === 'products' ? ' active' : ''; ?>" data-tab="products">Товары</button>
                <button type="button" class="tab-button<?php echo $activeTab === 'add-product' ? ' active' : ''; ?>" data-tab="add-product"><?php echo isset($_POST['product_id']) && $_POST['product_id'] !== '' ? 'Редактировать товар' : 'Добавить товар'; ?></button>
            </div>
            
            <div id="users" class="tab-content<?php echo $activeTab === 'users' ? ' active' : ''; ?>">
                <h3>Список пользователей</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя пользователя</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Дата регистрации</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <select name="new_role" class="role-select" data-user-id="<?php echo $user['user_id']; ?>">
                                    <option value="admin" <?php echo $user['user_role'] == 'admin' ? 'selected' : ''; ?>>Админ</option>
                                    <option value="registered_user" <?php echo $user['user_role'] == 'registered_user' ? 'selected' : ''; ?>>Пользователь</option>
                                </select>
                                <span class="role-status" style="margin-left: 10px; display: none;">
                                    <i class="fas fa-check" style="color: green;"></i>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="products" class="tab-content<?php echo $activeTab === 'products' ? ' active' : ''; ?>">
                <h3>Список товаров</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Размеры</th>
                            <th>Цвета</th>
                            <th>Цена</th>
                            <th>Остаток</th>
                            <th>Изображение</th>
                            <th>Дата добавления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['product_id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php
                            $ck = $product['category'] ?? 'accessories';
                            $cm = admin_product_category_labels();
                            echo htmlspecialchars(isset($cm[$ck]) ? $cm[$ck] : $ck);
                            ?></td>
                            <td><?php
                            $sz = (string)($product['sizes'] ?? '');
                            echo htmlspecialchars(strlen($sz) > 40 ? substr($sz, 0, 40) . '…' : $sz);
                            ?></td>
                            <td><?php
                            $cl = (string)($product['colors'] ?? '');
                            echo htmlspecialchars(strlen($cl) > 40 ? substr($cl, 0, 40) . '…' : $cl);
                            ?></td>
                            <td><?php echo number_format($product['price'], 2); ?> ₽</td>
                            <td><?php echo $product['stock']; ?></td>
                            <td>
                                <?php if (!empty($product['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['photo']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="current-image" style="max-width: 100px;">
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($product['created_at'])); ?></td>
                            <td class="admin-actions-cell">
                                <div class="admin-row-actions">
                                <button type="button" class="btn-row-action btn-edit-row edit-product" 
                                    data-id="<?php echo $product['product_id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                                    data-description="<?php echo htmlspecialchars($product['description']); ?>" 
                                    data-price="<?php echo $product['price']; ?>" 
                                    data-stock="<?php echo $product['stock']; ?>" 
                                    data-photo="<?php echo htmlspecialchars($product['photo']); ?>"
                                    data-category="<?php echo htmlspecialchars($product['category'] ?? 'accessories', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-sizes="<?php echo htmlspecialchars($product['sizes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-colors="<?php echo htmlspecialchars($product['colors'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-gallery="<?php echo htmlspecialchars(json_encode(admin_decode_gallery($product['gallery'] ?? null), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                ><i class="fas fa-pen" aria-hidden="true"></i> Редактировать</button>
                                <button type="button" class="btn-row-action btn-delete-row delete-product" data-id="<?php echo (int)$product['product_id']; ?>"><i class="fas fa-trash-alt" aria-hidden="true"></i> Удалить</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="add-product" class="tab-content<?php echo $activeTab === 'add-product' ? ' active' : ''; ?>">
                <h3><?php echo isset($_POST['product_id']) ? 'Редактировать товар' : 'Добавить новый товар'; ?></h3>
                <?php
                $selCategory = getValue($_POST, 'category', 'accessories');
                if (!array_key_exists($selCategory, admin_product_category_labels())) {
                    $selCategory = 'accessories';
                }
                ?>
                <form method="post" enctype="multipart/form-data" id="product-form">
                    <input type="hidden" name="product_id" id="product_id" value="<?php echo htmlspecialchars(getValue($_POST, 'product_id')); ?>">
                    <input type="hidden" name="old_photo" id="old_photo" value="<?php echo htmlspecialchars(getValue($_POST, 'old_photo')); ?>">
                    <input type="hidden" name="old_gallery" id="old_gallery" value="<?php echo htmlspecialchars(getValue($_POST, 'old_gallery', '[]'), ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Название товара *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars(getValue($_POST, 'name')); ?>" required>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="error"><?php echo htmlspecialchars($errors['name']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="price">Цена (₽) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars(getValue($_POST, 'price')); ?>" required>
                            <?php if (!empty($errors['price'])): ?>
                                <div class="error"><?php echo htmlspecialchars($errors['price']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock">Количество на складе *</label>
                        <input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars(getValue($_POST, 'stock', '0')); ?>" required style="max-width:280px;">
                        <?php if (!empty($errors['stock'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['stock']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="category">Категория на сайте *</label>
                        <select id="category" name="category" required>
                            <?php foreach (admin_product_category_labels() as $cval => $clabel): ?>
                                <option value="<?php echo htmlspecialchars($cval); ?>"<?php echo $selCategory === $cval ? ' selected' : ''; ?>><?php echo htmlspecialchars($clabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--muted); font-size: 0.85rem;">Те же разделы, что в каталоге (фильтры «Одежда», «Обувь», «Аксессуары»)</small>
                    </div>

                    <div class="form-group">
                        <label>Главное фото (обложка) *</label>
                        <div class="file-dropzone" id="photo-dropzone">
                            <input type="file" id="photo" name="photo" class="file-dropzone-file" accept="image/jpeg,image/png,image/gif,image/webp" aria-label="Загрузить обложку товара">
                            <div class="file-dropzone-label">
                                <i class="fas fa-image"></i>
                                <span><strong>Загрузить обложку</strong> — нажмите или перетащите файл</span>
                                <small>JPG, PNG, GIF, WebP · до 5 МБ. Можно не указывать обложку, если загружаете галерею — первый кадр станет обложкой</small>
                            </div>
                        </div>
                        <div class="preview-container" style="margin-top:12px;">
                            <?php if (!empty(getValue($_POST, 'old_photo'))): ?>
                                <p style="font-size:12px;color:var(--muted);margin-bottom:6px;">Текущее фото:</p>
                                <img src="<?php echo htmlspecialchars(getValue($_POST, 'old_photo')); ?>" alt="" class="current-image" id="main-photo-current" style="max-width:220px;border-radius:10px;border:1px solid var(--border);">
                            <?php endif; ?>
                            <img id="image-preview" src="#" alt="" style="max-width: 220px; display: none; border-radius:10px;border:1px solid var(--border);">
                        </div>
                        <?php if (!empty($errors['photo'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['photo']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <span class="form-field-heading">Галерея — карусель в каталоге</span>
                        <div class="file-dropzone file-dropzone-secondary" id="gallery-dropzone">
                            <label class="file-dropzone-gallery-hit">
                                <input type="file" id="gallery_files" name="gallery_files[]" class="file-dropzone-file" accept="image/jpeg,image/png,image/gif,image/webp" multiple aria-label="Добавить фотографии в галерею">
                                <div class="file-dropzone-label">
                                    <i class="fas fa-images"></i>
                                    <span><strong>Добавить кадры</strong> — можно выбрать несколько файлов</span>
                                    <small>Дополнительные фото показываются в карусели на витрине</small>
                                </div>
                            </label>
                        </div>
                        <p id="gallery-files-status" class="gallery-files-status" style="margin:8px 0 0;font-size:0.85rem;color:var(--muted);min-height:1.2em;" aria-live="polite"></p>
                        <div id="gallery-existing" class="gallery-thumb-row" aria-live="polite"></div>
                        <?php if (!empty($errors['gallery'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['gallery']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание товара</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars(getValue($_POST, 'description')); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="sizes">Размеры</label>
                            <input type="text" id="sizes" name="sizes" placeholder="Например: XS, S, M, L или 40, 41, 42"
                                value="<?php echo htmlspecialchars(getValue($_POST, 'sizes')); ?>">
                            <small style="color: var(--text-light); font-size: 0.85rem;">Через запятую</small>
                        </div>
                        <div class="form-group">
                            <label for="colors">Цвета</label>
                            <input type="text" id="colors" name="colors" placeholder="Например: Чёрный, Бежевый, Синий"
                                value="<?php echo htmlspecialchars(getValue($_POST, 'colors')); ?>">
                            <small style="color: var(--text-light); font-size: 0.85rem;">Через запятую</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="<?php echo getValue($_POST, 'product_id') !== '' ? 'update_product' : 'add_product'; ?>" id="submit-product" class="btn">
                        <?php echo getValue($_POST, 'product_id') !== '' ? 'Обновить товар' : 'Добавить товар'; ?>
                    </button>
                    <button type="button" id="cancel-edit" class="btn btn-danger" style="<?php echo getValue($_POST, 'product_id') === '' ? 'display: none;' : 'display: inline-block;'; ?>">Отменить редактирование</button>
                </form>
            </div>
        </section>
    </main>

<footer>

<div class="container">

<div class="footer-grid">

<div>
<h3>VELURA</h3>
<a href="about.php">О нас</a>
<a href="contacts.php">Контакты</a>
</div>

<div>
<h3>Категории</h3>
<a href="products.php?category=clothing">Одежда</a>
<a href="products.php?category=shoes">Обувь</a>
<a href="products.php?category=accessories">Аксессуары</a>
</div>

<div>
<h3>Аккаунт</h3>
<a href="login.php">Вход</a>
<a href="register.php">Регистрация</a>
</div>

<div>
<h3>Соцсети</h3>
<a href="#">ВКонтакте</a>
<a href="#">Telegram</a>
</div>

</div>
</div>

</footer>

    <script>
        function adminTabUrl(tabId) {
            return 'admin.php?tab=' + encodeURIComponent(tabId);
        }

        function activateTab(tabId, pushUrl) {
            document.querySelectorAll('.tab-button').forEach(function(btn) {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
            });
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.toggle('active', content.id === tabId);
            });
            if (pushUrl) {
                history.replaceState(null, '', adminTabUrl(tabId));
            }
        }

        function getGalleryPaths() {
            try {
                var raw = document.getElementById('old_gallery').value || '[]';
                var a = JSON.parse(raw);
                return Array.isArray(a) ? a.filter(Boolean) : [];
            } catch (e) {
                return [];
            }
        }

        function setGalleryPaths(paths) {
            document.getElementById('old_gallery').value = JSON.stringify(paths);
            renderGalleryThumbs();
        }

        function renderGalleryThumbs() {
            var host = document.getElementById('gallery-existing');
            host.innerHTML = '';
            getGalleryPaths().forEach(function(path) {
                var wrap = document.createElement('div');
                wrap.className = 'gallery-thumb';
                var img = document.createElement('img');
                img.src = path;
                img.alt = '';
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'rm';
                rm.innerHTML = '&times;';
                rm.title = 'Убрать из галереи';
                rm.addEventListener('click', function() {
                    var next = getGalleryPaths().filter(function(p) { return p !== path; });
                    setGalleryPaths(next);
                });
                wrap.appendChild(img);
                wrap.appendChild(rm);
                host.appendChild(wrap);
            });
        }

        document.querySelectorAll('.tab-button').forEach(function(button) {
            button.addEventListener('click', function() {
                activateTab(this.getAttribute('data-tab'), true);
            });
        });

        (function syncUrlFromServer() {
            var params = new URLSearchParams(location.search);
            if (!params.get('tab')) {
                var cur = document.querySelector('.tab-button.active');
                if (cur) {
                    history.replaceState(null, '', adminTabUrl(cur.getAttribute('data-tab')));
                }
            }
        })();

        document.querySelectorAll('.edit-product').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                document.getElementById('product_id').value = this.getAttribute('data-id');
                document.getElementById('name').value = this.getAttribute('data-name');
                document.getElementById('description').value = this.getAttribute('data-description');
                document.getElementById('price').value = this.getAttribute('data-price');
                document.getElementById('stock').value = this.getAttribute('data-stock');
                document.getElementById('old_photo').value = this.getAttribute('data-photo');
                document.getElementById('sizes').value = this.getAttribute('data-sizes') || '';
                document.getElementById('colors').value = this.getAttribute('data-colors') || '';
                var catSel = document.getElementById('category');
                if (catSel) {
                    catSel.value = this.getAttribute('data-category') || 'accessories';
                }

                var gRaw = this.getAttribute('data-gallery') || '[]';
                document.getElementById('old_gallery').value = gRaw;
                renderGalleryThumbs();

                document.getElementById('submit-product').textContent = 'Обновить товар';
                document.getElementById('submit-product').name = 'update_product';
                document.getElementById('cancel-edit').style.display = 'inline-block';

                document.querySelector('[data-tab="add-product"]').textContent = 'Редактировать товар';

                activateTab('add-product', true);

                var op = this.getAttribute('data-photo');
                var prevImg = document.getElementById('image-preview');
                var curMain = document.getElementById('main-photo-current');
                if (op) {
                    if (!curMain) {
                        curMain = document.createElement('img');
                        curMain.id = 'main-photo-current';
                        curMain.className = 'current-image';
                        curMain.alt = '';
                        curMain.style.cssText = 'max-width:220px;border-radius:10px;border:1px solid var(--border);';
                        var hint = document.createElement('p');
                        hint.style.cssText = 'font-size:12px;color:var(--muted);margin-bottom:6px;';
                        hint.textContent = 'Текущее фото:';
                        document.querySelector('.preview-container').insertBefore(hint, prevImg);
                        document.querySelector('.preview-container').insertBefore(curMain, prevImg);
                    }
                    curMain.src = op;
                    curMain.style.display = 'block';
                } else if (curMain) {
                    curMain.style.display = 'none';
                }
                prevImg.style.display = 'none';
                document.getElementById('photo').value = '';
                document.getElementById('photo').removeAttribute('required');

                document.getElementById('gallery_files').value = '';
                updateGalleryFilesStatus();
                document.getElementById('product-form').scrollIntoView({ behavior: 'smooth' });
            });
        });

        document.getElementById('cancel-edit').addEventListener('click', function() {
            document.getElementById('product-form').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('old_photo').value = '';
            document.getElementById('old_gallery').value = '[]';
            renderGalleryThumbs();

            document.getElementById('submit-product').textContent = 'Добавить товар';
            document.getElementById('submit-product').name = 'add_product';
            this.style.display = 'none';

            document.querySelector('[data-tab="add-product"]').textContent = 'Добавить товар';

            document.getElementById('image-preview').style.display = 'none';
            var curMain = document.getElementById('main-photo-current');
            if (curMain) {
                curMain.remove();
            }
            document.getElementById('photo').removeAttribute('required');
            updateGalleryFilesStatus();
            var catSel = document.getElementById('category');
            if (catSel) {
                catSel.value = 'accessories';
            }
        });

        document.querySelectorAll('.delete-product').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Вы уверены, что хотите удалить этот товар?')) {
                    return;
                }
                var id = this.getAttribute('data-id');
                var row = this.closest('tr');
                btn.disabled = true;
                var body = 'ajax_delete_product=1&product_id=' + encodeURIComponent(id);
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body,
                    credentials: 'same-origin'
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) {
                        if (row) {
                            row.remove();
                        }
                    } else {
                        alert(data.message || 'Не удалось удалить товар');
                        btn.disabled = false;
                    }
                }).catch(function() {
                    alert('Ошибка сети');
                    btn.disabled = false;
                });
            });
        });

        document.querySelectorAll('.role-select').forEach(function(select) {
            select.addEventListener('change', function() {
                var userId = this.getAttribute('data-user-id');
                var newRole = this.value;
                var statusElement = this.nextElementSibling;

                statusElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                statusElement.style.display = 'inline-block';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'admin.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.success) {
                                statusElement.innerHTML = '<i class="fas fa-check" style="color: green;"></i>';
                                setTimeout(function() {
                                    statusElement.style.display = 'none';
                                }, 2000);
                            } else {
                                statusElement.innerHTML = '<i class="fas fa-times" style="color: red;"></i>';
                                setTimeout(function() {
                                    statusElement.style.display = 'none';
                                    if (select.value === 'admin') {
                                        select.value = 'registered_user';
                                    } else {
                                        select.value = 'admin';
                                    }
                                }, 2000);
                            }
                        } catch (err) {
                            console.error('Error parsing response');
                            statusElement.style.display = 'none';
                        }
                    }
                };
                xhr.send('change_role=1&user_id=' + userId + '&new_role=' + encodeURIComponent(newRole));
            });
        });

        document.getElementById('photo').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(ev) {
                    var preview = document.getElementById('image-preview');
                    preview.src = ev.target.result;
                    preview.style.display = 'block';
                    var curMain = document.getElementById('main-photo-current');
                    if (curMain) {
                        curMain.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        function updateGalleryFilesStatus() {
            var input = document.getElementById('gallery_files');
            var el = document.getElementById('gallery-files-status');
            if (!input || !el) {
                return;
            }
            var n = input.files ? input.files.length : 0;
            if (n === 0) {
                el.textContent = '';
                return;
            }
            el.textContent = 'Выбрано файлов в галерею: ' + n + '. Они сохранятся после нажатия «Добавить товар» / «Обновить товар».';
        }

        document.getElementById('gallery_files').addEventListener('change', updateGalleryFilesStatus);
        document.getElementById('gallery_files').addEventListener('input', updateGalleryFilesStatus);

        function bindDropZone(zoneId, inputId, multiple) {
            var zone = document.getElementById(zoneId);
            var input = document.getElementById(inputId);
            if (!zone || !input) {
                return;
            }

            function prevent(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            input.addEventListener('dragenter', prevent);
            input.addEventListener('dragover', function(e) {
                prevent(e);
                zone.style.borderColor = 'var(--accent)';
            });
            input.addEventListener('dragleave', function() {
                zone.style.borderColor = '';
            });
            input.addEventListener('drop', function(e) {
                prevent(e);
                zone.style.borderColor = '';
                var files = e.dataTransfer.files;
                if (!files || !files.length) {
                    return;
                }
                var dt = new DataTransfer();
                if (multiple) {
                    for (var i = 0; i < files.length; i++) {
                        dt.items.add(files[i]);
                    }
                } else {
                    dt.items.add(files[0]);
                }
                input.files = dt.files;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        bindDropZone('photo-dropzone', 'photo', false);
        bindDropZone('gallery-dropzone', 'gallery_files', true);

        renderGalleryThumbs();
    </script>
</body>
</html> 