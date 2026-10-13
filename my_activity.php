
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

/* Only logged-in customers can access this page. */
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header("Location: user_login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

function activityEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function activityDate($value)
{
    if (empty($value)) {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? date('d M Y, h:i A', $timestamp)
        : activityEscape($value);
}

function activityStatusClass($status)
{
    $status = strtolower(trim((string)$status));

    if (in_array($status, [
        'confirmed', 'resolved', 'completed', 'approved', 'paid'
    ], true)) {
        return 'status-success';
    }

    if (in_array($status, [
        'cancelled', 'canceled', 'rejected', 'closed', 'sold'
    ], true)) {
        return 'status-danger';
    }

    if (in_array($status, [
        'in progress', 'under review', 'contacted'
    ], true)) {
        return 'status-info';
    }

    return 'status-pending';
}

$enquiries = [];
$testDrives = [];
$pageError = '';

try {
    /*
     * Get only this customer's enquiries.
     * Include the saved admin reply and reply timestamp.
     */
    $sql = "
        SELECT
            e.enquiry_id,
            e.car_id,
            e.message,
            e.status,
            e.admin_reply,
            e.replied_at,
            e.created_at,
            e.updated_at,
            c.brand,
            c.model,
            c.year,
            c.image
        FROM enquiries e
        LEFT JOIN cars c ON c.car_id = e.car_id
        WHERE e.user_id = ?
        ORDER BY e.created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Could not prepare the enquiry query.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $enquiries[] = $row;
    }

    $stmt->close();

    /* Get only this customer's test-drive bookings. */
    $sql = "
        SELECT
            t.test_drive_id,
            t.car_id,
            t.booking_date,
            t.booking_time,
            t.status,
            t.notes,
            t.created_at,
            t.updated_at,
            c.brand,
            c.model,
            c.year,
            c.image
        FROM test_drives t
        LEFT JOIN cars c ON c.car_id = t.car_id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Could not prepare the test-drive query.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $testDrives[] = $row;
    }

    $stmt->close();

} catch (Throwable $e) {
    error_log('DriveMart My Activity error: ' . $e->getMessage());
    $pageError = 'We could not load your activity right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.activity-page {
    min-height: 65vh;
    background: #f5f7fb;
    padding: 35px 0 55px;
}

.activity-container {
    max-width: 1120px;
    margin: auto;
    padding: 0 18px;
}

.activity-heading {
    margin-bottom: 25px;
}

.activity-heading h1 {
    color: #182742;
    font-weight: 700;
    margin-bottom: 8px;
}

.activity-heading p {
    color: #697586;
    margin-bottom: 0;
}

.activity-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.activity-tabs a {
    display: inline-block;
    padding: 10px 17px;
    background: #fff;
    border: 1px solid #dce2ea;
    border-radius: 8px;
    color: #27364f;
    text-decoration: none;
    font-weight: 600;
}

.activity-tabs a:hover {
    border-color: #d94135;
    color: #d94135;
}

.activity-section-title {
    color: #182742;
    font-size: 23px;
    font-weight: 700;
    margin: 30px 0 16px;
}

.activity-card {
    background: #fff;
    border: 1px solid #e1e6ee;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 17px;
    box-shadow: 0 3px 12px rgba(20, 35, 60, 0.04);
}

.activity-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.activity-card h3 {
    font-size: 19px;
    color: #182742;
    font-weight: 700;
    margin: 0 0 7px;
}

.activity-meta {
    color: #667085;
    font-size: 13px;
    margin-bottom: 8px;
}

.activity-car-id {
    display: inline-block;
    background: #edf2f8;
    color: #263d5c;
    border-radius: 5px;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 700;
    margin: 4px 0 10px;
}

.activity-status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-pending {
    color: #875900;
    background: #fff1c9;
}

.status-success {
    color: #17683c;
    background: #dff5e7;
}

.status-danger {
    color: #a32626;
    background: #fde5e5;
}

.status-info {
    color: #185b91;
    background: #e0f1ff;
}

.activity-message {
    background: #f7f9fc;
    border-left: 3px solid #d94135;
    padding: 12px 14px;
    border-radius: 5px;
    margin-top: 12px;
    overflow-wrap: anywhere;
}

.activity-message strong {
    display: block;
    color: #27364f;
    font-size: 13px;
    margin-bottom: 5px;
}

.activity-message p {
    color: #4c5667;
    margin: 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.admin-reply {
    background: #eaf8ef;
    border-left: 4px solid #198754;
    padding: 15px;
    border-radius: 7px;
    margin-top: 14px;
    overflow-wrap: anywhere;
}

.admin-reply-title {
    color: #17683c;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 8px;
}

.admin-reply-text {
    color: #254b35;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    margin: 0;
}

.reply-time {
    display: block;
    color: #587663;
    font-size: 12px;
    margin-top: 10px;
}

.waiting-reply {
    color: #777f8d;
    font-size: 13px;
    margin-top: 12px;
}

.activity-button {
    display: inline-block;
    margin-top: 13px;
    padding: 9px 14px;
    border-radius: 7px;
    background: #182742;
    color: #fff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.activity-button:hover {
    background: #d94135;
}

.activity-empty {
    background: #fff;
    border: 1px dashed #ccd4df;
    border-radius: 12px;
    padding: 28px;
    text-align: center;
    color: #697586;
}

.activity-empty h3 {
    color: #27364f;
    font-size: 18px;
}

.activity-error {
    background: #fff0f0;
    color: #a32626;
    border: 1px solid #f1c6c6;
    padding: 14px;
    border-radius: 8px;
}

@media (max-width: 600px) {
    .activity-page {
        padding-top: 24px;
    }

    .activity-card {
        padding: 16px;
    }

    .activity-heading h1 {
        font-size: 27px;
    }
}
</style>

<main class="activity-page">
    <div class="activity-container">

        <div class="activity-heading">
            <h1>My Activity</h1>
            <p>
                Track your car enquiries, admin replies and
                test-drive booking updates in one place.
            </p>
        </div>

        <div class="activity-tabs">
            <a href="#my-enquiries">
                My Enquiries (<?= count($enquiries) ?>)
            </a>

            <a href="#my-test-drives">
                My Test Drives (<?= count($testDrives) ?>)
            </a>

            <a href="cars.php">Browse More Cars</a>
        </div>

        <?php if ($pageError !== ''): ?>

            <div class="activity-error">
                <?= activityEscape($pageError) ?>
            </div>

        <?php else: ?>

            <!-- MY ENQUIRIES -->
            <section id="my-enquiries">
                <h2 class="activity-section-title">My Car Enquiries</h2>

                <?php if (empty($enquiries)): ?>

                    <div class="activity-empty">
                        <h3>No enquiries yet</h3>
                        <p>
                            When you enquire about a car, your request
                            and its status will appear here.
                        </p>

                        <a href="cars.php" class="activity-button">
                            Browse Cars
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($enquiries as $enquiry): ?>

                        <?php
                        $carName = trim(
                            ($enquiry['brand'] ?? '') . ' ' .
                            ($enquiry['model'] ?? '')
                        );

                        if ($carName === '') {
                            $carName = 'Car listing';
                        }

                        $status = $enquiry['status'] ?: 'Pending';
                        $adminReply = trim(
                            (string)($enquiry['admin_reply'] ?? '')
                        );
                        ?>

                        <article class="activity-card">

                            <div class="activity-card-top">
                                <div>
                                    <h3>
                                        <?= activityEscape($carName) ?>
                                    </h3>

                                    <div class="activity-meta">
                                        Enquiry #<?= (int)$enquiry['enquiry_id'] ?>
                                        &bull;
                                        Submitted
                                        <?= activityDate($enquiry['created_at']) ?>
                                    </div>

                                    <span class="activity-car-id">
                                        Car ID:
                                        <?= activityEscape($enquiry['car_id']) ?>
                                    </span>
                                </div>

                                <span class="activity-status
                                    <?= activityStatusClass($status) ?>">
                                    <?= activityEscape($status) ?>
                                </span>
                            </div>

                            <div class="activity-message">
                                <strong>Your message</strong>
                                <p><?= activityEscape($enquiry['message']) ?></p>
                            </div>

                            <?php if ($adminReply !== ''): ?>

                                <!-- Saved reply from the admin -->
                                <div class="admin-reply">
                                    <div class="admin-reply-title">
                                        <i class="bi bi-chat-left-text-fill"></i>
                                        DriveMart Admin Reply
                                    </div>

                                    <p class="admin-reply-text"><?= activityEscape($adminReply) ?></p>

                                    <?php if (!empty($enquiry['replied_at'])): ?>
                                        <span class="reply-time">
                                            <i class="bi bi-clock"></i>
                                            Replied on
                                            <?= activityDate($enquiry['replied_at']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>

                                <div class="waiting-reply">
                                    <i class="bi bi-hourglass-split"></i>
                                    Waiting for a reply from the DriveMart team.
                                </div>

                            <?php endif; ?>

                            <?php if (
                                !empty($enquiry['updated_at'])
                                && $enquiry['updated_at'] !== $enquiry['created_at']
                            ): ?>
                                <div class="activity-meta" style="margin-top:12px;">
                                    Last updated:
                                    <?= activityDate($enquiry['updated_at']) ?>
                                </div>
                            <?php endif; ?>

                            <a
                                class="activity-button"
                                href="car.php?id=<?= rawurlencode((string)$enquiry['car_id']) ?>"
                            >
                                View Car Details
                            </a>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>
            </section>

            <!-- MY TEST-DRIVE BOOKINGS -->
            <section id="my-test-drives">
                <h2 class="activity-section-title">My Test Drives</h2>

                <?php if (empty($testDrives)): ?>

                    <div class="activity-empty">
                        <h3>No test-drive bookings yet</h3>
                        <p>
                            Your test-drive requests and confirmation
                            status will appear here.
                        </p>

                        <a href="cars.php" class="activity-button">
                            Find a Car
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($testDrives as $booking): ?>

                        <?php
                        $carName = trim(
                            ($booking['brand'] ?? '') . ' ' .
                            ($booking['model'] ?? '')
                        );

                        if ($carName === '') {
                            $carName = 'Car listing';
                        }

                        $status = $booking['status'] ?: 'Pending';

                        $bookingDate = !empty($booking['booking_date'])
                            ? date('d M Y', strtotime($booking['booking_date']))
                            : 'Not specified';

                        $bookingTime = !empty($booking['booking_time'])
                            ? date('h:i A', strtotime($booking['booking_time']))
                            : 'Not specified';
                        ?>

                        <article class="activity-card">

                            <div class="activity-card-top">
                                <div>
                                    <h3>
                                        <?= activityEscape($carName) ?>
                                    </h3>

                                    <div class="activity-meta">
                                        Booking #<?= (int)$booking['test_drive_id'] ?>
                                        &bull;
                                        Requested
                                        <?= activityDate($booking['created_at']) ?>
                                    </div>

                                    <span class="activity-car-id">
                                        Car ID:
                                        <?= activityEscape($booking['car_id']) ?>
                                    </span>
                                </div>

                                <span class="activity-status
                                    <?= activityStatusClass($status) ?>">
                                    <?= activityEscape($status) ?>
                                </span>
                            </div>

                            <div class="row mt-3">
                                <div class="col-sm-6">
                                    <strong>Booking date</strong>
                                    <p><?= activityEscape($bookingDate) ?></p>
                                </div>

                                <div class="col-sm-6">
                                    <strong>Booking time</strong>
                                    <p><?= activityEscape($bookingTime) ?></p>
                                </div>
                            </div>

                            <?php if (!empty($booking['notes'])): ?>
                                <div class="activity-message">
                                    <strong>Booking notes</strong>
                                    <p><?= activityEscape($booking['notes']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (
                                !empty($booking['updated_at'])
                                && $booking['updated_at'] !== $booking['created_at']
                            ): ?>
                                <div class="activity-meta" style="margin-top:12px;">
                                    Last updated:
                                    <?= activityDate($booking['updated_at']) ?>
                                </div>
                            <?php endif; ?>

                            <a
                                class="activity-button"
                                href="car.php?id=<?= rawurlencode((string)$booking['car_id']) ?>"
                            >
                                View Car Details
                            </a>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>
            </section>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
