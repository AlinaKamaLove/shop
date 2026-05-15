<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/connect.php';
session_start();

$errors = array();
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // Валидация
    if (empty($username)) $errors['username'] = "Введите имя пользователя";
    if (empty($email)) $errors['email'] = "Введите email";
    if (empty($password)) $errors['password'] = "Введите пароль";
    if ($password !== $confirm_password) $errors['confirm_password'] = "Пароли не совпадают";

    if (strlen($username) < 4) $errors['username'] = "Не менее 4 символов";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Некорректный email";
    if (strlen($password) < 6) $errors['password'] = "Не менее 6 символов";

    if (empty($errors)) {

        $db = getDB();

        // Проверка существующего пользователя
        $stmt = $db->prepare("SELECT user_id FROM Users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $errors['general'] = "Пользователь с таким именем или email уже существует";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_role = 'registered_user';

            $stmt = $db->prepare("
                INSERT INTO Users (username, password, user_role, email, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $username,
                $hashed_password,
                $user_role,
                $email
            ]);

            // Авторизация
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['logged_in'] = true;

            header("Location: profile.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Регистрация | PlantLover</title>

<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500&family=Cormorant+Garamond:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
body {
    font-family: 'Quicksand', sans-serif;
    background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
    url('images/image1.jpg') center/cover no-repeat;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.register-container {
    width: 100%;
    max-width: 430px;
    background: rgba(255,255,255,0.92);
    border-radius: 16px;
    padding: 45px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.45);
}

.logo {
    text-align: center;
    font-family: 'Cormorant Garamond', serif;
    font-size: 34px;
    margin-bottom: 10px;
}

h1 {
    text-align: center;
    font-family: 'Cormorant Garamond', serif;
    font-weight: 400;
    margin-bottom: 25px;
    color: #111;
}

.form-group {
    margin-bottom: 16px;
}

input {
    width: 100%;
    padding: 12px;
    border: none;
    border-bottom: 1px solid #ccc;
    background: transparent;
}

input:focus {
    outline: none;
    border-bottom: 1px solid #111;
}

button {
    width: 100%;
    padding: 14px;
    margin-top: 15px;
    border: none;
    background: #111;
    color: white;
    cursor: pointer;
}

button:hover {
    background: #333;
}

.login-link {
    text-align: center;
    margin-top: 18px;
    font-size: 13px;
    color: #111;
}

/* 👉 ПОДЧЁРКИВАНИЕ ТОЛЬКО ПРИ HOVER */
.login-link a {
    color: #111;
    text-decoration: none;
    transition: 0.2s ease;
}

.login-link a:hover {
    text-decoration: underline;
}

.auth-mini-nav {
    text-align: center;
    margin-bottom: 22px;
    font-size: 12px;
    letter-spacing: 1px;
}

.auth-mini-nav a {
    color: #555;
    text-decoration: none;
    text-transform: uppercase;
    margin: 0 10px;
    transition: color 0.2s;
}

/* 👉 подчёркивание только при наведении */
.auth-mini-nav a:hover {
    color: #111;
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="register-container">

<nav class="auth-mini-nav" aria-label="Навигация">
    <a href="index.php">Главная</a>
    <a href="products.php">Каталог</a>

</nav>

<div class="logo">VELURA</div>

<h1>Регистрация</h1>

<form method="POST">

<div class="form-group">
<input type="text" name="username" placeholder="Имя пользователя">
</div>

<div class="form-group">
<input type="email" name="email" placeholder="Email">
</div>

<div class="form-group">
<input type="password" name="password" placeholder="Пароль">
</div>

<div class="form-group">
<input type="password" name="confirm_password" placeholder="Повтор пароля">
</div>

<button type="submit">
<i class="fas fa-user-plus"></i> СОЗДАТЬ АККАУНТ
</button>

</form>

<div class="login-link">
Уже есть аккаунт? <a href="login.php">Войти</a>
</div>

</div>

</body>
</html>
