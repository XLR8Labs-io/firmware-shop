# IT-Firmware.com

An online store for mobile firmware, flash files and repair solutions. Technicians top up a USD wallet with bKash, Nagad or USDT and buy individual files from it. The files are stored on Google Drive and streamed to buyers through the site.

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
4. Run `php artisan storage:link` once so uploaded folder images are served, then `composer dev` to start the app, queue worker and Vite together.
5. Register an account on the site and confirm its email address (locally, the link is written to `storage/logs/laravel.log`). Then make it an admin with `php artisan app:make-admin you@example.com`. Before the admin panel at `/admin` will open, turn on two-factor authentication under Settings → Security.

`composer test` runs Pint, PHPStan and the test suite. Tests use the `it_firmware_test` database.

## Google Drive

Files live in a Google Workspace Shared Drive and are read by a service account:

1. In Google Cloud Console, enable the Google Drive API, create a service account and download its JSON key.
2. Add the service account's email to the Shared Drive as a Viewer.
3. Put the key at `storage/app/private/google-sa.json` (or set `GOOGLE_SA_JSON` to its path) and set `GOOGLE_SHARED_DRIVE_ID`. The key is git-ignored; never put it under `public/`.

In the admin panel, **Drive imports** brings a Drive folder's folders and files into the catalogue as drafts, and a product's **Drive link or file ID** field fills in the file details from a share link. Imports run on the queue, so a queue worker must be running.

## Crypto deposits

Customers can pay in USDT on TRON (TRC20) or BNB Smart Chain (BEP20). Each deposit gets its own amount, such as 10.37 USDT, so the amount alone shows who paid.

- Set `CRYPTO_TRON_ADDRESS` and `CRYPTO_BSC_ADDRESS` to the store's own wallet addresses. Use self-custody wallets, not exchange deposit addresses. Only public addresses go here; never put a private key or seed phrase on the server. If a network has no address, its tab tells customers it isn't available.
- TRON payments are found automatically every minute through TronGrid. Get a free API key at trongrid.io and set `TRONGRID_API_KEY`. This needs the scheduler running.
- BEP20 payments are confirmed when the customer pastes their transaction hash. The hash is checked on a public BSC node (`BSC_RPC_URL`).
- `CRYPTO_MIN_USD` sets the smallest deposit.
- Payments that match no deposit, such as an underpayment, appear under **Crypto transfers** in the admin panel. An admin can assign one to a customer from there.

## Telegram alerts

Customers' deposit emails need a real mail setup (`MAIL_MAILER` and friends) and the queue worker.

Set `TELEGRAM_BOT_TOKEN` (from @BotFather) and `TELEGRAM_ADMIN_CHAT_ID` to receive admin alerts, for example when Google limits a file's downloads. Alerts are sent by the queue worker. Leave both empty to turn alerts off.

## Deployment

Not deployed yet. The target is an Ubuntu VPS with nginx, PHP-FPM, MySQL 8, Supervisor for the queue worker and a cron entry for the scheduler. Downloads stream through the server, so turn off response buffering for `/dl` (`fastcgi_buffering off` in nginx, `output_buffering` off in PHP). See PLAN.md §14 for details.
