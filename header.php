
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isCustomerLoggedIn = isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] > 0;
$customerName = trim($_SESSION["user_name"] ?? "");

if ($customerName === "") {
    $customerName = "Customer";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DriveMart - Premium Used Cars</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark custom-nav">
    <div class="container">

        <a class="navbar-brand" href="index.php">DriveMart</a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="cars.php">Browse Cars</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="compare.php">Compare</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">❤️ Wishlist</a>
                </li>

                <?php if ($isCustomerLoggedIn): ?>

                    <!-- Customer Activity Page -->
                    <li class="nav-item">
                        <a class="nav-link" href="my_activity.php">
                            📋 My Activity
                        </a>
                    </li>

                    <li class="nav-item">
                        <span class="nav-link">
                            Welcome, <?php echo htmlspecialchars($customerName, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a class="nav-link" href="user_login.php">Login</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
