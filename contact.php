
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

function contactEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$success = '';
$error = '';

$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (
        $name === '' ||
        $email === '' ||
        $subject === '' ||
        $message === ''
    ) {
        $error = 'Please fill in all fields.';
    } elseif (
        mb_strlen($name) > 100 ||
        mb_strlen($email) > 150 ||
        mb_strlen($subject) > 150 ||
        mb_strlen($message) > 10000
    ) {
        $error = 'One or more fields are too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $userId = isset($_SESSION['user_id'])
            ? (int)$_SESSION['user_id']
            : null;

        $stmt = $conn->prepare(
            "INSERT INTO contact_messages
                (user_id, name, email, subject, message)
             VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $error = 'Unable to process your message right now.';
        } else {
            $stmt->bind_param(
                "issss",
                $userId,
                $name,
                $email,
                $subject,
                $message
            );

            if ($stmt->execute()) {
                $stmt->close();

                header('Location: contact.php?sent=1');
                exit();
            } else {
                $error = 'Your message could not be saved. Please try again.';
                $stmt->close();
            }
        }
    }
}

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $success = 'Your message has been submitted successfully.';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.contact-wrapper {
    max-width: 850px;
    margin: 45px auto;
    padding: 0 18px;
}
.contact-card {
    background: #fff;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 8px 28px rgba(0,0,0,.08);
}
.contact-heading {
    margin-bottom: 25px;
}
.contact-heading h1 {
    font-weight: 750;
    color: #172554;
}
.contact-card label {
    font-weight: 600;
    margin-bottom: 7px;
}
.contact-card .form-control {
    padding: 12px;
}
.contact-card textarea {
    min-height: 150px;
    resize: vertical;
}
.contact-submit {
    background: #2563eb;
    color: #fff;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
}
.contact-submit:hover {
    background: #1d4ed8;
}
</style>

<div class="contact-wrapper">
    <div class="contact-heading">
        <h1>Contact DriveMart</h1>
        <p class="text-muted">
            Have a question about a car or our services?
            Send us a message and our team will review it.
        </p>
    </div>

    <div class="contact-card">

        <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= contactEscape($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= contactEscape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="contact.php">
            <div class="mb-3">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    maxlength="100"
                    value="<?= contactEscape($name) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    maxlength="150"
                    value="<?= contactEscape($email) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="subject">Subject</label>
                <input
                    type="text"
                    id="subject"
                    name="subject"
                    class="form-control"
                    maxlength="150"
                    value="<?= contactEscape($subject) ?>"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="message">Your Message</label>
                <textarea
                    id="message"
                    name="message"
                    class="form-control"
                    maxlength="10000"
                    required
                ><?= contactEscape($message) ?></textarea>
            </div>

            <button type="submit" class="contact-submit">
                <i class="bi bi-send"></i> Send Message
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
