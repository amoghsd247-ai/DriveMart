
<?php
include "includes/db.php";

$cars = $conn->query("SELECT * FROM cars ORDER BY RAND() LIMIT 8");

include "includes/header.php";
?>

<section class="hero-premium">
    <div class="overlay"></div>

    <div class="container hero-content">
        <span class="hero-badge">🚘 India's Trusted Used Car Marketplace</span>

        <h1>Find Your Dream Car <br>At The Best Price</h1>

        <p>5000+ Verified Cars • Instant RC Transfer • Warranty • Easy Finance</p>

        <div class="hero-buttons">
            <a href="cars.php" class="btn btn-primary btn-lg">Browse Cars</a>
            <a href="compare.php" class="btn btn-light btn-lg">Compare Cars</a>
        </div>
    </div>
</section>

<div class="container search-wrapper">
    <div class="search-glass">
        <form action="cars.php" method="GET">
            <div class="row g-3">

                <div class="col-lg-3 col-md-6">
                    <select name="brand" class="form-select">
                        <option value="">Brand</option>
                        <option>Hyundai</option>
                        <option>Mahindra</option>
                        <option>Honda</option>
                        <option>Tata</option>
                        <option>Toyota</option>
                        <option>Maruti</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <input
                        type="text"
                        name="city"
                        class="form-control"
                        placeholder="City"
                    >
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="fuel" class="form-select">
                        <option value="">Fuel Type</option>
                        <option>Petrol</option>
                        <option>Diesel</option>
                        <option>CNG</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <button class="btn btn-primary w-100 h-100">
                        🔍 Search Cars
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<div class="container">

    <h2 class="section-title">Featured Cars</h2>

    <div class="row g-4">

        <?php if ($cars && $cars->num_rows > 0): ?>

            <?php while ($row = $cars->fetch_assoc()): ?>
                <?php
                // Preserve the actual database ID as a string.
                $carId = (string)($row['car_id'] ?? '');

                $carName = trim(
                    ($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')
                );

                // Resolve image safely.
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

                // Read current status from the database.
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
                    in_array($carStatus, ['Sold', 'Unavailable'], true)
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

                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
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
                                style="width:100%;height:210px;object-fit:cover;"
                            >
                        <?php else: ?>
                            <div
                                class="d-flex align-items-center justify-content-center bg-light text-secondary"
                                style="height:210px;"
                            >
                                Image unavailable
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <span class="badge-custom">Verified</span>

                            <h5 class="car-title mt-2">
                                <?= htmlspecialchars(
                                    $carName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h5>

                            <!-- Car ID and current status -->
                            <div class="d-flex flex-wrap gap-2 my-2">
                                <span class="badge bg-primary">
                                    ID:
                                    <?= htmlspecialchars(
                                        $carId,
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
                                <div>📅 <?= (int)$row['year'] ?></div>

                                <div>
                                    ⛽ <?= htmlspecialchars(
                                        $row['fuel'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <div>
                                    🛣 <?= number_format(
                                        (float)$row['km_driven']
                                    ) ?> km
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
                                href="car.php?id=<?= rawurlencode($carId) ?>"
                                class="btn btn-primary w-100 mt-3"
                            >
                                View Details
                            </a>

                            <a
                                href="compare.php?id=<?= rawurlencode($carId) ?>"
                                class="btn btn-outline-secondary w-100 mt-2"
                            >
                                ⚖ Compare
                            </a>

                            <a
                                href="wishlist.php?add=<?= rawurlencode($carId) ?>"
                                class="btn btn-outline-danger w-100 mt-2"
                            >
                                ♥ Add to Wishlist
                            </a>
                        </div>

                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="col-12">
                <div class="alert alert-warning">
                    No featured cars are available right now.
                </div>
            </div>

        <?php endif; ?>

    </div>

    <h2 class="section-title text-center mt-5">Popular Brands</h2>

    <div class="row text-center g-4 mb-5">

        <div class="col-4 col-md-2">
            <img src="assets/images/hyundai-logo.png" width="70" alt="Hyundai">
            <p>Hyundai</p>
        </div>

        <div class="col-4 col-md-2">
            <img src="assets/images/maruti-logo.png" width="70" alt="Maruti">
            <p>Maruti</p>
        </div>

        <div class="col-4 col-md-2">
            <img src="assets/images/tata-logo.png" width="70" alt="Tata">
            <p>Tata</p>
        </div>

        <div class="col-4 col-md-2">
            <img src="assets/images/toyota-logo.png" width="70" alt="Toyota">
            <p>Toyota</p>
        </div>

        <div class="col-4 col-md-2">
            <img src="assets/images/honda-logo.png" width="70" alt="Honda">
            <p>Honda</p>
        </div>

        <div class="col-4 col-md-2">
            <img src="assets/images/mahindra-logo.png" width="70" alt="Mahindra">
            <p>Mahindra</p>
        </div>

    </div>

    <h2 class="section-title text-center">Why Choose DriveMart?</h2>

    <div class="row text-center mb-5">

        <div class="col-md-3">
            <h2 class="text-primary fw-bold">5000+</h2>
            <p>Verified Cars</p>
        </div>

        <div class="col-md-3">
            <h2 class="text-primary fw-bold">2500+</h2>
            <p>Happy Buyers</p>
        </div>

        <div class="col-md-3">
            <h2 class="text-primary fw-bold">4.9 ★</h2>
            <p>Customer Rating</p>
        </div>

        <div class="col-md-3">
            <h2 class="text-primary fw-bold">100%</h2>
            <p>Trusted Marketplace</p>
        </div>

    </div>

</div>

<?php include "includes/footer.php"; ?>
