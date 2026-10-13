
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once "includes/db.php";

// Get and validate the car ID.
$id = trim($_GET['id'] ?? $_POST['car_id'] ?? '');

if ($id === '' || !preg_match('/^C\d+$/', $id)) {
    include "includes/header.php";
    ?>
    <div class="container mt-5 pt-5 mb-5">
        <div class="alert alert-danger">
            Invalid car ID. Please select a car from the Browse Cars page.
        </div>
        <a href="cars.php" class="btn btn-primary">Back to Cars</a>
    </div>
    <?php
    include "includes/footer.php";
    exit;
}

// Fetch the selected car, including its current status.
$stmt = $conn->prepare(
    "SELECT * FROM cars WHERE car_id = ? LIMIT 1"
);

if (!$stmt) {
    die("Unable to prepare the car query.");
}

$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$car = $result->fetch_assoc();
$stmt->close();

// Check whether the car exists.
if (!$car) {
    include "includes/header.php";
    ?>
    <div class="container mt-5 pt-5 mb-5">
        <div class="alert alert-warning">
            Car not found. Please select another car.
        </div>
        <a href="cars.php" class="btn btn-primary">Back to Cars</a>
    </div>
    <?php
    include "includes/footer.php";
    exit;
}

// Safely display database values.
function carText($value)
{
    return htmlspecialchars(
        (string)($value ?? 'N/A'),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Current car status.
$carStatus = trim((string)($car['status'] ?? 'Available'));

$allowedStatuses = [
    'Available',
    'Reserved',
    'Sold',
    'Under Inspection',
    'Under Repair',
    'Unavailable'
];

// Avoid displaying an unexpected database value as a trusted status.
if (!in_array($carStatus, $allowedStatuses, true)) {
    $carStatus = 'Unavailable';
}

// Sold and unavailable cars cannot accept new enquiries or test-drive requests.
$canCustomerAct = !in_array(
    $carStatus,
    ['Sold', 'Unavailable'],
    true
);

// Select a Bootstrap badge colour.
$statusBadgeClass = 'bg-success';

if ($carStatus === 'Reserved') {
    $statusBadgeClass = 'bg-warning text-dark';
} elseif (in_array($carStatus, ['Sold', 'Unavailable'], true)) {
    $statusBadgeClass = 'bg-danger';
} elseif (in_array(
    $carStatus,
    ['Under Repair', 'Under Inspection'],
    true
)) {
    $statusBadgeClass = 'bg-secondary';
}

$isCustomerLoggedIn = isset($_SESSION['user_id'])
    && (int)$_SESSION['user_id'] > 0;

// Separate messages for the two forms.
$enquiryMessage = '';
$enquiryType = '';
$enquiryText = '';

$bookingMessage = '';
$bookingType = '';
$bookingDate = '';
$bookingTime = '';
$bookingNotes = '';

// Create CSRF tokens for both forms.
if (empty($_SESSION['enquiry_csrf_token'])) {
    $_SESSION['enquiry_csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['test_drive_csrf_token'])) {
    $_SESSION['test_drive_csrf_token'] = bin2hex(random_bytes(32));
}

// Display messages after successful redirects.
if (($_GET['enquiry'] ?? '') === 'success') {
    $enquiryMessage =
        'Your enquiry has been submitted successfully! Our team will review it.';
    $enquiryType = 'success';
}

if (($_GET['booking'] ?? '') === 'success') {
    $bookingMessage =
        'Your test-drive request has been submitted! Your booking is pending confirmation.';
    $bookingType = 'success';
}

// Process the enquiry form.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_enquiry'])
) {
    if (!$canCustomerAct) {
        $enquiryMessage =
            'This car is marked ' . $carStatus
            . ', so new enquiries are unavailable.';
        $enquiryType = 'warning';

    } elseif (!$isCustomerLoggedIn) {
        $enquiryMessage = 'Please log in to submit an enquiry.';
        $enquiryType = 'warning';

    } elseif (
        !isset($_POST['csrf_token'])
        || !hash_equals(
            $_SESSION['enquiry_csrf_token'],
            (string)$_POST['csrf_token']
        )
    ) {
        $enquiryMessage =
            'Your form session has expired. Please refresh the page and try again.';
        $enquiryType = 'danger';

    } else {
        $enquiryText = trim($_POST['message'] ?? '');

        if ($enquiryText === '') {
            $enquiryMessage = 'Please enter your enquiry message.';
            $enquiryType = 'danger';

        } elseif (strlen($enquiryText) > 5000) {
            $enquiryMessage = 'Your message must not exceed 5000 characters.';
            $enquiryType = 'danger';

        } else {
            $userId = (int)$_SESSION['user_id'];

            $insert = $conn->prepare(
                "INSERT INTO enquiries (user_id, car_id, message)
                 VALUES (?, ?, ?)"
            );

            if (!$insert) {
                $enquiryMessage =
                    'Unable to prepare your enquiry. Please try again later.';
                $enquiryType = 'danger';

            } else {
                $insert->bind_param(
                    "iss",
                    $userId,
                    $id,
                    $enquiryText
                );

                if ($insert->execute()) {
                    $insert->close();

                    $_SESSION['enquiry_csrf_token'] =
                        bin2hex(random_bytes(32));

                    header(
                        "Location: car.php?id="
                        . urlencode($id)
                        . "&enquiry=success"
                    );
                    exit;

                } else {
                    $enquiryMessage =
                        'We could not save your enquiry. Please try again.';
                    $enquiryType = 'danger';
                    $insert->close();
                }
            }
        }
    }
}

// Process the test-drive booking form.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_test_drive'])
) {
    $bookingDate = trim($_POST['booking_date'] ?? '');
    $bookingTime = trim($_POST['booking_time'] ?? '');
    $bookingNotes = trim($_POST['booking_notes'] ?? '');

    if (!$canCustomerAct) {
        $bookingMessage =
            'This car is marked ' . $carStatus
            . ', so test-drive requests are unavailable.';
        $bookingType = 'warning';

    } elseif (!$isCustomerLoggedIn) {
        $bookingMessage = 'Please log in to book a test drive.';
        $bookingType = 'warning';

    } elseif (
        !isset($_POST['test_drive_csrf_token'])
        || !hash_equals(
            $_SESSION['test_drive_csrf_token'],
            (string)$_POST['test_drive_csrf_token']
        )
    ) {
        $bookingMessage =
            'Your booking form session has expired. Please refresh the page and try again.';
        $bookingType = 'danger';

    } else {
        // Strictly validate date and time formats.
        $dateObject = DateTime::createFromFormat('!Y-m-d', $bookingDate);
        $dateErrors = DateTime::getLastErrors();

        $validDate = $dateObject !== false
            && $dateObject->format('Y-m-d') === $bookingDate
            && (
                $dateErrors === false
                || (
                    $dateErrors['warning_count'] === 0
                    && $dateErrors['error_count'] === 0
                )
            );

        $timeObject = DateTime::createFromFormat('!H:i', $bookingTime);
        $timeErrors = DateTime::getLastErrors();

        $validTime = $timeObject !== false
            && $timeObject->format('H:i') === $bookingTime
            && (
                $timeErrors === false
                || (
                    $timeErrors['warning_count'] === 0
                    && $timeErrors['error_count'] === 0
                )
            );

        if (!$validDate || !$validTime) {
            $bookingMessage = 'Please select a valid booking date and time.';
            $bookingType = 'danger';

        } elseif (
            strtotime($bookingDate . ' ' . $bookingTime)
            < strtotime(date('Y-m-d H:i'))
        ) {
            $bookingMessage = 'Please select a future date and time.';
            $bookingType = 'danger';

        } elseif (strlen($bookingNotes) > 3000) {
            $bookingMessage = 'Your notes must not exceed 3000 characters.';
            $bookingType = 'danger';

        } else {
            $userId = (int)$_SESSION['user_id'];
            $dbTime = $bookingTime . ':00';
            $notes = $bookingNotes !== '' ? $bookingNotes : null;

            $insert = $conn->prepare(
                "INSERT INTO test_drives
                    (user_id, car_id, booking_date, booking_time, notes)
                 VALUES (?, ?, ?, ?, ?)"
            );

            if (!$insert) {
                $bookingMessage =
                    'Unable to prepare your booking. Please try again later.';
                $bookingType = 'danger';

            } else {
                $insert->bind_param(
                    "issss",
                    $userId,
                    $id,
                    $bookingDate,
                    $dbTime,
                    $notes
                );

                if ($insert->execute()) {
                    $insert->close();

                    $_SESSION['test_drive_csrf_token'] =
                        bin2hex(random_bytes(32));

                    header(
                        "Location: car.php?id="
                        . urlencode($id)
                        . "&booking=success"
                    );
                    exit;

                } else {
                    $bookingMessage =
                        'We could not save your booking. Please check your details and try again.';
                    $bookingType = 'danger';
                    $insert->close();
                }
            }
        }
    }
}

// Resolve the car image.
$imageFile = basename(trim((string)($car['image'] ?? '')));
$image = '';

if (
    $imageFile !== ''
    && is_file(__DIR__ . '/assets/images/' . $imageFile)
) {
    $image = 'assets/images/' . $imageFile;

} else {
    $modelFile = strtolower(trim((string)$car['model']));
    $modelFile = str_replace([' ', '-'], '_', $modelFile);

    $candidate = 'assets/images/' . $modelFile . '.jpg';

    if (is_file(__DIR__ . '/' . $candidate)) {
        $image = $candidate;

    } elseif (is_file(__DIR__ . '/assets/images/default.jpg')) {
        $image = 'assets/images/default.jpg';
    }
}

// Prepare the WhatsApp message.
$message = "Hi, I'm interested in the "
    . $car['brand'] . " " . $car['model']
    . " (" . $car['year'] . ") priced at ₹"
    . number_format((float)$car['selling_price']);

$whatsappLink = "https://wa.me/917058344158?text="
    . urlencode($message);

include "includes/header.php";
?>

<div class="container mt-5 pt-5 mb-5">

    <!-- Car details -->
    <div class="row g-4">

        <div class="col-lg-7">
            <?php if ($image !== ''): ?>
                <img
                    src="<?= carText($image) ?>"
                    alt="<?= carText($car['brand'] . ' ' . $car['model']) ?>"
                    class="img-fluid rounded shadow"
                    style="width:100%; height:430px; object-fit:cover;"
                >
            <?php else: ?>
                <div class="alert alert-secondary" style="height:200px;">
                    Image unavailable for this car.
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">

            <h2 class="fw-bold">
                <?= carText($car['brand']) ?>
                <?= carText($car['model']) ?>
            </h2>

            <h3 class="text-primary fw-bold">
                ₹<?= number_format((float)$car['selling_price']) ?>
            </h3>

            <!-- Live vehicle status -->
            <div class="mb-3">
                <strong>Availability:</strong>
                <span class="badge <?= carText($statusBadgeClass) ?> fs-6">
                    <?= carText($carStatus) ?>
                </span>
            </div>

            <?php if (!$canCustomerAct): ?>
                <div class="alert alert-warning">
                    This vehicle is currently
                    <?= carText(strtolower($carStatus)) ?>.
                    New enquiries and test-drive requests are unavailable.
                </div>
            <?php elseif ($carStatus === 'Reserved'): ?>
                <div class="alert alert-info">
                    This vehicle is reserved. Contact DriveMart to check its availability.
                </div>
            <?php elseif (in_array(
                $carStatus,
                ['Under Repair', 'Under Inspection'],
                true
            )): ?>
                <div class="alert alert-secondary">
                    This vehicle is <?= carText(strtolower($carStatus)) ?>.
                    Contact DriveMart to confirm its availability.
                </div>
            <?php endif; ?>

            <hr>

            <div class="row gy-3">
                <div class="col-6">
                    <b>Year</b><br><?= carText($car['year']) ?>
                </div>

                <div class="col-6">
                    <b>Kilometers</b><br>
                    <?= number_format((float)$car['km_driven']) ?> km
                </div>

                <div class="col-6">
                    <b>Fuel</b><br><?= carText($car['fuel']) ?>
                </div>

                <div class="col-6">
                    <b>Transmission</b><br><?= carText($car['transmission']) ?>
                </div>

                <div class="col-6">
                    <b>Owner</b><br><?= carText($car['owner']) ?>
                </div>

                <div class="col-6">
                    <b>City</b><br><?= carText($car['city']) ?>
                </div>

                <div class="col-6">
                    <b>Color</b><br><?= carText($car['color']) ?>
                </div>

                <div class="col-6">
                    <b>Condition</b><br>
                    <?= carText($car['condition_score']) ?>/10
                </div>

                <div class="col-6">
                    <b>Insurance</b><br><?= carText($car['insurance']) ?>
                </div>

                <div class="col-6">
                    <b>Service History</b><br>
                    <?= carText($car['service_history']) ?>
                </div>
            </div>

            <hr>

            <a
                href="<?= carText($whatsappLink) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-success w-100 mb-2"
            >
                📞 Contact Seller on WhatsApp
            </a>

            <a
                href="compare.php?id=<?= urlencode($id) ?>"
                class="btn btn-outline-primary w-100 mb-2"
            >
                ⚖️ Compare This Car
            </a>

            <a
                href="wishlist.php?add=<?= urlencode($id) ?>"
                class="btn btn-outline-danger w-100 mb-2"
            >
                ♥ Add to Wishlist
            </a>

            <a href="cars.php" class="btn btn-outline-dark w-100">
                ← Back to Cars
            </a>

        </div>
    </div>

    <!-- Test-drive booking section -->
    <div class="row mt-5" id="test-drive-form">
        <div class="col-lg-8 mx-auto">

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">

                    <h3 class="fw-bold mb-2">Book a Test Drive</h3>

                    <p class="text-muted">
                        Choose a convenient date and time to request a test drive
                        for this
                        <?= carText($car['brand'] . ' ' . $car['model']) ?>.
                        Your booking will remain pending until the team confirms it.
                    </p>

                    <?php if ($bookingMessage !== ''): ?>
                        <div
                            class="alert alert-<?= carText($bookingType) ?>"
                            role="alert"
                        >
                            <?= carText($bookingMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$canCustomerAct): ?>

                        <div class="alert alert-warning">
                            Test-drive requests are unavailable because this car is
                            <?= carText(strtolower($carStatus)) ?>.
                        </div>

                    <?php elseif ($isCustomerLoggedIn): ?>

                        <form
                            method="POST"
                            action="car.php?id=<?= urlencode($id) ?>#test-drive-form"
                        >
                            <input
                                type="hidden"
                                name="car_id"
                                value="<?= carText($id) ?>"
                            >

                            <input
                                type="hidden"
                                name="test_drive_csrf_token"
                                value="<?= carText($_SESSION['test_drive_csrf_token']) ?>"
                            >

                            <div class="mb-3">
                                <label for="booking_date" class="form-label fw-semibold">
                                    Preferred Date
                                </label>

                                <input
                                    type="date"
                                    name="booking_date"
                                    id="booking_date"
                                    class="form-control"
                                    min="<?= date('Y-m-d') ?>"
                                    value="<?= carText($bookingDate) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="booking_time" class="form-label fw-semibold">
                                    Preferred Time
                                </label>

                                <input
                                    type="time"
                                    name="booking_time"
                                    id="booking_time"
                                    class="form-control"
                                    value="<?= carText($bookingTime) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="booking_notes" class="form-label fw-semibold">
                                    Additional Notes (Optional)
                                </label>

                                <textarea
                                    name="booking_notes"
                                    id="booking_notes"
                                    class="form-control"
                                    rows="3"
                                    maxlength="3000"
                                    placeholder="Any preferred arrangements or questions..."
                                ><?= carText($bookingNotes) ?></textarea>
                            </div>

                            <button
                                type="submit"
                                name="submit_test_drive"
                                value="1"
                                class="btn btn-primary w-100"
                            >
                                Request Test Drive
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="alert alert-info">
                            Please log in to book a test drive.
                        </div>

                        <a href="user_login.php" class="btn btn-primary w-100">
                            Log In to Continue
                        </a>

                        <p class="text-center mt-3 mb-0">
                            New to DriveMart?
                            <a href="register.php">Create an account</a>
                        </p>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

    <!-- Customer enquiry section -->
    <div class="row mt-5" id="enquiry-form">
        <div class="col-lg-8 mx-auto">

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">

                    <h3 class="fw-bold mb-2">Send a Car Enquiry</h3>

                    <p class="text-muted">
                        Interested in this
                        <?= carText($car['brand'] . ' ' . $car['model']) ?>?
                        Send us a message and our team can follow up with you.
                    </p>

                    <?php if ($enquiryMessage !== ''): ?>
                        <div
                            class="alert alert-<?= carText($enquiryType) ?>"
                            role="alert"
                        >
                            <?= carText($enquiryMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$canCustomerAct): ?>

                        <div class="alert alert-warning">
                            New enquiries are unavailable because this car is
                            <?= carText(strtolower($carStatus)) ?>.
                        </div>

                    <?php elseif ($isCustomerLoggedIn): ?>

                        <form
                            method="POST"
                            action="car.php?id=<?= urlencode($id) ?>#enquiry-form"
                        >
                            <input
                                type="hidden"
                                name="car_id"
                                value="<?= carText($id) ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= carText($_SESSION['enquiry_csrf_token']) ?>"
                            >

                            <div class="mb-3">
                                <label for="message" class="form-label fw-semibold">
                                    Your message
                                </label>

                                <textarea
                                    name="message"
                                    id="message"
                                    class="form-control"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="I am interested in this car. Please share more details..."
                                    required
                                ><?= carText($enquiryText) ?></textarea>

                                <small class="text-muted">
                                    Maximum 5000 characters.
                                </small>
                            </div>

                            <button
                                type="submit"
                                name="submit_enquiry"
                                value="1"
                                class="btn btn-primary w-100"
                            >
                                Submit Enquiry
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="alert alert-info">
                            Please log in to submit an enquiry about this car.
                        </div>

                        <a href="user_login.php" class="btn btn-primary w-100">
                            Log In to Continue
                        </a>

                        <p class="text-center mt-3 mb-0">
                            New to DriveMart?
                            <a href="register.php">Create an account</a>
                        </p>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

</div>

<?php include "includes/footer.php"; ?>
