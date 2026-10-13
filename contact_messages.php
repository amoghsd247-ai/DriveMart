
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

/* Escape output before displaying it on the page */
function contactAdminEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$allowedStatuses = ['Unread', 'Read', 'Resolved', 'Closed'];

if (empty($_SESSION['contact_messages_csrf'])) {
    $_SESSION['contact_messages_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['contact_messages_csrf'];
$error = '';
$success = '';

/* =========================================================
   PROCESS STATUS UPDATES, ADMIN REPLIES AND EMAIL NOTIFICATIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = 'Your session token was invalid. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        $messageId = filter_input(
            INPUT_POST,
            'message_id',
            FILTER_VALIDATE_INT
        );

        if (!$messageId || $messageId < 1) {
            $error = 'Invalid message ID.';
        } elseif ($action !== 'update') {
            $error = 'Invalid action.';
        } else {
            $status = $_POST['status'] ?? '';
            $reply = trim($_POST['admin_reply'] ?? '');

            if (!in_array($status, $allowedStatuses, true)) {
                $error = 'Please select a valid status.';
            } elseif (mb_strlen($reply) > 10000) {
                $error = 'The reply must be 10,000 characters or fewer.';
            } else {
                /* Get the original message before updating it */
                $lookup = $conn->prepare(
                    "SELECT email, name, subject, admin_reply
                     FROM contact_messages
                     WHERE message_id = ?
                     LIMIT 1"
                );

                $original = null;

                if ($lookup) {
                    $lookup->bind_param("i", $messageId);

                    if ($lookup->execute()) {
                        $result = $lookup->get_result();
                        $original = $result->fetch_assoc();
                    }

                    $lookup->close();
                }

                if (!$original) {
                    $error = 'The contact message could not be found.';
                } else {
                    $oldReply = (string)($original['admin_reply'] ?? '');
                    $replyChanged = ($reply !== '' && $reply !== $oldReply);

                    /* Save the reply and status to the database */
                    $stmt = false;

                    if ($reply !== '') {
                        $stmt = $conn->prepare(
                            "UPDATE contact_messages
                             SET status = ?,
                                 admin_reply = ?,
                                 replied_at = CURRENT_TIMESTAMP
                             WHERE message_id = ?"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "ssi",
                                $status,
                                $reply,
                                $messageId
                            );
                        }
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE contact_messages
                             SET status = ?,
                                 admin_reply = NULL,
                                 replied_at = NULL
                             WHERE message_id = ?"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "si",
                                $status,
                                $messageId
                            );
                        }
                    }

                    if (!$stmt) {
                        $error = 'Unable to prepare the database update.';
                    } elseif (!$stmt->execute()) {
                        $error = 'Unable to save the update.';
                        $stmt->close();
                    } else {
                        $stmt->close();

                        $success = 'Message and status saved successfully.';

                        /*
                         * Send email only when a non-empty reply is
                         * new or different from the previous reply.
                         */
                        if ($replyChanged) {
                            $recipient = trim(
                                (string)$original['email']
                            );

                            $customerName = (string)$original['name'];
                            $messageSubject = (string)$original['subject'];

                            if (!filter_var(
                                $recipient,
                                FILTER_VALIDATE_EMAIL
                            )) {
                                $error =
                                    'The reply was saved, but the customer email address is invalid.';
                            } else {
                                /*
                                 * This must be a sender address accepted
                                 * by your hosting mail service.
                                 */
                                $sender = 'noreply@drivemart.byethost32.com';

                                $emailSubject =
                                    'DriveMart - Reply to your message';

                                $emailBody =
                                    "Hello " . $customerName . ",\r\n\r\n" .
                                    "Thank you for contacting DriveMart.\r\n\r\n" .
                                    "Subject: " . $messageSubject . "\r\n\r\n" .
                                    "Our reply:\r\n" .
                                    $reply . "\r\n\r\n" .
                                    "Regards,\r\n" .
                                    "DriveMart Team";

                                $headers = [
                                    'From' => 'DriveMart <' . $sender . '>',
                                    'Reply-To' => $sender,
                                    'MIME-Version' => '1.0',
                                    'Content-Type' =>
                                        'text/plain; charset=UTF-8',
                                    'X-Mailer' => 'PHP'
                                ];

                                $mailSent = false;

                                if (function_exists('mail')) {
                                    $mailSent = @mail(
                                        $recipient,
                                        $emailSubject,
                                        $emailBody,
                                        $headers
                                    );
                                }

                                if ($mailSent) {
                                    $success =
                                        'Reply saved and email accepted by the mail server. Check the recipient inbox and spam folder.';
                                } else {
                                    $error =
                                        'The reply was saved in the database, but the hosting mail server could not send the email. Your reply is not lost.';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/* =========================================================
   SEARCH AND STATUS FILTERS
   ========================================================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

/* =========================================================
   SUMMARY COUNTS
   ========================================================= */

$totalMessages = 0;
$unreadMessages = 0;
$readMessages = 0;
$resolvedMessages = 0;

$statsResult = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Unread'), 0) AS unread,
        COALESCE(SUM(status = 'Read'), 0) AS read_count,
        COALESCE(SUM(status = 'Resolved'), 0) AS resolved
     FROM contact_messages"
);

if ($statsResult) {
    $stats = $statsResult->fetch_assoc();

    $totalMessages = (int)$stats['total'];
    $unreadMessages = (int)$stats['unread'];
    $readMessages = (int)$stats['read_count'];
    $resolvedMessages = (int)$stats['resolved'];
}

/* =========================================================
   FETCH MESSAGES USING PREPARED STATEMENTS
   ========================================================= */

$sql = "SELECT
            message_id,
            user_id,
            name,
            email,
            subject,
            message,
            status,
            admin_reply,
            replied_at,
            created_at
        FROM contact_messages
        WHERE 1 = 1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        name LIKE ?
        OR email LIKE ?
        OR subject LIKE ?
        OR message LIKE ?
    )";

    $searchTerm = '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= 'ssss';
}

if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC, message_id DESC";

$stmt = $conn->prepare($sql);
$messageRows = [];

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $messages = $stmt->get_result();

        while ($row = $messages->fetch_assoc()) {
            $messageRows[] = $row;
        }
    } else {
        $error = $error ?: 'Unable to load contact messages.';
    }

    $stmt->close();
} else {
    $error = $error ?: 'Unable to prepare the contact messages query.';
}

require_once "../includes/header.php";
?>

<style>
.contact-admin-page {
    min-height: 70vh;
    background: #eef3f8;
    padding: 30px 0 45px;
}

.contact-admin-container {
    width: min(1500px, 95%);
    margin: auto;
}

.contact-admin-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 24px;
}

.contact-admin-heading h1 {
    font-weight: 750;
    color: #172554;
    margin-bottom: 5px;
}

.contact-admin-stat {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.05);
    height: 100%;
}

.contact-admin-stat .stat-number {
    font-size: 29px;
    font-weight: 750;
    margin-top: 7px;
}

.contact-admin-panel {
    background: white;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(0,0,0,.05);
}

.contact-admin-panel textarea {
    min-height: 100px;
    resize: vertical;
}

.contact-message-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    max-width: 420px;
}

.contact-status {
    display: inline-block;
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
}

.status-unread {
    background: #fee2e2;
    color: #991b1b;
}

.status-read {
    background: #dbeafe;
    color: #1e40af;
}

.status-resolved {
    background: #dcfce7;
    color: #166534;
}

.status-closed {
    background: #e5e7eb;
    color: #374151;
}

.contact-message-form {
    min-width: 250px;
}

.contact-message-form select,
.contact-message-form textarea {
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .contact-admin-panel {
        padding: 14px;
    }
}
</style>

<div class="contact-admin-page">
    <div class="contact-admin-container">

        <div class="contact-admin-heading">
            <div>
                <h1>
                    <i class="bi bi-envelope-paper"></i>
                    Contact Messages
                </h1>

                <p class="text-muted mb-0">
                    View customer messages, manage statuses and send replies.
                </p>
            </div>

            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= contactAdminEscape($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= contactAdminEscape($error) ?>
            </div>
        <?php endif; ?>

        <!-- Summary cards -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">
                <div class="contact-admin-stat">
                    <div class="text-muted">Total Messages</div>
                    <div class="stat-number">
                        <?= number_format($totalMessages) ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="contact-admin-stat">
                    <div class="text-muted">Unread</div>
                    <div class="stat-number text-danger">
                        <?= number_format($unreadMessages) ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="contact-admin-stat">
                    <div class="text-muted">Read</div>
                    <div class="stat-number text-primary">
                        <?= number_format($readMessages) ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="contact-admin-stat">
                    <div class="text-muted">Resolved</div>
                    <div class="stat-number text-success">
                        <?= number_format($resolvedMessages) ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Search and status filter -->
        <div class="contact-admin-panel mb-4">
            <form
                method="GET"
                action="contact_messages.php"
                class="row g-3 align-items-end"
            >

                <div class="col-lg-7">
                    <label for="search" class="form-label">
                        Search messages
                    </label>

                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="form-control"
                        placeholder="Name, email, subject or message"
                        value="<?= contactAdminEscape($search) ?>"
                    >
                </div>

                <div class="col-lg-3">
                    <label for="status" class="form-label">
                        Filter by status
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-select"
                    >
                        <option value="">All statuses</option>

                        <?php foreach ($allowedStatuses as $status): ?>
                            <option
                                value="<?= contactAdminEscape($status) ?>"
                                <?= $statusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= contactAdminEscape($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 d-flex gap-2">
                    <button
                        class="btn btn-primary flex-grow-1"
                        type="submit"
                    >
                        <i class="bi bi-search"></i> Search
                    </button>

                    <a
                        href="contact_messages.php"
                        class="btn btn-outline-secondary"
                    >
                        Clear
                    </a>
                </div>

            </form>
        </div>

        <!-- Customer inbox -->
        <div class="contact-admin-panel">
            <h4 class="fw-bold mb-3">Customer Inbox</h4>

            <?php if (count($messageRows) === 0): ?>

                <div class="alert alert-info mb-0">
                    No contact messages match your search.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>ID / Date</th>
                                <th>Customer</th>
                                <th>Subject / Message</th>
                                <th>Status</th>
                                <th style="min-width:300px;">
                                    Admin Reply / Update
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($messageRows as $row): ?>

                            <?php
                            $statusClass = 'status-unread';

                            if ($row['status'] === 'Read') {
                                $statusClass = 'status-read';
                            } elseif ($row['status'] === 'Resolved') {
                                $statusClass = 'status-resolved';
                            } elseif ($row['status'] === 'Closed') {
                                $statusClass = 'status-closed';
                            }
                            ?>

                            <tr>

                                <!-- Message ID and date -->
                                <td>
                                    <strong>
                                        #<?= (int)$row['message_id'] ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        <?= contactAdminEscape($row['created_at']) ?>
                                    </small>
                                </td>

                                <!-- Customer information -->
                                <td>
                                    <strong>
                                        <?= contactAdminEscape($row['name']) ?>
                                    </strong>

                                    <br>

                                    <a href="mailto:<?= contactAdminEscape($row['email']) ?>">
                                        <?= contactAdminEscape($row['email']) ?>
                                    </a>

                                    <?php if (!empty($row['user_id'])): ?>
                                        <div class="small text-muted mt-1">
                                            Customer ID:
                                            <?= (int)$row['user_id'] ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Original message and saved reply -->
                                <td>
                                    <strong>
                                        <?= contactAdminEscape($row['subject']) ?>
                                    </strong>

                                    <details class="mt-2">
                                        <summary
                                            class="text-primary"
                                            style="cursor:pointer;"
                                        >
                                            View message
                                        </summary>

                                        <div class="contact-message-text mt-2">
                                            <?= contactAdminEscape($row['message']) ?>
                                        </div>
                                    </details>

                                    <?php if (!empty($row['admin_reply'])): ?>

                                        <details class="mt-2">
                                            <summary
                                                class="text-success"
                                                style="cursor:pointer;"
                                            >
                                                View saved reply
                                            </summary>

                                            <div class="contact-message-text mt-2">
                                                <?= contactAdminEscape($row['admin_reply']) ?>
                                            </div>

                                            <?php if (!empty($row['replied_at'])): ?>
                                                <small class="text-muted">
                                                    Replied:
                                                    <?= contactAdminEscape($row['replied_at']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </details>

                                    <?php endif; ?>
                                </td>

                                <!-- Current status -->
                                <td>
                                    <span class="contact-status <?= $statusClass ?>">
                                        <?= contactAdminEscape($row['status']) ?>
                                    </span>
                                </td>

                                <!-- Admin reply form -->
                                <td>
                                    <form
                                        method="POST"
                                        action="contact_messages.php"
                                        class="contact-message-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= contactAdminEscape($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="message_id"
                                            value="<?= (int)$row['message_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >

                                        <label class="form-label small fw-semibold">
                                            Status
                                        </label>

                                        <select
                                            name="status"
                                            class="form-select"
                                        >
                                            <?php foreach ($allowedStatuses as $status): ?>
                                                <option
                                                    value="<?= contactAdminEscape($status) ?>"
                                                    <?= $row['status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= contactAdminEscape($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <label class="form-label small fw-semibold">
                                            Admin reply
                                        </label>

                                        <textarea
                                            name="admin_reply"
                                            class="form-control"
                                            maxlength="10000"
                                            placeholder="Write a reply (optional)"
                                        ><?= contactAdminEscape($row['admin_reply']) ?></textarea>

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm w-100"
                                        >
                                            <i class="bi bi-save"></i>
                                            Save Changes
                                        </button>

                                    </form>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>

        <p class="text-muted text-center mt-4 small">
            DriveMart Administration &copy; <?= date('Y') ?>
        </p>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
