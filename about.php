<?php
session_start();
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum(array_column($_SESSION['cart'], 'qty'))
    : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>О нас | VELURA</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --bg: #e7e7e7;
    --card: #f5f5f5;
    --soft: #ececec;
    --text: #151515;
    --muted: #6d6d6d;
    --accent: #111111;
    --border: rgba(0,0,0,0.08);
    --shadow: 0 20px 60px rgba(0,0,0,0.06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

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
    background: rgba(235,235,235,0.85);
    backdrop-filter: blur(14px);
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
    padding: 14px 0;
}

.logo {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    font-weight: 800;
    letter-spacing: 5px;
    text-decoration: none;
    color: #111;
    text-transform: uppercase;
}

nav ul {
    display: flex;
    list-style: none;
    gap: 22px;
}

nav ul li a {
    text-decoration: none;
    color: #111;
    font-size: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.cart-count {
    background: #111;
    color: #fff;
    font-size: 10px;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
}

/* ABOUT */
.about {
    max-width: 900px;
    margin: 80px auto;
    padding: 0 24px;
}

.about h1 {
    font-family: 'Playfair Display', serif;
    font-size: 48px;
    text-align: center;
    margin-bottom: 20px;
}

.about p {
    font-size: 16px;
    line-height: 1.8;
    color: var(--muted);
    margin-bottom: 16px;
    text-align: center;
}

.about-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-top: 40px;
}

.about-card {
    background: var(--card);
    padding: 24px;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    text-align: center;
}

.about-card h3 {
    font-family: 'Playfair Display', serif;
    margin-bottom: 10px;
}

.about-card p {
    font-size: 14px;
    color: var(--muted);
    line-height: 1.6;
}

/* FOOTER */
footer {
    text-align: center;
    padding: 30px;
    font-size: 12px;
    color: var(--muted);
    border-top: 1px solid var(--border);
    margin-top: 80px;
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
                <li><a href="products.php">Каталог</a></li>
                <li>
                    <a href="cart.php">Корзина
                        <span class="cart-count"><?= (int)$cart_count ?></span>
                    </a>
                </li>
                <li><a href="login.php">Вход</a></li>
            </ul>
        </nav>
    </div>
</div>
</header>

<section class="about">
    <h1>О бренде VELURA</h1>

    <p>
        VELURA — это современный минималистичный бренд одежды, созданный для тех,
        кто ценит чистый стиль, качество и внимание к деталям.
    </p>

    <p>
        Мы вдохновляемся уличной модой, скандинавской эстетикой и классическим
        европейским кроем, соединяя их в универсальные образы на каждый день.
    </p>

    <div class="about-grid">

        <div class="about-card">
            <h3>Качество</h3>
            <p>Мы используем только проверенные материалы и контролируем каждый этап производства.</p>
        </div>

        <div class="about-card">
            <h3>Стиль</h3>
            <p>Наши коллекции создаются так, чтобы легко сочетаться между собой.</p>
        </div>

        <div class="about-card">
            <h3>Философия</h3>
            <p>Меньше лишнего — больше смысла. Простота, которая выглядит дорого.</p>
        </div>

    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> VELURA. Все права защищены.
</footer>

</body>
</html>