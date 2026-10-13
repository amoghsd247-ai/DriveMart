
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function enquiryText($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* CSRF protection */
if (empty($_SESSION['admin_enquiry_token'])) {
    $_SESSION['admin_enquiry_token'] = bin2hex(random_bytes(32));
}

$allowedStatuses = ['Pending', 'In Progress', 'Resolved', 'Closed'];
$notice = '';
$noticeType = 'success';

/* -----------------------------------------
   UPDATE ENQUIRY STATUS
----------------------------------------- */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])
) {
    $token = (string)($_POST['csrf_token'] ?? '');
    $enquiryId = filter_var(
        $_POST['enquiry_id'] ?? '',
        FILTER_VALIDATE_INT
    );
    $newStatus = trim($_POST['status'] ?? '');

    if (
        $token === ''
        || !hash_equals($_SESSION['admin_enquiry_token'], $token)
    ) {
        $notice = 'Your session expired. Please refresh the page.';
        $noticeType = 'danger';

    } elseif (
        !$enquiryId
        || !in_array($newStatus, $allowedStatuses, true)
    ) {
        $notice = 'Invalid enquiry or status.';
        $noticeType = 'danger';

    } else {
        $update = $conn->prepare(
            "UPDATE enquiries SET status = ? WHERE enquiry_id = ?"
        );

        if ($update) {
            $update->bind_param("si", $newStatus, $enquiryId);

            if ($update->execute()) {
                $update->close();

                header("Location: enquiries.php?updated=1");
                exit();
            }

            $update->close();
        }

        $notice = 'Unable to update this enquiry.';
        $noticeType = 'danger';
    }
}

/* -----------------------------------------
   SAVE ADMIN REPLY
----------------------------------------- */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_reply'])
) {
    $token = (string)($_POST['csrf_token'] ?? '');
    $enquiryId = filter_var(
        $_POST['enquiry_id'] ?? '',
        FILTER_VALIDATE_INT
    );
    $reply = trim($_POST['admin_reply'] ?? '');

    if (
        $token === ''
        || !hash_equals($_SESSION['admin_enquiry_token'], $token)
    ) {
        $notice = 'Your session expired. Please refresh the page.';
        $noticeType = 'danger';

    } elseif (!$enquiryId) {
        $notice = 'Invalid enquiry ID.';
        $noticeType = 'danger';

    } elseif ($reply === '') {
        $notice = 'Please enter a reply before saving.';
        $noticeType = 'warning';

    } elseif (strlen($reply) > 10000) {
        $notice = 'The reply is too long. Please shorten it.';
        $noticeType = 'warning';

    } else {
        $update = $conn->prepare(
            "UPDATE enquiries
             SET admin_reply = ?, replied_at = NOW()
             WHERE enquiry_id = ?"
        );

        if ($update) {
            $update->bind_param("si", $reply, $enquiryId);

            if ($update->execute()) {
                $update->close();

                header("Location: enquiries.php?reply_saved=1");
                exit();
            }

            $update->close();
        }

        $notice = 'Unable to save the reply. Please try again.';
        $noticeType = 'danger';
    }
}

/* -----------------------------------------
   SUCCESS NOTICES
----------------------------------------- */
if (($_GET['updated'] ?? '') === '1') {
    $notice = 'Enquiry status updated successfully.';
    $noticeType = 'success';
}

if (($_GET['reply_saved'] ?? '') === '1') {
    $notice = 'Admin reply saved successfully.';
    $noticeType = 'success';
}

/* -----------------------------------------
   SEARCH AND STATUS FILTERS
----------------------------------------- */
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        e.enquiry_id,
        e.user_id,
        e.car_id,
        e.message,
        e.status,
        e.admin_reply,
        e.replied_at,
        e.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        c.brand,
        c.model,
        c.year,
        c.selling_price
    FROM enquiries e
    INNER JOIN users u ON u.user_id = e.user_id
    INNER JOIN cars c ON c.car_id = e.car_id
";

$conditions = [];
$params = [];
$types = '';

if (
    $statusFilter !== ''
    && in_array($statusFilter, $allowedStatuses, true)
) {
    $conditions[] = 'e.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = "(
        u.name LIKE ?
        OR u.email LIKE ?
        OR u.phone LIKE ?
        OR c.brand LIKE ?
        OR c.model LIKE ?
        OR e.car_id LIKE ?
        OR e.message LIKE ?
        OR e.admin_reply LIKE ?
    )";

    $term = '%' . $search . '%';

    for ($i = 0; $i < 8; $i++) {
        $params[] = $term;
        $types .= 's';
    }
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY e.created_at DESC';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to prepare the enquiries query.');
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$enquiries = $stmt->get_result();

/* -----------------------------------------
   DASHBOARD COUNTS
----------------------------------------- */
$counts = [
    'total' => 0,
    'Pending' => 0,
    'In Progress' => 0,
    'Resolved' => 0,
    'Closed' => 0
];

$countResult = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM enquiries
     GROUP BY status"
);

if ($countResult) {
    while ($countRow = $countResult->fetch_assoc()) {
        $counts['total'] += (int)$countRow['total'];

        if (isset($counts[$countRow['status']])) {
            $counts[$countRow['status']] =
                (int)$countRow['total'];
        }
    }
}

function statusClass($status)
{
    switch ($status) {
        case 'Pending':
            return 'bg-warning text-dark';

        case 'In Progress':
            return 'bg-primary';

        case 'Resolved':
            return 'bg-success';

        case 'Closed':
            return 'bg-secondary';

        default:
            return 'bg-light text-dark';
    }
}

/*
 * Convert supported Indian mobile numbers to international format.
 * Example: 9876543210 becomes 919876543210.
 */
function normaliseWhatsappPhone($phone)
{
    $phone = preg_replace('/\D+/', '', (string)($phone ?? ''));

    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '91' . $phone;
    }

    if (preg_match('/^0[6-9]\d{9}$/', $phone)) {
        $phone = '91' . substr($phone, 1);
    }

    if (preg_match('/^\+?91[6-9]\d{9}$/', (string)($phone ?? ''))) {
        $phone = preg_replace('/\D+/', '', (string)$phone);
    }

    return preg_match('/^[1-9]\d{7,14}$/', $phone)
        ? $phone
        : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Customer Enquiries | DriveMart Admin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            background: #eef3f8;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 240px;
            background: #0f172a;
            padding: 25px 18px;
            overflow-y: auto;
        }

        .sidebar h3 {
            color: white;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            padding: 12px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 7px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .main {
            margin-left: 240px;
            padding: 28px;
        }

        .topbar {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: white;
            padding: 24px;
            border-radius: 18px;
            margin-bottom: 25px;
        }

        .stat-card,
        .table-card {
            background: white;
            border: 0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,.05);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 750;
        }

        .enquiry-message {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            min-width: 180px;
            max-width: 300px;
        }

        .status-select {
            min-width: 130px;
        }

        .reply-box {
            min-width: 230px;
            max-width: 340px;
        }

        .reply-box textarea {
            min-height: 95px;
            resize: vertical;
        }

        .saved-reply {
            background: #eefbf3;
            border-left: 4px solid #198754;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .whatsapp-btn {
            background: #25D366;
            color: #fff;
            border: none;
        }

        .whatsapp-btn:hover {
            background: #1da851;
            color: #fff;
        }

        .customer-phone {
            overflow-wrap: anywhere;
        }

        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <h3>
        <i class="bi bi-car-front-fill"></i> DriveMart
    </h3>

    <a href="dashboard.php">
        <i class="bi bi-grid-fill"></i> Dashboard
    </a>

    <a href="add_car.php">
        <i class="bi bi-plus-circle"></i> Add Car
    </a>

    <a href="enquiries.php" class="active">
        <i class="bi bi-chat-left-text"></i> Customer Enquiries
    </a>

    <a href="test_drives.php">
        <i class="bi bi-calendar-check"></i> Test-Drive Bookings
    </a>

    <a href="../cars.php" target="_blank"
       rel="noopener noreferrer">
        <i class="bi bi-car-front"></i> Browse Cars
    </a>

    <a href="../index.php" target="_blank"
       rel="noopener noreferrer">
        <i class="bi bi-house"></i> View Website
    </a>

    <a href="../logout.php"
       onclick="return confirm('Are you sure you want to log out?');">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</aside>

<main class="main">

    <div class="topbar d-flex justify-content-between
                align-items-center flex-wrap gap-3">

        <div>
            <h2 class="fw-bold mb-1">Customer Enquiries</h2>
            <p class="mb-0">
                Review enquiries, update statuses, reply to customers
                and prepare WhatsApp messages.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-light">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="alert alert-<?= enquiryText($noticeType) ?>">
            <?= enquiryText($notice) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">

        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="text-muted">Total</div>
                <div class="stat-number">
                    <?= $counts['total'] ?>
                </div>
            </div>
        </div>

        <?php foreach (
            ['Pending', 'In Progress', 'Resolved', 'Closed']
            as $status
        ): ?>
            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="text-muted">
                        <?= enquiryText($status) ?>
                    </div>

                    <div class="stat-number">
                        <?= $counts[$status] ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <div class="table-card">

        <form method="GET"
              action="enquiries.php"
              class="row g-2 mb-4">

            <div class="col-md-6">
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Search customer, phone, car, email, message or reply"
                    value="<?= enquiryText($search) ?>"
                >
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>

                    <?php foreach ($allowedStatuses as $status): ?>
                        <option
                            value="<?= enquiryText($status) ?>"
                            <?= $statusFilter === $status ? 'selected' : '' ?>
                        >
                            <?= enquiryText($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill"
                        type="submit">
                    <i class="bi bi-search"></i> Search
                </button>

                <a href="enquiries.php"
                   class="btn btn-outline-secondary">
                    Clear
                </a>
            </div>

        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">

                <thead>
                    <tr>
                        <th>ID / Date</th>
                        <th>Customer</th>
                        <th>Car</th>
                        <th>Customer Message</th>
                        <th>Status</th>
                        <th>Update Status</th>
                        <th>Reply / WhatsApp</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($enquiries->num_rows > 0): ?>

                    <?php while ($row = $enquiries->fetch_assoc()): ?>

                        <?php
                        $carName = trim(
                            ($row['brand'] ?? '') . ' ' .
                            ($row['model'] ?? '')
                        );

                        $carId = (string)$row['car_id'];
                        $currentStatus = (string)$row['status'];
                        $savedReply = (string)($row['admin_reply'] ?? '');

                        $customerPhone = normaliseWhatsappPhone(
                            $row['customer_phone'] ?? ''
                        );

                        /*
                         * Prepare a message using the saved reply.
                         * This link opens WhatsApp but never sends automatically.
                         */
                        $whatsappMessage =
                            "Hello " . $row['customer_name'] . "!\n\n" .
                            "This is an update from DriveMart regarding your car enquiry.\n\n" .
                            "Car: " . $carName . "\n" .
                            "Car ID: " . $carId . "\n" .
                            "Enquiry ID: #" . $row['enquiry_id'] . "\n" .
                            "Current Status: " . $currentStatus . "\n\n";

                        if (trim($savedReply) !== '') {
                            $whatsappMessage .=
                                "Our reply:\n" . $savedReply . "\n\n";
                        } else {
                            $whatsappMessage .=
                                "Please contact DriveMart for an update on your enquiry.\n\n";
                        }

                        $whatsappMessage .=
                            "Thank you for choosing DriveMart!";

                        $whatsappUrl = $customerPhone !== ''
                            ? "https://wa.me/" . $customerPhone .
                              "?text=" . rawurlencode($whatsappMessage)
                            : '';
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    #<?= (int)$row['enquiry_id'] ?>
                                </strong>

                                <br>

                                <small class="text-muted">
                                    <?= enquiryText($row['created_at']) ?>
                                </small>
                            </td>

                            <td>
                                <strong>
                                    <?= enquiryText($row['customer_name']) ?>
                                </strong>

                                <br>

                                <a href="mailto:<?= enquiryText($row['customer_email']) ?>">
                                    <?= enquiryText($row['customer_email']) ?>
                                </a>

                                <br>

                                <small class="text-muted customer-phone">
                                    <i class="bi bi-whatsapp"></i>

                                    <?php if ($customerPhone !== ''): ?>
                                        +<?= enquiryText($customerPhone) ?>
                                    <?php else: ?>
                                        Phone number not available
                                    <?php endif; ?>
                                </small>
                            </td>

                            <td>
                                <strong>
                                    <?= enquiryText($carName) ?>
                                </strong>

                                <br>

                                <small class="text-muted">
                                    <?= enquiryText($carId) ?>
                                    &bull;
                                    <?= enquiryText($row['year']) ?>
                                </small>

                                <br>

                                <a
                                    href="../car.php?id=<?= rawurlencode($carId) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    View car
                                </a>
                            </td>

                            <td>
                                <div class="enquiry-message">
                                    <?= enquiryText($row['message']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="badge <?= statusClass($currentStatus) ?>">
                                    <?= enquiryText($currentStatus) ?>
                                </span>
                            </td>

                            <!-- STATUS UPDATE -->
                            <td>
                                <form method="POST"
                                      action="enquiries.php">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= enquiryText($_SESSION['admin_enquiry_token']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="enquiry_id"
                                        value="<?= (int)$row['enquiry_id'] ?>"
                                    >

                                    <select
                                        name="status"
                                        class="form-select form-select-sm status-select mb-2"
                                        required
                                    >
                                        <?php foreach ($allowedStatuses as $status): ?>
                                            <option
                                                value="<?= enquiryText($status) ?>"
                                                <?= $currentStatus === $status ? 'selected' : '' ?>
                                            >
                                                <?= enquiryText($status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button
                                        type="submit"
                                        name="update_status"
                                        value="1"
                                        class="btn btn-sm btn-primary w-100"
                                    >
                                        Save Status
                                    </button>
                                </form>
                            </td>

                            <!-- REPLY AND WHATSAPP -->
                            <td>
                                <div class="reply-box">

                                    <?php if (trim($savedReply) !== ''): ?>
                                        <div class="saved-reply">
                                            <strong>
                                                <i class="bi bi-check-circle-fill"></i>
                                                Saved reply
                                            </strong>

                                            <div class="mt-2">
                                                <?= enquiryText($savedReply) ?>
                                            </div>

                                            <?php if (!empty($row['replied_at'])): ?>
                                                <small class="text-muted d-block mt-2">
                                                    Saved:
                                                    <?= enquiryText($row['replied_at']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST"
                                          action="enquiries.php">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= enquiryText($_SESSION['admin_enquiry_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="enquiry_id"
                                            value="<?= (int)$row['enquiry_id'] ?>"
                                        >

                                        <label class="form-label small fw-bold">
                                            Write or edit reply
                                        </label>

                                        <textarea
                                            name="admin_reply"
                                            class="form-control form-control-sm mb-2"
                                            maxlength="10000"
                                            placeholder="Write your reply to the customer..."
                                            required
                                        ><?= enquiryText($savedReply) ?></textarea>

                                        <button
                                            type="submit"
                                            name="save_reply"
                                            value="1"
                                            class="btn btn-sm btn-success w-100 mb-2"
                                        >
                                            <i class="bi bi-save"></i>
                                            Save Reply
                                        </button>
                                    </form>

                                    <?php if ($customerPhone !== ''): ?>

                                        <a
                                            href="<?= enquiryText($whatsappUrl) ?>"
                                            class="btn btn-sm whatsapp-btn w-100"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <i class="bi bi-whatsapp"></i>
                                            Reply on WhatsApp
                                        </a>

                                        <small class="text-muted d-block mt-2">
                                            Opens this customer's chat with
                                            your saved reply prefilled.
                                            Review it and press Send yourself.
                                        </small>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-secondary w-100"
                                            disabled
                                        >
                                            <i class="bi bi-telephone"></i>
                                            No Phone Number
                                        </button>

                                        <small class="text-danger d-block mt-2">
                                            This customer has no valid saved
                                            phone number. Ask them to add one
                                            to their account.
                                        </small>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7"
                            class="text-center py-5 text-muted">

                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>

                            No enquiries found. Enquiries submitted by
                            customers will appear here.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

    <p class="text-muted text-center small mt-4">
        DriveMart Administration &copy; <?= date('Y') ?>
    </p>

</main>

</body>
</html>
