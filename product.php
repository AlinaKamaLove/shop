<?php
session_start();

require_once __DIR__ . '/connect.php';
$pdo = getDB();

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: products.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'], $_POST['product_id'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = 'Для добавления в корзину войдите в аккаунт';
        $_SESSION['message_type'] = 'danger';
        header('Location: login.php');
        exit();
    }

    $postPid = (int) $_POST['product_id'];
    $addQty = isset($_POST['quantity']) ? max(1, (int) $_POST['quantity']) : 1;
    $pickSize = isset($_POST['cart_size']) ? trim((string) $_POST['cart_size']) : '';
    $pickColor = isset($_POST['cart_color']) ? trim((string) $_POST['cart_color']) : '';

    try {
        $stmt = $pdo->prepare('SELECT * FROM Products WHERE product_id = ?');
        $stmt->execute([$postPid]);
        $prodRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prodRow) {
            $_SESSION['message'] = 'Товар не найден';
            $_SESSION['message_type'] = 'danger';
        } else {
            $alSizes = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($prodRow['sizes'] ?? '')))));
            $alColors = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($prodRow['colors'] ?? '')))));

            $pair = cart_variant_allowed($alSizes, $alColors, $pickSize, $pickColor);
            if ($pair === null) {
                $_SESSION['message'] = 'Выберите размер и цвет из списка';
                $_SESSION['message_type'] = 'danger';
            } else {
                list($pickSize, $pickColor) = $pair;
                $stock = (int) $prodRow['stock'];
                if ($stock <= 0) {
                    $_SESSION['message'] = 'Товар недоступен';
                    $_SESSION['message_type'] = 'danger';
                } else {
                    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    cart_session_normalize($_SESSION['cart']);
                    if (cart_add_or_merge($_SESSION['cart'], $postPid, $addQty, $pickSize, $pickColor, $stock)) {
                        cart_persist_cookie($_SESSION['cart']);
                        $_SESSION['message'] = 'Товар добавлен в корзину';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Недостаточно товара на складе';
                        $_SESSION['message_type'] = 'danger';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Ошибка: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }

    header('Location: product.php?id=' . $productId);
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM Products WHERE product_id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: products.php');
    exit();
}

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    cart_session_normalize($_SESSION['cart']);
}
$cart_count = cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []);

$categoryLabels = [
    'clothing' => 'Одежда',
    'shoes' => 'Обувь',
    'accessories' => 'Аксессуары',
];
$catKey = $product['category'] ?? '';
$catLabel = $categoryLabels[$catKey] ?? ($catKey !== '' ? $catKey : 'Каталог');

$slides = product_catalog_slides($product);
if ($slides === []) {
    $slides = array('');
}

$sizesList = array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($product['sizes'] ?? ''))));
$colorsList = array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($product['colors'] ?? ''))));
$sizesList = array_values($sizesList);
$colorsList = array_values($colorsList);

$defaultSize = $sizesList !== [] ? $sizesList[0] : '';
$defaultColor = $colorsList !== [] ? $colorsList[0] : '';

$stock = (int) $product['stock'];
$price = (float) $product['price'];
$nameBc = (string) $product['name'];
if (function_exists('mb_strimwidth')) {
    $nameBcShort = mb_strimwidth($nameBc, 0, 48, '…', 'UTF-8');
} else {
    $nameBcShort = strlen($nameBc) > 48 ? substr($nameBc, 0, 45) . '...' : $nameBc;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> — VELURA</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    /* 🔥 НОВАЯ СВЕТЛАЯ ТЕМА */
    --bg: #e7e7e7;
    --card: #f5f5f5;
    --soft: #ffffff;
    --text: #1a1a1a;
    --muted: #666;
    
    /* 🔥 вместо оранжевого */
    --accent: #111111;

    --border: #d6d6d6;
    --shadow: 0 15px 40px rgba(0,0,0,0.08);
    --transition: all .3s ease;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Inter', sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    line-height:1.5;
}

/* CONTAINER */
.container{
    max-width:1200px;
    margin:auto;
    padding:0 24px;
}

/* ===================== */
/* HEADER */
/* ===================== */

header{
    position:sticky;
    top:0;
    z-index:1000;

    background:rgba(235,235,235,0.82);
    backdrop-filter:blur(16px);

    border-bottom:1px solid rgba(0,0,0,0.05);

    box-shadow:0 4px 16px rgba(0,0,0,0.03);
}

.header-content{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 0;
}

.logo{
    font-family:'Playfair Display', serif;
    font-size:24px;
    letter-spacing:4px;
    text-decoration:none;
    color:#111;
    text-transform:uppercase;
}

/* NAV */

nav ul{
    display:flex;
    list-style:none;
    gap:22px;
    align-items:center;
    flex-wrap:wrap;
}

nav ul li a{
    text-decoration:none;
    color:#111;
    font-size:13px;
    letter-spacing:1px;
    text-transform:uppercase;
    transition:var(--transition);
}

nav ul li a:hover{
    opacity:.6;
}

/* 🔥 CART */

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

/* ===================== */
/* BREADCRUMBS */
/* ===================== */

.breadcrumbs{
    padding:16px 0 8px;
    font-size:12px;
    color:#777;
    letter-spacing:.5px;
}

.breadcrumbs a{
    color:#111;
    text-decoration:none;
    transition:var(--transition);
}

.breadcrumbs a:hover{
    opacity:.6;
}

.breadcrumbs span.sep{
    margin:0 10px;
    color:#aaa;
}

.breadcrumbs span:last-of-type{
    color:#111;
}

/* ===================== */
/* PRODUCT PAGE */
/* ===================== */

.pd-main{
    max-width:1200px;
    margin:0 auto;

    padding:28px 24px 72px;

    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(300px,400px);

    gap:28px;
    align-items:start;
}

@media (max-width:960px){
    .pd-main{
        grid-template-columns:1fr;
    }
}

/* ===================== */
/* GALLERY */
/* ===================== */

.pd-gallery-panel{
    background:var(--card);
    border:1px solid var(--border);

    border-radius:20px;

    padding:20px;

    box-shadow:var(--shadow);
}

.pd-main-visual{
    position:relative;

    aspect-ratio:1;
    max-height:560px;

    margin:0 auto;

    background:#fff;

    border-radius:16px;

    overflow:hidden;

    border:1px solid var(--border);
}

.pd-main-visual img{
    width:100%;
    height:100%;

    object-fit:contain;
    display:block;
}

.pd-main-visual .no-photo{
    width:100%;
    height:100%;

    display:flex;
    align-items:center;
    justify-content:center;

    color:#777;
    font-size:14px;
}

/* THUMBS */

.pd-thumbs{
    display:flex;
    gap:10px;

    margin-top:14px;

    overflow-x:auto;

    padding-bottom:4px;

    scrollbar-width:none;
}

.pd-thumbs::-webkit-scrollbar{
    display:none;
}

.pd-thumb{
    flex:0 0 72px;

    width:72px;
    height:72px;

    border-radius:12px;

    overflow:hidden;

    border:2px solid transparent;

    cursor:pointer;

    background:#fff;

    transition:var(--transition);
}

.pd-thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.pd-thumb.active,
.pd-thumb:hover{
    border-color:#111;
}

/* ===================== */
/* BUY PANEL */
/* ===================== */

.pd-buy-panel{
    background:var(--card);

    border:1px solid var(--border);

    border-radius:20px;

    padding:26px 24px;

    box-shadow:var(--shadow);

    position:sticky;
    top:88px;
}

/* TITLE */

.pd-title{
    font-family:'Playfair Display', serif;

    font-size:28px;
    font-weight:600;

    line-height:1.3;

    margin-bottom:18px;
}

/* PRICE */

.pd-price-row{
    display:flex;
    align-items:baseline;
    flex-wrap:wrap;

    gap:12px;

    margin-bottom:10px;
}

.pd-price{
    font-size:30px;
    font-weight:700;

    color:#111;
}

/* STOCK */

.pd-stock{
    font-size:14px;
    color:#3f8f5b;

    margin-bottom:20px;
}

.pd-stock.out{
    color:#d34f4f;
}

/* LABELS */

.pd-chips-label{
    font-size:10px;

    font-weight:600;

    text-transform:uppercase;

    letter-spacing:2px;

    color:#777;

    margin:18px 0 10px;
}

/* CHIPS */

.pd-chips{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.pd-chip{
    padding:8px 14px;

    border-radius:999px;

    border:1px solid var(--border);

    background:#fff;

    font-size:12px;

    color:#111;

    cursor:pointer;

    transition:var(--transition);
}

.pd-chip-choice:hover{
    border-color:#111;
}

.pd-chip-choice.active{
    border-color:#111;
    background:#111;
    color:#fff;
}

.pd-chip-static{
    cursor:default;
    pointer-events:none;
}

/* ===================== */
/* QUANTITY */
/* ===================== */

.pd-qty-row{
    display:flex;
    align-items:center;
    gap:14px;

    margin:24px 0 16px;
}

.pd-qty-row label{
    font-size:12px;
    color:#777;

    letter-spacing:1px;
    text-transform:uppercase;
}

.pd-qty{
    display:inline-flex;
    align-items:center;

    border:1px solid var(--border);

    border-radius:12px;

    overflow:hidden;

    background:#fff;
}

.pd-qty button{
    width:40px;
    height:40px;

    border:none;

    background:#f1f1f1;

    cursor:pointer;

    font-size:18px;

    color:#111;

    transition:var(--transition);
}

.pd-qty button:hover{
    background:#111;
    color:#fff;
}

.pd-qty input{
    width:52px;
    height:40px;

    border:none;

    text-align:center;

    font-size:15px;
    font-weight:600;

    background:#fff;

    color:#111;
}

.pd-qty input:focus{
    outline:none;
}

/* ===================== */
/* BUTTON */
/* ===================== */

.pd-cta{
    width:100%;

    padding:15px;

    border:1px solid #111;

    border-radius:12px;

    background:#111;
    color:#fff;

    font-size:11px;
    font-weight:600;

    letter-spacing:2px;
    text-transform:uppercase;

    cursor:pointer;

    transition:var(--transition);

    text-decoration:none;

    display:block;
    text-align:center;
}

.pd-cta:hover{
    background:#fff;
    color:#111;
}

/* ===================== */
/* DESCRIPTION */
/* ===================== */

.pd-about{
    margin-top:28px;

    padding-top:24px;

    border-top:1px solid var(--border);
}

.pd-about h2{
    font-family:'Playfair Display', serif;

    font-size:18px;

    margin-bottom:12px;

    color:#111;
}

.pd-about p{
    font-size:14px;

    color:#555;

    line-height:1.7;

    white-space:pre-wrap;
}

/* ===================== */
/* ALERTS */
/* ===================== */

.message{
    max-width:720px;

    margin:0 auto 24px;

    padding:14px 18px;

    border-radius:12px;

    text-align:center;

    font-size:14px;
}

.message.success{
    background:rgba(107,191,138,0.12);
    border:1px solid rgba(107,191,138,0.35);

    color:#3f8f5b;
}

.message.danger{
    background:rgba(232,93,93,0.12);
    border:1px solid rgba(232,93,93,0.35);

    color:#d34f4f;
}

/* ===================== */
/* FOOTER */
/* ===================== */

footer.site-footer{
    background:#111;

    border-top:none;

    padding:30px 24px;

    text-align:center;

    color:#aaa;

    font-size:12px;

    margin-top:70px;
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
                    <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <li><a href="admin.php?tab=products">Админ</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="cart.php">Корзина
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?= (int) $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="logout.php">Выход</a></li>
                <?php else: ?>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="products.php">Каталог</a></li>
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

<div class="container">
<nav class="breadcrumbs" aria-label="Навигация">
    <a href="index.php">Главная</a>
    <span class="sep">/</span>
    <a href="products.php">Каталог</a>
    <?php if ($catKey !== ''): ?>
        <span class="sep">/</span>
        <a href="products.php?category=<?= htmlspecialchars($catKey) ?>"><?= htmlspecialchars($catLabel) ?></a>
    <?php endif; ?>
    <span class="sep">/</span>
    <span><?= htmlspecialchars($nameBcShort) ?></span>
</nav>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?= htmlspecialchars($_SESSION['message_type']) === 'success' ? 'success' : 'danger' ?>">
        <?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>
</div>

<main class="pd-main">
    <div class="pd-gallery-panel">
        <div class="pd-main-visual" id="pd-main-visual">
            <?php if ($slides[0] !== ''): ?>
                <img src="<?= htmlspecialchars($slides[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="pd-main-img">
            <?php else: ?>
                <div class="no-photo">Нет фото</div>
            <?php endif; ?>
        </div>
        <?php if (count($slides) > 1): ?>
            <div class="pd-thumbs" id="pd-thumbs" role="tablist">
                <?php foreach ($slides as $i => $src): ?>
                    <?php if ($src === '') { continue; } ?>
                    <button type="button" class="pd-thumb<?= $i === 0 ? ' active' : '' ?>" data-src="<?= htmlspecialchars($src) ?>" aria-label="Фото <?= $i + 1 ?>">
                        <img src="<?= htmlspecialchars($src) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside class="pd-buy-panel">
        <h1 class="pd-title"><?= htmlspecialchars($product['name']) ?></h1>
        <div class="pd-price-row">
            <span class="pd-price"><?= number_format($price, 0, ',', ' ') ?> ₽</span>
        </div>
        <p class="pd-stock<?= $stock <= 0 ? ' out' : '' ?>">
            <?php if ($stock > 0): ?>
                В наличии <?= $stock ?> шт. · доставка по РФ (демо)
            <?php else: ?>
                Нет в наличии
            <?php endif; ?>
        </p>

        <?php if (isset($_SESSION['user_id']) && $stock > 0): ?>
            <form method="post" action="">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="hidden" name="cart_size" id="pd-cart-size" value="<?= htmlspecialchars($defaultSize) ?>">
                <input type="hidden" name="cart_color" id="pd-cart-color" value="<?= htmlspecialchars($defaultColor) ?>">

                <?php if ($sizesList !== []): ?>
                    <p class="pd-chips-label">Размер</p>
                    <div class="pd-chips" role="group" aria-label="Размер">
                        <?php foreach ($sizesList as $i => $sz): ?>
                            <button type="button" class="pd-chip pd-chip-choice pd-chip-size<?= $i === 0 ? ' active' : '' ?>" data-value="<?= htmlspecialchars($sz) ?>"><?= htmlspecialchars($sz) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($colorsList !== []): ?>
                    <p class="pd-chips-label">Цвет</p>
                    <div class="pd-chips" role="group" aria-label="Цвет">
                        <?php foreach ($colorsList as $i => $c): ?>
                            <button type="button" class="pd-chip pd-chip-choice pd-chip-color<?= $i === 0 ? ' active' : '' ?>" data-value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="pd-qty-row">
                    <label for="pd-qty-input">Количество</label>
                    <div class="pd-qty">
                        <button type="button" id="pd-qty-minus" aria-label="Меньше">−</button>
                        <input id="pd-qty-input" name="quantity" type="number" value="1" min="1" max="<?= $stock ?>">
                        <button type="button" id="pd-qty-plus" aria-label="Больше">+</button>
                    </div>
                </div>
                <button type="submit" name="add_to_cart" value="1" class="pd-cta">В корзину</button>
            </form>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <?php if ($sizesList !== []): ?>
                <p class="pd-chips-label">Размер</p>
                <div class="pd-chips">
                    <?php foreach ($sizesList as $sz): ?>
                        <span class="pd-chip pd-chip-static"><?= htmlspecialchars($sz) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($colorsList !== []): ?>
                <p class="pd-chips-label">Цвет</p>
                <div class="pd-chips">
                    <?php foreach ($colorsList as $c): ?>
                        <span class="pd-chip pd-chip-static"><?= htmlspecialchars($c) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="pd-cta" href="login.php">Войти, чтобы заказать</a>
        <?php else: ?>
            <?php if ($sizesList !== []): ?>
                <p class="pd-chips-label">Размер</p>
                <div class="pd-chips">
                    <?php foreach ($sizesList as $sz): ?>
                        <span class="pd-chip pd-chip-static"><?= htmlspecialchars($sz) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($colorsList !== []): ?>
                <p class="pd-chips-label">Цвет</p>
                <div class="pd-chips">
                    <?php foreach ($colorsList as $c): ?>
                        <span class="pd-chip pd-chip-static"><?= htmlspecialchars($c) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <button type="button" class="pd-cta" disabled>Нет в наличии</button>
        <?php endif; ?>

        <div class="pd-about">
            <h2>О товаре</h2>
            <p><?= $product['description'] !== '' ? htmlspecialchars($product['description']) : 'Описание скоро появится.' ?></p>
        </div>
    </aside>
</main>

<footer class="site-footer">&copy; <?= date('Y') ?> VELURA</footer>

<script>
(function() {
    var main = document.getElementById('pd-main-img');
    var thumbs = document.querySelectorAll('.pd-thumb');
    thumbs.forEach(function(t) {
        t.addEventListener('click', function() {
            var src = this.getAttribute('data-src');
            if (main && src) {
                main.src = src;
            }
            thumbs.forEach(function(x) { x.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    var qty = document.getElementById('pd-qty-input');
    var max = qty ? parseInt(qty.getAttribute('max'), 10) : 0;
    function setVal(n) {
        if (!qty) return;
        n = Math.max(1, Math.min(max || 9999, n));
        qty.value = String(n);
    }
    var minus = document.getElementById('pd-qty-minus');
    var plus = document.getElementById('pd-qty-plus');
    if (minus && plus && qty) {
        minus.addEventListener('click', function() { setVal(parseInt(qty.value, 10) - 1); });
        plus.addEventListener('click', function() { setVal(parseInt(qty.value, 10) + 1); });
    }

    var hidSize = document.getElementById('pd-cart-size');
    var hidColor = document.getElementById('pd-cart-color');
    document.querySelectorAll('.pd-chip-size').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pd-chip-size').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            if (hidSize) {
                hidSize.value = this.getAttribute('data-value') || '';
            }
        });
    });
    document.querySelectorAll('.pd-chip-color').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pd-chip-color').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            if (hidColor) {
                hidColor.value = this.getAttribute('data-value') || '';
            }
        });
    });
})();
</script>

</body>
</html>
