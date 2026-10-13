
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function statusHistoryEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$allowedStatuses = [
    'Available',
    'Reserved',
    'Sold',
    'Under Inspection',
    'Under Repair',
    'Unavailable'
];

$error = '';
$success = '';

if (empty($_SESSION['status_history_csrf'])) {
    $_SESSION['status_history_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['status_history_csrf'];

/* ==========================================
   UPDATE CAR STATUS AND RECORD HISTORY
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = 'Invalid session token. Refresh the page and try again.';
    } else {
        $carId = strtoupper(trim($_POST['car_id'] ?? ''));
        $newStatus = trim($_POST['new_status'] ?? '');
        $reason = trim($_POST['change_reason'] ?? '');

        if (!preg_match('/^C\d+$/', $carId)) {
            $error = 'Enter a valid car ID, for example C01260.';
        } elseif (!in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Please select a valid new status.';
        } elseif (mb_strlen($reason) > 5000) {
            $error = 'The reason must not exceed 5,000 characters.';
        } else {
            try {
                // Start a transaction so the car and history stay consistent.
                if (!$conn->begin_transaction()) {
                    throw new Exception('Could not start database transaction.');
                }

                // Lock the car row while reading and updating its status.
                $carStmt = $conn->prepare(
                    "SELECT status
                     FROM cars
                     WHERE car_id = ?
                     LIMIT 1
                     FOR UPDATE"
                );

                if (!$carStmt) {
                    throw new Exception('Could not prepare the car lookup.');
                }

                $carStmt->bind_param("s", $carId);

                if (!$carStmt->execute()) {
                    $carStmt->close();
                    throw new Exception('Could not retrieve the car.');
                }

                $car = $carStmt->get_result()->fetch_assoc();
                $carStmt->close();

                if (!$car) {
                    throw new Exception(
                        'That car ID does not exist in your inventory.'
                    );
                }

                $oldStatus = (string)$car['status'];

                if ($oldStatus === $newStatus) {
                    throw new Exception(
                        'The car already has this status. Choose a different status.'
                    );
                }

                // Update the current status.
                $updateStmt = $conn->prepare(
                    "UPDATE cars
                     SET status = ?
                     WHERE car_id = ?"
                );

                if (!$updateStmt) {
                    throw new Exception('Could not prepare the status update.');
                }

                $updateStmt->bind_param("ss", $newStatus, $carId);

                if (!$updateStmt->execute()) {
                    $updateStmt->close();
                    throw new Exception('Could not update the car status.');
                }

                $updateStmt->close();

                // The current admin session has no database admin ID.
                $changedBy = null;

                $reasonValue = ($reason === '') ? null : $reason;

                // Record the same transition in car_status_history.
                $historyStmt = $conn->prepare(
                    "INSERT INTO car_status_history
                        (car_id, old_status, new_status, changed_by, change_reason)
                     VALUES (?, ?, ?, ?, ?)"
                );

                if (!$historyStmt) {
                    throw new Exception('Could not prepare the history entry.');
                }

                $historyStmt->bind_param(
                    "sssis",
                    $carId,
                    $oldStatus,
                    $newStatus,
                    $changedBy,
                    $reasonValue
                );

                if (!$historyStmt->execute()) {
                    $historyStmt->close();
                    throw new Exception('Could not save the history entry.');
                }

                $historyStmt->close();

                // Commit both changes together.
                if (!$conn->commit()) {
                    throw new Exception('Could not commit the status change.');
                }

                $success = "Car {$carId} status changed from {$oldStatus} to {$newStatus}.";
            } catch (Throwable $e) {
                // Roll back any incomplete status or history update.
                $conn->rollback();

                $error = $e->getMessage();
            }
        }
    }
}

/* ==========================================
   SEARCH AND DISPLAY HISTORY
   ========================================== */

$search = trim($_GET['search'] ?? '');

$sql = "SELECT
            h.history_id,
            h.car_id,
            h.old_status,
            h.new_status,
            h.changed_by,
            h.change_reason,
            h.changed_at
        FROM car_status_history h
        WHERE 1 = 1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        h.car_id LIKE ?
        OR h.old_status LIKE ?
        OR h.new_status LIKE ?
        OR h.change_reason LIKE ?
    )";

    $term = '%' . $search . '%';

    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

$sql .= " ORDER BY h.changed_at DESC, h.history_id DESC";

$historyRows = [];
$listStmt = $conn->prepare($sql);

if ($listStmt) {
    if ($params) {
        $listStmt->bind_param($types, ...$params);
    }

    if ($listStmt->execute()) {
        $result = $listStmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $historyRows[] = $row;
        }
    } else {
        $error = $error ?: 'Unable to load status history.';
    }

    $listStmt->close();
} else {
    $error = $error ?: 'Unable to prepare the history query.';
}

$totalHistory = 0;

$countResult = $conn->query(
    "SELECT COUNT(*) AS total FROM car_status_history"
);

if ($countResult) {
    $countRow = $countResult->fetch_assoc();
    $totalHistory = (int)$countRow['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Car Status Management | DriveMart Admin</title>

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
            color: #0f172a;
        }

        .page-container {
            width: min(1500px, 95%);
            margin: 0 auto;
            padding: 30px 0 45px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 24px;
        }

        .page-heading h1 {
            color: #172554;
            font-weight: 750;
            margin-bottom: 6px;
        }

        .panel, .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 23px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 750;
            color: #2563eb;
            margin-top: 8px;
        }

        .history-table th {
            white-space: nowrap;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
        }

        .status-pill {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-arrow {
            color: #64748b;
            margin: 0 5px;
        }

        @media (max-width: 600px) {
            .panel, .stat-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
<div class="page-container">

    <div class="page-heading">
        <div>
            <h1>
                <i class="bi bi-clock-history"></i>
                Car Status Management
            </h1>

            <p class="text-muted mb-0">
                Update the current vehicle status and keep a complete change history.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= statusHistoryEscape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= statusHistoryEscape($error) ?>
        </div>
    <?php endif; ?>

    <div class="stat-card mb-4">
        <div class="text-muted">Total History Entries</div>

        <div class="stat-number">
            <?= number_format($totalHistory) ?>
        </div>

        <p class="text-muted mb-0">
            Every successful status change is recorded here.
        </p>
    </div>

    <div class="panel mb-4">
        <h4 class="fw-bold mb-2">Update Car Status</h4>

        <p class="text-muted">
            The current status is retrieved from the cars table.
            Saving a change updates both the car and its history.
        </p>

        <form method="POST" action="car_status_history.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= statusHistoryEscape($csrfToken) ?>"
            >

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="car_id" class="form-label">Car ID</label>

                    <input
                        type="text"
                        id="car_id"
                        name="car_id"
                        class="form-control"
                        placeholder="Example: C00040"
                        pattern="C[0-9]+"
                        maxlength="10"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label for="new_status" class="form-label">
                        New Status
                    </label>

                    <select
                        id="new_status"
                        name="new_status"
                        class="form-select"
                        required
                    >
                        <option value="">Select new status</option>

                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= statusHistoryEscape($status) ?>">
                                <?= statusHistoryEscape($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="change_reason" class="form-label">
                        Reason for Change
                    </label>

                    <input
                        type="text"
                        id="change_reason"
                        name="change_reason"
                        class="form-control"
                        maxlength="5000"
                        placeholder="e.g. Customer reservation"
                    >
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Status
                    </button>

                    <button type="reset" class="btn btn-outline-secondary">
                        Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="panel mb-4">
        <h4 class="fw-bold mb-3">Search History</h4>

        <form method="GET" action="car_status_history.php"
              class="row g-3 align-items-end">

            <div class="col-md-9">
                <label for="search" class="form-label">
                    Car ID, status or reason
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    class="form-control"
                    value="<?= statusHistoryEscape($search) ?>"
                    placeholder="Search status history"
                >
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>

                <a href="car_status_history.php"
                   class="btn btn-outline-secondary">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="panel">
        <h4 class="fw-bold mb-3">Recorded Changes</h4>

        <?php if (count($historyRows) === 0): ?>
            <div class="alert alert-info mb-0">
                No status history entries found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle history-table">
                    <thead>
                        <tr>
                            <th>History ID</th>
                            <th>Car ID</th>
                            <th>Status Transition</th>
                            <th>Reason</th>
                            <th>Changed By</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($historyRows as $row): ?>
                        <tr>
                            <td>
                                #<?= (int)$row['history_id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= statusHistoryEscape($row['car_id']) ?>
                                </strong>
                            </td>

                            <td>
                                <?php if ($row['old_status'] !== null && $row['old_status'] !== ''): ?>
                                    <span class="status-pill">
                                        <?= statusHistoryEscape($row['old_status']) ?>
                                    </span>

                                    <i class="bi bi-arrow-right status-arrow"></i>
                                <?php endif; ?>

                                <span class="status-pill">
                                    <?= statusHistoryEscape($row['new_status']) ?>
                                </span>
                            </td>

                            <td style="min-width:180px; white-space:pre-wrap;">
                                <?= $row['change_reason'] !== null
                                    && $row['change_reason'] !== ''
                                    ? statusHistoryEscape($row['change_reason'])
                                    : '—' ?>
                            </td>

                            <td>
                                <?= $row['changed_by'] === null
                                    ? 'Admin (ID not recorded)'
                                    : 'User ID ' . (int)$row['changed_by'] ?>
                            </td>

                            <td>
                                <?= statusHistoryEscape($row['changed_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p class="text-center text-muted small mt-4">
        DriveMart Administration &copy; <?= date('Y') ?>
    </p>

</div>
</body>
</html>
