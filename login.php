<?php
session_start();

$error = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $adminEmail = getenv('DRIVEMART_ADMIN_EMAIL') ?: '';
    $adminPassword = getenv('DRIVEMART_ADMIN_PASSWORD') ?: '';

    if (
        $adminEmail !== '' &&
        $adminPassword !== '' &&
        hash_equals($adminEmail, $email) &&
        hash_equals($adminPassword, $password)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;

        header("Location: admin/dashboard.php");
        exit();
    }

    $error = "Invalid Email or Password";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DriveMart Admin Login</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: "Segoe UI", sans-serif;
        }

        .card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 18px;
        }

        .logo {
            font-size: 42px;
        }

        .form-control {
            padding: 11px 13px;
        }

        .btn-primary {
            padding: 10px;
        }
    </style>
</head>

<body>

    <div class="card shadow p-4">

        <div class="text-center mb-3">

            <div class="logo">🚗</div>

            <h2 class="fw-bold">DriveMart Admin</h2>

            <p class="text-muted">Login to Dashboard</p>

        </div>

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                name="email"
                type="email"
                class="form-control mb-3"
                placeholder="Email"
                autocomplete="username"
                required
            >

            <input
                name="password"
                type="password"
                class="form-control mb-3"
                placeholder="Password"
                autocomplete="current-password"
                required
            >

            <button
                type="submit"
                name="login"
                value="1"
                class="btn btn-primary w-100"
            >
                Login
            </button>

        </form>

    </div>

</body>

</html>
