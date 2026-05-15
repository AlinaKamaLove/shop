<?php
session_start();

require_once __DIR__ . '/connect.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'], $_POST['product_id'])) {
    $wantJson = !empty($_POST['catalog_add_ajax']);

    if (!isset($_SESSION['user_id'])) {
        if ($wantJson) {
            if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                cart_session_normalize($_SESSION['cart']);
            }
            $cc = cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'login' => true,
                'message' => 'Для добавления в корзину войдите в аккаунт',
                'cart_count' => $cc,
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $_SESSION['message'] = 'Для добавления в корзину войдите в аккаунт';
        $_SESSION['message_type'] = 'danger';
        header('Location: login.php');
        exit();
    }

    $productId = (int) $_POST['product_id'];
    $addQty = isset($_POST['quantity']) ? max(1, (int) $_POST['quantity']) : 1;
    $pickSize = isset($_POST['cart_size']) ? trim((string) $_POST['cart_size']) : '';
    $pickColor = isset($_POST['cart_color']) ? trim((string) $_POST['cart_color']) : '';

    $success = false;
    $msg = '';

    try {
        $stmt = $pdo->prepare('SELECT * FROM Products WHERE product_id = ?');
        $stmt->execute([$productId]);
        $prodRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prodRow) {
            $msg = 'Товар не найден';
        } else {
            $alSizes = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($prodRow['sizes'] ?? '')))));
            $alColors = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($prodRow['colors'] ?? '')))));

            $pair = cart_variant_allowed($alSizes, $alColors, $pickSize, $pickColor);
            if ($pair === null) {
                $msg = 'Выберите размер и цвет из списка';
            } else {
                list($pickSize, $pickColor) = $pair;
                $stock = (int) $prodRow['stock'];
                if ($stock <= 0) {
                    $msg = 'Товар недоступен';
                } else {
                    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    cart_session_normalize($_SESSION['cart']);
                    if (cart_add_or_merge($_SESSION['cart'], $productId, $addQty, $pickSize, $pickColor, $stock)) {
                        cart_persist_cookie($_SESSION['cart']);
                        $success = true;
                        $msg = 'Товар добавлен в корзину';
                    } else {
                        $msg = 'Недостаточно товара на складе';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $msg = 'Ошибка: ' . $e->getMessage();
    }

    if ($wantJson) {
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            cart_session_normalize($_SESSION['cart']);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $msg,
            'cart_count' => cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []),
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $_SESSION['message'] = $msg !== '' ? $msg : ($success ? 'Товар добавлен в корзину' : 'Не удалось добавить товар');
    $_SESSION['message_type'] = $success ? 'success' : 'danger';

    $redir = 'products.php';
    if (!empty($_GET)) {
        $redir .= '?' . http_build_query($_GET);
    }
    header('Location: ' . $redir);
    exit();
}

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    cart_session_normalize($_SESSION['cart']);
}
$cart_count = cart_total_items(isset($_SESSION['cart']) ? $_SESSION['cart'] : []);

// Только строковые параметры (массив в ?category[]= ломает PDO и даёт 500 на части хостингов)
$category = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : null;
if ($category === '') {
    $category = null;
}
$search = (isset($_GET['search']) && is_string($_GET['search'])) ? trim($_GET['search']) : '';
$price_min_in = (isset($_GET['price_min']) && is_string($_GET['price_min'])) ? trim($_GET['price_min']) : '';
$price_max_in = (isset($_GET['price_max']) && is_string($_GET['price_max'])) ? trim($_GET['price_max']) : '';
$sort = (isset($_GET['sort']) && is_string($_GET['sort'])) ? $_GET['sort'] : 'new';
if (!in_array($sort, array('new', 'price_asc', 'price_desc'), true)) {
    $sort = 'new';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE :search OR description LIKE :search_desc)';
    $params['search'] = '%' . $search . '%';
    $params['search_desc'] = '%' . $search . '%';
}

if ($category) {
    $where[] = 'category = :category';
    $params['category'] = $category;
}

if ($price_min_in !== '') {
    $pv = (float) str_replace(',', '.', $price_min_in);
    if ($pv >= 0) {
        $where[] = 'price >= :price_min';
        $params['price_min'] = $pv;
    }
}
if ($price_max_in !== '') {
    $pv = (float) str_replace(',', '.', $price_max_in);
    if ($pv >= 0) {
        $where[] = 'price <= :price_max';
        $params['price_max'] = $pv;
    }
}

$sql = 'SELECT * FROM Products';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
if ($sort === 'price_asc') {
    $sql .= ' ORDER BY price ASC, created_at DESC';
} elseif ($sort === 'price_desc') {
    $sql .= ' ORDER BY price DESC, created_at DESC';
} else {
    $sql .= ' ORDER BY created_at DESC';
}

$catalog_error = '';
$products = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $catalog_error = $e->getMessage();
}

$cats = [
    '' => 'Все',
    'clothing' => 'Одежда',
    'shoes' => 'Обувь',
    'accessories' => 'Аксессуары',
];

$catalogFilterQs = array();
if ($search !== '') {
    $catalogFilterQs['search'] = $search;
}
if ($price_min_in !== '') {
    $catalogFilterQs['price_min'] = $price_min_in;
}
if ($price_max_in !== '') {
    $catalogFilterQs['price_max'] = $price_max_in;
}
if ($sort !== 'new') {
    $catalogFilterQs['sort'] = $sort;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Каталог | VELURA</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

    --transition: all .3s ease;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

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

/* PAGE */

.page-title{
    font-family:'Playfair Display',serif;
    font-size:54px;
    text-align:center;
    margin:60px 0 10px;
    color:#111;
    letter-spacing:3px;
}

.page-sub{
    text-align:center;
    color:var(--muted);
    font-size:15px;
    margin-bottom:40px;
}

/* TOOLBAR */

.toolbar{
    max-width:960px;
    margin:0 auto 40px;
    display:flex;
    flex-direction:column;
    gap:20px;
    align-items:center;
}

.categories{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:12px;
}

.categories a{
    text-decoration:none;
    padding:10px 18px;
    border:1px solid rgba(0,0,0,0.08);
    border-radius:999px;
    font-size:11px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:#444;
    background:#f1f1f1;
    transition:var(--transition);
}

.categories a:hover,
.categories a.active{
    background:#111;
    color:#fff;
    border-color:#111;
}

/* FILTERS */

.filters-form{
    width:100%;
    max-width:960px;
    margin:0 auto;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
    justify-content:center;
}

.filters-form input[type="text"]{
    flex:1 1 200px;
    min-width:160px;
    padding:13px 18px;
    border:1px solid rgba(0,0,0,0.08);
    border-radius:14px;
    background:var(--soft);
    color:var(--text);
    font-family:inherit;
    font-size:14px;
}

.filters-form input[type="number"]{
    width:120px;
    padding:13px 14px;
    border:1px solid rgba(0,0,0,0.08);
    border-radius:14px;
    background:var(--soft);
    color:var(--text);
    font-family:inherit;
    font-size:14px;
}

.filters-form select{
    padding:13px 16px;
    border:1px solid rgba(0,0,0,0.08);
    border-radius:14px;
    background:var(--soft);
    color:var(--text);
    font-family:inherit;
    font-size:13px;
    cursor:pointer;
}

.filters-form input:focus,
.filters-form select:focus{
    outline:none;
    border-color:#111;
}

.filters-form button{
    padding:13px 22px;
    border:1px solid #111;
    border-radius:14px;
    background:#111;
    color:#fff;
    font-size:11px;
    letter-spacing:1px;
    text-transform:uppercase;
    cursor:pointer;
    font-family:inherit;
    transition:var(--transition);
}

.filters-form button:hover{
    opacity:.9;
    transform:translateY(-2px);
}

/* MESSAGES */

.message{
    max-width:720px;
    margin:0 auto 24px;
    padding:14px 18px;
    border-radius:14px;
    text-align:center;
    font-size:14px;
}

.message.success{
    background:#ebf7ef;
    border:1px solid #cfe6d7;
    color:#2f7b4c;
}

.message.danger{
    background:#fff0f0;
    border:1px solid #f1cfcf;
    color:#b84b4b;
}

/* GRID */

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:26px;
    padding-bottom:72px;
}

/* CARD */

.card{
    background:var(--card);
    border:1px solid rgba(0,0,0,0.06);
    border-radius:18px;
    overflow:hidden;
    box-shadow:var(--shadow);
    transition:var(--transition);
}

.card:hover{
    transform:translateY(-5px);
    border-color:rgba(0,0,0,0.15);
}

a.card-link{
    display:block;
    text-decoration:none;
    color:inherit;
}

.card-title-link{
    color:inherit;
    text-decoration:none;
    transition:var(--transition);
}

.card-title-link:hover{
    opacity:.6;
}

.card-img-wrap{
    position:relative;
    aspect-ratio:3 / 4;
    overflow:hidden;
    background:var(--soft);
}

.card-img-wrap > img,
.card-carousel .carousel-slide img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
    transition:.5s ease;
}

.card:hover img{
    transform:scale(1.04);
}

.card-carousel{
    width:100%;
    height:100%;
}

.carousel-track{
    display:flex;
    height:100%;
    overflow-x:auto;
    overflow-y:hidden;
    scroll-snap-type:x mandatory;
    scroll-behavior:smooth;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:none;
    -ms-overflow-style:none;
}

.carousel-track::-webkit-scrollbar{
    display:none;
}

.carousel-slide{
    flex:0 0 100%;
    height:100%;
    scroll-snap-align:start;
}

/* CARD BODY */

.card-body{
    padding:20px;
}

.card-body h3{
    font-family:'Playfair Display',serif;
    font-size:22px;
    margin-bottom:10px;
    line-height:1.3;
}

.card-desc{
    font-size:14px;
    color:var(--muted);
    line-height:1.6;
    margin-bottom:14px;

    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

.badge{
    display:inline-block;
    font-size:10px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:#666;
    margin-bottom:12px;
}

.price{
    font-size:22px;
    font-weight:600;
    margin-bottom:16px;
}

.price span{
    font-size:13px;
    font-weight:400;
    color:var(--muted);
}

/* BUTTON */

.btn-cart{
    width:100%;
    padding:14px;
    border:1px solid #111;
    background:#111;
    color:#fff;
    font-size:11px;
    letter-spacing:2px;
    text-transform:uppercase;
    cursor:pointer;
    border-radius:12px;
    font-family:inherit;
    transition:var(--transition);
}

.btn-cart:hover{
    background:#222;
    border-color:#222;
}

/* EMPTY */

.empty{
    text-align:center;
    padding:60px 24px;
    color:var(--muted);
}

/* FOOTER */

footer.site-footer{
    border-top:1px solid rgba(0,0,0,0.06);
    padding:24px;
    text-align:center;
    color:#666;
    font-size:12px;
    background:#efefef;
}

/* MODAL */

.cart-modal-backdrop{
    position:fixed;
    inset:0;
    z-index:3000;
    background:rgba(0,0,0,0.65);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    opacity:0;
    visibility:hidden;
    transition:opacity .25s ease,visibility .25s ease;
    backdrop-filter:blur(6px);
}

.cart-modal-backdrop.is-open{
    opacity:1;
    visibility:visible;
}

.cart-modal-panel{
    background:var(--card);
    border:1px solid rgba(0,0,0,0.08);
    border-radius:20px;
    max-width:440px;
    width:100%;
    padding:28px 26px;
    box-shadow:var(--shadow);
    transform:translateY(12px);
    transition:transform .25s ease;
}

.cart-modal-backdrop.is-open .cart-modal-panel{
    transform:translateY(0);
}

.cart-modal-panel h3{
    font-family:'Playfair Display',serif;
    font-size:24px;
    font-weight:600;
    margin-bottom:8px;
    line-height:1.3;
}

.cart-modal-stock{
    font-size:13px;
    color:var(--muted);
    margin-bottom:22px;
}

.cart-modal-label{
    font-size:10px;
    font-weight:600;
    letter-spacing:2px;
    text-transform:uppercase;
    color:var(--muted);
    margin:18px 0 10px;
}

.cart-modal-label:first-of-type{
    margin-top:0;
}

.cart-modal-chips{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.cart-modal-chip{
    padding:9px 15px;
    border-radius:999px;
    border:1px solid rgba(0,0,0,0.08);
    background:#ececec;
    font-size:12px;
    color:#111;
    cursor:pointer;
    transition:var(--transition);
}

.cart-modal-chip.active{
    border-color:#111;
    background:#111;
    color:#fff;
}

.cart-modal-qty-row{
    display:flex;
    align-items:center;
    gap:12px;
    margin-top:20px;
}

.cart-modal-qty-row label{
    font-size:10px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:var(--muted);
}

.cart-modal-qty-row input{
    width:64px;
    padding:10px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,0.08);
    background:var(--soft);
    color:var(--text);
    font-size:14px;
    text-align:center;
    font-family:inherit;
}

.cart-modal-actions{
    display:flex;
    gap:10px;
    margin-top:26px;
}

.cart-modal-actions button{
    flex:1;
    padding:14px 16px;
    border-radius:12px;
    font-size:11px;
    letter-spacing:2px;
    text-transform:uppercase;
    font-family:inherit;
    cursor:pointer;
    transition:var(--transition);
}

.cart-modal-cancel{
    background:#e4e4e4;
    color:#111;
    border:none;
}

.cart-modal-confirm{
    background:#111;
    color:#fff;
    border:none;
}

.cart-modal-actions button:hover{
    opacity:.92;
}

/* TOAST */

.catalog-toast{
    position:fixed;
    bottom:28px;
    left:50%;
    transform:translateX(-50%) translateY(80px);
    z-index:4000;
    padding:14px 22px;
    border-radius:12px;
    font-size:14px;
    max-width:90vw;
    box-shadow:var(--shadow);
    opacity:0;
    transition:transform .3s ease,opacity .3s ease;
    pointer-events:none;
}

.catalog-toast.is-visible{
    transform:translateX(-50%) translateY(0);
    opacity:1;
}

.catalog-toast.success{
    background:#111;
    color:#fff;
}

.catalog-toast.danger{
    background:#c84e4e;
    color:#fff;
}

/* MOBILE */

@media(max-width:900px){

    .page-title{
        font-size:42px;
    }

    .grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:600px){

    .grid{
        grid-template-columns:1fr;
    }

    .header-content{
        flex-direction:column;
        gap:14px;
    }

    nav ul{
        justify-content:center;
    }

    .page-title{
        font-size:34px;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="profile.php">Профиль</a></li>
                    <li><a href="products.php">Каталог</a></li>
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

<h1 class="page-title">Каталог</h1>
<p class="page-sub">Одежда, обувь и аксессуары</p>

<?php if ($catalog_error !== ''): ?>
    <div class="container">
        <div class="message danger" style="margin-bottom:20px;">
            Ошибка загрузки каталога. Чаще всего в базе нет нужных таблиц или колонок — импортируйте <code>nairak5l_shoping.sql</code>
            в ту же БД, что в <code>connect.php</code>.<br>
            <small style="opacity:0.85;"><?= htmlspecialchars($catalog_error) ?></small>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="container">
        <div class="message <?= htmlspecialchars($_SESSION['message_type']) === 'success' ? 'success' : 'danger' ?>">
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<div class="toolbar">
    <div class="categories">
        <?php
        $base = 'products.php';
        foreach ($cats as $key => $label) {
            if ($key === '') {
                $qs = $catalogFilterQs;
            } else {
                $qs = array_merge($catalogFilterQs, array('category' => $key));
            }
            $href = $base . ($qs ? '?' . http_build_query($qs) : '');
            $isActive = ($category === $key) || ($key === '' && !$category);
            echo '<a href="' . htmlspecialchars($href) . '"' . ($isActive ? ' class="active"' : '') . '>' . htmlspecialchars($label) . '</a>';
        }
        ?>
    </div>
    <form class="filters-form" method="get" action="products.php">
        <?php if ($category): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по названию или описанию" autocomplete="off">
        <input type="number" name="price_min" min="0" step="1" inputmode="numeric" placeholder="Цена от, ₽" value="<?= htmlspecialchars($price_min_in) ?>">
        <input type="number" name="price_max" min="0" step="1" inputmode="numeric" placeholder="Цена до, ₽" value="<?= htmlspecialchars($price_max_in) ?>">
        <select name="sort" aria-label="Сортировка">
            <option value="new"<?= $sort === 'new' ? ' selected' : '' ?>>Сначала новые</option>
            <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Дешевле</option>
            <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Дороже</option>
        </select>
        <button type="submit">Применить</button>
    </form>
</div>

<div class="container">
    <?php if (!$products): ?>
        <div class="empty">Нет товаров по выбранным условиям.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($products as $p): ?>
                <?php
                $slides = product_catalog_slides($p);
                $slideCount = count($slides);
                $badgeKey = $p['category'] ?? '';
                $badgeLabel = ($badgeKey !== '' && isset($cats[$badgeKey])) ? $cats[$badgeKey] : ($badgeKey !== '' ? $badgeKey : 'Каталог');
                ?>
                <div class="card">
                    <?php if ($slideCount === 0): ?>
                    <a class="card-img-wrap card-link" href="product.php?id=<?= (int)$p['product_id'] ?>">
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px;">Нет фото</div>
                    </a>
                    <?php elseif ($slideCount === 1): ?>
                    <a class="card-img-wrap card-link" href="product.php?id=<?= (int)$p['product_id'] ?>">
                        <div class="card-carousel">
                            <div class="carousel-track">
                                <div class="carousel-slide">
                                    <img src="<?= htmlspecialchars($slides[0]) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php else: ?>
                    <div class="card-img-wrap">
                        <div class="card-carousel">
                            <div class="carousel-track">
                                <?php foreach ($slides as $src): ?>
                                    <div class="carousel-slide">
                                        <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="badge"><?= htmlspecialchars($badgeLabel) ?></div>
                        <h3><a class="card-title-link" href="product.php?id=<?= (int)$p['product_id'] ?>"><?= htmlspecialchars($p['name']) ?></a></h3>
                        <?php
                        $desc = (string) $p['description'];
                        if (function_exists('mb_strimwidth')) {
                            $descShort = mb_strimwidth($desc, 0, 140, '…', 'UTF-8');
                        } else {
                            $descShort = strlen($desc) > 140 ? substr($desc, 0, 137) . '...' : $desc;
                        }
                        ?>
                        <p class="card-desc"><?= htmlspecialchars($descShort) ?></p>
                        <div class="price"><?= number_format((float)$p['price'], 0, ',', ' ') ?> ₽ <span>· <?= (int)$p['stock'] ?> шт.</span></div>
                        <?php
                        $sizesArr = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($p['sizes'] ?? '')))));
                        $colorsArr = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) ($p['colors'] ?? '')))));
                        $siz = $sizesArr !== [] ? implode(', ', $sizesArr) : '';
                        $col = $colorsArr !== [] ? implode(', ', $colorsArr) : '';
                        if ($siz !== '' || $col !== ''):
                        ?>
                        <p style="font-size:12px;color:var(--muted);margin-bottom:12px;line-height:1.5;">
                            <?php if ($siz !== ''): ?><strong>Размеры:</strong> <?= htmlspecialchars($siz) ?><?php endif; ?>
                            <?php if ($siz !== '' && $col !== ''): ?> · <?php endif; ?>
                            <?php if ($col !== ''): ?><strong>Цвета:</strong> <?= htmlspecialchars($col) ?><?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php
                            $catalogPayload = [
                                'id' => (int) $p['product_id'],
                                'name' => (string) $p['name'],
                                'sizes' => $sizesArr,
                                'colors' => $colorsArr,
                                'stock' => (int) $p['stock'],
                            ];
                            $catJsonFlags = JSON_UNESCAPED_UNICODE;
                            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                                $catJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
                            }
                            $catJson = json_encode($catalogPayload, $catJsonFlags);
                            if ($catJson === false) {
                                $catJson = '{"id":0,"name":"","sizes":[],"colors":[],"stock":0}';
                            }
                            ?>
                            <button type="button" class="btn-cart js-catalog-add-btn"
                                data-product="<?= htmlspecialchars($catJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">В корзину</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-cart" style="display:block;text-align:center;text-decoration:none;line-height:1.2;">Войти для заказа</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="site-footer">&copy; <?= date('Y') ?> VELURA</footer>

<?php if (isset($_SESSION['user_id'])): ?>
<div id="catalog-cart-modal" class="cart-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="catalog-modal-title" hidden>
    <div class="cart-modal-panel">
        <h3 id="catalog-modal-title"></h3>
        <p class="cart-modal-stock" id="catalog-modal-stock-line"></p>
        <div id="catalog-modal-sizes-block" hidden>
            <p class="cart-modal-label">Размер</p>
            <div class="cart-modal-chips" id="catalog-modal-sizes"></div>
        </div>
        <div id="catalog-modal-colors-block" hidden>
            <p class="cart-modal-label">Цвет</p>
            <div class="cart-modal-chips" id="catalog-modal-colors"></div>
        </div>
        <div class="cart-modal-qty-row">
            <label for="catalog-modal-qty">Количество</label>
            <input type="number" id="catalog-modal-qty" value="1" min="1" step="1">
        </div>
        <div class="cart-modal-actions">
            <button type="button" class="cart-modal-cancel" id="catalog-modal-cancel">Отмена</button>
            <button type="button" class="cart-modal-confirm" id="catalog-modal-confirm">В корзину</button>
        </div>
    </div>
</div>
<div id="catalog-toast" class="catalog-toast" role="status"></div>
<script>
(function() {
    var postUrl = 'products.php' + window.location.search;
    var modal = document.getElementById('catalog-cart-modal');
    var toast = document.getElementById('catalog-toast');
    var titleEl = document.getElementById('catalog-modal-title');
    var stockLine = document.getElementById('catalog-modal-stock-line');
    var sizesBlock = document.getElementById('catalog-modal-sizes-block');
    var colorsBlock = document.getElementById('catalog-modal-colors-block');
    var sizesWrap = document.getElementById('catalog-modal-sizes');
    var colorsWrap = document.getElementById('catalog-modal-colors');
    var qtyInput = document.getElementById('catalog-modal-qty');
    var btnCancel = document.getElementById('catalog-modal-cancel');
    var btnConfirm = document.getElementById('catalog-modal-confirm');
    var cartBadge = document.getElementById('nav-cart-count');
    var currentProduct = null;

    function showToast(text, isErr) {
        if (!toast) return;
        toast.textContent = text;
        toast.className = 'catalog-toast ' + (isErr ? 'danger' : 'success');
        toast.classList.add('is-visible');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function() {
            toast.classList.remove('is-visible');
        }, 3200);
    }

    function updateCartBadge(n) {
        if (!cartBadge) return;
        cartBadge.textContent = String(n);
        cartBadge.style.display = n > 0 ? 'inline-flex' : 'none';
    }

    function openModal() {
        if (!modal) return;
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
        setTimeout(function() { modal.hidden = true; }, 250);
    }

    function renderChips(container, values, cssClass) {
        container.innerHTML = '';
        values.forEach(function(val, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'cart-modal-chip ' + cssClass + (i === 0 ? ' active' : '');
            b.setAttribute('data-value', val);
            b.textContent = val;
            b.addEventListener('click', function() {
                container.querySelectorAll('.' + cssClass).forEach(function(x) { x.classList.remove('active'); });
                b.classList.add('active');
            });
            container.appendChild(b);
        });
    }

    function getSelectedChip(container, cssClass) {
        var a = container.querySelector('.' + cssClass + '.active');
        return a ? (a.getAttribute('data-value') || '') : '';
    }

    function fillModal(p) {
        currentProduct = p;
        titleEl.textContent = p.name;
        stockLine.textContent = 'В наличии ' + p.stock + ' шт.';
        var hasS = p.sizes && p.sizes.length > 0;
        var hasC = p.colors && p.colors.length > 0;
        sizesBlock.hidden = !hasS;
        colorsBlock.hidden = !hasC;
        if (hasS) {
            renderChips(sizesWrap, p.sizes, 'cart-modal-chip-size');
        } else {
            sizesWrap.innerHTML = '';
        }
        if (hasC) {
            renderChips(colorsWrap, p.colors, 'cart-modal-chip-color');
        } else {
            colorsWrap.innerHTML = '';
        }
        qtyInput.value = '1';
        qtyInput.max = String(Math.max(1, p.stock));
        qtyInput.min = '1';
    }

    function catalogAddAjax(productId, qty, size, color) {
        var fd = new FormData();
        fd.append('add_to_cart', '1');
        fd.append('product_id', String(productId));
        fd.append('quantity', String(qty));
        fd.append('cart_size', size);
        fd.append('cart_color', color);
        fd.append('catalog_add_ajax', '1');
        return fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); });
    }

    document.querySelectorAll('.js-catalog-add-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var p;
            try {
                p = JSON.parse(this.getAttribute('data-product'));
            } catch (e) {
                return;
            }
            if (!p || p.stock < 1) {
                showToast('Товар недоступен', true);
                return;
            }
            var needsModal = (p.sizes && p.sizes.length) || (p.colors && p.colors.length);
            if (!needsModal) {
                catalogAddAjax(p.id, 1, '', '').then(function(data) {
                    if (data.login) {
                        window.location.href = 'login.php';
                        return;
                    }
                    if (data.success) {
                        updateCartBadge(data.cart_count);
                        showToast(data.message || 'Товар добавлен в корзину', false);
                    } else {
                        showToast(data.message || 'Ошибка', true);
                    }
                }).catch(function() {
                    showToast('Ошибка сети', true);
                });
                return;
            }
            fillModal(p);
            openModal();
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            if (!currentProduct) return;
            var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
            var max = Math.max(1, currentProduct.stock);
            if (qty > max) qty = max;
            var size = '';
            var color = '';
            if (!sizesBlock.hidden) {
                size = getSelectedChip(sizesWrap, 'cart-modal-chip-size');
            }
            if (!colorsBlock.hidden) {
                color = getSelectedChip(colorsWrap, 'cart-modal-chip-color');
            }
            btnConfirm.disabled = true;
            catalogAddAjax(currentProduct.id, qty, size, color).then(function(data) {
                btnConfirm.disabled = false;
                if (data.login) {
                    window.location.href = 'login.php';
                    return;
                }
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    closeModal();
                    showToast(data.message || 'Товар добавлен в корзину', false);
                } else {
                    showToast(data.message || 'Ошибка', true);
                }
            }).catch(function() {
                btnConfirm.disabled = false;
                showToast('Ошибка сети', true);
            });
        });
    }
})();
</script>
<?php endif; ?>

</body>
</html>
