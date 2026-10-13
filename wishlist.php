<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

$message = "";
$messageType = "info";

// Customer must be logged in to use a personal wishlist.
$userId = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : 0;

// Support both wishlist.php?add=C00001 and wishlist.php?id=C00001
$addId = trim($_GET["add"] ?? $_GET["id"] ?? "");
$removeId = trim($_GET["remove"] ?? "");

// Handle adding a car.
if ($addId !== "" && $userId > 0) {
    if (!preg_match('/^C\d+$/', $addId)) {
        $message = "Invalid car selected.";
        $messageType = "danger";
    } else {
        // Confirm that this car exists.
        $checkCar = $conn->prepare(
            "SELECT car_id FROM cars WHERE car_id = ? LIMIT 1"
        );

        if ($checkCar) {
            $checkCar->bind_param("s", $addId);
            $checkCar->execute();
            $checkCar->store_result();
            $carExists = $checkCar->num_rows > 0;
            $checkCar->close();

            if (!$carExists) {
                $message = "This car could not be found.";
                $messageType = "danger";
            } else {
                // INSERT IGNORE prevents duplicate wishlist entries.
                $stmt = $conn->prepare(
                    "INSERT IGNORE INTO wishlist (user_id, car_id)
                     VALUES (?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param("is", $userId, $addId);

                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $message = "Car added to your wishlist!";
                            $messageType = "success";
                        } else {
                            $message = "This car is already in your wishlist.";
                            $messageType = "info";
                        }
                    } else {
                        $message = "Could not add this car. Please try again.";
                        $messageType = "danger";
                    }

                    $stmt->close();
                } else {
                    $message = "Unable to update your wishlist right now.";
                    $messageType = "danger";
                }
            }
        } else {
            $message = "Unable to check the selected car.";
            $messageType = "danger";
        }
    }
}

// Handle removing a car from this customer's wishlist only.
if ($removeId !== "" && $userId > 0) {
    if (!preg_match('/^C\d+$/', $removeId)) {
        $message = "Invalid car selected.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare(
            "DELETE FROM wishlist WHERE user_id = ? AND car_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("is", $userId, $removeId);

            if ($stmt->execute()) {
                $message = $stmt->affected_rows > 0
                    ? "Car removed from your wishlist."
                    : "That car was not in your wishlist.";

                $messageType = "success";
            } else {
                $message = "Could not remove the car. Please try again.";
                $messageType = "danger";
            }

            $stmt->close();
        } else {
            $message = "Unable to update your wishlist right now.";
            $messageType = "danger";
        }
    }
}

// Load only the logged-in customer's saved cars.
$cars = null;

if ($userId > 0) {
    $stmt = $conn->prepare(
        "SELECT c.*
         FROM wishlist w
         INNER JOIN cars c ON c.car_id = w.car_id
         WHERE w.user_id = ?
         ORDER BY w.created_at DESC"
    );

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cars = $stmt->get_result();
    }
}

include "includes/header.php";
?>

<div class="container py-5">

    <h2 class="fw-bold mb-4">❤️ My Wishlist</h2>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($userId <= 0): ?>

        <div class="card shadow-sm p-4">
            <h4>Please log in to view your wishlist.</h4>
            <p>Sign in to save cars and access your wishlist whenever you return.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="user_login.php" class="btn btn-primary">Login</a>
                <a href="register.php" class="btn btn-outline-primary">Register</a>
                <a href="cars.php" class="btn btn-outline-secondary">Browse Cars</a>
            </div>
        </div>

    <?php elseif ($cars === null): ?>

        <div class="alert alert-danger">
            Unable to load your wishlist. Please try again.
        </div>

    <?php elseif ($cars->num_rows === 0): ?>

        <div class="card shadow-sm p-4">
            <h4>No cars saved yet.</h4>
            <p>Browse our available cars and add your favourites to your wishlist.</p>
            <a href="cars.php" class="btn btn-primary">Browse Cars</a>
        </div>

    <?php else: ?>

        <div class="row">

            <?php while ($row = $cars->fetch_assoc()): ?>

                <?php
                $carId = (string) ($row["car_id"] ?? "");
                $brand = (string) ($row["brand"] ?? "");
                $model = (string) ($row["model"] ?? "");
                $image = trim((string) ($row["image"] ?? ""));

                $imagePath = "assets/images/" . basename($image);
                $price = isset($row["selling_price"])
                    ? number_format((float) $row["selling_price"])
                    : "Price unavailable";
                ?>

                <div class="col-md-4 mb-4">

                    <div class="card shadow h-100">

                        <?php if ($image !== ""): ?>
                            <img
                                src="<?php echo htmlspecialchars($imagePath); ?>"
                                alt="<?php echo htmlspecialchars($brand . " " . $model); ?>"
                                class="card-img-top"
                                style="height:220px; object-fit:cover;"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div
                                class="d-flex align-items-center justify-content-center bg-light"
                                style="height:220px;"
                            >
                                <span class="text-muted">Image unavailable</span>
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">

                            <h5 class="card-title">
                                <?php echo htmlspecialchars($brand . " " . $model); ?>
                            </h5>

                            <h4 class="text-primary">
                                <?php
                                echo $price === "Price unavailable"
                                    ? htmlspecialchars($price)
                                    : "₹" . $price;
                                ?>
                            </h4>

                            <a
                                href="car.php?id=<?php echo urlencode($carId); ?>"
                                class="btn btn-primary w-100 mb-2"
                            >
                                View Details
                            </a>

                            <a
                                href="wishlist.php?remove=<?php echo urlencode($carId); ?>"
                                class="btn btn-danger w-100"
                                onclick="return confirm('Remove this car from your wishlist?');"
                            >
                                Remove
                            </a>

                        </div>
                    </div>
                </div>

            <?php endwhile; ?>

        </div>

    <?php endif; ?>

</div>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
include "includes/footer.php";
?>
