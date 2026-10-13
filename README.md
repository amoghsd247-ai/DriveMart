# DriveMart — Used Car Marketplace

DriveMart is a PHP and MySQL used-car marketplace website. The project includes car browsing and filtering, compare and wishlist pages, user login/registration, and an admin area.

## Project structure

- `index.php` — homepage
- `cars.php`, `car.php` — browse and view cars
- `compare.php`, `wishlist.php` — compare and wishlist features
- `includes/` — shared database connection, header, and footer
- `admin/` — admin dashboard and management pages
- `assets/css/` and `assets/images/` — styling and vehicle/brand images

## Setup

1. Install PHP and MySQL (for example, XAMPP).
2. Put these files in your web server's document root (for XAMPP, usually `htdocs/DriveMart/`).
3. Create/import the DriveMart database and ensure the `cars` table and any other tables used by the application exist. The SQL dump was not included in the supplied ZIP.
4. Configure the following environment variables for the PHP process/server:
   - `DRIVEMART_DB_HOST`
   - `DRIVEMART_DB_NAME`
   - `DRIVEMART_DB_USER`
   - `DRIVEMART_DB_PASSWORD`
   - `DRIVEMART_ADMIN_EMAIL`
   - `DRIVEMART_ADMIN_PASSWORD`
5. Open the project through your local PHP server.

The database connection and admin login have been changed to read credentials from environment variables so real credentials are not published in this public project. Configure these values on your own machine/hosting before running the site.

## Security notes

- Never commit real database passwords, API keys, or admin credentials.
- `test.php` and the hosting provider's default `index2.html` page were excluded from this GitHub-ready package.
- Downloaded browser-cache assets under `assets/images/venue_files/` were excluded because they are unrelated to the vehicle image assets.
