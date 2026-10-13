
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db.php";

function adminText($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Inventory search
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $searchTerm = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT * FROM cars
         WHERE brand LIKE ?
            OR model LIKE ?
            OR city LIKE ?
         ORDER BY year DESC
         LIMIT 20"
    );

    if (!$stmt) {
        die("Unable to prepare the car search.");
    }

    $stmt->bind_param(
        "sss",
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

    $stmt->execute();
    $cars = $stmt->get_result();
} else {
    $cars = $conn->query(
        "SELECT * FROM cars ORDER BY year DESC LIMIT 20"
    );
}

if (!$cars) {
    die("Unable to load the car inventory.");
}

// Dashboard statistics
$totalCars = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM cars"
)->fetch_assoc()['total'];

$avgPrice = (float)$conn->query(
    "SELECT COALESCE(AVG(selling_price), 0) AS average_price FROM cars"
)->fetch_assoc()['average_price'];

$totalBrands = (int)$conn->query(
    "SELECT COUNT(DISTINCT brand) AS total FROM cars"
)->fetch_assoc()['total'];

$expensiveResult = $conn->query(
    "SELECT brand, model, selling_price
     FROM cars
     ORDER BY selling_price DESC
     LIMIT 1"
);

$expensive = $expensiveResult
    ? $expensiveResult->fetch_assoc()
    : null;

// Customer enquiry statistics
$totalEnquiries = 0;
$pendingEnquiries = 0;

$enquiryStats = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending
     FROM enquiries"
);

if ($enquiryStats) {
    $stats = $enquiryStats->fetch_assoc();
    $totalEnquiries = (int)$stats['total'];
    $pendingEnquiries = (int)$stats['pending'];
}

// Test-drive statistics
$totalTestDrives = 0;
$pendingTestDrives = 0;

$testDriveStats = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending
     FROM test_drives"
);

if ($testDriveStats) {
    $driveStats = $testDriveStats->fetch_assoc();
    $totalTestDrives = (int)$driveStats['total'];
    $pendingTestDrives = (int)$driveStats['pending'];
}

// Contact message statistics
$totalContactMessages = 0;
$unreadMessages = 0;

$contactStats = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Unread'), 0) AS unread
     FROM contact_messages"
);

if ($contactStats) {
    $contactRow = $contactStats->fetch_assoc();
    $totalContactMessages = (int)$contactRow['total'];
    $unreadMessages = (int)$contactRow['unread'];
}

// Car inspection statistics
$totalInspections = 0;

$inspectionStats = $conn->query(
    "SELECT COUNT(*) AS total FROM car_inspections"
);

if ($inspectionStats) {
    $inspectionRow = $inspectionStats->fetch_assoc();
    $totalInspections = (int)$inspectionRow['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DriveMart Admin Dashboard</title>

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
            background: #eef3f8;
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: #0f172a;
            padding: 25px 18px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar h3 {
            color: white;
            font-weight: 700;
            margin-bottom: 30px;
            padding-left: 5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 7px;
            transition: background .2s, color .2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .nav-count {
            margin-left: auto;
            background: #dc2626;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 700;
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
            box-shadow: 0 8px 20px rgba(0,0,0,.08);
        }

        .stat {
            border: none;
            border-radius: 18px;
            color: white;
            padding: 22px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(0,0,0,.06);
        }

        .stat h2 {
            font-weight: 750;
            margin: 8px 0 0;
            overflow-wrap: anywhere;
        }

        .table-card {
            background: white;
            padding: 22px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,.06);
        }

        .table-card table {
            margin-bottom: 0;
        }

        .table-card thead th {
            white-space: nowrap;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
        }

        table img {
            width: 90px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
        }

        .quick-link {
            display: block;
            background: white;
            border-radius: 16px;
            padding: 20px;
            color: #0f172a;
            text-decoration: none;
            box-shadow: 0 6px 18px rgba(0,0,0,.05);
            height: 100%;
            transition: transform .2s;
        }

        .quick-link:hover {
            transform: translateY(-3px);
            color: #2563eb;
        }

        .quick-link i {
            font-size: 25px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 200px;
                padding: 20px 12px;
            }

            .main {
                margin-left: 200px;
                padding: 18px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .sidebar h3 {
                margin-bottom: 15px;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .topbar {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <h3><i class="bi bi-car-front-fill"></i> DriveMart</h3>

    <a href="dashboard.php" class="active">
        <i class="bi bi-grid-fill"></i> Dashboard
    </a>

    <a href="add_car.php">
        <i class="bi bi-plus-circle"></i> Add Car
    </a>

    <a href="enquiries.php">
        <i class="bi bi-chat-left-text"></i> Customer Enquiries

        <?php if ($pendingEnquiries > 0): ?>
            <span class="nav-count">
                <?= $pendingEnquiries ?>
            </span>
        <?php endif; ?>
    </a>

    <a href="contact_messages.php">
        <i class="bi bi-envelope-paper"></i> Contact Messages

        <?php if ($unreadMessages > 0): ?>
            <span class="nav-count">
                <?= $unreadMessages ?>
            </span>
        <?php endif; ?>
    </a>

    <a href="test_drives.php">
        <i class="bi bi-calendar-check"></i> Test-Drive Bookings

        <?php if ($pendingTestDrives > 0): ?>
            <span class="nav-count">
                <?= $pendingTestDrives ?>
            </span>
        <?php endif; ?>
    </a>

    <a href="sales.php">
        <i class="bi bi-receipt"></i> Sales Management
    </a>

    <!-- NEW: Car Inspections -->
    <a href="car_inspections.php">
        <i class="bi bi-clipboard-check"></i> Car Inspections
    </a>

    <!-- NEW: Car Status History -->
    <a href="car_status_history.php">
        <i class="bi bi-clock-history"></i> Car Status History
    </a>

    <a href="../cars.php" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-car-front"></i> Browse Cars
    </a>

    <a href="../index.php" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-house"></i> View Website
    </a>

    <a href="../logout.php"
       onclick="return confirm('Are you sure you want to log out?');">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</aside>

<main class="main">

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Admin Dashboard</h2>
            <p class="mb-0">
                Welcome back, Administrator. Manage your DriveMart marketplace.
            </p>
        </div>

        <a href="add_car.php" class="btn btn-light fw-semibold">
            <i class="bi bi-plus-circle"></i> Add New Car
        </a>
    </div>

    <!-- Dashboard statistics -->
    <div class="row g-4 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="stat" style="background:#2563eb;">
                <small><i class="bi bi-car-front"></i> Total Cars</small>
                <h2><?= number_format($totalCars) ?></h2>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat" style="background:#16a34a;">
                <small><i class="bi bi-currency-rupee"></i> Average Price</small>
                <h2>₹<?= number_format($avgPrice) ?></h2>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat" style="background:#f59e0b; color:#1f2937;">
                <small><i class="bi bi-tags"></i> Car Brands</small>
                <h2><?= number_format($totalBrands) ?></h2>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat" style="background:#7c3aed;">
                <small><i class="bi bi-chat-left-text"></i> Pending Enquiries</small>
                <h2><?= number_format($pendingEnquiries) ?></h2>
                <small><?= number_format($totalEnquiries) ?> total enquiries</small>
            </div>
        </div>

    </div>

    <!-- Quick actions -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <a href="enquiries.php" class="quick-link">
                <i class="bi bi-chat-square-text text-primary"></i>
                <h5 class="fw-bold mt-3">Manage Enquiries</h5>
                <p class="text-muted mb-0">
                    Review customer questions and update their status.
                </p>
            </a>
        </div>

        <div class="col-md-4">
            <a href="test_drives.php" class="quick-link">
                <i class="bi bi-calendar-check text-primary"></i>
                <h5 class="fw-bold mt-3">Test-Drive Bookings</h5>
                <p class="text-muted mb-0">
                    <?= number_format($totalTestDrives) ?> total bookings.
                    <?= number_format($pendingTestDrives) ?> pending confirmation.
                    View bookings and update their status.
                </p>
            </a>
        </div>

        <div class="col-md-4">
            <a href="contact_messages.php" class="quick-link">
                <i class="bi bi-envelope-paper text-primary"></i>
                <h5 class="fw-bold mt-3">Contact Messages</h5>
                <p class="text-muted mb-0">
                    <?= number_format($totalContactMessages) ?> total messages.
                    <?= number_format($unreadMessages) ?> unread.
                    View messages and save replies.
                </p>
            </a>
        </div>

        <div class="col-md-4">
            <a href="sales.php" class="quick-link">
                <i class="bi bi-receipt text-success"></i>
                <h5 class="fw-bold mt-3">Sales Management</h5>
                <p class="text-muted mb-0">
                    Record transactions, review sales history, and update sale and payment statuses.
                </p>
            </a>
        </div>

        <!-- NEW: Car Inspections card -->
        <div class="col-md-4">
            <a href="car_inspections.php" class="quick-link">
                <i class="bi bi-clipboard-check text-primary"></i>
                <h5 class="fw-bold mt-3">Car Inspections</h5>
                <p class="text-muted mb-0">
                    <?= number_format($totalInspections) ?> inspection records.
                    Record vehicle condition, inspection scores and repair costs.
                </p>
            </a>
        </div>

        <!-- NEW: Car Status History card -->
        <div class="col-md-4">
            <a href="car_status_history.php" class="quick-link">
                <i class="bi bi-clock-history text-warning"></i>
                <h5 class="fw-bold mt-3">Car Status History</h5>
                <p class="text-muted mb-0">
                    Review recorded status changes and the reasons behind them.
                </p>
            </a>
        </div>

        <div class="col-md-4">
            <a href="add_car.php" class="quick-link">
                <i class="bi bi-plus-circle text-success"></i>
                <h5 class="fw-bold mt-3">Add a Car</h5>
                <p class="text-muted mb-0">
                    Add a vehicle to the DriveMart inventory.
                </p>
            </a>
        </div>

        <div class="col-md-4">
            <a href="../cars.php" target="_blank" rel="noopener noreferrer"
               class="quick-link">
                <i class="bi bi-globe text-warning"></i>
                <h5 class="fw-bold mt-3">View Marketplace</h5>
                <p class="text-muted mb-0">
                    Open the customer-facing website.
                </p>
            </a>
        </div>

    </div>

    <!-- Most expensive car -->
    <?php if ($expensive): ?>
        <div class="alert alert-primary border-0 rounded-4 mb-4">
            <i class="bi bi-trophy-fill"></i>
            <strong>Highest-priced car:</strong>
            <?= adminText($expensive['brand']) ?>
            <?= adminText($expensive['model']) ?>
            &mdash;
            ₹<?= number_format((float)$expensive['selling_price']) ?>
        </div>
    <?php endif; ?>

    <!-- Inventory table -->
    <div class="table-card">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Car Inventory</h4>
                <p class="text-muted mb-0">
                    Showing the latest 20 cars, or matching search results.
                </p>
            </div>

            <form method="GET" action="dashboard.php"
                  class="d-flex" style="max-width:350px; width:100%;">

                <input
                    type="text"
                    name="search"
                    value="<?= adminText($search) ?>"
                    class="form-control me-2"
                    placeholder="Brand, model or city"
                    aria-label="Search cars"
                >

                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-search"></i>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="dashboard.php"
                       class="btn btn-outline-secondary ms-2"
                       title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>

            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Car</th>
                        <th>Year</th>
                        <th>City</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($cars->num_rows > 0): ?>

                    <?php while ($row = $cars->fetch_assoc()): ?>
                        <?php
                        $model = strtolower((string)$row['model']);
                        $model = str_replace([' ', '-'], '_', $model);

                        $imagePath = "../assets/images/" . $model . ".jpg";

                        if (!is_file(__DIR__ . "/" . $imagePath)) {
                            $imagePath = "../assets/images/default.jpg";
                        }

                        $carId = (string)$row['car_id'];
                        ?>

                        <tr>
                            <td>
                                <img
                                    src="<?= adminText($imagePath) ?>"
                                    alt="<?= adminText($row['brand'] . ' ' . $row['model']) ?>"
                                    loading="lazy"
                                >
                            </td>

                            <td>
                                <strong>
                                    <?= adminText($row['brand']) ?>
                                    <?= adminText($row['model']) ?>
                                </strong>
                                <br>
                                <small class="text-muted">
                                    <?= adminText($row['fuel']) ?>
                                    &bull;
                                    <?= adminText($row['transmission']) ?>
                                </small>
                            </td>

                            <td><?= adminText($row['year']) ?></td>

                            <td><?= adminText($row['city']) ?></td>

                            <td>
                                <strong>
                                    ₹<?= number_format((float)$row['selling_price']) ?>
                                </strong>
                            </td>

                            <td class="text-nowrap">
                                <a
                                    href="edit_car.php?id=<?= urlencode($carId) ?>"
                                    class="btn btn-sm btn-warning"
                                    title="Edit car"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a
                                    href="delete_car.php?id=<?= urlencode($carId) ?>"
                                    class="btn btn-sm btn-danger"
                                    title="Delete car"
                                    onclick="return confirm('Delete this car?');"
                                >
                                    <i class="bi bi-trash"></i>
                                </a>

                                <a
                                    href="../car.php?id=<?= urlencode($carId) ?>"
                                    class="btn btn-sm btn-outline-primary"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="View car"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No cars matched your search.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>

            </table>
        </div>

    </div>

    <p class="text-muted text-center mt-4 small">
        DriveMart Administration &copy; <?= date('Y') ?>
    </p>

</main>

</body>
</html>
