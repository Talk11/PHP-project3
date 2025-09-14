<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получаем имя пользователя
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $user['username'] ?? 'Гость';

// Обработка формы добавления транзакции
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount']);
    $type = $_POST['type'] ?? '';
    $category = trim($_POST['category']);
    $transaction_date = $_POST['transaction_date'];

    if (!empty($amount) && is_numeric($amount) && $amount > 0 && !empty($type) && !empty($category) && !empty($transaction_date)) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, category, transaction_date) VALUES (:user_id, :amount, :type, :category, :transaction_date)");
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'amount' => $amount,
            'type' => $type,
            'category' => $category,
            'transaction_date' => $transaction_date
        ]);
        header('Location: index.php');
        exit;
    } else {
        $error = "Заполните все поля корректно!";
    }
}

// Фильтры
$type_filter = $_GET['type'] ?? '';
$category_filter = $_GET['category'] ?? '';
$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = ['user_id' => $_SESSION['user_id']];
if ($type_filter) {
    $query .= " AND type = :type";
    $params['type'] = $type_filter;
}
if ($category_filter) {
    $query .= " AND category = :category";
    $params['category'] = $category_filter;
}
$query .= " ORDER BY transaction_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчёт баланса
$stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = :user_id GROUP BY type");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
$income = 0;
$expense = 0;
foreach ($totals as $total) {
    if ($total['type'] === 'income') $income = $total['total'];
    if ($total['type'] === 'expense') $expense = $total['total'];
}
$balance = $income - $expense;

// Получение категорий
$categories = $pdo->query("SELECT DISTINCT category FROM transactions WHERE user_id = {$_SESSION['user_id']}")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Калькулятор бюджета</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Калькулятор бюджета</h1>
        <p class="text-muted">Привет, <?php echo htmlspecialchars($username); ?>!</p>
        <p class="fs-5">Баланс: <span class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
            <?php echo number_format($balance, 2); ?> руб.
        </span></p>

        <!-- Форма добавления транзакции -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Добавить транзакцию</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Сумма (руб.)</label>
                        <input type="number" step="0.01" class="form-control" name="amount">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип</label>
                        <select name="type" class="form-select">
                            <option value="income">Доход</option>
                            <option value="expense">Расход</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Категория</label>
                        <input type="text" class="form-control" name="category" placeholder="Например, Еда, Зарплата">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Дата</label>
                        <input type="date" class="form-control" name="transaction_date">
                    </div>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </form>
                <?php if (isset($error)) echo "<div class='alert alert-danger mt-3'>$error</div>"; ?>
            </div>
        </div>

        <!-- Фильтры -->
        <form method="GET" class="mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="form-label">Тип</label>
                    <select name="type" class="form-select">
                        <option value="">Все</option>
                        <option value="income" <?php echo $type_filter === 'income' ? 'selected' : ''; ?>>Доход</option>
                        <option value="expense" <?php echo $type_filter === 'expense' ? 'selected' : ''; ?>>Расход</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Категория</label>
                    <select name="category" class="form-select">
                        <option value="">Все</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Фильтровать</button>
                </div>
            </div>
        </form>

        <!-- Список транзакций -->
        <h2 class="mb-3">Транзакции</h2>
        <?php if ($transactions): ?>
            <div class="row">
                <?php foreach ($transactions as $transaction): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($transaction['category']); ?></h5>
                                <p class="card-text <?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>
                                    <?php echo number_format($transaction['amount'], 2); ?> руб.
                                </p>
                                <p class="card-text">Дата: <?php echo $transaction['transaction_date']; ?></p>
                                <p class="card-text"><small class="text-muted">Создано: <?php echo $transaction['created_at']; ?></small></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Транзакций пока нет.</p>
        <?php endif; ?>
        <p class="mt-3"><a href="logout.php" class="btn btn-outline-secondary">Выйти</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>