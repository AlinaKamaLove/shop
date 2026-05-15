<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = "Заполните все поля";
    } else {

        try {
            $db = getDB();

            $stmt = $db->prepare("
                SELECT * FROM Users 
                WHERE username = ? OR email = ? 
                LIMIT 1
            ");

            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = isset($user['user_role']) ? $user['user_role'] : 'registered_user';
                $_SESSION['logged_in'] = true;

                session_regenerate_id(true);

                header("Location: profile.php");
                exit();

            } else {
                $error = "Неверное имя пользователя или пароль";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Ошибка сервера";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход | VELURA</title>

<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500&family=Cormorant+Garamond:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Quicksand', sans-serif;

    background:
        linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
        url('images/image1.jpg') center/cover no-repeat;

    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-container {
    width: 100%;
    max-width: 420px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(12px);
    border-radius: 16px;
    padding: 45px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.45);
}

.logo {
    text-align: center;
    font-family: 'Cormorant Garamond', serif;
    font-size: 34px;
    letter-spacing: 2px;
    margin-bottom: 15px;
    color: #111;
}

h2 {
    text-align: center;
    font-family: 'Cormorant Garamond', serif;
    font-weight: 400;
    margin-bottom: 25px;
    color: #111;
}

.error {
    color: #c0392b;
    text-align: center;
    margin-bottom: 15px;
    font-size: 13px;
}

input {
    width: 100%;
    padding: 12px;
    border: none;
    border-bottom: 1px solid #ccc;
    background: transparent;
    font-family: 'Quicksand', sans-serif;
    margin-bottom: 18px;
}

input:focus {
    outline: none;
    border-bottom: 1px solid #111;
}

button {
    width: 100%;
    padding: 14px;
    border: none;
    background: #111;
    color: white;
    font-size: 13px;
    letter-spacing: 2px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #333;
}
.register-link {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: #444;
}

.register-link a {
    color: #111;
    text-decoration: none;
    font-weight: 500;
    transition: 0.3s;
}

.register-link a:hover {
    text-decoration: underline;
    color: #333;
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
.auth-mini-nav a:hover {
    color: #111;
}
</style>
</head>

<body>

<div class="login-container">

<nav class="auth-mini-nav" aria-label="Навигация">
    <a href="index.php">Главная</a>
    <a href="products.php">Каталог</a>
 
</nav>

<div class="logo">VELURA</div>

<h2>Вход</h2>

<?php if (!empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<input type="text" name="username" placeholder="Имя пользователя или Email">
<input type="password" name="password" placeholder="Пароль">

<button type="submit">
    <i class="fas fa-sign-in-alt"></i> ВОЙТИ
</button>

</form>
<div class="register-link">
    Нет аккаунта?
    <a href="register.php">Зарегистрироваться</a>
</div>
</div>


</body>
</html>