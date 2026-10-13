<?php
include "../includes/db.php";

$id = $_GET['id'];

if(isset($_POST['update'])){

$brand=$_POST['brand'];
$model=$_POST['model'];
$year=$_POST['year'];
$km=$_POST['km'];
$fuel=$_POST['fuel'];
$trans=$_POST['transmission'];
$city=$_POST['city'];
$price=$_POST['price'];

$conn->query("UPDATE cars SET
brand='$brand',
model='$model',
year='$year',
km_driven='$km',
fuel='$fuel',
transmission='$trans',
city='$city',
selling_price='$price'
WHERE car_id='$id'");

header("Location: dashboard.php");
}

$car = $conn->query("SELECT * FROM cars WHERE car_id='$id'")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow p-4">

<h2>Edit Car</h2>

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label>Brand</label>
<input name="brand" value="<?php echo $car['brand']; ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Model</label>
<input name="model" value="<?php echo $car['model']; ?>" class="form-control">
</div>

<div class="col-md-4 mb-3">
<label>Year</label>
<input name="year" value="<?php echo $car['year']; ?>" class="form-control">
</div>

<div class="col-md-4 mb-3">
<label>Kilometers</label>
<input name="km" value="<?php echo $car['km_driven']; ?>" class="form-control">
</div>

<div class="col-md-4 mb-3">
<label>Price</label>
<input name="price" value="<?php echo $car['selling_price']; ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Fuel</label>
<input name="fuel" value="<?php echo $car['fuel']; ?>" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Transmission</label>
<input name="transmission" value="<?php echo $car['transmission']; ?>" class="form-control">
</div>

<div class="col-md-12 mb-3">
<label>City</label>
<input name="city" value="<?php echo $car['city']; ?>" class="form-control">
</div>

</div>

<button name="update" class="btn btn-success w-100">
Update Car
</button>

</form>

</div>

</div>

</body>
</html>
