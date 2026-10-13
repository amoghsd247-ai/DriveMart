
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function inspectionEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';
$success = '';

$allowedConditions = [
    'Excellent',
    'Good',
    'Fair',
    'Poor',
    'Not Inspected'
];

if (empty($_SESSION['inspection_csrf'])) {
    $_SESSION['inspection_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['inspection_csrf'];

/* =====================================================
   ADD INSPECTION
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = 'Invalid session token. Refresh the page and try again.';
    } else {
        $carId = strtoupper(trim($_POST['car_id'] ?? ''));
        $inspectionDate = trim($_POST['inspection_date'] ?? '');

        $engine = $_POST['engine_condition'] ?? '';
        $exterior = $_POST['exterior_condition'] ?? '';
        $interior = $_POST['interior_condition'] ?? '';
        $tyres = $_POST['tyre_condition'] ?? '';

        $accidentDamage = isset($_POST['accident_damage']) ? 1 : 0;

        $scoreInput = trim($_POST['inspection_score'] ?? '');
        $repairInput = trim($_POST['estimated_repair_cost'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        /* Validate car ID */
        if (!preg_match('/^C\d+$/', $carId)) {
            $error = 'Enter a valid car ID, for example C01260.';
        } elseif (
            !in_array($engine, $allowedConditions, true) ||
            !in_array($exterior, $allowedConditions, true) ||
            !in_array($interior, $allowedConditions, true) ||
            !in_array($tyres, $allowedConditions, true)
        ) {
            $error = 'Please select valid condition values.';
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inspectionDate) ||
            !checkdate(
                (int)substr($inspectionDate, 5, 2),
                (int)substr($inspectionDate, 8, 2),
                (int)substr($inspectionDate, 0, 4)
            )
        ) {
            $error = 'Please enter a valid inspection date.';
        } elseif (
            $scoreInput !== '' &&
            (
                !is_numeric($scoreInput) ||
                (float)$scoreInput < 0 ||
                (float)$scoreInput > 10
            )
        ) {
            $error = 'Inspection score must be between 0 and 10.';
        } elseif (
            $repairInput !== '' &&
            (
                !is_numeric($repairInput) ||
                (float)$repairInput < 0 ||
                (float)$repairInput > 9999999999.99
            )
        ) {
            $error = 'Enter a valid non-negative repair cost.';
        } elseif (mb_strlen($remarks) > 10000) {
            $error = 'Remarks must not exceed 10,000 characters.';
        } else {
            /* Confirm that this car exists */
            $carCheck = $conn->prepare(
                "SELECT car_id
                 FROM cars
                 WHERE car_id = ?
                 LIMIT 1"
            );

            $carExists = false;

            if ($carCheck) {
                $carCheck->bind_param("s", $carId);
                $carCheck->execute();

                $carExists = (bool)$carCheck
                    ->get_result()
                    ->fetch_assoc();

                $carCheck->close();
            }

            if (!$carExists) {
                $error = 'This car ID does not exist in the cars table.';
            } else {
                /*
                 * Your current admin login has no database admin ID.
                 * Use system inspector ID 1 for this single-admin demo.
                 */
                $inspectedBy = 1;

                $score = (
                    $scoreInput === ''
                    ? null
                    : (float)$scoreInput
                );

                $repairCost = (
                    $repairInput === ''
                    ? 0.00
                    : (float)$repairInput
                );

                $remarksValue = ($remarks === '' ? null : $remarks);

                $insert = $conn->prepare(
                    "INSERT INTO car_inspections (
                        car_id,
                        inspected_by,
                        inspection_date,
                        engine_condition,
                        exterior_condition,
                        interior_condition,
                        tyre_condition,
                        accident_damage,
                        inspection_score,
                        estimated_repair_cost,
                        remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$insert) {
                    $error = 'Unable to prepare the inspection record.';
                } else {
                    $insert->bind_param(
                        "sisssssidds",
                        $carId,
                        $inspectedBy,
                        $inspectionDate,
                        $engine,
                        $exterior,
                        $interior,
                        $tyres,
                        $accidentDamage,
                        $score,
                        $repairCost,
                        $remarksValue
                    );

                    if ($insert->execute()) {
                        $success = 'Car inspection added successfully.';
                    } else {
                        $error = 'Unable to save the inspection record.';
                    }

                    $insert->close();
                }
            }
        }
    }
}

/* =====================================================
   SEARCH INSPECTION RECORDS
   ===================================================== */

$search = trim($_GET['search'] ?? '');

$sql = "SELECT
            i.inspection_id,
            i.car_id,
            i.inspected_by,
            i.inspection_date,
            i.engine_condition,
            i.exterior_condition,
            i.interior_condition,
            i.tyre_condition,
            i.accident_damage,
            i.inspection_score,
            i.estimated_repair_cost,
            i.remarks,
            i.created_at
        FROM car_inspections i
        WHERE 1 = 1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        i.car_id LIKE ?
        OR i.remarks LIKE ?
        OR i.engine_condition LIKE ?
        OR i.exterior_condition LIKE ?
    )";

    $term = '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;

    $types .= 'ssss';
}

$sql .= " ORDER BY i.inspection_date DESC, i.inspection_id DESC";

$listStmt = $conn->prepare($sql);
$inspectionRows = [];

if ($listStmt) {
    if (!empty($params)) {
        $listStmt->bind_param($types, ...$params);
    }

    if ($listStmt->execute()) {
        $result = $listStmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $inspectionRows[] = $row;
        }
    } else {
        $error = $error ?: 'Unable to load inspection records.';
    }

    $listStmt->close();
} else {
    $error = $error ?: 'Unable to prepare the inspection query.';
}

/* Total inspections */
$countResult = $conn->query(
    "SELECT COUNT(*) AS total FROM car_inspections"
);

$totalInspections = 0;

if ($countResult) {
    $countRow = $countResult->fetch_assoc();
    $totalInspections = (int)$countRow['total'];
}

require_once "../includes/header.php";
?>

<style>
.inspection-page {
    min-height: 70vh;
    background: #eef3f8;
    padding: 30px 0 45px;
}

.inspection-container {
    width: min(1500px, 95%);
    margin: auto;
}

.inspection-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 24px;
}

.inspection-heading h1 {
    color: #172554;
    font-weight: 750;
    margin-bottom: 5px;
}

.inspection-panel,
.inspection-stat {
    background: #fff;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(0, 0, 0, .05);
}

.inspection-stat {
    height: 100%;
}

.inspection-stat-number {
    font-size: 30px;
    font-weight: 750;
    margin-top: 7px;
}

.inspection-panel textarea {
    min-height: 100px;
    resize: vertical;
}

.inspection-table th {
    white-space: nowrap;
}

.condition-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    background: #e0e7ff;
    color: #3730a3;
    font-size: 12px;
    font-weight: 650;
}

@media (max-width: 768px) {
    .inspection-panel {
        padding: 14px;
    }
}
</style>

<div class="inspection-page">
    <div class="inspection-container">

        <div class="inspection-heading">
            <div>
                <h1>
                    <i class="bi bi-clipboard-check"></i>
                    Car Inspection Management
                </h1>

                <p class="text-muted mb-0">
                    Record vehicle condition, inspection scores and repair costs.
                </p>
            </div>

            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= inspectionEscape($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= inspectionEscape($error) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="inspection-stat">
                    <div class="text-muted">Total Inspections</div>
                    <div class="inspection-stat-number text-primary">
                        <?= number_format($totalInspections) ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="inspection-stat">
                    <div class="fw-bold mb-2">Inspection checklist</div>
                    <div class="text-muted">
                        Assess the engine, exterior, interior, tyres,
                        accident damage and estimated repair costs.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add inspection form -->
        <div class="inspection-panel mb-4">
            <h4 class="fw-bold mb-3">Add New Inspection</h4>

            <form method="POST" action="car_inspections.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= inspectionEscape($csrfToken) ?>"
                >

                <div class="row g-3">

                    <div class="col-md-4">
                        <label for="car_id" class="form-label">
                            Car ID
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="car_id"
                            name="car_id"
                            placeholder="Example: C01260"
                            pattern="C[0-9]+"
                            maxlength="10"
                            required
                        >

                        <small class="text-muted">
                            Enter an existing car ID.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label for="inspection_date" class="form-label">
                            Inspection Date
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="inspection_date"
                            name="inspection_date"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <?php
                    $conditionFields = [
                        'engine_condition' => 'Engine Condition',
                        'exterior_condition' => 'Exterior Condition',
                        'interior_condition' => 'Interior Condition',
                        'tyre_condition' => 'Tyre Condition'
                    ];
                    ?>

                    <?php foreach ($conditionFields as $field => $label): ?>
                        <div class="col-md-3">
                            <label
                                for="<?= inspectionEscape($field) ?>"
                                class="form-label"
                            >
                                <?= inspectionEscape($label) ?>
                            </label>

                            <select
                                class="form-select"
                                id="<?= inspectionEscape($field) ?>"
                                name="<?= inspectionEscape($field) ?>"
                                required
                            >
                                <option value="">Select condition</option>

                                <?php foreach ($allowedConditions as $condition): ?>
                                    <option value="<?= inspectionEscape($condition) ?>">
                                        <?= inspectionEscape($condition) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>

                    <div class="col-md-3">
                        <label for="inspection_score" class="form-label">
                            Inspection Score (0–10)
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="inspection_score"
                            name="inspection_score"
                            min="0"
                            max="10"
                            step="0.1"
                            placeholder="Example: 8.5"
                        >
                    </div>

                    <div class="col-md-3">
                        <label for="estimated_repair_cost" class="form-label">
                            Estimated Repair Cost (₹)
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="estimated_repair_cost"
                            name="estimated_repair_cost"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            value="0"
                            required
                        >
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="accident_damage"
                                name="accident_damage"
                                value="1"
                            >

                            <label
                                class="form-check-label"
                                for="accident_damage"
                            >
                                Accident damage found
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="remarks" class="form-label">
                            Inspection Remarks
                        </label>

                        <textarea
                            class="form-control"
                            id="remarks"
                            name="remarks"
                            maxlength="10000"
                            placeholder="Describe issues found, repairs needed, or other observations..."
                        ></textarea>
                    </div>

                    <div class="col-12">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-save"></i>
                            Save Inspection
                        </button>

                        <button
                            type="reset"
                            class="btn btn-outline-secondary"
                        >
                            Reset Form
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <!-- Search inspections -->
        <div class="inspection-panel mb-4">
            <h4 class="fw-bold mb-3">Search Inspections</h4>

            <form
                method="GET"
                action="car_inspections.php"
                class="row g-3 align-items-end"
            >
                <div class="col-md-9">
                    <label for="search" class="form-label">
                        Search by car ID, condition or remarks
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control"
                        value="<?= inspectionEscape($search) ?>"
                        placeholder="Enter car ID or keyword"
                    >
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>

                    <a
                        href="car_inspections.php"
                        class="btn btn-outline-secondary"
                    >
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Inspection records -->
        <div class="inspection-panel">
            <h4 class="fw-bold mb-3">Inspection Records</h4>

            <?php if (count($inspectionRows) === 0): ?>

                <div class="alert alert-info mb-0">
                    No inspection records found.
                    Add an inspection using the form above.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle inspection-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Car ID</th>
                                <th>Date</th>
                                <th>Engine</th>
                                <th>Exterior</th>
                                <th>Interior</th>
                                <th>Tyres</th>
                                <th>Accident Damage</th>
                                <th>Score</th>
                                <th>Repair Cost</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($inspectionRows as $row): ?>
                                <tr>
                                    <td>
                                        #<?= (int)$row['inspection_id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= inspectionEscape($row['car_id']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= inspectionEscape($row['inspection_date']) ?>
                                    </td>

                                    <td>
                                        <span class="condition-badge">
                                            <?= inspectionEscape($row['engine_condition']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= inspectionEscape($row['exterior_condition']) ?>
                                    </td>

                                    <td>
                                        <?= inspectionEscape($row['interior_condition']) ?>
                                    </td>

                                    <td>
                                        <?= inspectionEscape($row['tyre_condition']) ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$row['accident_damage'] === 1): ?>
                                            <span class="badge text-bg-danger">
                                                Yes
                                            </span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success">
                                                No
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= $row['inspection_score'] === null
                                            ? '—'
                                            : inspectionEscape($row['inspection_score']) . '/10' ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format(
                                            (float)$row['estimated_repair_cost'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['remarks'])): ?>
                                            <details>
                                                <summary style="cursor:pointer;">
                                                    View remarks
                                                </summary>

                                                <div class="mt-2" style="min-width:200px; white-space:pre-wrap;">
                                                    <?= inspectionEscape($row['remarks']) ?>
                                                </div>
                                            </details>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
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
