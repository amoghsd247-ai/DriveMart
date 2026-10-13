<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

$error = "";
$registered = isset($_GET["registered"]) && $_GET["registered"] === "1";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, email, password_hash
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "Unable to log in right now. Please try again.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $stmt->close();

            if ($user && password_verify($password, $user["password_hash"])) {
                // Prevent session fixation after authentication.
                session_regenerate_id(true);

                $_SESSION["user_id"] = (int) $user["user_id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid email or password.";
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
    <title>Customer Login | DriveMart</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            padding: 24px;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            border: 0;
            border-radius: 18px;
        }
    </style>
</head>
<body>

<div class="card login-card shadow p-4">
    <div class="text-center mb-4">
        <div class="fs-1">🚗</div>
        <h2 class="fw-bold">Welcome Back</h2>
        <p class="text-muted">Sign in to your DriveMart account</p>
    </div>

    <?php if ($registered): ?>
        <div class="alert alert-success">
            Account created successfully! You can now sign in.
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="user_login.php">
        <label class="form-label" for="email">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            class="form-control mb-3"
            maxlength="150"
            autocomplete="email"
            value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label class="form-label" for="password">Password</label>
        <input
            id="password"
            name="password"
            type="password"
            class="form-control mb-3"
            autocomplete="current-password"
            required
        >

        <button type="submit" class="btn btn-primary w-100">
            Login
        </button>
    </form>

    <p class="text-center mt-3 mb-0">
        Don't have an account?
        <a href="register.php">Register</a>
    </p>

    <p class="text-center mt-3 mb-0">
        <a href="index.php">← Back to DriveMart</a>
    </p>
</div>

</body>
</html>
