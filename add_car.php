<?php
include "../includes/db.php";

if(isset($_POST['submit'])){

$id=uniqid("C");

$brand=$_POST['brand'];
$model=$_POST['model'];
$year=$_POST['year'];
$km=$_POST['km'];
$fuel=$_POST['fuel'];
$trans=$_POST['transmission'];
$owner=$_POST['owner'];
$condition=$_POST['condition'];
$insurance=$_POST['insurance'];
$service=$_POST['service'];
$city=$_POST['city'];
$color=$_POST['color'];
$price=$_POST['price'];

$conn->query("INSERT INTO cars VALUES(
'$id','$brand','$model','$year','$km','$fuel',
'$trans','$owner','$condition','$insurance',
'$service','0','$city','$color','$price',''
)");

header("Location: dashboard.php");

}
?>

<!DOCTYPE html>
<html>
<head>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card p-4 shadow">

<h2>Add New Car</h2>

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<input name="brand" class="form-control" placeholder="Brand" required>
</div>

<div class="col-md-6 mb-3">
<input name="model" class="form-control" placeholder="Model" required>
</div>

<div class="col-md-4 mb-3">
<input name="year" class="form-control" placeholder="Year" required>
</div>

<div class="col-md-4 mb-3">
<input name="km" class="form-control" placeholder="KM Driven" required>
</div>

<div class="col-md-4 mb-3">
<input name="price" class="form-control" placeholder="Selling Price" required>
</div>

<div class="col-md-6 mb-3">
<input name="fuel" class="form-control" placeholder="Fuel">
</div>

<div class="col-md-6 mb-3">
<input name="transmission" class="form-control" placeholder="Transmission">
</div>

<div class="col-md-6 mb-3">
<input name="owner" class="form-control" placeholder="Owner">
</div>

<div class="col-md-6 mb-3">
<input name="condition" class="form-control" placeholder="Condition Score">
</div>

<div class="col-md-6 mb-3">
<input name="insurance" class="form-control" placeholder="Insurance">
</div>

<div class="col-md-6 mb-3">
<input name="service" class="form-control" placeholder="Service History">
</div>

<div class="col-md-6 mb-3">
<input name="city" class="form-control" placeholder="City">
</div>

<div class="col-md-6 mb-3">
<input name="color" class="form-control" placeholder="Color">
</div>

</div>

<button name="submit" class="btn btn-success w-100">
Add Car
</button>

</form>

</div>

</div>

</body>
</html>