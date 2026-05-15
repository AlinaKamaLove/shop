<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_set_cookie_params(86400, '/');
    session_start();
}

// Проверяем авторизацию пользователя
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Для просмотра корзины необходимо авторизоваться';
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/connect.php';

// Инициализация корзины (из сессии или куки)
if (!isset($_SESSION['cart'])) {
    if (isset($_COOKIE['cart'])) {
        $_SESSION['cart'] = json_decode($_COOKIE['cart'], true);
    } else {
        $_SESSION['cart'] = [];
    }
}
if (!is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
cart_session_normalize($_SESSION['cart']);
cart_persist_cookie($_SESSION['cart']);

$pdo = getDB();

if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product && $product['stock'] > 0) {
            cart_session_normalize($_SESSION['cart']);
            if (cart_add_or_merge($_SESSION['cart'], $productId, 1, '', '', (int) $product['stock'])) {
                cart_persist_cookie($_SESSION['cart']);
                $_SESSION['message'] = 'Товар добавлен в корзину!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Недостаточно товара на складе';
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            $_SESSION['message'] = 'Товара нет в наличии';
            $_SESSION['message_type'] = 'danger';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Ошибка при добавлении товара: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header("Location: cart.php");
    exit();
}

// Обработка удаления товара
if (isset($_POST['remove_item']) && isset($_POST['line_index'])) {
    cart_session_normalize($_SESSION['cart']);
    $idx = (int)$_POST['line_index'];
    if (isset($_SESSION['cart'][$idx])) {
        unset($_SESSION['cart'][$idx]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        cart_persist_cookie($_SESSION['cart']);
        $_SESSION['message'] = 'Товар удален из корзины';
        $_SESSION['message_type'] = 'success';
    }
    
    header("Location: cart.php");
    exit();
}

// Обработка обновления количества
if (isset($_POST['update_quantity']) && isset($_POST['line_index'])) {
    cart_session_normalize($_SESSION['cart']);
    $idx = (int)$_POST['line_index'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    try {
        if (!isset($_SESSION['cart'][$idx])) {
            $_SESSION['message'] = 'Строка корзины не найдена';
            $_SESSION['message_type'] = 'danger';
        } else {
            $line = $_SESSION['cart'][$idx];
            $productId = (int)$line['product_id'];
            $stmt = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product) {
                $stock = (int) $product['stock'];
                $otherQty = 0;
                foreach ($_SESSION['cart'] as $i => $row) {
                    if ((int) $i !== $idx && (int) $row['product_id'] === $productId) {
                        $otherQty += (int) $row['quantity'];
                    }
                }
                if ($quantity > 0 && $otherQty + $quantity <= $stock) {
                    $_SESSION['cart'][$idx]['quantity'] = $quantity;
                    cart_persist_cookie($_SESSION['cart']);
                    $_SESSION['message'] = 'Количество обновлено';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Недопустимое количество';
                    $_SESSION['message_type'] = 'danger';
                }
            } else {
                unset($_SESSION['cart'][$idx]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                cart_persist_cookie($_SESSION['cart']);
                $_SESSION['message'] = 'Товар не найден';
                $_SESSION['message_type'] = 'danger';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Ошибка при обновлении количества: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header("Location: cart.php");
    exit();
}

// Получаем товары для отображения
$cartItems = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $idSet = [];
    foreach ($_SESSION['cart'] as $row) {
        if (is_array($row) && isset($row['product_id'])) {
            $idSet[(int) $row['product_id']] = true;
        }
    }
    $ids = array_keys($idSet);
    if ($ids !== []) {
        $placeholders = implode(",", array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id IN ($placeholders)");
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $productsById = [];
        foreach ($products as $product) {
            $productsById[(int) $product['product_id']] = $product;
        }
        foreach ($_SESSION['cart'] as $idx => $row) {
            if (!is_array($row) || !isset($row['product_id'])) {
                continue;
            }
            $productId = (int) $row['product_id'];
            if (!isset($productsById[$productId])) {
                continue;
            }
            $product = $productsById[$productId];
            $quantity = (int) $row['quantity'];
            $subtotal = $product['price'] * $quantity;
            $total += $subtotal;
            $cartItems[] = [
                'line_index' => $idx,
                'product' => $product,
                'quantity' => $quantity,
                'size' => isset($row['size']) ? (string) $row['size'] : '',
                'color' => isset($row['color']) ? (string) $row['color'] : '',
                'subtotal' => $subtotal
            ];
        }
    }
}

$cart_count = cart_total_items($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Корзина | VELURA</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&amp;family=Montserrat:wght@300;400;500;600&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    /* 🔥 СВЕТЛАЯ ТЕМА */
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

    --err: #d34f4f;
    --ok: #3f8f5b;
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
}

/* ===================== */
/* HEADER */
/* ===================== */

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

/* ===================== */
/* CART */
/* ===================== */

.cart-wrap{
    max-width:900px;
    margin:48px auto 80px;
}

.cart-card{
    background:var(--card);

    border:1px solid var(--border);

    border-radius:20px;

    padding:36px;

    box-shadow:var(--shadow);
}

.cart-card h1{
    font-family:'Playfair Display', serif;

    font-size:30px;

    text-align:center;

    margin-bottom:32px;

    color:#111;
}

/* ===================== */
/* ALERTS */
/* ===================== */

.message{
    padding:14px;

    margin-bottom:20px;

    border-radius:12px;

    text-align:center;

    font-size:14px;
}

.message-success{
    background:rgba(107,191,138,0.12);

    border:1px solid rgba(107,191,138,0.3);

    color:var(--ok);
}

.message-danger{
    background:rgba(232,93,93,0.12);

    border:1px solid rgba(232,93,93,0.3);

    color:var(--err);
}

/* ===================== */
/* EMPTY CART */
/* ===================== */

.empty-cart{
    text-align:center;
    padding:40px 20px;
}

.empty-cart p{
    color:var(--muted);
    margin-bottom:22px;
}

/* ===================== */
/* BUTTONS */
/* ===================== */

.btn{
    display:inline-block;

    padding:13px 26px;

    background:#111;

    border:1px solid #111;

    color:#fff;

    text-transform:uppercase;

    letter-spacing:2px;

    font-size:11px;

    text-decoration:none;

    cursor:pointer;

    transition:var(--transition);

    font-family:inherit;

    border-radius:12px;
}

button.btn{
    line-height:1.2;
}

.btn:hover{
    background:#fff;
    color:#111;
}

/* PRIMARY */

.btn-accent{
    background:#111;
    border-color:#111;
    color:#fff;
}

.btn-accent:hover{
    background:#fff;
    color:#111;
}

/* DELETE */

.btn-danger{
    background:transparent;

    border:1px solid var(--err);

    color:var(--err);
}

.btn-danger:hover{
    background:rgba(232,93,93,0.12);
}

/* ===================== */
/* TABLE */
/* ===================== */

.cart-table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.cart-table th,
.cart-table td{
    padding:18px 12px;

    text-align:left;

    border-bottom:1px solid var(--border);
}

.cart-table th{
    font-size:11px;

    letter-spacing:1px;

    text-transform:uppercase;

    color:var(--muted);

    font-weight:500;
}

/* PRODUCT */

.product-cell{
    display:flex;
    align-items:center;
    gap:16px;
}

.product-image{
    width:80px;
    height:80px;

    object-fit:cover;

    border-radius:14px;

    border:1px solid var(--border);
}

.product-meta h3{
    font-family:'Playfair Display', serif;

    font-size:18px;

    font-weight:600;

    color:#111;
}

.product-meta .stock{
    font-size:12px;

    color:var(--muted);

    margin-top:4px;
}

/* ===================== */
/* INPUT */
/* ===================== */

.quantity-input{
    width:64px;

    padding:10px;

    text-align:center;

    border:1px solid var(--border);

    border-radius:10px;

    background:#fff;

    color:#111;
}

.quantity-input:focus{
    outline:none;
    border-color:#111;
}

/* ===================== */
/* TOTAL */
/* ===================== */

.total-row td{
    font-weight:600;

    border-bottom:none;

    padding-top:24px;

    color:#111;
}

/* ===================== */
/* ACTIONS */
/* ===================== */

.cart-actions{
    margin-top:32px;

    display:flex;

    justify-content:center;

    gap:16px;

    flex-wrap:wrap;
}

/* PAY BUTTON */

.btn-pay{
    background:#111;

    border-color:#111;

    color:#fff;
}

.btn-pay:hover{
    background:#fff;
    color:#111;
}

/* ===================== */
/* FOOTER */
/* ===================== */

footer.site-footer{
    background:#efefef;

    padding:30px;

    text-align:center;

    color:#aaa;

    font-size:12px;

    margin-top:70px;
}

/* ===================== */
/* MOBILE */
/* ===================== */

@media (max-width:768px){

    .cart-table{
        display:block;
        overflow-x:auto;
    }

    .cart-card{
        padding:24px;
    }

    .product-cell{
        min-width:260px;
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
    <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <li><a href="admin.php?tab=products">Админ</a></li>
    <?php endif; ?>
    <li>
        <a href="cart.php">Корзина
            <?php if ($cart_count > 0): ?><span class="cart-count"><?= (int)$cart_count ?></span><?php endif; ?>
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

<main class="container cart-wrap">
<div class="cart-card">
<h1>Ваша корзина</h1>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message message-<?= htmlspecialchars($_SESSION['message_type']) ?>"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<?php if (empty($cartItems)): ?>
    <div class="empty-cart">
        <p>Ваша корзина пуста</p>
        <a href="products.php" class="btn btn-accent">Перейти в каталог</a>
    </div>
<?php else: ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th>Товар</th>
                <th>Цена</th>
                <th>Количество</th>
                <th>Сумма</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cartItems as $item): ?>
            <tr>
                <td>
                    <div class="product-cell">
                        <img src="<?= htmlspecialchars($item['product']['photo']) ?>" alt="" class="product-image">
                        <div class="product-meta">
                            <h3><?= htmlspecialchars($item['product']['name']) ?></h3>
                            <?php
                            $vz = [];
                            if (($item['size'] ?? '') !== '') {
                                $vz[] = 'Размер: ' . $item['size'];
                            }
                            if (($item['color'] ?? '') !== '') {
                                $vz[] = 'Цвет: ' . $item['color'];
                            }
                            if ($vz !== []):
                            ?>
                            <div class="cart-variant" style="font-size:12px;color:var(--muted);margin-top:6px;"><?= htmlspecialchars(implode(' · ', $vz)) ?></div>
                            <?php endif; ?>
                            <div class="stock"><?= $item['product']['stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?></div>
                        </div>
                    </div>
                </td>
                <td><?= number_format($item['product']['price'], 2) ?> ₽</td>
                <td>
                    <form method="post" style="display:flex;align-items:center;gap:8px;">
                        <input type="hidden" name="line_index" value="<?= (int)$item['line_index'] ?>">
                        <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= (int)$item['product']['stock'] ?>" class="quantity-input">
                        <button type="submit" name="update_quantity" value="1" class="btn" title="Обновить"><i class="fas fa-sync-alt"></i></button>
                    </form>
                </td>
                <td><?= number_format($item['subtotal'], 2) ?> ₽</td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="line_index" value="<?= (int)$item['line_index'] ?>">
                        <button type="submit" name="remove_item" value="1" class="btn btn-danger"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="3" style="text-align:right;">Итого</td>
            <td><?= number_format($total, 2) ?> ₽</td>
            <td></td>
        </tr>
        </tbody>
    </table>
    <div class="cart-actions">
        <a href="checkout.php" class="btn btn-pay">Перейти к оплате</a>
        <a href="products.php" class="btn btn-accent">Продолжить покупки</a>
    </div>
<?php endif; ?>
</div>
</main>

<footer class="site-footer">
&copy; <?= date('Y') ?> VELURA
</footer>
</body>
</html>
