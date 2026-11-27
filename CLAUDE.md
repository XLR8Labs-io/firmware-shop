# Project instructions

This is a mobile-firmware download store built for a client. The full specification, decisions, build phases and research are in **docs/PLAN.md**. Read it completely before writing any code. The ground rules in AGENTS.md apply as well.

- Build strictly phase by phase (PLAN.md §12). Finish each phase, with its tests, before moving on. At the end of a phase, list what was done and anything skipped.
- Follow the money rules (DECIMAL columns, bcmath, append-only ledger, every debit through `WalletService::debit()`) and the security rules (no private keys or seed phrases, never trust webhook bodies) in PLAN.md §0, §7 and §9.
- Never guess third-party API endpoints or fields. Check the official docs or a live response first, and record any differences under "Deviations" in PLAN.md.
- Use `Http::fake()` in tests. No test may hit a real external API.
- Databases: MariaDB locally (`it_firmware`) and for tests (`it_firmware_test`), MySQL 8 in CI and production. Never SQLite. Keep migrations portable between MariaDB and MySQL (PLAN.md §4).
- No Redis. Cache, locks, queue and sessions use the database drivers.
- Branding is the client's own. Never add XLR8Labs marks, colours or copy to the site or the admin.
