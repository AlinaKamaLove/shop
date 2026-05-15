<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_set_cookie_params(86400, '/');
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/connect.php';
$pdo = getDB();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
cart_session_normalize($_SESSION['cart']);

$cartRaw = $_SESSION['cart'];
$cartItems = [];
$total = 0;

if (!empty($cartRaw)) {
    $idSet = [];
    foreach ($cartRaw as $row) {
        if (is_array($row) && isset($row['product_id'])) {
            $idSet[(int) $row['product_id']] = true;
        }
    }
    $ids = array_keys($idSet);
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id IN ($placeholders)");
        $stmt->execute($ids);
        $productsById = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $productsById[(int) $product['product_id']] = $product;
        }
        foreach ($cartRaw as $row) {
            if (!is_array($row) || !isset($row['product_id'])) {
                continue;
            }
            $pid = (int) $row['product_id'];
            if (!isset($productsById[$pid])) {
                continue;
            }
            $product = $productsById[$pid];
            $qty = (int) $row['quantity'];
            $line = $product['price'] * $qty;
            $total += $line;
            $cartItems[] = [
                'product' => $product,
                'quantity' => $qty,
                'size' => isset($row['size']) ? (string) $row['size'] : '',
                'color' => isset($row['color']) ? (string) $row['color'] : '',
                'subtotal' => $line,
            ];
        }
    }
}

$errors = [];
$done = isset($_GET['success']) && $_GET['success'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {
    $name = trim($_POST['recipient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $cardHolder = trim($_POST['card_holder'] ?? '');
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $card_cvc = trim($_POST['card_cvc'] ?? '');

    if ($name === '') {
        $errors[] = 'Укажите получателя';
    }
    if ($address === '') {
        $errors[] = 'Укажите адрес доставки';
    }
    if ($phone === '' || strlen($phone) < 10) {
        $errors[] = 'Укажите корректный телефон';
    }
    if ($cardHolder === '') {
        $errors[] = 'Укажите имя на карте';
    }
    if (!preg_match('/^\d{16}$/', $cardNumber)) {
        $errors[] = 'Номер карты: ровно 16 цифр (демо-форма, реальные данные не обрабатываются)';
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $errors[] = 'Срок действия в формате ММ/ГГ';
    }
    if (!preg_match('/^\d{3}$/', $card_cvc)) {
        $errors[] = 'CVC: 3 цифры';
    }

    if (!$cartItems) {
        $errors[] = 'Корзина пуста';
    }

    if (!$errors) {
        $payNote = 'Демо: карта ****' . substr($cardNumber, -4) . ', срок ' . $cardExpiry;
        try {
            $pdo->beginTransaction();
            foreach ($cartItems as $row) {
                $pid = (int) $row['product']['product_id'];
                $qty = (int) $row['quantity'];
                $u = $pdo->prepare('UPDATE Products SET stock = stock - ? WHERE product_id = ? AND stock >= ?');
                $u->execute([$qty, $pid, $qty]);
                if ($u->rowCount() === 0) {
                    throw new RuntimeException('Недостаточно товара «' . ($row['product']['name'] ?? '') . '» на складе');
                }
            }

            $orderExtras = false;
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM Orders LIKE 'recipient_name'");
                $orderExtras = $chk && $chk->rowCount() > 0;
            } catch (Throwable $e) {
                $orderExtras = false;
            }

            if ($orderExtras) {
                $ins = $pdo->prepare(
                    "INSERT INTO Orders (user_id, total_amount, created_at, status, recipient_name, delivery_address, phone, payment_comment) " .
                    "VALUES (?, ?, NOW(), 'completed', ?, ?, ?, ?)"
                );
                $ins->execute([$_SESSION['user_id'], $total, $name, $address, $phone, $payNote]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO Orders (user_id, total_amount, created_at, status) VALUES (?, ?, NOW(), 'completed')"
                );
                $ins->execute([$_SESSION['user_id'], $total]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            cart_persist_cookie([]);
            header('Location: checkout.php?success=1');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
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
<title>Оплата | VELURA</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&amp;family=Montserrat:wght@300;400;500;600&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root{
    --bg:#e7e7e7;
    --card:#ffffff;
    --soft:#f3f3f3;
    --text:#1a1a1a;
    --muted:#666;
    --accent:#111;
    --border:#d6d6d6;
    --shadow:0 10px 30px rgba(0,0,0,0.08);
    --err:#d9534f;
    --ok:#5cb85c;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
   font-family: 'Montserrat', sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
}

/* HEADER */
header{
    position:sticky;
    top:0;
    z-index:100;
    background:rgba(235,235,235,0.82);
    backdrop-filter:blur(16px);
    border-bottom:1px solid rgba(0,0,0,0.05);
    box-shadow:0 4px 16px rgba(0,0,0,0.03);
}

.container{
    max-width:960px;
    margin:auto;
    padding:0 24px;
}

.header-content{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 0;
}

.logo{
    font-family:'Playfair Display', serif;
    font-size:28px;
    letter-spacing:4px;
    text-decoration:none;
    color:#111;
    text-transform:uppercase;
}

/* NAVIGATION */
nav ul{
    display:flex;
    list-style:none;
    gap:24px;
    flex-wrap:wrap;
}

nav a{
    color:#111;
    text-decoration:none;
    font-size:13px;
    letter-spacing:1px;
    text-transform:uppercase;
    transition:0.3s;
}

nav a:hover{
    opacity:0.6;
}

/* PAGE WRAP */
.wrap{
    max-width:640px;
    margin:50px auto 90px;
}

/* TITLE */
h1{
    font-family:'Playfair Display', serif;
    font-size:42px;
    text-align:center;
    margin-bottom:10px;
    letter-spacing:2px;
}

.note{
    text-align:center;
    color:#666;
    font-size:14px;
    margin-bottom:34px;
    line-height:1.6;
}

/* CARD */
.card{
    background:var(--card);
    border:1px solid #ddd;
    border-radius:20px;
    padding:32px;
    box-shadow:var(--shadow);
    margin-bottom:28px;
}

.card h2{
    font-family:'Playfair Display', serif;
    font-size:22px;
    margin-bottom:20px;
    color:#111;
    font-weight:500;
    letter-spacing:1px;
}

/* ORDER SUMMARY */
.summary-line{
    display:flex;
    justify-content:space-between;
    padding:12px 0;
    border-bottom:1px solid var(--border);
    font-size:15px;
}

.summary-line.total{
    border-bottom:none;
    font-weight:600;
    font-size:20px;
    margin-top:8px;
    padding-top:16px;
}

/* LABELS */
label{
    display:block;
    font-size:11px;
    letter-spacing:2px;
    text-transform:uppercase;
    color:#666;
    margin-bottom:8px;
    margin-top:18px;
}

label:first-of-type{
    margin-top:0;
}

/* INPUTS */
input,
textarea{
    width:100%;
    padding:14px 16px;
    border:1px solid #ddd;
    border-radius:12px;
    background:#fafafa;
    color:#111;
    font-family:inherit;
    font-size:15px;
    transition:0.3s;
}

input:focus,
textarea:focus{
    outline:none;
    border-color:#111;
    background:#fff;
}

textarea{
    min-height:90px;
    resize:vertical;
}

/* ===================== */
/* FIX CVV + EXPIRY ROW */
/* ===================== */

.row2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    align-items:end;
    margin-top:6px;
}

.row2 > div{
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
    padding-top:6px;
}

.row2 label{
    margin-top:0;
    margin-bottom:8px;
}

/* одинаковая высота + ровный низ */
.row2 input{
    height:52px;
    line-height:52px;
}

/* ERRORS */
.errors{
    background:rgba(217,83,79,0.08);
    border:1px solid rgba(217,83,79,0.25);
    color:#b02a27;
    padding:16px;
    border-radius:12px;
    margin-bottom:22px;
    font-size:14px;
}

.errors ul{
    margin:0;
    padding-left:18px;
}

/* SUCCESS */
.success-box{
    text-align:center;
    padding:50px 24px;
}

.success-box i{
    font-size:52px;
    color:var(--ok);
    margin-bottom:18px;
}

.success-box p{
    color:#666;
    margin-top:14px;
    line-height:1.6;
}

/* BUTTONS */
.btn{
    display:inline-block;
    width:100%;
    text-align:center;
    padding:16px;
    margin-top:24px;
    border:none;
    border-radius:14px;
    background:#111;
    color:#fff;
    font-size:12px;
    letter-spacing:2px;
    text-transform:uppercase;
    cursor:pointer;
    font-family:inherit;
    text-decoration:none;
    transition:0.35s ease;
}

.btn:hover{
    transform:translateY(-2px);
    background:#222;
}

.btn-secondary{
    background:#f5f5f5;
    color:#111;
    border:1px solid #ddd;
    margin-top:14px;
}

.btn-secondary:hover{
    background:#eaeaea;
}

/* FOOTER */
footer{
    background:#111;
    color:#aaa;
    padding:30px 20px;
    text-align:center;
    font-size:12px;
    margin-top:70px;
}
@media (max-width: 600px) {
    .row2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header>
<div class="container header-content">
<a href="index.php" class="logo">VELURA</a>
<nav>
<ul>
<li><a href="index.php">Главная</a></li>
<li><a href="products.php">Каталог</a></li>
<li><a href="cart.php">Корзина</a></li>
<li><a href="profile.php">Профиль</a></li>
</ul>
</nav>
</div>
</header>

<div class="container wrap">

<?php if ($done): ?>
<div class="card success-box">
<i class="fas fa-check-circle"></i>
<h1>Заказ оформлен</h1>
<p>Это демонстрационная оплата: платёж не проводился, списание не выполнялось.</p>
<p>Состав заказа сохранён в системе, остатки на складе уменьшены.</p>
<a href="products.php" class="btn">В каталог</a>
<a href="profile.php" class="btn btn-secondary">Профиль</a>
</div>

<?php elseif (!$cartItems && !$errors): ?>
<div class="card">
<p style="text-align:center;color:var(--muted);">Корзина пуста — нечего оплачивать.</p>
<a href="products.php" class="btn btn-secondary" style="display:block;margin-top:20px;">Перейти в каталог</a>
</div>

<?php else: ?>

<h1>Оформление и оплата</h1>
<p class="note">Учебная форма: введите тестовые данные. Номер карты — 16 цифр (например 4242 4242 4242 4242), CVC — 123, срок 12/30.</p>

<?php if ($errors): ?>
<div class="errors">
<ul>
<?php foreach ($errors as $er): ?>
<li><?= htmlspecialchars($er) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div class="card">
<h2>Заказ</h2>
<?php foreach ($cartItems as $row): ?>
<div class="summary-line">
<span><?= htmlspecialchars($row['product']['name']) ?><?php
$bits = [];
if (($row['size'] ?? '') !== '') {
    $bits[] = 'размер ' . $row['size'];
}
if (($row['color'] ?? '') !== '') {
    $bits[] = $row['color'];
}
if ($bits !== []) {
    echo ' (' . htmlspecialchars(implode(', ', $bits)) . ')';
}
?> × <?= (int) $row['quantity'] ?></span>
<span><?= number_format($row['subtotal'], 2, ',', ' ') ?> ₽</span>
</div>
<?php endforeach; ?>
<div class="summary-line total">
<span>Итого к оплате</span>
<span><?= number_format($total, 2, ',', ' ') ?> ₽</span>
</div>
</div>

<div class="card">
<h2>Доставка</h2>
<form method="post" action="checkout.php" autocomplete="off">
<label for="recipient_name">Получатель</label>
<input type="text" id="recipient_name" name="recipient_name" required
    value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>">

<label for="phone">Телефон</label>
<input type="tel" id="phone" name="phone" placeholder="+7 900 000-00-00" required
    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

<label for="delivery_address">Адрес доставки</label>
<textarea id="delivery_address" name="delivery_address" required><?= htmlspecialchars($_POST['delivery_address'] ?? '') ?></textarea>

<h2 style="margin-top:28px;">Карта (демо)</h2>
<label for="card_holder">Имя на карте</label>
<input type="text" id="card_holder" name="card_holder" placeholder="IVAN IVANOV" required
    value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>">

<label for="card_number">Номер карты</label>
<input type="text" id="card_number" name="card_number" inputmode="numeric" maxlength="19" placeholder="4242 4242 4242 4242" required
    value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>">

<div class="row2">
<div>
<label for="card_expiry">Срок (ММ/ГГ)</label>
<input type="text" id="card_expiry" name="card_expiry" placeholder="12/30" maxlength="5" required
    value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>">
</div>
<div>
<label for="card_cvc">CVC</label>
<input type="text" id="card_cvc" name="card_cvc" inputmode="numeric" maxlength="3" placeholder="123" required
    value="<?= htmlspecialchars($_POST['card_cvc'] ?? '') ?>">
</div>
</div>

<button type="submit" class="btn">Оплатить <?= number_format($total, 0, ',', ' ') ?> ₽</button>
<a href="cart.php" class="btn btn-secondary" style="display:block;">Назад в корзину</a>
</form>
</div>

<?php endif; ?>

</div>

<footer>&copy; <?= date('Y') ?> VELURA · демо-оплата</footer>

</body>
</html>
