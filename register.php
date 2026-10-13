
<?php
session_start();
require_once "includes/db.php";

$error = "";
$name = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Remove spaces, hyphens and brackets from the phone number.
    $phone = preg_replace('/[\s().-]+/', '', $phone);

    // Accept Indian numbers entered as 9876543210, +919876543210,
    // or 919876543210, and store them in international format.
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '91' . $phone;
    } elseif (preg_match('/^\+91[6-9]\d{9}$/', $phone)) {
        $phone = substr($phone, 1);
    }

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $password === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^91[6-9]\d{9}$/', $phone)) {
        $error = "Enter a valid 10-digit Indian mobile number, optionally starting with +91.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Unable to process registration right now.";
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "An account with this email already exists.";
                $check->close();
            } else {
                $check->close();

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, phone, password_hash)
                     VALUES (?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error = "Unable to create your account right now.";
                } else {
                    $stmt->bind_param(
                        "ssss",
                        $name,
                        $email,
                        $phone,
                        $passwordHash
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        header("Location: user_login.php?registered=1");
                        exit;
                    } else {
                        $error = "Registration failed. Please try again.";
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | DriveMart</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            padding: 24px;
        }

        .register-card {
            width: 100%;
            max-width: 440px;
            border: 0;
            border-radius: 18px;
        }
    </style>
</head>
<body>

<div class="card register-card shadow p-4">
    <div class="text-center mb-4">
        <div class="fs-1">🚗</div>
        <h2 class="fw-bold">Join DriveMart</h2>
        <p class="text-muted">Create your customer account</p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label class="form-label" for="name">Full name</label>
        <input
            id="name"
            name="name"
            type="text"
            class="form-control mb-3"
            maxlength="100"
            autocomplete="name"
            value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label class="form-label" for="email">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            class="form-control mb-3"
            maxlength="150"
            autocomplete="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label class="form-label" for="phone">
            Mobile number (WhatsApp)
        </label>
        <input
            id="phone"
            name="phone"
            type="tel"
            class="form-control mb-1"
            maxlength="16"
            autocomplete="tel"
            placeholder="9876543210 or +919876543210"
            value="<?= htmlspecialchars(
                trim($_POST["phone"] ?? ""),
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
            required
        >
        <small class="text-muted d-block mb-3">
            Enter your Indian mobile number. DriveMart may use it to
            respond to enquiries and coordinate test drives via WhatsApp.
        </small>

        <label class="form-label" for="password">Password</label>
        <input
            id="password"
            name="password"
            type="password"
            class="form-control mb-3"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label class="form-label" for="confirm_password">
            Confirm password
        </label>
        <input
            id="confirm_password"
            name="confirm_password"
            type="password"
            class="form-control mb-3"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit" class="btn btn-primary w-100">
            Create Account
        </button>
    </form>

    <p class="text-center mt-3 mb-0">
        Already registered?
        <a href="user_login.php">Sign in</a>
    </p>

    <p class="text-center mt-3 mb-0">
        <a href="cars.php">← Browse Cars</a>
    </p>
</div>

</body>
</html>
