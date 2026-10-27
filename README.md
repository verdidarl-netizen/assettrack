# AssetTrack Pro — Equipment & Maintenance Management System

PHP 8.1+ (PDO MySQL) + Tailwind. Built on your existing XAMPP database **`assettrack_pro`**.

## Run (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\assettrack`.
2. In the XAMPP Control Panel start **Apache** and **MySQL**.
3. Open http://localhost/assettrack/
4. If your MySQL login isn't `root` with no password, edit the `DB_*` constants at the top of `config.php`.

On the first page load the app upgrades your database **without deleting anything** (`classes/Schema.php`):
- `asset`: adds name, location, purchase_date, purchase_cost, salvage_value, useful_life_years, warranty_expiry, notes (name is filled from asset_tag for existing rows)
- `maintenance_ticket`: adds title, type, created_by, assigned_technician_id, cost, scheduled_date, resolved_at (your `issue_description`, `priority`, `status` are reused)
- `user`: adds department_id, notify_enabled
- creates `audit_log` and `notifications`; adds starter rows to `department` if it is empty
- widens `asset.status`, `user.role`, `maintenance_ticket.status/priority` to VARCHAR so every value fits (existing values are kept)
- users whose password_hash is the placeholder text `hash` (david.vance@, tech@, employee@company.com) get the password **password123**; real bcrypt hashes (e.g. your ryle@ accounts) are untouched

Asset and ticket IDs stay UUID strings if that is how your tables define them.

## Logins
david.vance@company.com (Admin) · tech@company.com (Technician) · employee@company.com (Employee) — password `password123`
plus your own accounts. Self sign-up always creates an Employee; only an Admin can change roles.

## Structure
```
index.php            front controller + layout + shared modal JS
config.php           DB settings, constants, CSRF/escape helpers, modal/field helpers
classes/             Database, Schema, Auth, Asset, Ticket, User, Notification, Log, Exporter, Transaction
backend/actions.php  every POST action (CSRF + role checks, then redirect)
backend/export.php   CSV / print-to-PDF export (Admin, Technician)
modals/              login, register, asset, ticket, user, profile
pages/               dashboard, assets, tickets, transactions, users, logs
```

## Transaction demo (Admin, Technician) — menu: Transactions
Based on the `transaction_demo` project (beginTransaction → steps → check → commit / rollBack), applied to AssetTrack data in `classes/Transaction.php`:
1. **Assign Asset** — asset row + audit log + notification; rolls back if the asset is not Available or the user ID does not exist.
2. **Transfer Asset** — release from sender, check receiver exists, assign to receiver (same shape as the money transfer); a wrong receiver ID rolls the release back.
3. **Complete Ticket** — ticket resolved + cost saved + asset freed from maintenance + audit log + notification; rolls back if already resolved or cost is negative.
Tick *Force a failure* on any form to see everything undone. Each run shows its BEGIN/COMMIT/ROLLBACK steps, and a Recent Transaction Log table.
Rollback needs **InnoDB** tables (the page warns if one isn't). No new tables or SQL import needed.

## Notes about your current data
- `users` (the extra empty table) is not used by the app; you can drop it in phpMyAdmin.
- `asset_category` has duplicate rows (5–7 repeat Laptops/Servers/Networking). The dropdowns hide the duplicates; delete rows 5–7 if nothing uses them.
- Asset statuses are now Available / Assigned / In Maintenance / Retired / Lost (matches your existing "Available" and "In Maintenance").

## Before production
Remove the Quick Demo Login box, set `APP_DEBUG` to false, use HTTPS and set `'secure' => true` in the session cookie params, and give MySQL `root` a password.
