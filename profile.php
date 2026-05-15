<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM Users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$password_message = '';
$password_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
    $newPwd = isset($_POST['new_password']) ? trim((string) $_POST['new_password']) : '';
    $confirmPwd = isset($_POST['confirm_new_password']) ? trim((string) $_POST['confirm_new_password']) : '';

    if ($current === '') {
        $password_error = 'Введите текущий пароль';
    } elseif (!is_array($user) || !isset($user['password']) || !password_verify($current, $user['password'])) {
        $password_error = 'Неверный текущий пароль';
    } elseif (strlen($newPwd) < 6) {
        $password_error = 'Новый пароль — не менее 6 символов';
    } elseif ($newPwd !== $confirmPwd) {
        $password_error = 'Новый пароль и подтверждение не совпадают';
    } else {
        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        $up = $db->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
        $up->execute([$hash, $_SESSION['user_id']]);
        $password_message = 'Пароль успешно изменён';
        $stmt = $db->prepare("SELECT * FROM Users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
}

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    cart_session_normalize($_SESSION['cart']);
}
$cart_count = cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Профиль | VELURA</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --bg: #f5f5f5;
    --card: #ffffff;
    --soft: #f0f0f0;
    --text: #111;
    --muted: #777;
    --accent: #111;
    --border: #ddd;
    --shadow: 0 10px 30px rgba(0,0,0,0.08);
    --transition: all .3s ease;
}

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* BODY */
body {
    font-family: 'Montserrat', sans-serif;
    background: var(--bg);
    color: var(--text);
}

/* HEADER */
header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(245,245,245,0.9);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border);
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 0 24px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 0;
}

/* LOGO */
.logo {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    letter-spacing: 3px;
    text-transform: uppercase;
    text-decoration: none;
    color: var(--text);
}

/* NAV */
nav ul {
    display: flex;
    list-style: none;
    gap: 18px;
    align-items: center;
}

nav ul li a {
    text-decoration: none;
    color: var(--muted);
    font-size: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: var(--transition);
}

nav ul li a:hover {
    color: var(--text);
}

/* CART */
.cart-count {
    background: var(--text);
    color: #fff;
    font-size: 10px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
}

/* PROFILE */
.profile-section {
    max-width: 700px;
    margin: 70px auto;
}

.profile-card {
    background: var(--card);
    border: 1px solid var(--border);
    padding: 35px;
    border-radius: 16px;
    box-shadow: var(--shadow);
}

/* HEADER PROFILE */
.profile-header {
    text-align: center;
    margin-bottom: 30px;
}

/* AVATAR */
.profile-avatar {
    width: 95px;
    height: 95px;
    border-radius: 50%;
    margin: 0 auto 14px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* NAME */
.profile-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
}

/* INFO BLOCK */
.profile-info {
    margin-top: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.info-label {
    color: var(--muted);
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* ROLES */
.role-admin {
    background: #111;
    color: #fff;
    padding: 4px 9px;
    font-size: 10px;
    text-transform: uppercase;
    border-radius: 4px;
}

.role-user {
    background: #eee;
    color: #111;
    padding: 4px 9px;
    font-size: 10px;
    text-transform: uppercase;
    border-radius: 4px;
}

/* BUTTON */
.btn {
    display: inline-block;
    margin-top: 22px;
    padding: 12px 24px;
    background: #111;
    border: 1px solid #111;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 11px;
    transition: var(--transition);
    cursor: pointer;
    width: 100%;
    text-align: center;
}

.btn:hover {
    background: #333;
}

/* PASSWORD SECTION */
.pwd-section {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.pwd-section h2 {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    margin-bottom: 16px;
}

/* LABEL */
.pwd-form label {
    display: block;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}

/* INPUT */
.pwd-form input[type="password"] {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--soft);
    color: var(--text);
}

.pwd-form input[type="password"]:focus {
    outline: none;
    border-color: #111;
}

/* BUTTON SUBMIT */
.btn-submit-pwd {
    margin-top: 12px;
    width: 100%;
    padding: 12px 24px;
    background: #111;
    border: none;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 11px;
    cursor: pointer;
    border-radius: 10px;
    transition: var(--transition);
}

.btn-submit-pwd:hover {
    background: #333;
}

/* MESSAGES */
.msg-ok {
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #e6f4ea;
    border: 1px solid #cce5cc;
    color: #2e7d32;
}

.msg-err {
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fdecea;
    border: 1px solid #f5c6cb;
    color: #b71c1c;
}

/* MOBILE */
@media (max-width: 768px) {
    .profile-card {
        padding: 25px;
    }

    .profile-section {
        margin: 40px auto;
    }

    .info-item {
        flex-direction: column;
        gap: 4px;
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

<?php if (isset($_SESSION['user_id'])): ?>

    <li><a href="index.php">Главная</a></li>
    <li><a href="profile.php">Профиль</a></li>
    <li><a href="products.php">Каталог</a></li>

    <?php if ($_SESSION['user_role'] == 'admin'): ?>
        <li><a href="admin.php?tab=products">Админ</a></li>
    <?php endif; ?>

    <li>
        <a href="cart.php">Корзина
            <?php if ($cart_count > 0): ?>
                <span class="cart-count"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>
    </li>

    <li><a href="logout.php">Выход</a></li>

<?php else: ?>

    <li><a href="index.php">Главная</a></li>
    <li><a href="products.php">Каталог</a></li>
    <li><a href="login.php">Вход</a></li>
    <li><a href="register.php">Регистрация</a></li>

<?php endif; ?>

</ul>
</nav>

</div>
</div>
</header>

<main class="container">
<section class="profile-section">

<div class="profile-card">

<div class="profile-header">
    <div class="profile-avatar">
        <img src="images/image3.jpg" alt="cat">
    </div>
    <h1><?= htmlspecialchars($user['username']) ?></h1>
</div>

<div class="profile-info">

<div class="info-item">
    <div class="info-label">Username</div>
    <div><?= htmlspecialchars($user['username']) ?></div>
</div>

<div class="info-item">
    <div class="info-label">Email</div>
    <div><?= htmlspecialchars($user['email']) ?></div>
</div>

<div class="info-item">
    <div class="info-label">Роль</div>
    <div>
        <?php if ($user['user_role'] == 'admin'): ?>
            <span class="role-admin">Admin</span>
        <?php else: ?>
            <span class="role-user">User</span>
        <?php endif; ?>
    </div>
</div>

</div>

<div class="pwd-section">
    <h2>Смена пароля</h2>
    <?php if ($password_message !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($password_message) ?></div>
    <?php endif; ?>
    <?php if ($password_error !== ''): ?>
        <div class="msg-err"><?= htmlspecialchars($password_error) ?></div>
    <?php endif; ?>
    <form method="post" class="pwd-form" autocomplete="off">
        <div class="form-row-profile">
            <label for="current_password">Текущий пароль</label>
            <input type="password" name="current_password" id="current_password" required>
        </div>
        <div class="form-row-profile">
            <label for="new_password">Новый пароль</label>
            <input type="password" name="new_password" id="new_password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-row-profile">
            <label for="confirm_new_password">Повторите новый пароль</label>
            <input type="password" name="confirm_new_password" id="confirm_new_password" required minlength="6" autocomplete="new-password">
        </div>
        <button type="submit" name="change_password" value="1" class="btn-submit-pwd">Сохранить пароль</button>
    </form>
</div>

<a href="logout.php" class="btn">Выйти</a>

</div>

</section>
</main>

</body>
</html>