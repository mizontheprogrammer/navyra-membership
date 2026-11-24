# Membership Management and Transaction Processing System

TW33 • Laravel 13 • PHP 8.5 • MySQL • Blade • Navy blue interface

## Start the application

1. Start MySQL in XAMPP. If its older Mac control panel does not load, use `sudo /Applications/XAMPP/xamppfiles/xampp startmysql` in Terminal and enter your own Mac administrator password.
2. In the project folder `/Applications/XAMPP/htdocs/tw33demo`, run `php artisan serve --host=127.0.0.1 --port=8000`.
3. In a second terminal in that folder, run `npm run dev -- --host 127.0.0.1 --port 5174 --strictPort` for live CSS updates. Alternatively run `npm run build` and stop Vite normally to use built assets.
4. Open http://localhost:8000. On first use, go to `/setup`, choose your administrator name, email, and password, and confirm the password. Passwords require at least 10 characters including upper/lowercase letters and a number. There is no shared default account or password. Setup closes after one administrator is created.
5. Use `/login` on subsequent visits. Log out with the sidebar button.

Apache is optional for Laravel's development server. Start Apache separately if you want phpMyAdmin at http://localhost/phpmyadmin. The live application database remains `laravel_tasks`.

## Demonstration: application → payment → approval → renewal

1. Categories: review Student, Regular, Senior Citizen, and Corporate. Add, rename, edit descriptions, or delete unused categories.
2. Levels: review Basic, Silver, Gold, and Platinum. Set a fee in PHP, optional validity in whole days, and benefits. A blank fee is unconfigured and cannot be used for a transaction; enter 0 for a free level. Blank validity means no expiry. Add or remove unused levels as needed.
3. Members: click Add Member. Enter a name, select a category and level, select Active or Inactive, and optionally enter email and phone. Leave the membership number blank to generate a unique number. Manually supplied numbers must also be unique and contain only letters, digits, or hyphens.
4. Open the profile and select New Application / Renewal. Choose Application and a configured level. The server generates the reference and snapshots the current member name, membership number, category, level, fee, benefits, and validity.
5. The new transaction is Pending. Click Record Payment. Enter an amount no greater than the displayed balance, payment method, date, and a reference for non-cash payments. This records money already received; it does not charge a bank account, card, or e-wallet.
6. Partial payments leave the transaction Pending. The exact full payment changes it to Paid. Open or print a receipt for each payment.
7. Open the Paid transaction and click Approve Membership. Only a fully paid transaction may be approved. A zero-fee transaction first requires Confirm Zero Fee / Mark Paid, then approval. Approval activates the member, assigns the approved level, and records the membership start and expiry.
8. Open the member's Transaction History to review applications, renewals, amounts, and status. Open a transaction to see its payments and receipt links.
9. To renew, choose Renewal after the first application has been approved. Pay and approve the renewal. If renewed early, the new period begins the day after the existing expiry. If already expired, it begins today. Validity is inclusive: 30 days starting today ends 29 days after today.
10. Print the member's Membership Record from their profile. Print styles remove navigation and action buttons.

## Transaction states and protections

- Pending: created, awaiting all or part of the fee.
- Paid: the complete fee is recorded, or a free fee is explicitly confirmed.
- Approved: an administrator approves a Paid transaction. Approval is not repeatable.
- Expired: an approved period has ended. The member is deactivated when their overall membership expires; a later approved renewal keeps them active.
- Cancelled: an unpaid Pending transaction is cancelled. A partially or fully paid transaction cannot be cancelled because no refund workflow is included.

Only one unfinished Pending/Paid transaction per member is allowed. A Renewal requires an approved Application; subsequent Applications are rejected. Payment amounts use decimal database fields and integer-cent comparisons. Positive amounts, at most two decimal places, valid transaction IDs, dates no earlier than the transaction and no later than today, and non-cash references are validated. Row locks and database transactions guard balance checks and approval. A unique submission key prevents re-recording a payment when a form is submitted again; changed details under the same key are rejected.

A member with transaction history cannot be deleted; deactivate them instead. Their membership number cannot be changed, and level changes must go through a new membership transaction. Categories and levels referenced by members or transactions cannot be deleted. Historical fee, period, identity, and benefit snapshots do not change when catalog or member details are edited. Receipts are internal payment records, not an integration with a government invoicing service.

## Expiry and dashboard

Expiry is refreshed when an authenticated administrator opens a system page. A daily scheduled expiry task is also registered. To process expiry while nobody is browsing, run `php artisan schedule:work` during local demonstrations, or configure Laravel's scheduler on the deployed server. Dates use Asia/Manila.

The dashboard shows total members, active members, Pending transactions, total collected payments, and recent transactions. Active is the administrator-managed member status, with expired memberships automatically deactivated; a newly registered record is not proof of payment. Transaction status is the source for payment/approval progress. Collections include all recorded payments, including partial payments.

## Database design and MVC structure

- users: Laravel authentication records; hashed password, administrator flag, timestamps.
- membership_categories: unique name and description.
- membership_levels: unique name, optional decimal fee, validity_days, benefits.
- members: unique membership_number; required category and level foreign keys; contact fields, active, joined_on, expires_on.
- membership_transactions: unique reference_number; member, level, creator, approver foreign keys; Application/Renewal type; status; decimal amount; original identity, price, benefit, and validity snapshots; period and approval timestamps.
- payments: unique receipt_number and submission_key; transaction and recorder foreign keys; decimal amount, method, external reference, paid_on, notes.
- system_settings: one locked setup record to prevent two first-administrator accounts being created concurrently.
- Laravel's sessions, cache, jobs, and the existing tasks tables remain available.

Relations: Category has many Members. Level has many Members and Transactions. Member belongs to Category and Level and has many Transactions. Transaction belongs to Member, Level, creator, and optional approver, and has many Payments. Payment belongs to Transaction and recorder. Foreign keys restrict deletion of referenced financial records.

Controllers: AuthController, DashboardController, MemberController, CategoryController, LevelController, TransactionController, PaymentController. Processing rules are centralized in `app/Services/MembershipProcessor.php`. Cent conversion is in `app/Support/Money.php`. Blade pages cover lists, forms, details, histories, login/setup, and print records. All management routes require both authentication and an administrator account. Login/setup are rate limited; modifying forms use CSRF protection. Existing Tasks routes remain protected and available.

## Migration and preservation

Run `php artisan migrate` to apply schema changes. Do not run `migrate:fresh` on the live database. The upgrade migrates existing text category/level assignments into foreign keys, retains legacy category/level names when necessary, and gives old member records unique numbers. The existing live member was preserved. No demo financial records or administrator credentials were inserted into the live database. There is a pre-upgrade source archive and MySQL SQL backup under this chat's work/full-system-backup folder; keep database backups private.

The code and database backups are a matched pre-upgrade pair. Restoring the old source without its corresponding database schema is not a complete rollback. Restore only to a separate database first and verify before replacing live work.

## Verification and rubric mapping

Verified: 27 automated tests and 211 assertions passed; frontend build passed; the live MySQL migrations passed; a second isolated MySQL database was migrated for browser testing. The Chrome walkthrough checked login, dashboard totals, member lists, transaction details, payment recording, receipt, and Paid → Approved. Screenshots below use clearly synthetic demonstration records, not the live member data. A full test run is `php artisan test --compact`; tests use an isolated SQLite in-memory database configured in phpunit.xml and do not reset MySQL.

- Setup/database (15): MySQL migrations, foreign keys, existing records preserved.
- Member CRUD (20): registration, profile, edits, deletion protection/deactivation, search and filters.
- Categories/levels (15): configurable CRUD, dropdown assignments, fees/validity/benefits.
- TPS (20): application, renewal, partial/full payment, status rules, unique references, history/receipts.
- Database design (10): normalized catalogs, required member links, transaction/payment links, financial snapshots.
- Interface (10): navy sidebar, responsive forms/tables, validation errors, centered member panels, print styles.
- Code quality (5): Laravel MVC, Eloquent, centralized transactional processing, validation, focused automated tests.
- Documentation/presentation (5): this guide, demonstration flow, schema summary, screenshots, verification evidence.

This mapping documents the implementation; the final grade is determined by your instructor.

## Screenshots

Dashboard: `screenshots/dashboard.png`
Members: `screenshots/members.png`
Transaction list: `screenshots/transactions.png`
Approved transaction: `screenshots/approved-transaction.png`
Payment receipt: `screenshots/receipt.png`


## Email, Maps, and Weather continuation

The public client form is `/membership/apply`; protected admin review is `/applications`. Local email testing uses Mailpit at http://127.0.0.1:8025 and the `membership-mail` queue. Google Maps and live OpenWeather require provider keys in `.env`. Detailed admin/client and phpMyAdmin test steps are in this chat output: `/Users/misha/Documents/Codex/2026-10-21/can-x20/outputs/email-maps-weather/TEST-GUIDE.html`.
