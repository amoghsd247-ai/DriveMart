<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_email"]);

header("Location: index.php");
exit;
