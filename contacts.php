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
<title>Контакты | VELURA</title>

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

/* CONTACTS */
.contacts {
    max-width: 1100px;
    margin: 80px auto;
    padding: 0 24px;
}

.contacts h1 {
    font-family: 'Playfair Display', serif;
    font-size: 48px;
    text-align: center;
    margin-bottom: 20px;
}

.contacts p {
    text-align: center;
    color: var(--muted);
    margin-bottom: 40px;
    line-height: 1.7;
}

/* GRID */
.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

@media(max-width: 900px) {
    .contact-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 600px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
}

.contact-card {
    background: var(--card);
    padding: 26px;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    text-align: center;
    transition: 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-4px);
}

.contact-card h3 {
    font-family: 'Playfair Display', serif;
    margin-bottom: 10px;
}

.contact-card p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
}

.contact-card a {
    display: inline-block;
    margin-top: 10px;
    color: var(--accent);
    text-decoration: none;
    font-weight: 500;
}

.contact-card a:hover {
    opacity: 0.6;
}

/* MAP */
.map {
    margin-top: 40px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.map iframe {
    width: 100%;
    height: 380px;
    border: 0;
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

<section class="contacts">
    <h1>Контакты</h1>

    <p>
        Мы всегда на связи. Ответим на любые вопросы по заказам, доставке и сотрудничеству.
    </p>

    <div class="contact-grid">

        <div class="contact-card">
            <h3>Поддержка</h3>
            <p>Вопросы по заказам</p>
            <a href="mailto:support@velura.com">support@velura.com</a>
        </div>

        <div class="contact-card">
            <h3>Телефон</h3>
            <p>Звонки и консультации</p>
            <a href="tel:+79990000000">+7 (999) 000-00-00</a>
        </div>

        <div class="contact-card">
            <h3>VK</h3>
            <p>Сообщество бренда</p>
            <a href="#">vk.com/velura</a>
        </div>

        <div class="contact-card">
            <h3>Telegram</h3>
            <p>Быстрая связь</p>
            <a href="#">@velura_support</a>
        </div>

        <div class="contact-card">
            <h3>Адрес</h3>
            <p>г. Калининград, ТРЦ «Европа»</p>
            <a target="_blank"
               href="https://www.google.com/maps/search/?api=1&query=ТРЦ+Европа+Калининград">
                Открыть на карте
            </a>
        </div>

        <div class="contact-card">
            <h3>Время работы</h3>
            <p>Ежедневно</p>
            <a>10:00 – 21:00</a>
        </div>

    </div>

    <div class="map">
        <iframe
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps?q=ТРЦ%20Европа%20Калининград&output=embed">
        </iframe>
    </div>

</section>

<footer>
    &copy; <?= date('Y') ?> VELURA. Все права защищены.
</footer>

</body>
</html>