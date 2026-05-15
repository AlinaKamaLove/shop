<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_set_cookie_params(86400, '/');
    session_start();
}

require_once __DIR__ . '/connect.php';
$pdo = getDB();

// Получаем 3 последних товара из базы
try {
    $stmt = $pdo->query("SELECT * FROM Products ORDER BY created_at DESC LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении товаров: " . $e->getMessage());
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
<title>VELURA</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
:root{
    --bg:#e7e7e7;
    --text:#1a1a1a;
    --border:#d6d6d6;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter', sans-serif;
}

body{
    background:var(--bg);
    color:var(--text);
}

/* HEADER */
body{
    font-family:'Montserrat',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
}

/* HEADER */

header{
    position:sticky;
    top:0;
    z-index:1000;
    background:rgba(235, 235, 235, 0.82);
    backdrop-filter:blur(14px);
    border-bottom:1px solid rgba(255,255,255,0.06);
}

.container{
    max-width:1200px;
    margin:auto;
    padding:0 24px;
}

.header-content{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 0;
}

.logo{
    font-family:'Playfair Display', serif;
    font-size:26px;
    font-weight:800;
    letter-spacing:5px;
    text-decoration:none;
    color:#111;
    text-transform:uppercase;
}

nav ul{
    display:flex;
    list-style:none;
    gap:28px;
    align-items:center;
}

nav ul li a{
    text-decoration:none;
    color:#111;
    font-size:12px;
    letter-spacing:2px;
    text-transform:uppercase;
    transition:0.3s ease;
    position:relative;
}

nav ul li a:hover{
    opacity:.6;
}

.cart-count{
    background:#111;
    color:#fff;
    font-size:10px;
    min-width:18px;
    height:18px;
    padding:0 5px;
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    margin-left:6px;
}


/* DROPDOWN */
.dropdown{
    position:relative;
}

.dropdown-menu{
    position:absolute;
    top:34px;
    left:0;
    background:rgba(245,245,245,0.97);
    backdrop-filter:blur(14px);
    border:1px solid rgba(0,0,0,0.05);
    min-width:170px;
    opacity:0;
    visibility:hidden;
    transform:translateY(10px);
    transition:0.25s ease;
    box-shadow:0 20px 40px rgba(0,0,0,0.08);
    border-radius:12px;
    overflow:hidden;
}

.dropdown-menu a{
    display:block;
    padding:13px 16px;
    font-size:12px;
    color:#111;
    text-decoration:none;
    transition:0.3s;
}

.dropdown-menu a:hover{
    background:#111;
    color:#fff;
    padding-left:22px;
}

.dropdown:hover .dropdown-menu{
    opacity:1;
    visibility:visible;
    transform:translateY(0);
}

/* HERO */
.hero{
    height:100vh;
    background:
    linear-gradient(rgba(0,0,0,0.66),rgba(0,0,0,0.66)),
    url('images/image2.jpg') center/cover;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:white;
}

.hero h1{
    font-family:'Playfair Display';
    font-size:78px;
    letter-spacing:8px;
}

.hero p{
    margin-top:14px;
    opacity:0.88;
    font-size:15px;
    letter-spacing:1px;
}

/* BUTTON */
.btn{
    margin-top:30px;
    display:inline-block;
    padding:14px 34px;
    border:1px solid rgba(255,255,255,0.65);
    background:transparent;
    color:white;
    text-decoration:none;
    font-size:12px;
    letter-spacing:3px;
    text-transform:uppercase;
    transition:0.4s ease;
}

.btn:hover{
    background:white;
    color:#111;
    transform:translateY(-3px);
}

/* SLIDER */
.slider{
    width:90%;
    max-width:1100px;
    margin:70px auto;
    overflow:hidden;
    border-radius:20px;
    box-shadow:0 20px 50px rgba(0,0,0,0.08);
}

.slides{
    display:flex;
    transition:0.8s ease;
}

.slide{
    min-width:100%;
    height:65vh;
    position:relative;
}

.slide img{
    width:100%;
    height:100%;
    object-fit:cover;
    filter:brightness(0.82);
}

.slide-content{
    position:absolute;
    bottom:50px;
    left:50px;
    color:white;
}

.slide-content h2{
    font-family:'Playfair Display';
    font-size:44px;
}

.slide-content p{
    font-size:14px;
    opacity:0.85;
    margin-top:8px;
}

/* ===================== */
/* 🔥 PRODUCTS (ИСПРАВЛЕНО) */
/* ===================== */

.products{
    padding:90px 0;
    background:#f5f5f5;
}

/* только ОДИН вариант заголовка */
.section-title{
    text-align:center;
    margin-bottom:50px;
}

.section-title h2{
    font-family:'Playfair Display', serif;
    font-size:42px;
    letter-spacing:4px;
    text-transform:uppercase;
}

.section-title p{
    font-size:13px;
    color:#777;
    margin-top:10px;
    letter-spacing:2px;
}

/* сетка */
.product-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:26px;
}

/* карточка */
/* ===================== */
/* 🔥 PRODUCTS SECTION */
/* ===================== */

.products{
    padding:100px 0;
    background:#f5f5f5;
}

/* заголовок секции */
.section-title{
    text-align:center;
    margin-bottom:60px;
}

.section-title h2{
    font-family:'Playfair Display', serif;
    font-size:46px;
    letter-spacing:5px;
    text-transform:uppercase;
    font-weight:500;
    color:#111;
}

.section-title p{
    font-size:14px;
    color:#777;
    margin-top:10px;
    letter-spacing:2px;
}

/* сетка товаров */
.product-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:30px;
}

/* карточка товара */
.product{
    display:flex;
    flex-direction:column;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
    transition:0.35s ease;
    min-height:460px;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    text-decoration:none;
    color:#111;

    box-shadow:0 10px 30px rgba(0,0,0,0.08);
    transition:0.35s ease;

    min-height:460px;
}

/* 🔥 картинка ПРИЖАТА к верху */
.product img{
    width:100%;
    height:340px;
    object-fit:cover;
    display:block; /* важно */
    margin:0;
    padding:0;
}
/* hover эффект */
.product:hover{
    transform:translateY(-10px);
    box-shadow:0 25px 60px rgba(0,0,0,0.15);
}



.product:hover img{
    transform:scale(1.05);
}

/* инфо блока */
.product-info{
    padding:22px;
    width:100%;
    text-align:center;
}

/* название товара */
.product-info h3{
    font-family:'Playfair Display', serif;
    font-size:20px;
    font-weight:500;
    margin-bottom:10px;
}

/* цена */
.product-info p{
    font-size:16px;
    font-weight:600;
    color:#111;
    letter-spacing:1px;
}

/* FOOTER */
/* FOOTER (UPDATED + ALIGNED + UNIFIED STYLE) */
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

/* MOBILE */
@media (max-width: 900px) {
    .footer-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .footer-grid {
        grid-template-columns: 1fr;
    }
}



@media(max-width:600px){

    .product-grid{
        grid-template-columns:1fr;
    }

    .hero h1{
        font-size:40px;
    }

    .header-content{
        flex-direction:column;
        gap:14px;
    }

    nav ul{
        flex-wrap:wrap;
        justify-content:center;
    }

    .logo{
        font-size:24px;
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

<li class="dropdown">
    <a href="products.php">Каталог</a>

    <div class="dropdown-menu">
        <a href="products.php?category=clothing">Одежда</a>
        <a href="products.php?category=shoes">Обувь</a>
        <a href="products.php?category=accessories">Аксессуары</a>
    </div>
</li>

<?php if (isset($_SESSION['user_id'])): ?>
    <li><a href="profile.php">Профиль</a></li>
    <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <li><a href="admin.php?tab=products">Админ</a></li>
    <?php endif; ?>
    <li>
        <a href="cart.php">Корзина
            <span class="cart-count" id="nav-cart-count"<?= $cart_count < 1 ? ' style="display:none;"' : '' ?>><?= (int)$cart_count ?></span>
        </a>
    </li>
    <li><a href="logout.php">Выход</a></li>
<?php else: ?>
    <li>
        <a href="login.php">Корзина</a>
    </li>
    <li><a href="login.php">Вход</a></li>
    <li><a href="register.php">Регистрация</a></li>
<?php endif; ?>

</ul>
</nav>

</div>
</div>
</header>

<section class="hero">
<div>

<h1>VELURA</h1>
<a href="products.php" class="btn">
Смотреть
</a>

</div>
</section>

<div class="slider">

<div class="slides">

<div class="slide">
<img src="images/collage.jpg">

<div class="slide-content">
<h2>New Season</h2>
<p>Minimal elegance collection</p>
</div>

</div>

<div class="slide">
<img src="images/collage2.jpg">

<div class="slide-content">
<h2>Luxury Wear</h2>
<p>Premium style selection</p>
</div>

</div>

<div class="slide">
<img src="images/collage3.jpg">

<div class="slide-content">
<h2>Accessories</h2>
<p>Details define the look</p>
</div>

</div>

</div>
</div>

<?php
$stmt = $pdo->prepare("SELECT * FROM Products ORDER BY created_at DESC LIMIT 4");
$stmt->execute();
$latest = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="products">
<div class="container">

<div class="section-title">
    <h2 class="title">NEW DROP</h2>
    <p class="subtitle">Свежие поступления коллекции</p>
</div>

<div class="product-grid">

<?php foreach ($latest as $p): ?>

<a href="product.php?id=<?= (int)$p['product_id'] ?>" class="product">

    <!-- ВАЖНО: photo, а не image -->
    <img src="<?= htmlspecialchars($p['photo']) ?>">

    <div class="product-info">
        <h3><?= htmlspecialchars($p['name']) ?></h3>
        <p><?= number_format($p['price'], 0, ',', ' ') ?>₽</p>
    </div>

</a>

<?php endforeach; ?>

</div>

</div>
</section>

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
<?php if (isset($_SESSION['user_id'])): ?>
    <a href="profile.php">Профиль</a>
    <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <a href="admin.php?tab=products">Админ</a>
    <?php endif; ?>
    <a href="cart.php">Корзина</a>
    <a href="logout.php">Выход</a>
<?php else: ?>
    <a href="login.php">Вход</a>
    <a href="register.php">Регистрация</a>
<?php endif; ?>
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
let index = 0;
const slides = document.querySelector('.slides');

setInterval(() => {
    index = (index + 1) % 3;
    slides.style.transform =
    `translateX(-${index * 100}%)`;
}, 5000);
</script>

</body>
</html>