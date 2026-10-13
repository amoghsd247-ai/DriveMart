<?php

include "../includes/db.php";

$id=$_GET['id'];

$conn->query("DELETE FROM cars WHERE car_id='$id'");

header("Location: dashboard.php");

?>