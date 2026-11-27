<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
# AI ground rules

Instructions for any AI agent working in an XLR8Labs repository. They apply to every
project in the XLR8Labs-io organization, including this one. Read them before planning
or writing anything.

AI-written code and AI-made design have recognisable tells, and those tells cost us
trust with clients. The standard to work to: nobody reading the repository or the
finished site can tell which parts an agent wrote.

## Working on a problem

- Find the right solution before writing code. Read the surrounding code, understand
  why it is shaped the way it is, then choose the approach that fits. "It works" is not
  the goal; the goal is the solution a careful engineer would still be happy with in a
  year.
- Prefer the fix at the root over the patch at the symptom. If the real problem is one
  layer down, say so and fix it there.
- Reuse what is already here before adding anything new. Check for an existing model,
  helper, enum, component or pattern first.
- Do not add a dependency, an abstraction, a config flag or an interface for a single
  implementation unless it earns its place. State the trade-off when you do.
- Write efficient queries. Eager-load relationships, aggregate in the database, and
  never run a query inside a loop. Know the cost of what you wrote.
- Match the conventions of the file you are editing: its naming, its structure, its
  level of detail. Consistency beats personal preference.
- Leave nothing unfinished: no placeholder text, no commented-out alternatives, no dead
  code, no stray debugging output.
- Verify before claiming it is done. Run the tests and the static analysis. If you did
  not check something, say plainly that you did not check it.

## Git

- Never put yourself in a commit. No `Co-Authored-By`, no "Generated with", no model or
  assistant name anywhere in the subject, body or trailers. The founders are the authors
  of this work.
- Write the message a person would write: a short subject in plain words saying what
  changed and why. Not a restatement of the diff.
- Group related work into one commit. Do not fire off a string of tiny commits and
  pushes minutes apart; that pacing is a tell. One coherent change, one commit.
- Never commit secrets, credentials, `.env` files or generated build output.

## Comments

- Comment the way the people here comment: only where the reason is not obvious from the
  code, explaining _why_ rather than restating _what_.
- Never write a comment that addresses or identifies an AI agent. No "AI-generated", no
  "as an AI", no "TODO: agent", no notes left for your own future context.
- No banner comments, no decorative separator lines, no restating the function name
  directly above the function.
- Keep comments true. A stale comment is worse than none, so update it when the code
  under it changes.

## Design

Design the way a good designer did before AI tools existed: clean, modern, restrained
and specific to this project. Do not reach for the house style that code and image
generators default to.

- No letter-spaced or monospaced uppercase words used as badges, eyebrows or pills.
  Monospace is for code, URLs and identifiers only.
- One accent colour, used sparingly. Ours is the brand green `#00b37a`. Resist the
  default purple-to-indigo gradient.
- No gradient on everything, no glowing blurred shapes behind the hero, no glassmorphism
  out of habit, no heavy drop shadows standing in for layout.
- Create hierarchy with spacing, type weight and borders before reaching for decoration.
- No emoji as interface icons. Use the icon set the project already uses.
- Not every section needs three equal cards with a generic icon above a generic
  sentence. Let the content decide the layout.
- Consistent whitespace on a real spacing scale. Crowding and arbitrary one-off values
  both read as careless.
- Motion is subtle and purposeful, or absent. Nothing should pulse, float or fade in on
  scroll without a reason.
- Restraint over volume. Excessive styling is the clearest sign of AI slop; when in
  doubt, remove something.

## Writing

- Short sentences, plain words, specific claims. No adjective that could describe any
  company.
- No marketing voice: no "seamless", no "unlock", no "elevate", no "not just X, but Y"
  construction, no rhetorical question as a heading.
- Say the thing, then stop.

## Documentation

- Every project has a `README.md` saying what it is, who it is for, how to run it
  locally and how it is deployed. Keep it current: when behaviour, setup or commands
  change, update the README in the same commit.
- Every project has a `CHANGELOG.md`, newest first, written for a person rather than a
  programmer. "Expenses now record who paid", not "add paid_by_user_id to
  ExpenseResource". Add an entry whenever something changes that a founder or a client
  would notice.
- Keep both short and honest. A README nobody trusts is worse than a brief one.
- These rules travel with the work. Every repository keeps them as `AGENTS.md` in its
  top folder, downloadable from the project guide in our admin. Do not edit the copy in
  a project: change the original here, then hand out the new version.

## Branding

- Keep the XLR8Labs identity where it belongs: the logo in the admin and on the public
  site, the brand green as the accent, the favicon set. Light and dark versions of the
  mark both exist, so use the right one.
- Do not swap brand assets or colours for generic placeholders, and do not invent new
  marks, taglines or colour schemes.
- On client work the client's branding leads. Ours appears only where we have agreed it
  should.

## Security

- Ship no known vulnerability. Validate and authorize at the trust boundary every time,
  even when the only users are the two founders.
- Never interpolate input into a query, a shell command or a file path. Use the
  framework's tools.
- Escape or sanitize anything rendered as HTML, including markdown we wrote ourselves.
- Keep secrets in the environment, never in code, fixtures or commits.
- Keep dependencies patched. Do not add a package that is unmaintained or unnecessary.
- Never weaken a security control to make a test pass or a feature simpler.
