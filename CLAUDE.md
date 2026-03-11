# BookMyHall — AI Development Reference

> **Read this entire file before starting any new module.**  
> It captures every architectural decision, layout pattern, and hard-learned fix from Modules 1 & 2.

---

## 1. Project Environment

| Setting | Value |
|---|---|
| Server | Laragon (Apache + PHP 8.x + MySQL 8.x) |
| DB host | `localhost` |
| DB name | `bookmyhall` |
| DB user | `root` |
| DB pass | `root` (not empty string) |
| `BASE_URL` | `/BookMyHall` |
| `SITE_NAME` | `BookMyHall` |
| Timezone | `Asia/Colombo` |
| Config file | `config/config.php` |
| PDO object | `$pdo` (from `includes/db_connection.php`) |
| Default fetch mode | `PDO::FETCH_ASSOC` |

---

## 2. Module Status

| Module | Owner | Status |
|---|---|---|
| 1 – Auth & Admin Dashboard | Sahani | ✅ Complete |
| 2 – Hall & Package Management | Riffna | ✅ Complete |
| 3 – Customer Management | Nishtha | 🔜 Next |
| 4 – Booking & Feedback | Afrina | 🔜 Pending |
| 5 – Payment & Reports | Nihap | 🔜 Pending |

---

## 3. Admin Page Layout Pattern

Every admin page **must** use this exact structure — no variations.

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$pageTitle    = 'Page Title';
$pageSubtitle = 'Short description shown in topbar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $pageTitle ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/[module].css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <!-- page content here -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/[module].js"></script>
</body>
</html>
```

### Critical Admin Layout Rules
- `sidebar.php` and `header.php` are **separate includes** — sidebar first, then header, both at the body level.
- The `<main>` element uses **`class="content-wrapper"`** — never `content-area`, `main-wrapper`, or anything else.
- **Never** wrap sidebar + header in an extra `<div>`. The dashboard is the reference: `sidebar → header → <main class="content-wrapper">`.
- `$pageTitle` and `$pageSubtitle` must be set **before** `include header.php` — `header.php` reads them.

---

## 4. Customer Page Layout Pattern

Every customer page **must** use this exact structure.

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['customer_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Page Title — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/[module].css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'Page Title';
$pageSubtitle = 'Short subtitle shown in topbar';
include __DIR__ . '/../includes/customer_topbar.php';
?>

<div class="c-content-wrapper">
<div class="customer-content">

    <!-- page content here -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
```

### Critical Customer Layout Rules
- Use `customer_sidebar.php` + `customer_topbar.php`
- `$pageTitle` and `$pageSubtitle` must be set **before** `include customer_topbar.php`.
- All content sits inside **both** `c-content-wrapper` (handles sidebar offset + topbar offset) AND `customer-content` (handles max-width + padding).
- The footer goes **inside** `.c-content-wrapper`, after `.customer-content`.
- `index.php` is at the root — its include paths use `__DIR__ . '/customer/includes/...'` not `'/../includes/...'`.

### `__DIR__` path depth reference
| File location | Path to customer includes |
|---|---|
| `index.php` (root) | `__DIR__ . '/customer/includes/customer_sidebar.php'` |
| `customer/hall/*.php` | `__DIR__ . '/../includes/customer_sidebar.php'` |
| `customer/bookings/*.php` | `__DIR__ . '/../includes/customer_sidebar.php'` |
| `customer/profile/*.php` | `__DIR__ . '/../includes/customer_sidebar.php'` |
| `customer/feedback/*.php` | `__DIR__ . '/../includes/customer_sidebar.php'` |
| `customer/auth/*.php` | `__DIR__ . '/../includes/customer_sidebar.php'` |

---

## 5. Session Namespacing

| Role | Session key | Guard file |
|---|---|---|
| Admin | `$_SESSION['admin_id']`, `$_SESSION['role'] === 'admin'` | `includes/session_guard.php` |
| Customer | `$_SESSION['customer_id']`, `$_SESSION['customer_name']` | `includes/customer_session_guard.php` |

- Include `session_guard.php` at the top of **every admin page**.
- For customer pages that require login, include `customer_session_guard.php`.
- For public customer pages, just check `isset($_SESSION['customer_id'])` for conditional UI.

---

## 6. Shared Functions (`includes/functions.php`)

All available — never rewrite these:

| Function | Signature | Notes |
|---|---|---|
| `sanitizeInput` | `(string $data): string` | htmlspecialchars + strip_tags + trim |
| `validateEmail` | `(string $email): bool` | filter_var |
| `hashPassword` | `(string $password): string` | password_hash BCRYPT |
| `verifyPassword` | `(string $password, string $hash): bool` | password_verify |
| `formatDate` | `(string $date): string` | Returns Y-m-d |
| `formatDateReadable` | `(string $date): string` | Returns "March 10, 2026" |
| `formatCurrency` | `(float $amount): string` | Returns "LKR 1,200.00" |
| `setFlash` | `(string $type, string $message): void` | type = 'success' or 'error' |
| `getFlash` | `(): ?array` | Returns ['type'=>..., 'message'=>...] or null |
| `redirect` | `(string $url): void` | header Location + exit |
| `checkAvailability` | `(PDO $pdo, string $date, string $start, string $end, int $packageId): bool` | Afrina's module |
| `getDashboardStats` | `(PDO $pdo): array` | Returns total_bookings, pending_approvals, total_revenue, new_customers |
| `logActivity` | `(PDO $pdo, string $action, int $userId): void` | Stub — logs to error_log |
| `getPackagesByHall` | `(PDO $pdo, int $hallId): array` | Returns main pkgs with sub_packages[] and services_arr[] |

### Flash message rendering pattern (admin pages)
```php
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>
```

---

## 7. CSS Variables Reference

### Admin (`admin_global.css`)
```css
--sidebar-width:  260px;
--topbar-height:  88px;
--primary:        #4d5dfb;
--primary-light:  #eef1ff;
--text-main:      #1f1d2b;
--text-muted:     #6c6f83;
--bg-page:        #f5f7fd;
--card-bg:        #ffffff;
--success:        #2ecc71;
--warning:        #f39c12;
--danger:         #e74c3c;
--info:           #3498db;
--shadow-card:    0 4px 24px rgba(112,144,176,.12);
--radius-lg:      20px;
--radius-md:      14px;
--radius-sm:      10px;
```

### Customer (`customer_global.css`)
```css
--c-sidebar-width: 240px;
--nav-height:      68px;      /* also used as topbar height */
--primary:         #4d5dfb;
--primary-light:   #eef1ff;
--text-main:       #1f1d2b;
--text-muted:      #6c6f83;
--bg-page:         #f5f7fd;
--card-bg:         #ffffff;
--border:          #eaedf7;
--success:         #2ecc71;
--danger:          #e74c3c;
--warning:         #f39c12;
--shadow:          0 4px 24px rgba(112,144,176,.12);
--radius-lg:       20px;
--radius-md:       14px;
--radius-sm:       10px;
```

---

## 8. Admin CSS Utility Classes (from `admin_global.css`)

### Layout
- `.content-wrapper` — main content area (margin-left: 260px, padding below topbar)
- `.page-header` — flex row: title on left, actions on right
- `.page-title`, `.page-subtitle` — heading styles within `.page-header`
- `.page-header-actions` — flex row of buttons on right of page header

### Cards & Grid
- `.card` — white card with shadow and padding
- `.card-header` — flex row inside card (title + action)

### Buttons
- `.btn` — base button (inline-flex, 10px 22px padding)
- `.btn-primary`, `.btn-outline`, `.btn-success`, `.btn-danger`, `.btn-warning`
- `.btn-sm` — smaller button (6px 14px)
- `.btn-full` — full-width, centered

### Tables
- `.table-wrapper` — overflow-x: auto container
- `.data-table` — full-width styled table
- `.data-table thead` — `--primary-light` background

### Alerts
- `.alert`, `.alert-success`, `.alert-error`, `.alert-info`

### Status Badges
- `.badge-status.pending`, `.approved`, `.rejected`, `.cancelled`, `.completed`

---

## 9. Customer CSS Utility Classes (from `customer_global.css`)

### Layout
- `.c-content-wrapper` — handles sidebar offset (margin-left: 240px) + topbar offset (padding-top: 68px)
- `.customer-content` — max-width 1100px, centered, inner padding
- `.customer-sidebar` — fixed left sidebar (admin sidebar style)
- `.customer-topbar` — fixed top bar (admin topbar style)

### Buttons, Alerts, Forms, Badges — same names as admin_global.css
- `.btn`, `.btn-primary`, `.btn-outline`, `.btn-danger`, `.btn-sm`, `.btn-full`
- `.alert`, `.alert-success`, `.alert-error`, `.alert-info`
- `.form-group`, `.form-control`
- `.badge-status.pending/approved/rejected/cancelled/completed`

### Footer
- `.site-footer` — dark background, centered small text

---

## 10. Database — Key Table Structures

### `users`
```sql
user_id, full_name, email, phone, password_hash, role ENUM('admin','customer'),
address, profile_picture, status ENUM('active','blocked'), created_at
```

### `hall`
```sql
hall_id, name, description, capacity, location, size_sqft, base_price,
features JSON,   -- array of: ac, stage, parking, sound_system, catering, wifi, bridal_suite, projector
status ENUM('available','unavailable','maintenance'), last_updated
```

### `hall_images`
```sql
image_id, hall_id FK, filename, sort_order, uploaded_at
```
Images stored at: `assets/images/hall/` — filename format: `hall_{time}_{hex}.{ext}`

### `packages`
```sql
package_id, hall_id FK, parent_package_id FK(self/NULL), name,
type ENUM('main','sub'), price, seat_capacity, parking_capacity,
description, inclusions, services JSON,   -- array of: catering, ac, decoration, wifi, parking
is_active TINYINT(1)
```
- `type='main'` → `parent_package_id = NULL`
- `type='sub'` → `parent_package_id` = a main package's `package_id`
- **Bookings reference sub-packages only**

### `bookings`
```sql
booking_id, customer_id FK(users), hall_id FK, sub_package_id FK(packages),
event_date, start_time, end_time, event_type, guest_count, special_requests,
total_amount, advance_amount, balance_amount,
status ENUM('pending','approved','rejected','cancelled','completed'),
rejection_reason, is_deleted TINYINT(1), created_at, updated_at, completed_at
```

### `payments`
```sql
payment_id, booking_id FK, payment_type ENUM('advance','balance','full'),
amount, method ENUM('cash','bank_transfer','card'), transaction_reference,
notes, status ENUM('pending','paid','refunded','failed'), recorded_at, updated_at
```

### `transactions`
```sql
transaction_id, payment_id FK, previous_status, new_status, changed_by FK(users),
changed_at, notes
```

### `feedback`
```sql
feedback_id, booking_id UNIQUE FK, customer_id FK(users), rating TINYINT(1-5),
comment TEXT, is_visible TINYINT(1), submitted_at
```

---

## 11. Sidebar Navigation — Current Links

### Admin sidebar (`admin/includes/sidebar.php`)
Uses `sidebarLink(href, icon, label, $currentPage, $matchFiles[])` helper.

| Label | Files it activates on |
|---|---|
| Dashboard | `dashboard.php` |
| Hall | `manage_hall.php`, `edit_hall.php`, `manage_images.php` |
| Packages | `manage_packages.php`, `add_package.php`, `edit_package.php` |
| Bookings | `manage_bookings.php`, `booking_details.php`, `add_booking.php` |
| Customers | `manage_customers.php`, `customer_details.php` |
| Feedback | `manage_feedback.php`, `view_feedback.php` |
| Payments | `manage_payments.php`, `payment_details.php`, `add_payment.php` |
| Reports | `booking_report.php`, `income_report.php`, `monthly_report.php`, `utilization_report.php` |
| My Profile | `admin_profile.php` |

**When adding a new admin page, add its filename to the matching `$matchFiles` array in `sidebar.php`.**

### Customer sidebar (`customer/includes/customer_sidebar.php`)
Uses `csideLink(href, icon, label, $currentPage, $matchFiles[])` helper. Same pattern as admin.

| Label | Files it activates on |
|---|---|
| Home | `index.php` |
| The Hall | `view_hall.php` |
| Packages | `view_packages.php` |
| Book Now | `book_hall.php` |
| Booking History | `booking_history.php`, `customer_bookings.php`, `booking_details.php` |
| My Feedback | `my_feedback.php`, `submit_feedback.php` |
| My Profile | `profile.php`, `edit_profile.php`, `change_password.php` |

**When adding new customer pages, add their filenames to the matching `$matchFiles` array in `customer_sidebar.php`.**

---

## 12. Admin Header (`admin/includes/header.php`)

Reads `$pageTitle` and `$pageSubtitle` from the including page scope. Always set these before including:

```php
$pageTitle    = 'Manage Customers';
$pageSubtitle = 'View, search and manage registered customers';
include __DIR__ . '/../includes/header.php';
```

---

## 13. Customer Topbar (`customer/includes/customer_topbar.php`)

Same pattern — set `$pageTitle` and `$pageSubtitle` before including:

```php
$pageTitle    = 'My Profile';
$pageSubtitle = 'Manage your account details';
include __DIR__ . '/../includes/customer_topbar.php';
```

---

## 14. Security Rules (Non-Negotiable)

1. **All DB queries → prepared statements.** Never interpolate `$_POST`/`$_GET` into SQL.
2. **All user output → `htmlspecialchars()`**. Never echo raw user input.
3. **Password storage → `hashPassword()`** (bcrypt). Never plain text or MD5.
4. **Session guard on every admin page** → `require_once session_guard.php`.
5. **File uploads** → validate with `mime_content_type()` (not extension). Store in `assets/images/`. Generate random filenames with `bin2hex(random_bytes(4))`.
6. **Error messages** → generic to users, full detail to `error_log()`.
7. **Redirect after POST** → always `setFlash() + redirect()` after successful form submit (PRG pattern).

---

## 15. Common Mistakes Already Fixed — Do Not Repeat

| Mistake | What went wrong | Correct approach |
|---|---|---|
| `<div class="main-wrapper">` around sidebar+header+main | Content overlapped sidebar/topbar | Sidebar and header are siblings of `<main>`, not wrapped together |
| `class="content-area"` on `<main>` | No CSS rule existed — broken layout | Always use `class="content-wrapper"` on admin `<main>` |
| Not setting `$pageTitle` before header include | Topbar showed "Dashboard" as fallback | Always set `$pageTitle` before any include of header/topbar |
| PowerShell `Set-Content -Encoding UTF8` | Adds UTF-8 BOM → PHP outputs garbage characters | Never use PowerShell to write PHP files — use VS Code / create_file tool only |
| Inline duplicate page header `<h1>` | Showed title twice (topbar + inline) | Remove inline heading when topbar already shows page title |

---

## 16. New Module Checklist

Before writing any code for a new module:

- [ ] Read this file fully
- [ ] Check `includes/functions.php` — use existing functions, don't duplicate
- [ ] Check `admin/includes/sidebar.php` — add new page filenames to `$matchFiles`
- [ ] Check `customer/includes/customer_sidebar.php` — same for customer pages
- [ ] Use the exact admin or customer layout pattern from sections 3 & 4 above
- [ ] Create CSS file in `assets/css/admin/[module].css` and/or `assets/css/customer/[module].css`
- [ ] Create JS file in `assets/js/admin/[module].js` if needed
- [ ] Always use `require_once session_guard.php` on admin pages
- [ ] Use `setFlash() + redirect()` (PRG pattern) after POST operations
- [ ] All SQL → prepared statements only

---

*Last updated: Module 2 complete. Next: Module 3 – Customer Management (Nishtha).*
