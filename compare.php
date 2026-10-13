<?php
include "includes/db.php";
include "includes/header.php";

$cars = $conn->query("SELECT car_id,brand,model,year FROM cars ORDER BY brand,model");

$car1=null;
$car2=null;

if(isset($_GET['car1']) && isset($_GET['car2'])){

$id1=$_GET['car1'];
$id2=$_GET['car2'];

$car1=$conn->query("SELECT * FROM cars WHERE car_id='$id1'")->fetch_assoc();
$car2=$conn->query("SELECT * FROM cars WHERE car_id='$id2'")->fetch_assoc();

}
?>

<div class="container py-5" style="margin-top:90px;">

<h2 class="fw-bold text-center mb-4">⚖ Compare Cars</h2>

<form method="GET">

<div class="row g-3">

<div class="col-md-5">

<select name="car1" class="form-select" required>

<option value="">Select First Car</option>

<?php
$cars->data_seek(0);
while($row=$cars->fetch_assoc()){
?>

<option value="<?php echo $row['car_id']; ?>">

<?php echo $row['brand']." ".$row['model']." (".$row['year'].")"; ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-5">

<select name="car2" class="form-select" required>

<option value="">Select Second Car</option>

<?php
$cars->data_seek(0);
while($row=$cars->fetch_assoc()){
?>

<option value="<?php echo $row['car_id']; ?>">

<?php echo $row['brand']." ".$row['model']." (".$row['year'].")"; ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-2">

<button class="btn btn-primary w-100">

Compare

</button>

</div>

</div>

</form>

<?php if($car1 && $car2){ ?>

<div class="row mt-5">

<div class="col-md-6">

<div class="card shadow">

<img src="assets/images/<?php echo $car1['image']; ?>" style="height:260px;object-fit:cover;">

<div class="card-body">

<h4><?php echo $car1['brand']." ".$car1['model']; ?></h4>

<h3 class="text-primary">₹<?php echo number_format($car1['selling_price']); ?></h3>

</div>

</div>

</div>

<div class="col-md-6">

<div class="card shadow">

<img src="assets/images/<?php echo $car2['image']; ?>" style="height:260px;object-fit:cover;">

<div class="card-body">

<h4><?php echo $car2['brand']." ".$car2['model']; ?></h4>

<h3 class="text-primary">₹<?php echo number_format($car2['selling_price']); ?></h3>

</div>

</div>

</div>

</div>

<div class="card shadow mt-4">

<div class="card-body">

<h4 class="mb-3">Detailed Comparison</h4>

<table class="table table-bordered">

<tr>
<th width="35%">Feature</th>
<th><?php echo $car1['brand']." ".$car1['model']; ?></th>
<th><?php echo $car2['brand']." ".$car2['model']; ?></th>
</tr>

<tr><th>Year</th><td><?php echo $car1['year']; ?></td><td><?php echo $car2['year']; ?></td></tr>

<tr><th>Price</th><td>₹<?php echo number_format($car1['selling_price']); ?></td><td>₹<?php echo number_format($car2['selling_price']); ?></td></tr>

<tr><th>Kilometers</th><td><?php echo number_format($car1['km_driven']); ?></td><td><?php echo number_format($car2['km_driven']); ?></td></tr>

<tr><th>Fuel</th><td><?php echo $car1['fuel']; ?></td><td><?php echo $car2['fuel']; ?></td></tr>

<tr><th>Transmission</th><td><?php echo $car1['transmission']; ?></td><td><?php echo $car2['transmission']; ?></td></tr>

<tr><th>Owner</th><td><?php echo $car1['owner']; ?></td><td><?php echo $car2['owner']; ?></td></tr>

<tr><th>City</th><td><?php echo $car1['city']; ?></td><td><?php echo $car2['city']; ?></td></tr>

<tr><th>Color</th><td><?php echo $car1['color']; ?></td><td><?php echo $car2['color']; ?></td></tr>

<tr><th>Condition</th><td><?php echo $car1['condition_score']; ?>/10</td><td><?php echo $car2['condition_score']; ?>/10</td></tr>

<tr><th>Insurance</th><td><?php echo $car1['insurance']; ?></td><td><?php echo $car2['insurance']; ?></td></tr>

<tr><th>Service History</th><td><?php echo $car1['service_history']; ?></td><td><?php echo $car2['service_history']; ?></td></tr>

</table>

</div>

</div>

<?php } ?>

</div>

<?php include "includes/footer.php"; ?>