# Firmware Store: Build Plan & Specification

> Mobile firmware / flash-file / repair-solution download store.
> Per-product pricing, wallet-based payments (bKash, Nagad, crypto USDT), files stored on Google Drive.
> Brand: **IT-Firmware.com** (it-firmware.com). The client's logos, icons and palette are in `public/` (see Deviations #18).
> Plan written: 27 Nov 2026. Research data and sources are in the Appendix.

---

## 0. Instructions for Claude Code

- Build **phase by phase** (Section 12). Finish one phase, including its tests, before starting the next. At the end of each phase, list what was done and anything you skipped.
- **Don't guess third-party APIs.** Before writing an integration (Google Drive, TronGrid, BSC JSON-RPC, PipraPay), read its current official docs or make a test call to confirm endpoints and field names. If something differs from this plan, follow the real API and note the difference here under "Deviations".
- **Money rules:** never use floats for money. Use `DECIMAL` columns and PHP `bcmath` (`bcadd`, `bccomp`, …). Wallet balances are **always** the sum of ledger rows and are never stored as one editable number.
- **Security rules:** the server must never store a crypto private key or seed phrase, only public receiving addresses. Ledger rows are never updated or deleted; corrections are new rows.
- Use the latest stable Laravel (whatever `laravel new` installs) and the latest Filament version compatible with it. PHP 8.3 or newer.
- Store times in UTC. Display them in `Asia/Dhaka`.
- If a decision isn't covered here, choose the simplest option, write it down under "Deviations", and keep going. Stop and ask only for decisions that can't be undone.

### Deviations

_(Claude Code: record changes to this plan here.)_

**27 Nov 2026: plan review, before Phase 0.** These changes have already been applied to the sections named below.

1. **Database (§1, §3, §10, §13, §14):** production uses **MySQL 8**. Local development and tests use **MariaDB** (Laravel's `mariadb` connection), not SQLite. SQLite can't run `whereFullText` and ignores `lockForUpdate`, so the search tests and the concurrency tests would prove nothing on it. Migrations must stay portable between the two (see §4 "Portability").
2. **No Redis (§3, §4, §8.3.3, §8.3.5, §11, §14):** cache, atomic locks, queue, sessions and rate limiting all use Laravel's **database** drivers. The database cache store supports `Cache::lock()`, which covers the crypto invoice lock and `withoutOverlapping()`. The database queue uses `FOR UPDATE SKIP LOCKED` on MySQL 8 and MariaDB 10.6+. The TRON scan cursor now comes from the `crypto_transfers` table instead of a cache key, so it can never be evicted.
3. **Every debit locks the user row (§4, §7.2, §7.5):** the lock and the balance check moved from `PurchaseService` into `WalletService::debit()`. Before, an admin debit or a reseller transfer running at the same time as a purchase could push a balance below zero.
4. **Buying again after a refund (§4, §7.2):** a refunded purchase row is reactivated instead of a new row being inserted. Before, the unique (`user_id`, `product_id`) key made a second purchase impossible. The refund reference is now `refund:{purchaseId}:{ledgerId}`, so a re-bought product can be refunded again.
5. **bKash/Nagad exactly-once across manual and PipraPay (§4, §8.1, §8.2):** both paths credit with the same ledger reference, `{method}:{TRXID}`, and PipraPay deposits store the TrxID from the verify response in `mfs_deposits.trx_id`. Before, the two paths used different references (`bkash:{TRXID}` and `piprapay:{payment_id}`), so the same real payment could be credited twice.
6. **Admin 2FA moved to Phase 0 (§9, §12):** admins can move money, so 2FA is enforced from the start instead of in Phase 8.
7. **Download count (§4, §6.3):** counted once per user, per product, per 24 hours. Before, it was counted once per token, and every click created a new token, so repeated clicks inflated the count.
8. **Filament version (§3):** checked on 27 Nov 2026. `filament/filament` v5.12 installs on Laravel 13 / PHP 8.5 with Livewire 4.4. The Livewire starter kit must therefore use Livewire 4.
9. **Passkeys (§1):** the starter kit's Fortify setup also enables passkeys alongside email/password and TOTP 2FA. Kept as an optional extra sign-in method for customers. Admin panel access still requires confirmed TOTP 2FA (§9).
10. **Branding (§1):** the store uses the client's own branding (name, logo, colours, favicon). No XLR8Labs marks appear on the public site or in the admin. The brand is IT-Firmware.com (see #18).

**27 Nov 2026: Phase 0.**

11. **Admin sign-in (§5.3, §9):** the Filament panel has no login page of its own (`/admin/login` doesn't exist). Admins sign in through the site's Fortify login, so its two-factor challenge always applies. Filament's built-in MFA isn't used, so each user has a single TOTP setup. The panel requires `is_admin`, a verified email and confirmed TOTP 2FA. Admins without 2FA are sent to the security settings, which tell them why.
12. **First admin:** `php artisan app:make-admin {email}` promotes an existing account. `is_admin` is never mass-assignable.
13. **Settings table (§4):** the columns are `name` and `value`, not `key`, because `KEY` is a reserved word in MySQL. Settings are read through `App\Services\SiteSettings` (one cache entry, loaded once per request) and edited on the admin Settings page. Phase 0 covers the site name, ticker text, support contact text and community links. Later phases add their own keys (rates, payment numbers, download limits) to the same page.
14. **Header and footer (§5.1):** each piece arrives in the phase that builds what it points to. The search box comes in Phase 1, the wallet balance and "Add funds" in Phase 2, and the footer page links in Phase 6. The language switcher is left out until a second language exists. All interface text already goes through `__()`.
15. **Users table (§4):** Phase 0 adds only `is_admin`. `phone`, `country`, the ban columns and the last-login columns are added by the phase that first uses them.
16. **Display timezone:** `DISPLAY_TIMEZONE` (default `Asia/Dhaka`) is used by the `<x-local-time>` Blade component on the site and by Filament (`FilamentTimezone`). Storage stays UTC.
17. **Admin colour:** brand blue `#0076D9` is the panel's primary colour (see #18).

**27 Nov 2026: client brand assets.**

18. **Brand (§1):** the client's asset pack sets the name **IT-Firmware.com** (`APP_NAME`, and the default for the `site_name` setting). In `public/` are the light and dark logos (`logos/`, WebP at 1× and 2×), the favicons, the touch icon, `site.webmanifest` with its PWA and maskable icons, and the share image `social/og-light.png`. The 2000 px masters and `source/` files were not copied. The theme is a class on `<html>`, so `<x-brand-logo>` swaps the light and dark artwork by class rather than by `prefers-color-scheme`. Of the pack's palette (navy `#071A35`, blue `#0076D9`, green `#009B65`, dark `#101827`), blue is the single accent on the site and in the admin, navy is the browser `theme-color`, and green stays in the logo only. Grey surfaces keep the neutral scale. In dark mode, text accents use a lighter tint of the blue (`#4D9FE4`) so they stay readable.

**27 Nov 2026: Phase 1, catalogue.**

19. **Folder slugs (§4):** unique across the whole site, not per parent. MySQL treats `NULL` parents as distinct, so "unique per parent" wouldn't stop two top-level folders from sharing a slug, and one slug per folder gives simple `/folders/{slug}` URLs. Collisions get `-2`, `-3` (the same applies to product slugs, soft-deleted rows included).
20. **Hidden folders (§5.1):** hiding a folder hides every folder and file inside it. `App\Services\FolderTree` caches the whole hierarchy (id, parent, visibility) as one entry, cleared on any folder change, and answers visibility, breadcrumbs and "everything under this brand" without a query per level. `Product::visible()` means published *and* in a visible folder.
21. **Search (§5.1):** each word of three or more letters or digits becomes a required prefix term in a boolean-mode FULLTEXT query (`+garn*`). Shorter words and words with punctuation (`s9`, `SM-A155F`) use an escaped `LIKE`. Only letters and digits ever reach the FULLTEXT query, so users can't inject search operators. InnoDB only indexes rows on commit, so the search tests commit their writes (`Tests\Concerns\CommitsDatabaseWrites`) and truncate afterwards.
22. **Deleting (§5.3):** products are soft-deleted only (no permanent delete), because purchases and download logs will point at them. A folder can only be deleted once it holds no folders or files (deleted files included); the database also refuses. Admins can't move a folder inside itself or its own subfolders. "Unpublish" sets the status to `hidden`; `draft` means never published.
23. **Code layout:** enums live in `app/Enums` (the Laravel convention), starting with `ProductStatus`.

**27 Nov 2026: Phase 1, Google Drive.**

24. **Drive library (§3, §6.2):** `google/auth` (the official Google auth library, 3 small packages) replaces `google/apiclient`, which ships generated clients for every Google API. `App\Services\Drive\DriveClient` calls the Drive v3 REST API with Laravel's HTTP client and passes google/auth an HTTP handler built on the same client. The service-account token request and every Drive call can therefore be faked with `Http::fake()`, and the tests use `Http::preventStrayRequests()`. The access token is cached until a minute before it expires.
25. **Shared Drive listing (§5.3, checked against the Drive v3 docs on 27 Nov 2026):** besides `supportsAllDrives=true` and `includeItemsFromAllDrives=true`, `files.list` sends `corpora=drive` with `driveId=GOOGLE_SHARED_DRIVE_ID`, since Google requires `driveId` with that corpus. Boolean parameters are sent as the string `true`. `size` comes back as a string and is missing for folders; `md5Checksum` is missing for files without binary content.
26. **Drive Import (§5.3):** an import brings the *contents* of a Drive folder into a chosen site folder (or the top level). Drive folders become site folders (matched by `drive_folder_id`) and files become draft products at the chosen default price (matched by `drive_file_id`, deleted products included, so a deleted file is never brought back). Existing folders and products are left exactly as admins edited them, and `createOrFirst` makes overlapping imports safe. Google-native items (Docs, shortcuts) are skipped and counted, as are files at the top level with no site folder to go into. Progress is saved per Drive folder in `drive_imports` and shown in the admin panel, which polls while an import is running. The job doesn't retry automatically; starting it again is safe.

**27 Nov 2026: Phase 1, public pages.**

27. **Public pages (§5.1):** the URLs are `/folders/{slug}`, `/files/{slug}`, `/search?q=&brand=`, `/recent`, `/top`, `/rss` and `/sitemap.xml`. Draft, hidden and deleted products, hidden folders, and anything inside a hidden folder return 404. Folder sorting (`newest`, `downloads`, `price`) is checked against a fixed list. Search is limited to 60 requests a minute per visitor, and its brand filter includes every folder under the brand. The home page's announcements section comes with announcements in Phase 6.
28. **Product page in Phase 1:** it shows the price and, to guests, a "Log in to download / buy" button. The Buy and Download buttons come with Phases 2 and 3. Until purchases exist, the file password is shown only for free files, and only to signed-in users. On phones the price box comes before the details.
29. **Sitemap and RSS (§5.1, §11):** both are plain Blade XML views; `spatie/laravel-sitemap` isn't installed. The sitemap is cached for an hour instead of regenerated by a daily job. One file is enough up to Google's 50,000-URL limit, after which it needs splitting into a sitemap index.
30. **Markdown (§9):** product descriptions go through `Str::markdown()` with `html_input => escape` and `allow_unsafe_links => false`, so raw HTML and `javascript:` links in a description can't run.

**27 Nov 2026: Phase 2, wallet and purchases.**

31. **Audit trail (§5.3, §9):** the append-only ledger is the record of every money action. Each admin credit, debit and refund stores the acting admin (`created_by`) and their reason (`note`, required for credits and debits). There is no separate audit-log table or package. The `LedgerEntry` model refuses updates and deletes; the database has no trigger, so the rule lives in the application.
32. **Confirmed price (§7.3):** `PurchaseService::buy()` takes the price the customer saw. If an admin changed the price before the customer confirms, nothing is charged and the new price is shown for them to confirm. Free files and anything not visible can't be bought.
33. **Exact balance:** the balance is read as `COALESCE(SUM(amount), 0)`, which comes back as an exact decimal string. Any other type throws instead of being turned into a float.
34. **Before Add funds exists (§5.1, §7.3):** the header menu shows the balance. When it's too low, the buy confirmation shows the shortfall with the site's support contact text. The "Add funds" button with the shortfall filled in comes with the deposit pages in Phases 4 and 5.
35. **What customers see:** the wallet history shows each entry's type (and the file title for purchases); admin reasons stay internal. My purchases shows the file password for active purchases.
36. **Ledger types:** `LedgerEntryType` has only the types in use (purchase, refund, admin credit, admin debit). Deposit and transfer types are added in their phases. Ledger entries and purchases are only created through the services, so they have no factories.
37. **Concurrency tests (§12 Phase 2):** one test proves `WalletService::debit()` holds the user's row lock while it reads the balance: another connection's `FOR UPDATE NOWAIT` fails at that moment. It fails if the lock is removed, which was checked. Another starts two real PHP processes that buy at the same moment against a balance that covers only one; exactly one succeeds.

**27 Nov 2026: Phase 3, downloads.**

38. **Admin alerts (§5.4):** Telegram is configured with `TELEGRAM_BOT_TOKEN` and `TELEGRAM_ADMIN_CHAT_ID` in the environment, not an `admin_telegram_chat_id` setting. Leaving them empty turns alerts off. Alerts go through a queued job (3 tries), so a slow Telegram never holds up a download. The call (`POST https://api.telegram.org/bot<token>/sendMessage` with `chat_id` and `text` of up to 4096 characters) was checked against the Bot API docs on 27 Nov 2026.
39. **Download flow (§6.3):**
    - Each click on Download creates one token and one log row. The log's status becomes `quota_exceeded` or `error` if the stream fails, and it records whether the mirror was used.
    - The daily limit counts links created in the last 24 hours, checked under the user's row lock.
    - `/dl` keeps the web middleware (an unused session cookie is harmless) and is limited to 120 requests a minute per IP, because download managers open several connections.
    - An expired link returns 404 with a message.
    - The `Range` header is passed to Drive only when it matches `bytes=…`.
    - A download is counted on a link's first use, and only once per user, file and 24 hours.
40. **Quota detection (§6.3, §6.5):** a 403 with reason `downloadQuotaExceeded` or any 429 counts as Google's per-file limit; the mirror is then tried, else the customer gets a 503 "busy" page and admins are alerted. Google's download guide (checked 27 Nov 2026) confirms `Range` on `alt=media` but doesn't document this error; the reason string comes from Google's community forum (Appendix A.4).
41. **Bans (§5.2):** "banned users can't download" waits for the ban columns, which have no phase of their own yet. Downloads already require a verified email.
42. **Not yet proven:** the plan's "multi-GB file downloads and resumes in a browser and IDM" needs the real Shared Drive credentials. Tests cover the behaviour with faked Drive responses (full and `206` range responses, mirror and busy paths), and the real check belongs in the go-live test (§13).

**27 Nov 2026: Phase 4, bKash and Nagad.**

43. **Instructions per account type (§8.1):** personal accounts get "Send Money", merchant accounts "Payment", and agent accounts "Cash Out" (the plan didn't say what agent accounts need).
44. **Amounts:** dollars are taka ÷ the rate, rounded down to the cent (`bcdiv`, scale 2). The rate is stored with 4 decimals and snapshotted on each deposit. An admin's corrected amount is converted at that snapshot. A dollar shortfall from the Buy box becomes a taka amount rounded *up*, so the deposit covers it.
45. **Deposits list (§5.2):** a customer's deposits are listed on the Add funds page, with the reason shown for rejected ones, rather than on a separate Deposits page. Crypto invoices are listed on the same page. The 5-per-hour limit counts submissions that pass validation.
46. **Admin queue (§5.3):** pending deposits show as a count badge in the admin navigation, and the queue opens filtered to pending; there's no dashboard widget yet. Approving and rejecting lock the deposit row, so two admins can't review the same deposit twice. The ledger reference `{method}:{TRXID}` makes a double credit impossible anyway.
47. **Emails (§5.4):** "deposit approved/rejected" is a queued mail notification, so production needs a real `MAIL_MAILER` and a running queue worker.

**27 Nov 2026: Phase 5 (crypto deposits).**

48. **Payment page (§8.3.4, §8.3.7):** the page is a Livewire page at `/account/crypto/{invoice}`, next to the other account pages, instead of `/wallet/crypto/{invoice}`. It refreshes itself with `wire:poll.15s` until the invoice is paid, so there is no separate `/status` endpoint. The claim is a Livewire action rather than a `POST /claim` route. It is still limited to 6 a minute, per customer rather than per invoice, so opening several invoices doesn't multiply the limit. The page shows the time the invoice stays open until, in the customer's own time zone, instead of a ticking countdown.
49. **Unmatched alerts (§8.3.8):** the Telegram alert for an unmatched transfer is sent only when the TRON scanner finds one. A failed customer claim doesn't send one, so customers can't flood the admin chat. The transfer still appears in the admin "Crypto transfers" queue and its menu badge.
50. **Assign to user (§8.3.8):** an admin assigning an unmatched transfer credits it with the same ledger reference, `{network}:{tx_hash}`, as an automatic match. A transfer can therefore never be credited twice, whether by hand or automatically.
51. **TronGrid (§8.3.5):** the endpoint, header, query parameters, `meta.fingerprint` paging and item fields match TronGrid's current docs. The scanner also skips any item whose `value` isn't a number. The plan's `Hex::toDec` used `hexdec()`, which returns a float, and accepted non-hex input. The helper now uses `intval($digit, 16)` and rejects anything that isn't hex.
52. **Before launch:** check both USDT contract addresses in `config/crypto.php` against Tether's site, tronscan.org and bscscan.com, and make one small real deposit on each network. The tests only use faked responses.

---

## 1. Project summary & locked decisions

| Topic          | Decision                                                                                                                                                                                                                                                   |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Codebase       | Built from scratch in Laravel. No paid scripts.                                                                                                                                                                                                            |
| Business model | **Per-product pricing.** Each file is a product with its own price. A price of 0 means free (login required). No bandwidth or quota packages.                                                                                                              |
| Storage        | Paid Google Workspace plan. Files live in a **Shared Drive** and are read by a **service account**.                                                                                                                                                        |
| Delivery       | Downloads are streamed through our server from the Drive API (`files.get?alt=media`), using short-lived signed links. No Google sign-in for customers. If Drive's per-file quota is hit, customer support handles it, and the admin can set a mirror file. |
| Wallet         | All payment methods add money to a **USD wallet**. All purchases are paid from the wallet.                                                                                                                                                                 |
| bKash / Nagad  | Free approach. Phase A: manual "Send Money" with the customer submitting their TrxID and an admin approving. Phase B: automation with self-hosted **PipraPay** (open source; reads payment SMS on an Android phone).                                       |
| Crypto         | **Fully free, self-built.** USDT on TRC20 (detected automatically through TronGrid) and USDT on BEP20 (customer pastes the transaction hash, verified on a public BSC node). Each deposit has a unique amount. No third-party payment processor.           |
| Customer auth  | Email and password with email verification. Optional TOTP 2FA and passkeys.                                                                                                                                                                                |
| Branding       | The client's own brand throughout (name, logo, colours, favicon). No XLR8Labs branding on the site or in the admin.                                                                                                                                       |
| Admin          | Filament panel.                                                                                                                                                                                                                                            |
| Database       | MySQL 8 in production; MariaDB for local development and tests. No Redis: cache, locks, queue and sessions use the database drivers.                                                                                                                      |

---

## 2. What we are copying (reference sites)

Reference sites: https://hello-firmware.com and https://solution-firmware.com. Both run the paid script **Real Easy Store** by joudiSoft. We copy the **features and UX**, not the code.

**Features seen on both sites:**

- A notice bar (scrolling ticker) at the top with rules and warnings. Examples: "file sharing = permanent ban", "back up Security/Persist partitions before flashing", "anti-rollback: don't downgrade", "not responsible for damage".
- Social links: Telegram channel and group, WhatsApp, Facebook group, YouTube.
- Language switcher (they offer EN/PT/TR/AR). We start with EN and keep the code ready for translation; Bangla can come later.
- Home page sections:
    - **Recent Files** (last 10, with date)
    - **Top Files** (most downloaded)
    - **Featured** cards (title, short text, date, size, "Download" or "Buy $X" button)
    - **Brand folder grid** (Samsung, Xiaomi/Poco, Vivo/iQOO, Oppo, Realme, OnePlus, Tecno, Infinix, Itel, Huawei/Honor, Nokia/HMD, Motorola, Google Pixel, Apple, Walton, Symphony, Lava/Benco, Nothing/CMF, ZTE/Nubia, Asus, LG, etc.)
    - Special folders: "CPU Drill & ISP Pinout & Test Point", "EMMC & UFS Chip Config / FFU File", "Box/Dongle/Tool Setup & Driver/DA", "Various/Other Brands"
- Folder tree browsing: brand → series/model → files. Each folder has an icon or image and a short description.
- Search by model name, model number or codename.
- File page: title, size, date, download count, description, price or Buy button, and file password (visible to buyers only).
- Menu: Home, Recent Files, Pricing (we replace this with "How to buy / Add funds"), World Agents/Resellers, Blog, Request File, Contact.
- Announcements section, RSS feed of recent files.
- Footer pages: About, Privacy, Terms, Refund Policy (solution-firmware also has Return and Cancellation policies).
- Disclaimer: the site isn't responsible for misuse or damage. Example announcement: "Restore your IMEI safely and legally, in line with your country's laws."
- Typical file names: `CPH2659GDPR_11_16.0.7.200EX01_...`, `RMX5131GDPR_...`, `PD2446F_EX_A_16.1.11.0...`, "Engineering Firmware", "Dual Security Repair", "ISP Pinout Tested", "Full UFS Dump", "Xml File F64", tool setups (Flash64/F64, EasyJTAG).
- Per-file prices seen on solution-firmware: $40 and $200 for premium solutions; many files are free.

**What we do differently:** per-product prices instead of packages; bKash, Nagad and crypto wallet top-ups (the reference sites only show Visa/Mastercard/PayPal/Skrill logos, and hello-firmware tells buyers to "contact your reseller").

---

## 3. Tech stack

- **Laravel** (latest), **PHP 8.3+**.
- **Database:** **MySQL 8** in production (`DB_CONNECTION=mysql`), **MariaDB** locally and for tests (`DB_CONNECTION=mariadb`). Never SQLite (see Deviations #1).
- **No Redis.** Use the database drivers: `CACHE_STORE=database` (also provides `Cache::lock()` atomic locks and rate limiting), `QUEUE_CONNECTION=database` (worker under Supervisor), `SESSION_DRIVER=database`.
- **Filament** v5 (v5.12 checked; requires Livewire 4) for the admin panel.
- **Frontend:** Blade + Livewire + Tailwind (from Laravel's official Livewire starter kit). Mobile-first, because many technicians browse on phones.
- **Auth:** the starter kit's auth, plus Laravel Fortify's two-factor features for optional TOTP.
- **Packages:**
    - `google/apiclient` – Drive API (service account)
    - `simplesoftwareio/simple-qrcode` – QR codes for crypto addresses (or `bacon/bacon-qr-code`)
    - `spatie/laravel-sitemap` – sitemap
    - Optional: `spatie/laravel-activitylog` – audit log for admin actions
- PHP extensions: `bcmath`, `intl`, `gmp` (optional), `curl`, `pdo_mysql`.
- **Tests:** Pest. Use `Http::fake()` for every external API. Tests run against a separate MariaDB database (e.g. `it_firmware_test`) set in `phpunit.xml`, with `RefreshDatabase`.

---

## 4. Data model

All money columns are `DECIMAL(18,2)` in USD unless noted.

**Portability (MySQL 8 ↔ MariaDB):** use only schema-builder features that behave the same on both. That means no functional or expression indexes, no `CHECK` constraints that one side ignores, no generated columns, and no MySQL-only JSON functions in queries (a `json` column itself is fine). FULLTEXT and `lockForUpdate` work on both. Run the full test suite once against MySQL 8 before go-live.

### users

Starter-kit columns, plus:
`phone` (nullable), `country` (nullable), `is_admin` (bool), `is_banned` (bool), `ban_reason` (nullable), `two_factor_*` (Fortify), `last_login_ip`, `last_login_at`.

### folders (category tree)

`id`, `parent_id` (nullable, self FK), `name`, `slug` (unique per parent), `description` (nullable), `image_path` (nullable), `sort_order` (int), `is_visible` (bool), `drive_folder_id` (nullable; set when imported from Drive), timestamps.
Index: (`parent_id`, `sort_order`).

### products (one product = one downloadable file)

| column                   | notes                                                                                                           |
| ------------------------ | --------------------------------------------------------------------------------------------------------------- |
| id                       |                                                                                                                 |
| folder_id                | FK folders                                                                                                      |
| title                    | e.g. "Redmi Note 13 Pro (Garnet) Engineering Firmware V2.0"                                                     |
| slug                     | unique                                                                                                          |
| model_names              | text; comma-separated models, model numbers, codenames for search, e.g. "Redmi Note 13 Pro, 2312DRA50G, garnet" |
| short_description        | nullable, shown on cards                                                                                        |
| description              | longtext, Markdown                                                                                              |
| price                    | DECIMAL(18,2), 0 = free                                                                                         |
| is_featured              | bool                                                                                                            |
| status                   | enum: draft, published, hidden                                                                                  |
| drive_file_id            | string, required                                                                                                |
| mirror_drive_file_id     | string, nullable (fallback when quota is exceeded)                                                              |
| file_name                | from Drive metadata                                                                                             |
| file_size                | BIGINT bytes, from Drive                                                                                        |
| md5                      | nullable, from Drive (`md5Checksum`)                                                                            |
| mime_type                | nullable                                                                                                        |
| file_password            | nullable, **encrypted cast**, shown only to buyers (or anyone if free)                                          |
| download_count           | unsigned int, default 0; counted once per user per product per 24 h (see §6.3)                                 |
| published_at             | nullable                                                                                                        |
| timestamps, soft deletes |                                                                                                                 |

Indexes: FULLTEXT(`title`, `model_names`), (`folder_id`, `status`), (`status`, `published_at`), (`status`, `download_count`).

### wallet_ledger (append-only)

`id`, `user_id`, `amount` (signed DECIMAL(18,2): + credit, − debit), `type` (enum-like string: `deposit_bkash`, `deposit_nagad`, `deposit_crypto`, `purchase`, `refund`, `admin_credit`, `admin_debit`, `transfer_in`, `transfer_out`), `reference` (string, e.g. `trc20:{txhash}`, `purchase:{id}`), `note` (nullable), `created_by` (nullable admin user id), timestamps.
**Unique (`type`, `reference`)**: this prevents double crediting. Balance = `SUM(amount) WHERE user_id = ?`. Index (`user_id`, `id`).
Every debit (purchase, admin debit, transfer out) goes through `WalletService::debit()`, which locks the user row first (§7.2), so balances can't go negative under concurrency.

### purchases

`id`, `user_id`, `product_id`, `price_paid`, `ledger_id` (FK wallet_ledger), `status` (active, refunded), `refunded_at` (nullable), timestamps.
Unique (`user_id`, `product_id`): one row per user and product. A refunded row is **reactivated** when the user buys again (§7.2). It is never duplicated.

### mfs_deposits (bKash / Nagad)

`id`, `user_id`, `method` (bkash, nagad), `amount_bdt` DECIMAL(18,2), `rate_bdt_per_usd` DECIMAL(10,4) (snapshot of the rate at the time), `amount_usd` DECIMAL(18,2), `sender_number` (nullable for PipraPay), `trx_id` (nullable until a PipraPay payment is verified; always set for manual), `status` (pending, approved, rejected), `source` (manual, piprapay), `gateway_payment_id` (nullable, unique), `admin_note` (nullable), `reviewed_by` (nullable), `reviewed_at` (nullable), timestamps.
**Unique (`method`, `trx_id`)** (MySQL and MariaDB allow several NULLs). One real payment can therefore exist only once, whether it came in manually or through PipraPay. Both paths credit with ledger reference `{method}:{TRXID}` (§8.1, §8.2).

### crypto_invoices

`id`, `user_id`, `network` (trc20, bep20), `base_amount`, `pay_amount` (unique cents, see §8.3), `status` (pending, paid, expired), `tx_hash` (nullable, **unique**), `received_amount` DECIMAL(24,6) nullable, `expires_at`, `paid_at` (nullable), timestamps.
Index (`network`, `status`, `pay_amount`).

### crypto_transfers (every incoming USDT transfer we have seen)

`id`, `network`, `tx_hash` (**unique**), `from_address` (nullable), `amount` DECIMAL(24,6), `block_time`, `crypto_invoice_id` (nullable FK), `admin_note` (nullable), timestamps.
Rows where `crypto_invoice_id` is null are **unmatched** and go to an admin review queue.

### download_tokens

`id`, `token` (random 40 chars, unique), `user_id`, `product_id`, `expires_at`, `ip_addresses` (json array of IPs that used the token), `uses` (int), `bytes_served` (bigint), timestamps.

### download_logs

`id`, `user_id`, `product_id`, `ip`, `user_agent`, `drive_file_used` (primary or mirror), `status` (started, quota_exceeded, error), timestamps.

### file_requests

`id`, `user_id` (nullable), `name`, `contact` (email, Telegram or WhatsApp), `brand`, `model`, `details`, `status` (open, done, rejected), `admin_reply` (nullable), timestamps.

### announcements

`id`, `title`, `body` (Markdown), `show_in_ticker` (bool), `is_published`, `published_at`, timestamps.

### articles (blog)

`id`, `title`, `slug`, `excerpt`, `body`, `cover_image`, `is_published`, `published_at`, timestamps.

### pages (CMS)

`id`, `title`, `slug` (about-us, privacy-policy, terms-of-service, refund-policy, world-agents, how-to-buy…), `body`, `is_published`, timestamps.

### resellers (World Agents)

`id`, `name`, `country`, `whatsapp`, `telegram`, `payment_methods` (text), `sort_order`, `is_active`, `user_id` (nullable; links to a user account that may transfer balance, see §7.5), timestamps.

### settings (key–value)

Keys:

- `site_name`, `ticker_text`
- `telegram_channel_url`, `telegram_group_url`, `whatsapp_number`, `facebook_url`, `youtube_url`
- `support_contact_text`
- `bdt_per_usd` (rate used for bKash/Nagad top-ups)
- `bkash_number`, `bkash_account_type` (personal, agent, merchant), `nagad_number`, `nagad_account_type`
- `mfs_min_bdt`
- `crypto_min_usd`
- `download_link_hours` (default 6), `download_max_ips_per_token` (default 3), `download_daily_limit_per_user` (default 50)
- `admin_telegram_chat_id`

Cache all settings as one entry in the database cache store (`Cache::rememberForever`), clear it when settings are saved, and load it at most once per request (a singleton settings service), so a page view costs at most one cache query.

---

## 5. Features

### 5.1 Public site

- **Header:** logo, search box (model, model number or codename), language switcher (EN only at launch), Login/Register or Account menu with wallet balance and an "Add funds" button.
- **Notice ticker** (scrolling): ticker text from settings plus announcements marked `show_in_ticker`.
- **Social links** (Telegram, WhatsApp, Facebook, YouTube) from settings.
- **Home page:**
    1. Recent Files (10)
    2. Top Files (10, by download count)
    3. Featured products (cards)
    4. Brand folder grid with images
    5. Latest announcements
- **Folder page:** breadcrumb, subfolders grid, products table (title, size, date, downloads, price or "Free"), sort options (newest, most downloaded, price), pagination.
- **Product page:**
    - Title, folder breadcrumb, size (human readable), MD5, date, download count, price, description (Markdown, sanitized)
    - Buttons:
        - Guest → "Login to download/buy"
        - Free → "Download"
        - Paid and not bought → "Buy for $X" (shows balance; if the balance is too low, offers "Add $Y funds")
        - Bought → "Download", plus the file password if one is set
    - Warnings block (backup partitions, anti-rollback, no sharing)
    - Related products from the same folder
- **Search page:** MySQL FULLTEXT on title and model names, with a LIKE fallback for codenames shorter than the FULLTEXT minimum word length. Optional filter by brand folder.
- **Recent Files page** (paginated) and **Top Files page**.
- **Request File** form (rate-limited, with a honeypot field).
- **World Agents** page (list of resellers).
- **Blog** (list and detail) and **Announcements** (list and detail).
- **Static pages** from the CMS.
- **RSS feed** of recent products (`/rss`) and a **sitemap**.
- **SEO:** slugs, meta title and description per product and folder, Open Graph tags.

### 5.2 Customer account

- Dashboard: balance, recent purchases, recent deposits.
- **Add funds** page with tabs: bKash, Nagad, Crypto (USDT TRC20), Crypto (USDT BEP20).
- **My purchases:** list of bought products, each with a Download button and file password.
- **Wallet history:** all ledger rows for the user.
- **Deposits:** status of bKash/Nagad deposits and crypto invoices.
- **Profile:** change password, enable or disable 2FA, sessions.
- Banned users can log in but can't buy or download; they see the ban reason.

### 5.3 Admin (Filament)

- **Dashboard widgets:**
    - Revenue (sum of purchases) today, 7 days, 30 days
    - Deposits today by method
    - **Pending bKash/Nagad deposits** (count, links to the queue)
    - **Unmatched crypto transfers** (count, links to the queue)
    - Top products
    - New users
- **Folders:** tree CRUD with reordering and images.
- **Products:**
    - CRUD
    - "Drive link or ID" field that accepts a full Drive URL, extracts the file ID, and fetches name, size, MD5 and mime type through the API
    - Bulk actions: publish, unpublish, feature, set price, move to folder
- **Drive Import tool** (important for adding thousands of files):
    1. Admin enters a Shared Drive folder ID.
    2. The app walks the folder recursively (`files.list`, `q = '<id>' in parents and trashed = false`, `supportsAllDrives = true`, `includeItemsFromAllDrives = true`, `pageSize = 1000`, with `nextPageToken` paging).
    3. The app creates or updates the matching `folders` rows (using `drive_folder_id`) and creates **draft** products for new files (skipping files already imported by `drive_file_id`). Default price is chosen in the form.
    4. Runs as a queued job with a progress display.
- **Users:**
    - View user: balance, ledger, purchases, deposits, download logs
    - Actions: ban/unban, **manual wallet credit/debit** (required note; creates `admin_credit`/`admin_debit` ledger row; logged)
- **bKash/Nagad deposits queue:** approve (credits the wallet) or reject (with a reason), with filters.
- **Crypto:** invoices list; **unmatched transfers** queue with an "Assign to user" action (enter user and amount to credit; creates a ledger row with reference `{network}:{tx_hash}` and links the transfer).
- **Purchases:** list, with a refund action (credits the wallet, sets the purchase to refunded, removes access).
- **Download logs:** filter by user or product, flag suspicious activity (many IPs).
- File requests, announcements, articles, pages, resellers, settings.
- Every money action by an admin is written to an audit log.

### 5.4 Notifications

Admin gets **Telegram bot** messages (free) for:

- New pending bKash/Nagad deposit
- New unmatched crypto transfer
- Drive download quota exceeded
- Download link used from too many IPs

Customers get email when:

- A deposit is approved or rejected
- A crypto deposit is credited
- A purchase is completed

---

## 6. File storage & delivery (Google Drive)

### 6.1 Google setup (manual steps for the owner, documented in README)

1. Paid Google Workspace plan. Create a **Shared Drive** (e.g. "Store Files").
2. In Google Cloud Console: create a project, enable **Google Drive API**, create a **service account**, and download its JSON key. Store the key outside the web root, e.g. `storage/app/private/google-sa.json`, and add it to `.gitignore`.
3. Add the service account's email as a member of the Shared Drive (the **Viewer** role is enough for reading and downloading; use **Content manager** only if the app will upload or move files).
4. Upload files through the Drive web app or Drive for desktop. Mirror the folder structure you want on the site, then use the admin **Drive Import** tool.

### 6.2 Drive client

- `App\Services\Drive\DriveClient` wraps `Google\Client` with scope `https://www.googleapis.com/auth/drive.readonly` (use the full `drive` scope only if uploading is added later).
- Every call passes `supportsAllDrives=true` (and `includeItemsFromAllDrives=true` for list calls).
- Methods:
    - `metadata(id)`: fields `id,name,size,md5Checksum,mimeType,parents`
    - `listChildren(folderId)`: paginated generator
    - `openStream(id, ?rangeHeader)`: returns a PSR-7 response streamed from `GET https://www.googleapis.com/drive/v3/files/{id}?alt=media&supportsAllDrives=true` with an OAuth bearer token from the service account, and passes the `Range` header through
- Use **`files.get` with `alt=media`** for downloads. Google's billing table puts this in the "Light" bucket, while `files.download` is in "Premium".
- Cache the access token until it expires.

### 6.3 Download flow

1. User clicks **Download** on the product page. This sends `POST /products/{product}/download` (requires auth, not banned, CSRF-protected).
2. Server checks that the product is published and is either free or bought (an active purchase). It also enforces `download_daily_limit_per_user`.
3. Server creates a `download_tokens` row (expires after `download_link_hours`) and a `download_logs` row, then redirects to `GET /dl/{token}/{file_name}`. This URL **doesn't need a session**, so download managers like IDM and browsers on other devices work.
4. `GET /dl/{token}/{file_name}`:
    - Token must exist and not be expired. Record the client IP; if the number of distinct IPs is greater than `download_max_ips_per_token`, return 403 and notify the admin.
    - Stream from Drive. **Support HTTP Range** requests (IDM uses multiple parallel range connections; resuming multi-GB files needs it). Pass `Range` to Drive and send back `206`, `Content-Range`, `Accept-Ranges: bytes`, `Content-Length`, `Content-Type: application/octet-stream`, and `Content-Disposition: attachment; filename="…"`.
    - Read in 1 MB chunks with `set_time_limit(0)`. Count `bytes_served`. On a token's first use, increase `products.download_count`, **unless** the same user has another token for the same product, created in the last 24 hours, that already has `uses > 0`. (Each click creates a new token, so counting per token would inflate the number.)
    - **Quota handling:** if Drive returns **403 with reason `downloadQuotaExceeded`** (or 429), try `mirror_drive_file_id` if it's set. Otherwise show a friendly page ("This file is temporarily busy on our storage. Please try again later or contact support: …"), log it with status `quota_exceeded`, and notify the admin on Telegram.
5. Rate-limit `/dl/*` per IP.

### 6.4 Server bandwidth note

Every byte passes through our server (Drive → VPS → customer). Choose a VPS with high or unmetered bandwidth. Each active download keeps one PHP-FPM worker busy, so set `pm.max_children` for the expected number of simultaneous downloads.

**Phase 8 optimization:** let nginx do the streaming. PHP authorizes the request and returns `X-Accel-Redirect` to an internal nginx location that proxies to `www.googleapis.com/drive/v3/files/{id}?alt=media`, passing the bearer token in a response header that nginx forwards as `Authorization`. This frees PHP workers. Optional; do it only if load requires.

### 6.5 Drive limits (research, see Appendix)

- Uploads: 750 GB per day per user across My Drive and Shared Drives.
- There is an **undocumented per-file download quota**. Files downloaded too often in a short time are temporarily locked with `downloadQuotaExceeded`, even through the API with a service account.
- Drive API: a 1 TiB per day per project egress limit is published but **not yet enforced**. Google says Drive API charges start **from 1 April 2027** for usage above a daily free allowance. Track monthly egress in the admin dashboard (sum of `bytes_served`) so this can be watched.

---

## 7. Wallet & purchasing

### 7.1 Currency

- Wallet and prices are in **USD**, shown as `$12.00`.
- bKash/Nagad top-ups are in BDT, converted with the `bdt_per_usd` setting.

### 7.2 WalletService (`App\Services\Wallet\WalletService`)

- `balance(int $userId): string` — returns the ledger sum as a 2-decimal string.
- `credit(userId, amount, type, reference, note, createdBy = null)` — idempotent through the unique (`type`, `reference`).
- `debit(userId, amount, type, reference, note, createdBy = null)` — **owns the balance invariant.** It must run inside a `DB::transaction` (it opens one if none is active):
    1. `User::lockForUpdate()->find($userId)`. Every debit for a user is serialized on this row lock, whoever calls it (purchase, admin debit, transfer out).
    2. Compute the balance. If `bccomp(balance, amount) < 0`, throw `InsufficientFunds` with the shortfall.
    3. Insert the ledger row with a negative amount.
- Credits don't take the lock, because adding money can't break the invariant.
- **Purchase** (`PurchaseService::buy(User, Product)`), all inside one `DB::transaction`:
    1. `User::lockForUpdate()->find($id)`. Taking the lock first also serializes the "already bought?" check; `debit()` re-locking the same row in the same transaction is a no-op.
    2. If an **active** purchase already exists for this product, return it.
    3. Debit with type `purchase` and reference `purchase:{userId}:{productId}:{uuid}` (throws `InsufficientFunds`).
    4. If a **refunded** purchase row exists for this user and product, reactivate it: `status = active`, `refunded_at = null`, new `price_paid` and `ledger_id`. Otherwise create the purchase row. The ledger keeps the full history of both purchases and the refund.
- **Refund (admin):** credit with type `refund`, reference `refund:{purchaseId}:{ledgerId}` (the ledger id of the purchase being refunded, so a re-bought and refunded-again product gets a new reference while a double refund of the same payment is still impossible). Set the purchase to `refunded`. Access is removed.

### 7.3 Buy UX

- "Buy for $X" opens a confirm modal showing the current balance and the balance after purchase.
- If funds are short, the modal offers "Add funds" with the shortfall pre-filled.

### 7.4 Free products

Price 0 means login is required, there's no purchase row, and downloads follow the same token flow.

### 7.5 Reseller balance transfer (Phase 6, optional)

A user linked to a `resellers` row can transfer balance to another user by email. This creates `transfer_out` (through `WalletService::debit()`, which locks the sender's row) and `transfer_in` ledger rows with a shared reference, inside one transaction. It works the same way as the "contact your reseller" model on the reference sites.

---

## 8. Payments

### 8.1 bKash / Nagad, Phase A: manual (works from day one)

- The "Add funds → bKash" tab shows:
    - Our number and account type from settings
    - Instruction: "Send Money" for a personal account, "Payment" for a merchant account
    - The current rate, e.g. "1 USD = 125 BDT"
    - An input for the BDT amount, with a live preview of the USD that will be credited (`amount_usd = floor(amount_bdt / rate, 2)` using bcmath)
- Form fields: amount (BDT), sender number (BD mobile format `01XXXXXXXXX`), TrxID (alphanumeric, 6–20 characters, stored uppercase).
- Validation: minimum amount `mfs_min_bdt`; TrxID **unique per method** (duplicates are rejected with "already submitted"); rate limit of 5 submissions per hour per user.
- The deposit is created as **pending**. Notify the admin on Telegram.
- Admin checks their bKash/Nagad app and approves in Filament. Approval:
    1. Credits the wallet: type `deposit_bkash`/`deposit_nagad`, reference `{method}:{TRXID}` (e.g. `bkash:9AB3CD7EF1`, `nagad:7XK2...`). PipraPay uses the same reference (§8.2).
    2. Sets the deposit to `approved`.
    3. Emails the user.
- The admin can edit `amount_bdt` before approving if the customer sent a different amount. The USD amount is then recalculated with the **stored rate snapshot**.

### 8.2 bKash / Nagad, Phase B: automated with PipraPay (free, self-hosted)

- **What PipraPay is:** an open-source (AGPL-3.0) self-hosted payment automation system with a plugin system. It supports bKash, Nagad, Rocket, Upay and more, verifies payments from SMS using its **Android companion app**, and offers a REST API, webhooks and a Laravel SDK.
    - Repos: https://github.com/PipraPay (org), PipraPay-V2 (web), PipraPay-V2-App (Android), laravel-sdk-piprapay-gateway.
- **Deployment:** install PipraPay separately on a subdomain (e.g. `pay.example.com`) on the same VPS. Put the SIMs holding the bKash/Nagad accounts in an Android phone running the companion app. **We don't modify PipraPay's code** (AGPL obligations apply to modifications of PipraPay itself).
- **Integration (Claude Code: read PipraPay's current docs and Laravel SDK first; don't guess endpoints):**
    - `App\Payments\PipraPayGateway`: create a payment for a pending `mfs_deposits` row (`source = piprapay`), then redirect the user to the PipraPay checkout.
    - Webhook route `POST /webhooks/piprapay`: excluded from CSRF and protected by the shared secret or signature PipraPay provides. **Never credit from the webhook body alone.** Always call PipraPay's verify endpoint server-to-server and check that amount and status match.
    - **Exactly once across both paths.** Take the real bKash/Nagad TrxID from the verify response (confirm the field name in PipraPay's docs or a live response), uppercase it, and in one transaction:
        1. Lock the deposit row. If it's already approved, stop (repeat webhook or redirect).
        2. If **another** `mfs_deposits` row already has this (`method`, `trx_id`), for example because the customer also submitted it manually, don't credit. Mark this row `rejected` with the note "duplicate of deposit #N" and alert the admin on Telegram.
        3. Otherwise set `trx_id`, approve, and credit with ledger reference `{method}:{TRXID}`, the same reference the manual path uses. The unique (`type`, `reference`) on the ledger is the final guard.
    - If PipraPay's verify response does **not** return the TrxID, stop and ask the owner before going further, because exactly-once across both paths can't then be guaranteed automatically. Record the outcome under Deviations.
    - Also handle the user's return redirect by re-verifying the same way.
- Keep Phase A manual approval as a fallback. Both can be active at once.
- **Notes from research:**
    - Personal bKash/Nagad accounts have transaction limits, and their terms limit business use. Check these before scaling.
    - The official **bKash merchant payment gateway** (tokenized checkout API) requires an NID, a valid trade license, a bank account and a website. Once a trade license is obtained, add an official gateway adapter. Free Laravel/PHP libraries exist (e.g. `unipay/unipay-bd` covers bKash tokenized, Nagad RSA, Rocket, Upay, SSLCommerz).
    - A paid alternative to PipraPay is UddoktaPay (automation with personal accounts).

### 8.3 Crypto deposits: fully free, self-built (USDT)

#### 8.3.1 Overview

- Networks: **USDT TRC20** (TRON) and **USDT BEP20** (BNB Smart Chain).
- One **self-custody receiving address per network** (e.g. Trust Wallet or TronLink; **not** an exchange deposit address). Only the public addresses go in `.env`. **No private keys or seed phrases on the server, ever.**
- **Unique-amount method:** a user who wants to deposit $10 is asked to send, for example, **10.37 USDT**. That `pay_amount` is reserved for this invoice, so the amount alone identifies who paid. The user is credited the full `pay_amount`.
- Detection:
    - **TRC20:** automatic polling of TronGrid every minute (free API key required).
    - **BEP20:** the user submits the transaction hash, which is verified on a public BSC JSON-RPC node.
- **Why BSC can't be automatic for free:** the official public BSC mainnet nodes **disable `eth_getLogs`**, and Etherscan API V2 (which replaced BscScan) has **no free tier for BNB Chain** (paid from $49/month). `eth_getTransactionReceipt`, `eth_blockNumber` and `eth_getBlockByNumber` still work on the public nodes, so verifying a single hash is free.

#### 8.3.2 Config (`config/crypto.php`)

```php
return [
    'min_deposit'         => env('CRYPTO_MIN_USD', 5),
    'invoice_ttl_minutes' => 60,   // payment page shows "open" for this long
    'reserve_hours'       => 24,   // a pay_amount is not reused for this long; late payments still match
    'networks' => [
        'trc20' => [
            'label'    => 'USDT (TRC20 / TRON)',
            'address'  => env('CRYPTO_TRON_ADDRESS'),               // base58, starts with T
            'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',     // official USDT on TRON, VERIFY before launch
            'decimals' => 6,
            'api'      => 'https://api.trongrid.io',
            'api_key'  => env('TRONGRID_API_KEY'),
        ],
        'bep20' => [
            'label'         => 'USDT (BEP20 / BNB Smart Chain)',
            'address'       => strtolower((string) env('CRYPTO_BSC_ADDRESS')),
            'contract'      => '0x55d398326f99059ff775485246999027b3197955', // official USDT on BSC, VERIFY before launch
            'decimals'      => 18,
            'rpc'           => env('BSC_RPC_URL', 'https://bsc-dataseed.bnbchain.org'),
            'confirmations' => 15,
        ],
    ],
];
```

Before launch, check both contract addresses against Tether's official site, tronscan.org and bscscan.com. Fake tokens also use the name "USDT".

#### 8.3.3 Creating an invoice (`App\Services\Crypto\InvoiceService::create(User, network, baseAmount)`)

1. Validate: the network exists in config; `baseAmount` is ≥ `min_deposit` and has at most 2 decimals.
2. Take `Cache::lock("crypto-invoice-{network}", 10)->block(5, …)` (database cache store lock; the `array` store in tests also supports locks) so two users can't get the same amount at once.
3. `taken` = the `pay_amount` values of invoices on this network with `status IN (pending, expired)` and `created_at >= now - reserve_hours`. (Paid invoices free up their amount.)
4. Shuffle the offsets 1..99 cents. Choose the first `pay = base + cents/100` that isn't in `taken`.
5. If none is free, throw: "Too many open deposits for this amount; try a slightly different amount." (To scale later, switch to 3 decimals for 999 slots per base amount.)
6. Create the invoice with `status = pending` and `expires_at = now + invoice_ttl_minutes`.

#### 8.3.4 Payment page (`/wallet/crypto/{invoice}`; owner only)

- Shows:
    - Network name in large text, receiving address with a copy button, QR code of the address
    - **"Amount that must ARRIVE: 10.37 USDT"** with a copy button
    - A countdown to `expires_at`
- Warnings:
    - "Send USDT on {network} only. Sending on the wrong network or a different token will be lost."
    - "If you send from an exchange (Binance etc.), add the exchange's withdrawal fee so that exactly 10.37 USDT arrives."
    - "Paying less won't be detected automatically. Contact support with your TxID."
- A **"I have paid: enter TxID/hash"** form (required for BEP20, optional speed-up for TRC20).
- Polls `GET /wallet/crypto/{invoice}/status` every 15 s. When paid, it shows success and the new balance.
- After `expires_at`, the page says "expired; if you already paid, submit your TxID". Payments within `reserve_hours` are still matched.

#### 8.3.5 TRC20 automatic scanner (`App\Services\Crypto\TronScanner::scan()`, scheduled every minute)

- Request:
  `GET {api}/v1/accounts/{address}/transactions/trc20`
    - Header: `TRON-PRO-API-KEY: {api_key}`
    - Query: `only_to=true`, `only_confirmed=true`, `contract_address={contract}`, `min_timestamp={cursor_ms - 300000}`, `order_by=block_timestamp,asc`, `limit=200`
    - Paging: repeat while `meta.fingerprint` is present, passing `fingerprint={value}`
- Expected item shape (**confirm against a live response**):

```json
{
    "transaction_id": "64-hex",
    "token_info": {
        "symbol": "USDT",
        "address": "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",
        "decimals": 6,
        "name": "Tether USD"
    },
    "block_timestamp": 1764220000000,
    "from": "T...",
    "to": "T...",
    "type": "Transfer",
    "value": "10370000"
}
```

- For each item:
    - Skip unless `to === our address`, `token_info.address === contract`, and `type === "Transfer"`.
    - `amount = bcdiv(value, 10^decimals, 6)`.
    - `CryptoTransfer::firstOrCreate(['tx_hash' => lowercase(transaction_id)], [...])`. Only **newly created** rows go to the matcher.
- Cursor: `MAX(block_time)` of `crypto_transfers` rows with `network = trc20` (in ms), or `now - reserve_hours` if there are none. It's derived from stored data, so it can't be lost or evicted. The 5-minute overlap in `min_timestamp` plus `firstOrCreate` on `tx_hash` make re-reading old items harmless.
- Rate limits: TronGrid can return 429/403. Back off exponentially, never run two scans at once (`withoutOverlapping`), and log failures.

#### 8.3.6 BEP20 hash verification (`App\Services\Crypto\BscVerifier::verify(hash)`)

Returns `['status' => ok|pending|invalid, 'amount', 'from', 'time']`.

1. The hash must match `/^0x[0-9a-fA-F]{64}$/`. Lowercase it.
2. JSON-RPC `eth_getTransactionReceipt([hash])`. If null, return pending ("not found yet"). If `status !== "0x1"`, return invalid.
3. `eth_blockNumber`. If `head - receipt.blockNumber < confirmations`, return pending.
4. For each log in `receipt.logs`:
    - `log.address` (lowercased) must equal the contract
    - `topics[0]` must equal `0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef` (Transfer event)
    - `to = '0x' . substr(topics[2], -40)` must equal our address (compare lowercase)
    - `from = '0x' . substr(topics[1], -40)`
    - Sum `Hex::toDec(log.data)`
      If the sum is 0, return invalid.
5. `eth_getBlockByNumber([receipt.blockNumber, false])` and take `timestamp` (hex seconds) for `time`.
6. `amount = bcdiv(sum, 10^18, 6)`.
7. Each RPC call: `Http::timeout(15)->retry(2, 500)->post(rpc, {jsonrpc:"2.0", id:1, method, params})`. Throw on an `error` field.

- **Do not use `eth_getLogs`**; it's disabled on the official public nodes.

Hex helper (bcmath; uint256 values don't fit PHP integers):

```php
final class Hex {
    public static function toDec(string $hex): string {
        if (str_starts_with($hex, '0x')) $hex = substr($hex, 2);
        $hex = ltrim($hex, '0');
        if ($hex === '') return '0';
        $dec = '0';
        foreach (str_split($hex) as $ch) $dec = bcadd(bcmul($dec, '16'), (string) hexdec($ch));
        return $dec;
    }
}
```

#### 8.3.7 Claim endpoint (`POST /wallet/crypto/{invoice}/claim`, throttle 6/min, owner only)

- **BEP20:** run `BscVerifier`.
    - pending → "Waiting for confirmations, try again in a minute."
    - invalid → "Not a USDT BEP20 payment to our address."
    - ok → `firstOrCreate` a `crypto_transfers` row, then call the matcher **restricted to this invoice id**.
- **TRC20:** run `TronScanner::scan()`, find the unmatched transfer by hash, then call the matcher restricted to this invoice id.
- If no match: "The amount or time doesn't match this deposit. Contact support with your TxID." The transfer stays in the unmatched admin queue.

#### 8.3.8 Matcher (`App\Services\Crypto\InvoiceMatcher::match(network, txHash, amount, blockTime, ?onlyInvoiceId)`)

Inside `DB::transaction`:

1. If any invoice already has this `tx_hash`, return null (a hash can only be used once).
2. Query:

```php
CryptoInvoice::where('network', $network)
  ->whereIn('status', ['pending', 'expired'])
  ->where('created_at', '<=', $blockTime->copy()->addMinutes(2))          // clock-skew slack
  ->where('created_at', '>=', $blockTime->copy()->subHours(config('crypto.reserve_hours')))
  ->where('pay_amount', '<=', $amount)                                     // pay <= received
  ->where('pay_amount', '>', bcsub($amount, '0.01', 6))                    // received < pay + 0.01
  ->when($onlyInvoiceId, fn ($q) => $q->whereKey($onlyInvoiceId))
  ->lockForUpdate()->orderByDesc('id')->first();
```

3. If found:
    - Update the invoice: `status = paid`, `tx_hash`, `received_amount`, `paid_at`
    - `WalletService::credit(invoice.user_id, invoice.pay_amount, 'deposit_crypto', "{network}:{txHash}", "Crypto invoice #{id}")`
    - Link the `crypto_transfers` row to the invoice
    - Email the user
4. If not found, return null, and send an admin Telegram alert for a new unmatched transfer.

**Security properties (must be covered by tests):**

- The credit always goes to the **invoice owner**, never to whoever submitted the hash. Someone copying a hash from a block explorer gains nothing, because their own invoice has a different `pay_amount`.
- Fake tokens are rejected (contract address check).
- The same tx can't be credited twice (unique `tx_hash` on invoices + ledger reference uniqueness).
- A payment made before an invoice existed can't match it.
- Underpayments and big overpayments are never auto-matched; they go to admin review.

#### 8.3.9 Scheduling (`routes/console.php`)

```php
Schedule::call(fn () => app(\App\Services\Crypto\TronScanner::class)->scan())
    ->everyMinute()->name('crypto-tron-scan')->withoutOverlapping();
Schedule::call(fn () => \App\Models\CryptoInvoice::where('status', 'pending')
    ->where('expires_at', '<', now())->update(['status' => 'expired']))
    ->everyFiveMinutes()->name('crypto-expire');
```

Server cron: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

`withoutOverlapping()` uses a cache lock, which the database cache store provides.

---

## 9. Security & anti-abuse

- **Accounts:** email verification required before buying or downloading. Optional TOTP 2FA. Login throttling. Admin panel requires 2FA for admins, **enforced from Phase 0**: an admin without confirmed 2FA is sent to 2FA setup and can't open the panel.
- **Admin:** Filament is limited to `is_admin` users; consider an IP allowlist.
- **Rate limits:** login, register, file request, deposit submit, crypto claim, `/dl/*`.
- **Downloads:** signed tokens with short expiry, a distinct-IP limit, a daily per-user limit, and logs. Drive file IDs are never shown to customers.
- **Data:** file passwords are encrypted at rest. The service account JSON stays outside the web root. `.env` is never committed.
- **Money:** append-only ledger, unique references, row locks on purchase, bcmath everywhere.
- **Webhooks:** verify signatures, re-verify server-to-server, idempotent.
- **Content:**
    - Markdown is sanitized (no raw HTML from admins without escaping, or use an allowlist).
    - Rules: don't list cracked or pirated software (e.g. "activators") or locks/bypasses for devices that are stolen or still under financing. Those are the files that get payment accounts frozen and domains taken down.
- **Backups:** daily database backup (e.g. `spatie/laravel-backup` to Drive or another location).
- **Disclaimers:** the ticker and product page carry the "not responsible for damage / back up partitions / anti-rollback / no sharing" warnings.

---

## 10. Environment variables (`.env.example`)

```env
APP_NAME="IT-Firmware.com"
APP_TIMEZONE=UTC
DISPLAY_TIMEZONE=Asia/Dhaka

# Production: mysql (MySQL 8). Local: mariadb.
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=it_firmware
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

GOOGLE_SA_JSON=storage/app/private/google-sa.json
GOOGLE_SHARED_DRIVE_ID=

CRYPTO_TRON_ADDRESS=
CRYPTO_BSC_ADDRESS=
TRONGRID_API_KEY=
BSC_RPC_URL=https://bsc-dataseed.bnbchain.org
CRYPTO_MIN_USD=5

PIPRAPAY_BASE_URL=
PIPRAPAY_API_KEY=
PIPRAPAY_WEBHOOK_SECRET=

TELEGRAM_BOT_TOKEN=
TELEGRAM_ADMIN_CHAT_ID=
```

---

## 11. Scheduled jobs & queues

- Every minute: TRON scan.
- Every 5 min: expire crypto invoices.
- Hourly: delete download tokens older than 7 days.
- Daily: database backup; refresh the top-files cache; regenerate the sitemap.
- Queue (database driver + Supervisor running `php artisan queue:work`): Drive import, emails, Telegram notifications.

---

## 12. Build phases & acceptance criteria

**Phase 0 – Foundation**

- Project created from the Livewire starter kit. Filament v5. MariaDB for local and test databases (`phpunit.xml` points at the test database; no SQLite). Database drivers for cache, queue and sessions. Settings system (cached), base layout (header, ticker, footer, social links), `is_admin` gate, **admin 2FA enforcement**, display timezone helper.
- ✅ Admin can log into Filament only after confirming 2FA. A non-admin gets 403 on the panel. Settings save and show on the site.

**Phase 1 – Catalog & Drive metadata**

- Migrations and models for folders and products. Filament resources. DriveClient metadata and list. "Paste Drive link" autofill. Drive Import job (recursive, idempotent). Public pages: home sections, folder browse, product page, search, recent, top. SEO meta, sitemap, RSS.
- ✅ Importing a Drive folder creates the folder tree and draft products. Published products are browsable and searchable by model or codename.

**Phase 2 – Wallet & purchases**

- `wallet_ledger`, WalletService, PurchaseService, buy modal, insufficient-funds flow, My Purchases, Wallet History. Admin: manual credit/debit (audited), refunds.
- ✅ Tests cover concurrent purchases (no negative balance), a purchase and an admin debit running together (no negative balance), double-buy prevention, refunds, buying again after a refund, a double refund being impossible, and ledger sums.

**Phase 3 – Downloads**

- Download tokens, `/dl` streaming with Range/206 support, IP limit, daily limit, logs, quota-exceeded handling with mirror fallback, Telegram alert, `bytes_served` tracking and an admin egress widget.
- ✅ A multi-GB file downloads and resumes in a browser and in IDM. An unauthorized user gets 403. Exceeding the IP limit gets 403.

**Phase 4 – bKash / Nagad manual deposits**

- `mfs_deposits`, Add-funds tabs, BDT→USD preview, TrxID uniqueness, admin approve/reject queue, emails, Telegram alerts.
- ✅ Approving credits exactly once. A duplicate TrxID is rejected. The rate snapshot is respected.

**Phase 5 – Crypto deposits**

- Config, invoices, payment page with QR and polling, TronScanner + schedule, BscVerifier, claim endpoint, matcher, unmatched queue with "Assign to user".
- ✅ Tests with `Http::fake` for: exact match; overpay by under 1 cent; underpay → unmatched; a late payment within 24 h matches an expired invoice; duplicate hash ignored; fake-token contract ignored; a hash claimed by another user doesn't credit them; BEP20 pending confirmations.

**Phase 6 – Content & community**

- Announcements and ticker, blog, CMS pages (about, privacy, terms, refund, how-to-buy), file requests (public form + admin), World Agents page, optional reseller balance transfer.
- ✅ All pages are editable in the admin panel.

**Phase 7 – PipraPay automation**

- Read the PipraPay docs and SDK. Gateway adapter, checkout redirect, webhook with server-to-server verify, idempotent credit. Manual flow stays available.
- ✅ A real small payment (e.g. 10 BDT) credits the wallet automatically, exactly once. Tests (with `Http::fake`): a repeated webhook credits once; a forged webhook with a failing verify credits nothing; the same TrxID submitted manually and through PipraPay (in either order) credits once.

**Phase 8 – Hardening & performance**

- Optional nginx `X-Accel-Redirect` streaming, caching of heavy queries, backups, error monitoring, load test of `/dl`, one full test-suite run against MySQL 8.

---

## 13. Testing checklist

- Feature tests for every phase's acceptance criteria (above).
- Unit tests: `Hex::toDec` (large values), BDT→USD rounding, unique-amount allocation (no collisions under the lock; error when all 99 slots are used).
- No test may call a real external API. Use `Http::fake` and a fake DriveClient.
- Tests run on MariaDB, never SQLite (FULLTEXT and row locks must be real). Before go-live, run the whole suite once against MySQL 8.
- Manual go-live test: one real $1–2 USDT deposit on each network, one real bKash and one real Nagad deposit, one multi-GB download.

---

## 14. Deployment notes

- Ubuntu VPS with **high or unmetered bandwidth** (downloads stream through it), nginx, PHP-FPM 8.3+, MySQL 8, Supervisor (database queue worker), cron (`schedule:run`), Let's Encrypt SSL. No Redis.
- PHP: `memory_limit` 256M, `max_execution_time` 0 for the `/dl` route (`set_time_limit(0)` in code), `output_buffering` off for streaming. nginx: `proxy_buffering off` / `fastcgi_buffering off` on `/dl`.
- PipraPay on a subdomain of the same VPS (Phase 7).
- Back up `.env` and the service-account JSON separately and securely.

---

## 15. Defaults chosen (change if needed)

| Item                                          | Default                                                    |
| --------------------------------------------- | ---------------------------------------------------------- |
| Wallet currency                               | USD                                                        |
| Download link validity                        | 6 hours                                                    |
| Max distinct IPs per download link            | 3                                                          |
| Daily downloads per user                      | 50                                                         |
| Crypto minimum deposit                        | $5                                                         |
| Crypto invoice open time / amount reservation | 60 min / 24 h                                              |
| BEP20 confirmations                           | 15                                                         |
| Languages at launch                           | English (code ready for translation)                       |
| Cart                                          | None. One-click "Buy" from wallet (a cart can come later). |
| Refunds                                       | Admin-initiated only, to wallet balance                    |

---

## Appendix A – Research data (collected 27 Nov 2026)

### A.1 Reference sites

- hello-firmware.com and solution-firmware.com both show "Powered by Real Easy Store (joudiSoft ltd.)" in the footer.
- **Real Easy Store** (joudiSoft) is a PHP/MySQL script, **ionCube-encoded**. Requirements: PHP 7.1+, latest ionCube Loader, cURL.
    - Lifetime single-domain licenses: AIO $280, Ultimate $179, Shop $149, Downloads $149, Shop-Light $120, Downloads-Light $120.
    - Features: sell digital downloads; sell download/bandwidth/quota packages; wholesale and user-group pricing; coupons; blog; announcements; multilingual; API.
    - Built-in gateways: 2Checkout, FirstData Payeezy, MasterCard MIGS, MasterCard MPGS, PayPal Express, Skrill, Stripe, ZarinPal (online); Bank Deposit, Cash, Western Union (offline); Balance, Free Checkout (in-store). **No bKash, Nagad or crypto.** The developer docs pages for custom gateways are empty.
- hello-firmware packages (we are NOT copying this model):

    | Package          | Price | Period   | Bandwidth / files   | Fair usage               | Devices |
    | ---------------- | ----- | -------- | ------------------- | ------------------------ | ------- |
    | One Premium File | $2    | 1 day    | 50 GB / 1 file      | –                        | 1 PC    |
    | Silver           | $20   | 3 months | 165 GB / 25 files   | 30 GB / 2 files per 20 h | 5 PCs   |
    | Gold             | $35   | 6 months | 420 GB / 150 files  | 35 GB / 4 files per 20 h | 15 PCs  |
    | Premium          | $60   | 1 year   | 900 GB / 300 files  | 50 GB / 5 files per 20 h | 20 PCs  |
    | Ultra Premium    | $80   | 1 year   | 1.04 TB / 400 files | 65 GB / 6 files per 20 h | 25 PCs  |
    | Fan Edition      | $100  | 1 year   | 1.75 TB / 500 files | 80 GB / 7 files per 20 h | 25 PCs  |

- hello-firmware: "Auto-purchase disabled; contact your reseller"; trial accounts without a purchase are auto-deleted after 24 h; announces Alipay/WeChat Pay; recommends a VPN for slow FTP/Mediafire links; hosts a help file about Google Drive "download quota exceeded".
- solution-firmware: shows a "0 Bytes / 5.00 GB" download usage meter; per-file prices like $40 and $200; folders with custom images.

### A.2 Bangladeshi payments

- bKash's online-business application asks for an NID, a valid trade license, a bank account and a website/app. bKash offers a payment gateway, APIs, tokenized checkout and refunds.
- Other BD gateways (e.g. shurjoPay) also require a trade license, TIN, business bank account and NID.
- **PipraPay**: open source, AGPL-3.0, self-hosted, plugin-based. Supports bKash, Nagad, Rocket, Upay, SureCash and more. SMS verification engine with an Android companion app (on Play Store). REST API, webhooks, Laravel SDK, WHMCS/WooCommerce/FOSSBilling plugins. Repos: github.com/PipraPay.
- **UddoktaPay**: paid; automates payments to personal bKash/Nagad/Rocket/Upay accounts and verifies each payment server-to-server before marking it paid.
- Free libraries for the official APIs: `unipay/unipay-bd` (bKash PGW tokenized v1.2, Nagad RSA, Rocket, Upay, CellFin, SSLCommerz, shurjoPay), `devrkb21/bkash-pgw-laravel` (bKash Tokenized Checkout, MIT).

### A.3 Crypto

- TronGrid V1 API gives pre-indexed TRC-20 transfer history per account. Production use needs an API key; requests without one may be heavily rate-limited or rejected. Rate limits return 429/403, and TronGrid recommends using cursors to query only new ranges.
- BscScan API is deprecated and replaced by Etherscan API V2. Etherscan V2 has **no free tier for BNB Chain** (paid plans from $49/month; a cheaper "Lite" plan exists).
- The official public BSC endpoints (`bsc-dataseed.bnbchain.org`) **disable `eth_getLogs`** and are rate-limited to about 10,000 requests per 5 minutes.
- Paid alternative not used: NOWPayments (about 0.5% fee per transaction, REST API with PHP examples).
- Regulatory note: Bangladesh Bank restricts the buying, selling, exchange and transfer of virtual currencies and has reaffirmed this in its consolidated framework. Virtual currencies are not recognised as legal currency under the Foreign Exchange Regulation Act 1947. (The owner has accepted this risk.)

### A.4 Google Drive

- Uploads: 750 GB per day per user across My Drive and Shared Drives. Maximum file size 5 TB.
- Undocumented per-file download quota → `403 downloadQuotaExceeded`. This also affects service accounts and API calls.
- Drive API limits: 1,000,000 quota units per minute per project; 1 TiB per day per project egress (**not yet enforced**). **Charges from 1 April 2027** above a daily no-charge allowance. `files.get` with `alt=media` is in the "Light" billing bucket; `files.download` is "Premium".

### A.5 Sources

- https://hello-firmware.com/ and https://hello-firmware.com?a=downloads&b=packages
- https://solution-firmware.com/
- https://joudisoft.com/products/real-easy-store/
- https://joudisoft.com/licenses/real-easy-store/
- https://docs.joudisoft.com/real-easy-store/?path=user/system/payment-gateways
- https://knowledge.antom.com/bkash-payment-guide-psp
- https://shurjopay.com.bd/
- https://github.com/PipraPay and https://github.com/mdabdullahproplayer/PipraPay
- https://wordpress.org/plugins/uddoktapay-gateway
- https://packagist.org/packages/unipay/unipay-bd
- https://packagist.org/packages/devrkb21/bkash-pgw-laravel
- https://developers.tron.network/reference/trongrid-v1-api-overview.md
- https://developers.tron.network/reference-link/rate-limits
- https://www.bnbchain.org/en/blog/migration-guide-bscscan-api-to-bsctrace-api-via-meganode
- https://finance.yahoo.com/news/ethereum-explorer-etherscan-locks-avalanche-143215634.html
- https://chainstack.com/learn/how-to/how-to-how-to-get-bnb-rpc-endpoint-defi-2026/
- https://coingape.com/nowpayments-review/
- https://en.banking.gurukul.edu.bd/bangladesh-bank-reaffirms-restrictions-on-cryptocurrency-trading/
- https://developers.google.com/drive/api/v3/limits
- https://developers.google.com/drive/api/guides/limits
- https://discuss.google.dev/t/download-quota-exceeded-for-drive-files-using-api-and-service-account/145528
