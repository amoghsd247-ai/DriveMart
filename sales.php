
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function salesText($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$saleStatuses = ['Pending', 'Completed', 'Cancelled'];
$paymentStatuses = ['Pending', 'Paid', 'Partially Paid', 'Refunded'];
$paymentMethods = ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Finance', 'Other'];

if (empty($_SESSION['admin_sales_token'])) {
    $_SESSION['admin_sales_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

// Create a new sale or update an existing sale.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (
        $token === ''
        || !hash_equals($_SESSION['admin_sales_token'], $token)
    ) {
        $message = 'Your session expired. Refresh the page and try again.';
        $messageType = 'danger';
    } elseif (isset($_POST['create_sale'])) {
        $userId = filter_var(
            $_POST['user_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $carId = trim($_POST['car_id'] ?? '');
        $salePrice = trim($_POST['sale_price'] ?? '');
        $saleDate = trim($_POST['sale_date'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $paymentStatus = trim($_POST['payment_status'] ?? 'Pending');
        $saleStatus = trim($_POST['sale_status'] ?? 'Pending');
        $notes = trim($_POST['notes'] ?? '');

        $dateObject = DateTime::createFromFormat('!Y-m-d', $saleDate);
        $dateErrors = DateTime::getLastErrors();

        $validDate = $dateObject !== false
            && $dateObject->format('Y-m-d') === $saleDate
            && (
                $dateErrors === false
                || (
                    $dateErrors['warning_count'] === 0
                    && $dateErrors['error_count'] === 0
                )
            );

        if (!$userId || $carId === '') {
            $message = 'Please select a valid customer and car.';
            $messageType = 'danger';
        } elseif (!preg_match('/^C\d+$/', $carId)) {
            $message = 'Invalid car ID.';
            $messageType = 'danger';
        } elseif (
            !is_numeric($salePrice)
            || (float)$salePrice <= 0
            || (float)$salePrice > 9999999999.99
        ) {
            $message = 'Enter a valid sale price greater than zero.';
            $messageType = 'danger';
        } elseif (!$validDate) {
            $message = 'Please select a valid sale date.';
            $messageType = 'danger';
        } elseif (
            !in_array($paymentMethod, $paymentMethods, true)
            || !in_array($paymentStatus, $paymentStatuses, true)
            || !in_array($saleStatus, $saleStatuses, true)
        ) {
            $message = 'Please select valid sale and payment options.';
            $messageType = 'danger';
        } elseif (strlen($notes) > 5000) {
            $message = 'Notes must not exceed 5000 characters.';
            $messageType = 'danger';
        } else {
            // Verify that the customer and car exist.
            $check = $conn->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM users WHERE user_id = ?) AS user_count,
                    (SELECT COUNT(*) FROM cars WHERE car_id = ?) AS car_count"
            );

            if (!$check) {
                $message = 'Unable to validate the customer and car.';
                $messageType = 'danger';
            } else {
                $check->bind_param("is", $userId, $carId);
                $check->execute();
                $checkResult = $check->get_result()->fetch_assoc();
                $check->close();

                if (
                    (int)$checkResult['user_count'] !== 1
                    || (int)$checkResult['car_count'] !== 1
                ) {
                    $message = 'The selected customer or car does not exist.';
                    $messageType = 'danger';
                } else {
                    $price = (float)$salePrice;
                    $paymentMethodValue = $paymentMethod;
                    $notesValue = $notes !== '' ? $notes : null;

                    $insert = $conn->prepare(
                        "INSERT INTO sales
                            (user_id, car_id, sale_price, sale_date,
                             payment_method, payment_status, sale_status, notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if (!$insert) {
                        $message = 'Unable to prepare the sale record.';
                        $messageType = 'danger';
                    } else {
                        $insert->bind_param(
                            "isdsssss",
                            $userId,
                            $carId,
                            $price,
                            $saleDate,
                            $paymentMethodValue,
                            $paymentStatus,
                            $saleStatus,
                            $notesValue
                        );

                        if ($insert->execute()) {
                            $insert->close();

                            $_SESSION['admin_sales_token'] =
                                bin2hex(random_bytes(32));

                            header("Location: sales.php?created=1");
                            exit();
                        }

                        $insert->close();
                        $message = 'Unable to save the sale. Please check the database error log.';
                        $messageType = 'danger';
                    }
                }
            }
        }
    } elseif (isset($_POST['update_sale'])) {
        $saleId = filter_var(
            $_POST['sale_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $saleStatus = trim($_POST['sale_status'] ?? '');
        $paymentStatus = trim($_POST['payment_status'] ?? '');

        if (
            !$saleId
            || !in_array($saleStatus, $saleStatuses, true)
            || !in_array($paymentStatus, $paymentStatuses, true)
        ) {
            $message = 'Invalid sale ID or status.';
            $messageType = 'danger';
        } else {
            $update = $conn->prepare(
                "UPDATE sales
                 SET sale_status = ?, payment_status = ?
                 WHERE sale_id = ?"
            );

            if (!$update) {
                $message = 'Unable to prepare the sale update.';
                $messageType = 'danger';
            } else {
                $update->bind_param(
                    "ssi",
                    $saleStatus,
                    $paymentStatus,
                    $saleId
                );

                if ($update->execute()) {
                    $update->close();

                    $_SESSION['admin_sales_token'] =
                        bin2hex(random_bytes(32));

                    header("Location: sales.php?updated=1");
                    exit();
                }

                $update->close();
                $message = 'Unable to update this sale.';
                $messageType = 'danger';
            }
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'Sale record created successfully.';
}

if (isset($_GET['updated'])) {
    $message = 'Sale and payment statuses updated successfully.';
}

// Load customer and car choices for the form.
$customers = $conn->query(
    "SELECT user_id, name, email FROM users ORDER BY name"
);

$cars = $conn->query(
    "SELECT car_id, brand, model, year, selling_price
     FROM cars
     ORDER BY brand, model"
);

if (!$customers || !$cars) {
    die("Unable to load customers or cars.");
}

// Filters
$search = trim($_GET['search'] ?? '');
$saleStatusFilter = trim($_GET['sale_status'] ?? '');
$paymentStatusFilter = trim($_GET['payment_status'] ?? '');

$sql = "
    SELECT
        s.sale_id,
        s.user_id,
        s.car_id,
        s.sale_price,
        s.sale_date,
        s.payment_method,
        s.payment_status,
        s.sale_status,
        s.notes,
        s.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        c.brand,
        c.model,
        c.year
    FROM sales s
    INNER JOIN users u ON u.user_id = s.user_id
    INNER JOIN cars c ON c.car_id = s.car_id
";

$conditions = [];
$params = [];
$types = '';

if (
    $saleStatusFilter !== ''
    && in_array($saleStatusFilter, $saleStatuses, true)
) {
    $conditions[] = 's.sale_status = ?';
    $params[] = $saleStatusFilter;
    $types .= 's';
}

if (
    $paymentStatusFilter !== ''
    && in_array($paymentStatusFilter, $paymentStatuses, true)
) {
    $conditions[] = 's.payment_status = ?';
    $params[] = $paymentStatusFilter;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = "(
        u.name LIKE ?
        OR u.email LIKE ?
        OR c.brand LIKE ?
        OR c.model LIKE ?
        OR s.car_id LIKE ?
    )";

    $term = '%' . $search . '%';

    for ($i = 0; $i < 5; $i++) {
        $params[] = $term;
        $types .= 's';
    }
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY s.sale_date DESC, s.sale_id DESC';

$listStmt = $conn->prepare($sql);

if (!$listStmt) {
    die("Unable to prepare the sales list.");
}

if ($params) {
    $listStmt->bind_param($types, ...$params);
}

$listStmt->execute();
$sales = $listStmt->get_result();

// Sales summary
$summary = $conn->query(
    "SELECT
        COUNT(*) AS total_sales,
        COALESCE(SUM(CASE WHEN sale_status = 'Completed'
            THEN sale_price ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(payment_status = 'Pending'), 0) AS pending_payments,
        COALESCE(SUM(sale_status = 'Pending'), 0) AS pending_sales
     FROM sales"
)->fetch_assoc();

$totalSales = (int)$summary['total_sales'];
$revenue = (float)$summary['revenue'];
$pendingPayments = (int)$summary['pending_payments'];
$pendingSales = (int)$summary['pending_sales'];

include "../includes/header.php";
?>

<div class="container-fluid mt-4 mb-5 px-3 px-lg-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Management</h2>
            <p class="text-muted mb-0">
                Record transactions and manage payment and sale statuses.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= salesText($messageType) ?>">
            <?= salesText($message) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted">Total Records</div>
                    <h3 class="fw-bold"><?= number_format($totalSales) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted">Completed Sale Revenue</div>
                    <h3 class="fw-bold">₹<?= number_format($revenue, 2) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted">Pending Sales</div>
                    <h3 class="fw-bold"><?= number_format($pendingSales) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted">Pending Payments</div>
                    <h3 class="fw-bold"><?= number_format($pendingPayments) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Record a sale -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-lg-4">
            <h4 class="fw-bold mb-3">
                <i class="bi bi-receipt text-primary"></i> Record a Sale
            </h4>

            <form method="POST" action="sales.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= salesText($_SESSION['admin_sales_token']) ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="user_id">
                            Customer
                        </label>

                        <select
                            class="form-select"
                            id="user_id"
                            name="user_id"
                            required
                        >
                            <option value="">Select customer</option>
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                                <option value="<?= (int)$customer['user_id'] ?>">
                                    <?= salesText($customer['name']) ?>
                                    (<?= salesText($customer['email']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="car_id">
                            Car
                        </label>

                        <select
                            class="form-select"
                            id="car_id"
                            name="car_id"
                            required
                        >
                            <option value="">Select car</option>
                            <?php while ($carOption = $cars->fetch_assoc()): ?>
                                <option
                                    value="<?= salesText($carOption['car_id']) ?>"
                                    data-price="<?= salesText($carOption['selling_price']) ?>"
                                >
                                    <?= salesText($carOption['brand']) ?>
                                    <?= salesText($carOption['model']) ?>
                                    (<?= salesText($carOption['car_id']) ?>)
                                    — ₹<?= number_format((float)$carOption['selling_price']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <small class="text-muted">
                            The listed price is a guide; enter the agreed sale price below.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="sale_price">
                            Final Sale Price (₹)
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="sale_price"
                            name="sale_price"
                            min="0.01"
                            max="9999999999.99"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="sale_date">
                            Sale Date
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="sale_date"
                            name="sale_date"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="payment_method">
                            Payment Method
                        </label>

                        <select
                            class="form-select"
                            id="payment_method"
                            name="payment_method"
                            required
                        >
                            <option value="">Select method</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= salesText($method) ?>">
                                    <?= salesText($method) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="payment_status">
                            Payment Status
                        </label>

                        <select
                            class="form-select"
                            id="payment_status"
                            name="payment_status"
                            required
                        >
                            <?php foreach ($paymentStatuses as $status): ?>
                                <option
                                    value="<?= salesText($status) ?>"
                                    <?= $status === 'Pending' ? 'selected' : '' ?>
                                >
                                    <?= salesText($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="sale_status">
                            Sale Status
                        </label>

                        <select
                            class="form-select"
                            id="sale_status"
                            name="sale_status"
                            required
                        >
                            <?php foreach ($saleStatuses as $status): ?>
                                <option
                                    value="<?= salesText($status) ?>"
                                    <?= $status === 'Pending' ? 'selected' : '' ?>
                                >
                                    <?= salesText($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="notes">
                            Notes (Optional)
                        </label>

                        <textarea
                            class="form-control"
                            id="notes"
                            name="notes"
                            rows="3"
                            maxlength="5000"
                            placeholder="Transaction reference or additional details"
                        ></textarea>
                    </div>

                    <div class="col-12">
                        <button
                            type="submit"
                            name="create_sale"
                            value="1"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus-circle"></i> Save Sale Record
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Existing sales records -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-3 p-lg-4">

            <h4 class="fw-bold mb-3">Sales Records</h4>

            <form method="GET" action="sales.php" class="row g-2 mb-4">

                <div class="col-lg-4">
                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search customer, email, car or ID"
                        value="<?= salesText($search) ?>"
                    >
                </div>

                <div class="col-md-6 col-lg-3">
                    <select name="sale_status" class="form-select">
                        <option value="">All sale statuses</option>
                        <?php foreach ($saleStatuses as $status): ?>
                            <option
                                value="<?= salesText($status) ?>"
                                <?= $saleStatusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= salesText($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 col-lg-3">
                    <select name="payment_status" class="form-select">
                        <option value="">All payment statuses</option>
                        <?php foreach ($paymentStatuses as $status): ?>
                            <option
                                value="<?= salesText($status) ?>"
                                <?= $paymentStatusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= salesText($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">
                        Filter
                    </button>

                    <a href="sales.php" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>

            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Sale</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>Price</th>
                            <th>Date</th>
                            <th>Payment</th>
                            <th>Sale Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($sales->num_rows > 0): ?>

                        <?php while ($sale = $sales->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>#<?= (int)$sale['sale_id'] ?></strong>
                                    <div class="small text-muted">
                                        <?= salesText($sale['created_at']) ?>
                                    </div>
                                </td>

                                <td>
                                    <strong><?= salesText($sale['customer_name']) ?></strong>
                                    <div class="small">
                                        <a href="mailto:<?= salesText($sale['customer_email']) ?>">
                                            <?= salesText($sale['customer_email']) ?>
                                        </a>
                                    </div>
                                </td>

                                <td>
                                    <strong>
                                        <?= salesText($sale['brand']) ?>
                                        <?= salesText($sale['model']) ?>
                                    </strong>
                                    <div class="small text-muted">
                                        <?= salesText($sale['car_id']) ?>
                                        · <?= salesText($sale['year']) ?>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                    ₹<?= number_format((float)$sale['sale_price'], 2) ?>
                                </td>

                                <td class="text-nowrap">
                                    <?= salesText(date(
                                        'd M Y',
                                        strtotime($sale['sale_date'])
                                    )) ?>
                                </td>

                                <td>
                                    <div><?= salesText($sale['payment_method'] ?? 'Not specified') ?></div>
                                    <span class="badge <?= $sale['payment_status'] === 'Paid'
                                        ? 'text-bg-success'
                                        : ($sale['payment_status'] === 'Refunded'
                                            ? 'text-bg-danger'
                                            : 'text-bg-warning') ?>">
                                        <?= salesText($sale['payment_status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?= $sale['sale_status'] === 'Completed'
                                        ? 'text-bg-success'
                                        : ($sale['sale_status'] === 'Cancelled'
                                            ? 'text-bg-danger'
                                            : 'text-bg-secondary') ?>">
                                        <?= salesText($sale['sale_status']) ?>
                                    </span>
                                </td>

                                <td style="min-width:190px;">
                                    <form method="POST" action="sales.php">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= salesText($_SESSION['admin_sales_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="sale_id"
                                            value="<?= (int)$sale['sale_id'] ?>"
                                        >

                                        <select
                                            name="sale_status"
                                            class="form-select form-select-sm mb-2"
                                            required
                                        >
                                            <?php foreach ($saleStatuses as $status): ?>
                                                <option
                                                    value="<?= salesText($status) ?>"
                                                    <?= $sale['sale_status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= salesText($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select
                                            name="payment_status"
                                            class="form-select form-select-sm mb-2"
                                            required
                                        >
                                            <?php foreach ($paymentStatuses as $status): ?>
                                                <option
                                                    value="<?= salesText($status) ?>"
                                                    <?= $sale['payment_status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= salesText($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button
                                            type="submit"
                                            name="update_sale"
                                            value="1"
                                            class="btn btn-sm btn-primary w-100"
                                        >
                                            Save Changes
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-receipt fs-1 text-muted d-block mb-2"></i>
                                <strong>No sales records found</strong>
                                <p class="text-muted mb-0">
                                    Sales recorded by the admin will appear here.
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

<?php
$listStmt->close();
include "../includes/footer.php";
?>
