
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function driveText($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
 * Convert an Indian mobile number to WhatsApp-compatible format.
 * Example: 9876543210 becomes 919876543210.
 */
function driveWhatsappPhone($phone)
{
    $phone = preg_replace('/\D+/', '', (string)($phone ?? ''));

    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '91' . $phone;
    } elseif (preg_match('/^0[6-9]\d{9}$/', $phone)) {
        $phone = '91' . substr($phone, 1);
    }

    return preg_match('/^[1-9]\d{7,14}$/', $phone)
        ? $phone
        : '';
}

/* CSRF protection */
if (empty($_SESSION['admin_drive_csrf'])) {
    $_SESSION['admin_drive_csrf'] = bin2hex(random_bytes(32));
}

$allowedStatuses = [
    'Pending',
    'Confirmed',
    'Completed',
    'Cancelled'
];

$message = '';
$messageType = 'success';

/* -----------------------------------------
   UPDATE BOOKING STATUS
----------------------------------------- */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])
) {
    $token = (string)($_POST['csrf_token'] ?? '');
    $bookingId = filter_var(
        $_POST['test_drive_id'] ?? '',
        FILTER_VALIDATE_INT
    );
    $newStatus = trim($_POST['status'] ?? '');

    if (
        $token === ''
        || !hash_equals($_SESSION['admin_drive_csrf'], $token)
    ) {
        $message = 'Your session expired. Refresh the page and try again.';
        $messageType = 'danger';

    } elseif (
        !$bookingId
        || !in_array($newStatus, $allowedStatuses, true)
    ) {
        $message = 'Invalid booking ID or status.';
        $messageType = 'danger';

    } else {
        $update = $conn->prepare(
            "UPDATE test_drives
             SET status = ?
             WHERE test_drive_id = ?"
        );

        if (!$update) {
            $message = 'Unable to prepare the status update.';
            $messageType = 'danger';

        } else {
            $update->bind_param("si", $newStatus, $bookingId);

            if ($update->execute()) {
                $update->close();

                $_SESSION['admin_drive_csrf'] =
                    bin2hex(random_bytes(32));

                header("Location: test_drives.php?updated=1");
                exit();

            } else {
                $message = 'Unable to update this booking.';
                $messageType = 'danger';
                $update->close();
            }
        }
    }
}

/* -----------------------------------------
   STATUS UPDATE NOTICE
----------------------------------------- */
if (($_GET['updated'] ?? '') === '1') {
    $message = 'Booking status updated successfully.';
    $messageType = 'success';
}

/* -----------------------------------------
   STATUS FILTER
----------------------------------------- */
$filterStatus = trim($_GET['status'] ?? '');

if (
    $filterStatus !== ''
    && !in_array($filterStatus, $allowedStatuses, true)
) {
    $filterStatus = '';
}

/* -----------------------------------------
   SUMMARY COUNTS
----------------------------------------- */
$counts = [
    'total' => 0,
    'Pending' => 0,
    'Confirmed' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

$countResult = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM test_drives
     GROUP BY status"
);

if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $counts['total'] += (int)$row['total'];

        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['total'];
        }
    }
}

/* -----------------------------------------
   FETCH BOOKINGS, CUSTOMER PHONE AND CAR
----------------------------------------- */
$sql = "
    SELECT
        td.test_drive_id,
        td.user_id,
        td.car_id,
        td.booking_date,
        td.booking_time,
        td.status,
        td.notes,
        td.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        c.brand,
        c.model,
        c.year
    FROM test_drives td
    INNER JOIN users u ON u.user_id = td.user_id
    INNER JOIN cars c ON c.car_id = td.car_id
";

if ($filterStatus !== '') {
    $stmt = $conn->prepare(
        $sql . " WHERE td.status = ?
                 ORDER BY td.booking_date DESC,
                          td.booking_time DESC,
                          td.test_drive_id DESC"
    );

    if (!$stmt) {
        die("Unable to prepare the bookings query.");
    }

    $stmt->bind_param("s", $filterStatus);
    $stmt->execute();
    $bookings = $stmt->get_result();

} else {
    $stmt = $conn->prepare(
        $sql . " ORDER BY td.booking_date DESC,
                          td.booking_time DESC,
                          td.test_drive_id DESC"
    );

    if (!$stmt) {
        die("Unable to prepare the bookings query.");
    }

    $stmt->execute();
    $bookings = $stmt->get_result();
}

include "../includes/header.php";
?>

<div class="container-fluid mt-4 mb-5 px-3 px-lg-5">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h2 class="fw-bold mb-1">Test-Drive Bookings</h2>
            <p class="text-muted mb-0">
                Manage bookings and manually contact customers through WhatsApp.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                ← Dashboard
            </a>

            <a href="../index.php" class="btn btn-outline-primary">
                View Website
            </a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= driveText($messageType) ?>">
            <?= driveText($message) ?>
        </div>
    <?php endif; ?>

    <!-- BOOKING COUNTS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Bookings</div>
                    <h3 class="fw-bold mb-0">
                        <?= $counts['total'] ?>
                    </h3>
                </div>
            </div>
        </div>

        <?php foreach (
            ['Pending', 'Confirmed', 'Completed', 'Cancelled']
            as $status
        ): ?>
            <div class="col-6 col-md">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">
                            <?= driveText($status) ?>
                        </div>

                        <h3 class="fw-bold mb-0">
                            <?= $counts[$status] ?>
                        </h3>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- BOOKINGS TABLE -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-3 p-lg-4">

            <form method="GET"
                  action="test_drives.php"
                  class="row g-2 align-items-end mb-4">

                <div class="col-sm-8 col-md-5">
                    <label for="status" class="form-label fw-semibold">
                        Filter by status
                    </label>

                    <select name="status" id="status" class="form-select">
                        <option value="">All Bookings</option>

                        <?php foreach ($allowedStatuses as $status): ?>
                            <option
                                value="<?= driveText($status) ?>"
                                <?= $filterStatus === $status ? 'selected' : '' ?>
                            >
                                <?= driveText($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        Apply Filter
                    </button>
                </div>

                <div class="col-auto">
                    <a href="test_drives.php"
                       class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Booking</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>Date &amp; Time</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Update Status</th>
                            <th>WhatsApp</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($bookings->num_rows > 0): ?>

                        <?php while ($booking = $bookings->fetch_assoc()): ?>

                            <?php
                            $bookingId = (int)$booking['test_drive_id'];
                            $bookingStatus = (string)$booking['status'];

                            $customerName = (string)$booking['customer_name'];

                            $carName = trim(
                                (string)$booking['brand'] . ' ' .
                                (string)$booking['model']
                            );

                            $carId = (string)$booking['car_id'];

                            $phone = driveWhatsappPhone(
                                $booking['customer_phone'] ?? ''
                            );

                            $bookingDate = date(
                                'd M Y',
                                strtotime($booking['booking_date'])
                            );

                            $bookingTime = date(
                                'h:i A',
                                strtotime($booking['booking_time'])
                            );

                            /*
                             * Build a message based on the current status.
                             * The admin must open WhatsApp and press Send.
                             */
                            if ($bookingStatus === 'Confirmed') {
                                $openingMessage =
                                    "Good news! Your test-drive booking has been confirmed.";
                            } elseif ($bookingStatus === 'Pending') {
                                $openingMessage =
                                    "We have received your test-drive request. It is currently pending confirmation.";
                            } elseif ($bookingStatus === 'Completed') {
                                $openingMessage =
                                    "Thank you for visiting DriveMart for your test drive.";
                            } elseif ($bookingStatus === 'Cancelled') {
                                $openingMessage =
                                    "Your test-drive booking has been marked as cancelled. Please contact DriveMart if you have any questions.";
                            } else {
                                $openingMessage =
                                    "Here is an update regarding your test-drive booking.";
                            }

                            $whatsappMessage =
                                "Hello " . $customerName . "!\n\n" .
                                $openingMessage . "\n\n" .
                                "DriveMart Test-Drive Details\n" .
                                "Booking ID: #" . $bookingId . "\n" .
                                "Car: " . $carName . "\n" .
                                "Car ID: " . $carId . "\n" .
                                "Year: " . $booking['year'] . "\n" .
                                "Date: " . $bookingDate . "\n" .
                                "Time: " . $bookingTime . "\n" .
                                "Booking Status: " . $bookingStatus . "\n\n";

                            if (trim((string)($booking['notes'] ?? '')) !== '') {
                                $whatsappMessage .=
                                    "Your booking notes: " .
                                    trim((string)$booking['notes']) . "\n\n";
                            }

                            $whatsappMessage .=
                                "Thank you for choosing DriveMart!";

                            $whatsappUrl = $phone !== ''
                                ? 'https://wa.me/' . $phone .
                                  '?text=' . rawurlencode($whatsappMessage)
                                : '';

                            $badgeClass = 'text-bg-secondary';

                            if ($bookingStatus === 'Pending') {
                                $badgeClass = 'text-bg-warning';
                            } elseif ($bookingStatus === 'Confirmed') {
                                $badgeClass = 'text-bg-primary';
                            } elseif ($bookingStatus === 'Completed') {
                                $badgeClass = 'text-bg-success';
                            } elseif ($bookingStatus === 'Cancelled') {
                                $badgeClass = 'text-bg-danger';
                            }
                            ?>

                            <tr>
                                <!-- BOOKING ID -->
                                <td>
                                    <strong>
                                        #<?= $bookingId ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?= driveText($booking['created_at']) ?>
                                    </div>
                                </td>

                                <!-- CUSTOMER DETAILS -->
                                <td style="min-width:200px;">
                                    <strong>
                                        <?= driveText($customerName) ?>
                                    </strong>

                                    <div class="small">
                                        <a href="mailto:<?= driveText($booking['customer_email']) ?>">
                                            <?= driveText($booking['customer_email']) ?>
                                        </a>
                                    </div>

                                    <div class="small mt-1">
                                        <i class="bi bi-whatsapp text-success"></i>

                                        <?php if ($phone !== ''): ?>
                                            <span class="customer-phone">
                                                +<?= driveText($phone) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                No phone number saved
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- CAR DETAILS -->
                                <td style="min-width:150px;">
                                    <strong>
                                        <?= driveText($carName) ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?= driveText($booking['year']) ?>
                                        · <?= driveText($carId) ?>
                                    </div>

                                    <a
                                        href="../car.php?id=<?= rawurlencode($carId) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="small"
                                    >
                                        View car
                                    </a>
                                </td>

                                <!-- DATE AND TIME -->
                                <td style="min-width:130px;">
                                    <?= driveText($bookingDate) ?>

                                    <div class="small text-muted">
                                        <?= driveText($bookingTime) ?>
                                    </div>
                                </td>

                                <!-- NOTES -->
                                <td style="min-width:160px; max-width:240px; white-space:normal;">
                                    <?php if (
                                        trim((string)($booking['notes'] ?? '')) !== ''
                                    ): ?>
                                        <?= nl2br(driveText($booking['notes'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>

                                <!-- CURRENT STATUS -->
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= driveText($bookingStatus) ?>
                                    </span>
                                </td>

                                <!-- UPDATE STATUS -->
                                <td style="min-width:180px;">
                                    <form method="POST" action="test_drives.php">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= driveText($_SESSION['admin_drive_csrf']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="test_drive_id"
                                            value="<?= $bookingId ?>"
                                        >

                                        <select
                                            name="status"
                                            class="form-select form-select-sm mb-2"
                                            required
                                        >
                                            <?php foreach ($allowedStatuses as $status): ?>
                                                <option
                                                    value="<?= driveText($status) ?>"
                                                    <?= $bookingStatus === $status ? 'selected' : '' ?>
                                                >
                                                    <?= driveText($status) ?>
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

                                <!-- MANUAL WHATSAPP CONFIRMATION -->
                                <td style="min-width:190px;">

                                    <?php if ($phone !== ''): ?>

                                        <a
                                            href="<?= driveText($whatsappUrl) ?>"
                                            class="btn btn-sm btn-success w-100"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <i class="bi bi-whatsapp"></i>

                                            <?php if ($bookingStatus === 'Confirmed'): ?>
                                                Confirm on WhatsApp
                                            <?php else: ?>
                                                Message on WhatsApp
                                            <?php endif; ?>
                                        </a>

                                        <small class="text-muted d-block mt-2">
                                            Opens the customer's chat with
                                            the booking details prefilled.
                                            Review and send manually.
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
                                            Ask the customer to add a phone
                                            number to their account.
                                        </small>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <h5 class="fw-semibold">No bookings found</h5>
                                <p class="text-muted mb-0">
                                    Customer test-drive requests will appear here.
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
$stmt->close();
include "../includes/footer.php";
?>
