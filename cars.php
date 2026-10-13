
<?php
include "includes/db.php";

$brand = trim($_GET['brand'] ?? '');
$city  = trim($_GET['city'] ?? '');
$fuel  = trim($_GET['fuel'] ?? '');
$carId = strtoupper(trim($_GET['car_id'] ?? ''));
$sort  = $_GET['sort'] ?? '';

$brands = [];
$fuels = [];

// Load brands.
$brandQuery = mysqli_query(
    $conn,
    "SELECT DISTINCT TRIM(brand) AS brand
     FROM cars
     WHERE brand IS NOT NULL
       AND TRIM(brand) <> ''
       AND LOWER(TRIM(brand)) NOT IN ('brand', 'unknown', 'n/a')
     ORDER BY brand ASC"
);

if ($brandQuery) {
    while ($row = mysqli_fetch_assoc($brandQuery)) {
        $brands[] = $row['brand'];
    }
}

// Load fuel types.
$fuelQuery = mysqli_query(
    $conn,
    "SELECT DISTINCT TRIM(fuel) AS fuel
     FROM cars
     WHERE fuel IS NOT NULL
       AND TRIM(fuel) <> ''
       AND LOWER(TRIM(fuel)) NOT IN ('fuel', 'unknown', 'n/a')
     ORDER BY fuel ASC"
);

if ($fuelQuery) {
    while ($row = mysqli_fetch_assoc($fuelQuery)) {
        $fuels[] = $row['fuel'];
    }
}

// Build the car query.
$sql = "SELECT *
        FROM cars
        WHERE brand IS NOT NULL
          AND TRIM(brand) <> ''
          AND model IS NOT NULL
          AND TRIM(model) <> ''
          AND LOWER(TRIM(brand)) <> 'brand'
          AND LOWER(TRIM(model)) <> 'model'
          AND year > 0
          AND selling_price > 0";

$params = [];
$types = "";

// Filter by exact Car ID.
if ($carId !== '') {
    $sql .= " AND UPPER(TRIM(car_id)) = ?";
    $params[] = $carId;
    $types .= "s";
}

// Filter by brand.
if ($brand !== '') {
    $sql .= " AND TRIM(brand) = ?";
    $params[] = $brand;
    $types .= "s";
}

// Filter by city.
if ($city !== '') {
    $sql .= " AND city LIKE ?";
    $params[] = "%" . $city . "%";
    $types .= "s";
}

// Filter by fuel type.
if ($fuel !== '') {
    $sql .= " AND TRIM(fuel) = ?";
    $params[] = $fuel;
    $types .= "s";
}

// Sort results.
if ($sort === 'price_low') {
    $sql .= " ORDER BY selling_price ASC";
} elseif ($sort === 'price_high') {
    $sql .= " ORDER BY selling_price DESC";
} else {
    $sql .= " ORDER BY car_id DESC";
}

// Execute query safely using prepared statements.
$stmt = mysqli_prepare($conn, $sql);
$cars = false;

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (mysqli_stmt_execute($stmt)) {
        $cars = mysqli_stmt_get_result($stmt);
    }
}

include "includes/header.php";
?>

<div class="container mt-4">

    <h2 class="section-title">Available Cars</h2>

    <!-- Search and filter form -->
    <form method="GET" action="cars.php" class="search-glass mb-4">
        <div class="row g-3">

            <!-- Search by Car ID -->
            <div class="col-md-3">
                <label for="car_id" class="form-label">
                    Search by Car ID
                </label>

                <input
                    type="text"
                    name="car_id"
                    id="car_id"
                    class="form-control"
                    placeholder="e.g. C00040"
                    value="<?= htmlspecialchars(
                        $carId,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </div>

            <!-- Brand filter -->
            <div class="col-md-3">
                <label for="brand" class="form-label">Brand</label>

                <select name="brand" id="brand" class="form-select">
                    <option value="">All Brands</option>

                    <?php foreach ($brands as $option): ?>
                        <option
                            value="<?= htmlspecialchars(
                                $option,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            <?= strcasecmp($brand, $option) === 0
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(
                                $option,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- City filter -->
            <div class="col-md-3">
                <label for="city" class="form-label">City</label>

                <input
                    type="text"
                    name="city"
                    id="city"
                    class="form-control"
                    placeholder="Enter city"
                    value="<?= htmlspecialchars(
                        $city,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </div>

            <!-- Fuel filter -->
            <div class="col-md-3">
                <label for="fuel" class="form-label">Fuel Type</label>

                <select name="fuel" id="fuel" class="form-select">
                    <option value="">All Fuel Types</option>

                    <?php foreach ($fuels as $option): ?>
                        <option
                            value="<?= htmlspecialchars(
                                $option,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            <?= strcasecmp($fuel, $option) === 0
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(
                                $option,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sorting -->
            <div class="col-md-3">
                <label for="sort" class="form-label">Sort by Price</label>

                <select name="sort" id="sort" class="form-select">
                    <option value="">Default Order</option>

                    <option
                        value="price_low"
                        <?= $sort === 'price_low' ? 'selected' : '' ?>
                    >
                        Low to High
                    </option>

                    <option
                        value="price_high"
                        <?= $sort === 'price_high' ? 'selected' : '' ?>
                    >
                        High to Low
                    </option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    Search
                </button>

                <a href="cars.php" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>

        </div>
    </form>

    <div class="row g-4">

        <?php if ($cars && mysqli_num_rows($cars) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($cars)): ?>

                <?php
                // Keep IDs such as C00001 as strings.
                $currentCarId = (string)($row['car_id'] ?? '');

                $carName = trim(
                    ($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')
                );

                // Resolve the image.
                $imageFile = basename(
                    trim((string)($row['image'] ?? ''))
                );

                $imagePath = '';

                if (
                    $imageFile !== ''
                    && is_file(__DIR__ . '/assets/images/' . $imageFile)
                ) {
                    $imagePath = 'assets/images/' . $imageFile;
                } else {
                    $modelFile = strtolower(
                        trim((string)($row['model'] ?? ''))
                    );

                    $modelFile = str_replace(
                        [' ', '-'],
                        '_',
                        $modelFile
                    );

                    $candidate = 'assets/images/' . $modelFile . '.jpg';

                    if (is_file(__DIR__ . '/' . $candidate)) {
                        $imagePath = $candidate;
                    } elseif (
                        is_file(__DIR__ . '/assets/images/default.jpg')
                    ) {
                        $imagePath = 'assets/images/default.jpg';
                    }
                }

                // Current car status.
                $carStatus = trim(
                    (string)($row['status'] ?? 'Available')
                );

                $validStatuses = [
                    'Available',
                    'Reserved',
                    'Sold',
                    'Under Inspection',
                    'Under Repair',
                    'Unavailable'
                ];

                if (!in_array($carStatus, $validStatuses, true)) {
                    $carStatus = 'Unavailable';
                }

                $statusClass = 'bg-success';

                if ($carStatus === 'Reserved') {
                    $statusClass = 'bg-warning text-dark';
                } elseif (
                    in_array(
                        $carStatus,
                        ['Sold', 'Unavailable'],
                        true
                    )
                ) {
                    $statusClass = 'bg-danger';
                } elseif (
                    in_array(
                        $carStatus,
                        ['Under Inspection', 'Under Repair'],
                        true
                    )
                ) {
                    $statusClass = 'bg-secondary';
                }
                ?>

                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="car-card h-100">

                        <?php if ($imagePath !== ''): ?>

                            <img
                                src="<?= htmlspecialchars(
                                    $imagePath,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $carName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                loading="lazy"
                                style="width:100%;height:240px;object-fit:cover;"
                                onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';"
                            >

                            <div
                                style="display:none;width:100%;height:240px;background:#f1f5f9;color:#475569;align-items:center;justify-content:center;"
                            >
                                Image unavailable
                            </div>

                        <?php else: ?>

                            <div
                                style="display:flex;width:100%;height:240px;background:#f1f5f9;color:#475569;align-items:center;justify-content:center;"
                            >
                                Image unavailable
                            </div>

                        <?php endif; ?>

                        <div class="card-body">

                            <span class="badge-custom">Verified</span>

                            <h4 class="car-title mt-2">
                                <?= htmlspecialchars(
                                    $carName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h4>

                            <!-- Car ID and status -->
                            <div class="d-flex flex-wrap gap-2 my-2">
                                <span class="badge bg-primary">
                                    Car ID:
                                    <?= htmlspecialchars(
                                        $currentCarId,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span class="badge <?= $statusClass ?>">
                                    <?= htmlspecialchars(
                                        $carStatus,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>

                            <div class="specs">
                                <div>
                                    📅 <?= (int)$row['year'] ?>
                                </div>

                                <div>
                                    🛣 <?= number_format(
                                        (float)$row['km_driven']
                                    ) ?> km
                                </div>

                                <div>
                                    ⛽ <?= htmlspecialchars(
                                        $row['fuel'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <div>
                                    ⚙ <?= htmlspecialchars(
                                        $row['transmission'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                            </div>

                            <div class="price">
                                ₹<?= number_format(
                                    (float)$row['selling_price']
                                ) ?>
                            </div>

                            <a
                                href="car.php?id=<?= rawurlencode($currentCarId) ?>"
                                class="btn btn-primary w-100 mt-3"
                            >
                                View Details
                            </a>

                            <a
                                href="compare.php?id=<?= rawurlencode($currentCarId) ?>"
                                class="btn btn-outline-secondary w-100 mt-2"
                            >
                                ⚖ Compare
                            </a>

                            <a
                                href="wishlist.php?add=<?= rawurlencode($currentCarId) ?>"
                                class="btn btn-outline-danger w-100 mt-2"
                            >
                                ❤️ Add to Wishlist
                            </a>

                        </div>
                    </div>
                </div>

            <?php endwhile; ?>

        <?php elseif ($cars): ?>

            <div class="col-12">
                <div class="alert alert-warning text-center">
                    No cars found. Check the Car ID or try changing your filters.
                </div>
            </div>

        <?php else: ?>

            <div class="col-12">
                <div class="alert alert-danger text-center">
                    Unable to load cars right now. Please check the database connection.
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    mysqli_stmt_close($stmt);
}

include "includes/footer.php";
?>
