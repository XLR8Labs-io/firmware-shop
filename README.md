# Firmware store

An online store for mobile firmware, flash files and repair solutions. Technicians top up a USD wallet with bKash, Nagad or USDT and buy individual files from it. The files are stored on Google Drive and streamed to buyers through the site. "Firmware store" is a working name; the client's brand replaces it before launch.

The full specification and build plan are in [docs/PLAN.md](docs/PLAN.md).

## Stack

Laravel 13, Livewire 4 with Flux, Fortify (email verification, two-factor codes, passkeys), Tailwind CSS 4, Pest. MariaDB for local development, MySQL 8 in CI and production. No Redis: cache, queue and sessions use the database.

## Running it locally

You need PHP 8.3 or newer with the `bcmath`, `intl` and `pdo_mysql` extensions, Composer, Node 22 and MariaDB.

1. Create the databases and a user (run as a MariaDB admin, e.g. `sudo mariadb`):

    ```sql
    CREATE DATABASE it_firmware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE it_firmware_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'it_firmware'@'localhost' IDENTIFIED BY 'choose-a-password';
    GRANT ALL PRIVILEGES ON it_firmware.* TO 'it_firmware'@'localhost';
    GRANT ALL PRIVILEGES ON it_firmware_test.* TO 'it_firmware'@'localhost';
    ```

2. Copy `.env.example` to `.env` and set `DB_PASSWORD`.
3. Run `composer setup`. It installs dependencies, generates the app key, runs the migrations and builds the frontend.
4. Run `composer dev` to start the app, queue worker and Vite together.

`composer test` runs Pint, PHPStan and the test suite. Tests use the `it_firmware_test` database.

## Deployment

Not deployed yet. The target is an Ubuntu VPS with nginx, PHP-FPM, MySQL 8, Supervisor for the queue worker and a cron entry for the scheduler. See PLAN.md §14 for details.
