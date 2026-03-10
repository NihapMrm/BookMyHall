# BookMyHall – Event Hall Booking System
## Comprehensive Development Plan

**Project:** BookMyHall – Event Hall Booking System  
**Client:** Lee Maridean Banquet Hall  
**Course:** EEY4189 – Software Design in Group  
**Group ID:** AS_1  
**Supervisor:** Mrs. Ahalihai Suthakaran  
**Document Version:** 3.0  
**Last Updated:** March 2026

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Database Architecture](#3-database-architecture)
4. [System Modules](#4-system-modules)
5. [Team Member Assignments](#5-team-member-assignments)
6. [Development Phases & Timeline](#6-development-phases--timeline)
7. [Integration Plan](#7-integration-plan)
8. [Testing Strategy](#8-testing-strategy)
9. [Coding Standards & Conventions](#9-coding-standards--conventions)
10. [Risk Management](#10-risk-management)
11. [Deliverables Checklist](#11-deliverables-checklist)

---

## 1. Project Overview

### 1.1 Background

BookMyHall is a web-based event hall reservation system developed to automate and digitize the booking operations of Lee Maridean Banquet Hall. The hall currently manages all reservations manually via phone calls, physical visits, and handwritten records — leading to double bookings, scheduling conflicts, data loss, and poor customer experience.

### 1.2 Problem Statement

| Issue | Impact |
|---|---|
| Manual booking via phone/walk-in | Double bookings, scheduling errors |
| No centralized data management | Risk of data loss, inconsistency |
| No real-time availability visibility | Customer frustration, lost bookings |
| Manual payment tracking | Revenue leakage, delayed reconciliation |
| No reporting system | Poor decision-making by management |

### 1.3 Project Objectives

1. Develop an online platform enabling customers to check real-time availability, select packages, make reservations, and receive instant confirmations.
2. Provide an administrative dashboard for efficient management of bookings, payments, hall settings, and automated reporting.
3. Implement a secure, role-based authentication system for admins and customers.
4. Deliver a feedback and rating system to support service improvement.
5. Follow a structured SDLC with proper documentation, version control, and testing throughout.

### 1.4 Scope

**In Scope:**
- Single-hall booking system (Lee Maridean Banquet Hall)
- Customer registration, login, and profile management
- Hall and package management by admin
- Booking creation, approval, rejection, and cancellation
- Payment recording and tracking (advance + balance)
- Admin reports and analytics
- Customer feedback and ratings

**Out of Scope:**
- Multi-hall or multi-branch management
- Real-world payment gateway integration (academic scope)
- Native mobile application
- SMS notification service

---

## 2. Technology Stack

### 2.1 Frontend

| Technology | Purpose |
|---|---|
| HTML5 | Page structure, forms, navigation |
| CSS3 | Styling, layout, responsiveness |
| JavaScript (Vanilla) | Client-side validation, dynamic UI updates |
| Figma | UI/UX wireframes and mockups |

### 2.2 Backend

| Technology | Purpose |
|---|---|
| PHP 8.x | Server-side business logic, authentication, routing |
| Apache (via XAMPP) | Local web server |

### 2.3 Database

| Technology | Purpose |
|---|---|
| MySQL 8.x | Relational data storage |
| phpMyAdmin | Database administration during development |
| MySQL Workbench | ER diagram design and schema modeling |

### 2.4 Development & Collaboration Tools

| Tool | Purpose |
|---|---|
| Visual Studio Code | Code editing and debugging |
| XAMPP | Local development environment |
| Git & GitHub | Version control and team collaboration |
| Trello | Task management and sprint tracking |
| WhatsApp / Zoom | Daily standups and team communication |
| Google Sheets | Bug tracking and test case logging |

---

## 3. Database Architecture

### 3.1 Overview

The database `bookmyhall` is designed for a single-hall system using MySQL with `utf8mb4` character encoding. It consists of **8 core tables** with fully defined foreign key relationships.

### 3.2 Table Summary

| # | Table | Purpose | Key Relationships |
|---|---|---|---|
| 1 | `users` | Stores all user accounts (admin & customer) | Referenced by bookings, feedback |
| 2 | `hall` | Single hall information and settings | Referenced by hall_images, packages, bookings |
| 3 | `hall_images` | Gallery images for the hall | FK → hall |
| 4 | `packages` | Main and sub-packages for the hall | FK → hall, self-referencing for sub-packages |
| 5 | `bookings` | All reservation records | FK → users, hall, packages |
| 6 | `payments` | Payment transactions per booking | FK → bookings |
| 7 | `transactions` | Audit trail for payment status changes | FK → payments |
| 8 | `feedback` | Customer ratings and comments | FK → bookings, users |

### 3.3 Entity Relationships

```
users ──────────────────────────── bookings
  (customer_id)                      │
                                     ├── hall
                                     │     └── hall_images
                                     │     └── packages (main)
                                     │           └── packages (sub)
                                     ├── packages (sub_package booked)
                                     └── payments
                                           └── transactions
bookings ──────────────────────── feedback
  (one feedback per completed booking)
```

### 3.4 Key Business Rules (from SQL Schema)

- A booking **must reference a sub-package** (not a main package).
- **Different main packages** can be booked at overlapping times.
- **Same sub-package** cannot be booked at overlapping times (conflict prevention).
- Feedback is **unique per booking** (`UNIQUE` constraint on `booking_id`).
- Bookings support **soft delete** via `is_deleted` flag.
- Booking statuses: `pending → approved → completed` or `pending → rejected` or `cancelled`.
- Payment statuses: `pending → paid → refunded` or `failed`.

### 3.5 Folder Structure

```
/bookmyhall/
│
├── index.php                          ← Public landing page (hall overview + CTA)
│
├── /admin/                            ── ADMIN AREA ──
│   │
│   ├── /auth/                         [Module 1 – Sahani]
│   │   ├── login.php                  Admin login form
│   │   └── logout.php                 Session destroy + redirect
│   │
│   ├── /dashboard/                    [Module 1 – Sahani]
│   │   ├── dashboard.php              Main dashboard (stats, activity feed, alerts)
│   │   └── admin_profile.php          View & edit admin profile + change password
│   │
│   ├── /hall/                         [Module 2 – Riffna]
│   │   ├── manage_hall.php            View current hall details
│   │   ├── edit_hall.php              Edit hall info, amenities, status
│   │   └── manage_images.php          Upload, reorder, delete hall gallery images
│   │
│   ├── /packages/                     [Module 2 – Riffna]
│   │   ├── manage_packages.php        List all main & sub-packages with stats
│   │   ├── add_package.php            Create new main or sub-package
│   │   ├── edit_package.php           Update package details, toggle active status
│   │   └── delete_package.php         Delete package (with booking conflict check)
│   │
│   ├── /customers/                    [Module 3 – Nishtha]
│   │   ├── manage_customers.php       Paginated customer list, search & filter
│   │   ├── customer_details.php       Full profile + booking & payment history
│   │   ├── block_customer.php         Toggle customer account active/blocked
│   │   └── customer_stats.php         Aggregated customer statistics for dashboard
│   │
│   ├── /bookings/                     [Module 4 – Afrina]
│   │   ├── manage_bookings.php        All bookings list (calendar + table view, filters)
│   │   ├── booking_details.php        Full booking record view
│   │   ├── add_booking.php            Manually create booking on behalf of customer
│   │   ├── approve_booking.php        Approve pending booking
│   │   ├── reject_booking.php         Reject booking with reason
│   │   └── complete_booking.php       Mark booking as completed post-event
│   │
│   ├── /feedback/                     [Module 4 – Afrina]
│   │   ├── manage_feedback.php        All feedback list with filters & avg rating
│   │   ├── view_feedback.php          Single feedback detail view
│   │   └── toggle_feedback.php        Show/hide feedback from customer display
│   │
│   ├── /payments/                     [Module 5 – Nihap]
│   │   ├── manage_payments.php        All payments list, search & filter, summary cards
│   │   ├── payment_details.php        Single payment + full transaction audit trail
│   │   ├── add_payment.php            Record new payment for a booking
│   │   └── update_payment.php         Change payment status (logs to transactions)
│   │
│   └── /reports/                      [Module 5 – Nihap]
│       ├── booking_report.php         Booking summary with date range filter
│       ├── income_report.php          Revenue breakdown + trend chart
│       ├── monthly_report.php         Monthly performance table
│       ├── utilization_report.php     Hall utilization by date/time slot
│       ├── customer_report.php        Customer statistics (new vs returning, top spenders)
│       └── export_report.php          Export any report as PDF or Excel
│
├── /customer/                         ── CUSTOMER AREA ──
│   │
│   ├── /auth/                         [Module 1 – Sahani]
│   │   ├── register.php               Registration form (name, email, phone, password)
│   │   ├── customer_login.php         Customer login with separate session namespace
│   │   └── customer_logout.php        Destroy customer session + redirect
│   │
│   ├── /profile/                      [Module 3 – Nishtha]
│   │   ├── profile.php                View own profile details
│   │   ├── edit_profile.php           Edit name, phone, address, profile picture
│   │   └── change_password.php        Change password (requires current password)
│   │
│   ├── /hall/                         [Module 2 – Riffna]
│   │   ├── view_hall.php              Hall detail page (images, description, amenities)
│   │   └── view_packages.php          Active packages list with pricing & "Book Now"
│   │
│   ├── /bookings/                     [Module 4 – Afrina]
│   │   ├── book_hall.php              Booking form (date, time, package, guests, requests)
│   │   ├── customer_bookings.php      Calendar view of own bookings (colour-coded status)
│   │   ├── booking_history.php        Table view of all bookings with status & amounts
│   │   ├── booking_details.php        Single booking detail + payment status (read-only)
│   │   └── cancel_booking.php         Cancel pending booking with reason
│   │
│   └── /feedback/                     [Module 4 – Afrina]
│       ├── submit_feedback.php         Rating + comment form (completed bookings only)
│       └── my_feedback.php            View all own submitted feedback
│
├── /includes/                         ── SHARED FILES ──
│   ├── db_connection.php              PDO connection to bookmyhall database
│   ├── functions.php                  Shared utility functions (sanitize, hash, format…)
│   ├── session_guard.php              Admin session check (include on all admin pages)
│   └── access_denied.php             Redirect page for unauthorized access attempts
│
├── /config/
│   └── config.php                     Global settings (DB credentials, site URL, timezone)
│
└── /assets/
    │
    ├── /css/
    │   │
    │   ├── style.css                        Global base styles (typography, colors, layout, navbar, footer)
    │   │
    │   ├── /admin/                          ── Admin CSS ──
    │   │   ├── admin_global.css             Shared admin styles (sidebar, topbar, cards, tables)
    │   │   ├── auth.css                     Admin login page styles          [Module 1 – Sahani]
    │   │   ├── dashboard.css                Dashboard stat cards, activity feed, widgets  [Module 1 – Sahani]
    │   │   ├── hall.css                     Hall detail view, image grid, amenity badges  [Module 2 – Riffna]
    │   │   ├── packages.css                 Package cards, sub-package tree, toggle switch [Module 2 – Riffna]
    │   │   ├── customers.css                Customer list table, status badges, search bar [Module 3 – Nishtha]
    │   │   ├── bookings.css                 Booking calendar, status colour tags, filters  [Module 4 – Afrina]
    │   │   ├── feedback.css                 Star rating display, feedback cards, visibility toggle [Module 4 – Afrina]
    │   │   ├── payments.css                 Payment summary cards, transaction table, method badges [Module 5 – Nihap]
    │   │   └── reports.css                  Report tables, chart containers, date range picker, export button [Module 5 – Nihap]
    │   │
    │   └── /customer/                       ── Customer CSS ──
    │       ├── customer_global.css          Shared customer styles (nav, footer, buttons, alerts)
    │       ├── auth.css                     Register & login page styles      [Module 1 – Sahani]
    │       ├── profile.css                  Profile card, avatar upload, form layout [Module 3 – Nishtha]
    │       ├── hall.css                     Hall gallery slider, amenity icons, package cards [Module 2 – Riffna]
    │       ├── bookings.css                 Booking form layout, calendar view, status badges, history table [Module 4 – Afrina]
    │       └── feedback.css                 Star rating input, feedback form, submitted feedback list [Module 4 – Afrina]
    │
    ├── /js/
    │   │
    │   ├── main.js                          Global scripts (flash messages, confirm dialogs, active nav highlight)
    │   │
    │   ├── /admin/                          ── Admin JS ──
    │   │   ├── dashboard.js                 Fetch & render live dashboard stats, activity feed refresh [Module 1 – Sahani]
    │   │   ├── hall.js                      Image upload preview, drag-and-drop reorder, delete confirm [Module 2 – Riffna]
    │   │   ├── packages.js                  Show/hide sub-package form based on type selection, delete confirm [Module 2 – Riffna]
    │   │   ├── customers.js                 Live search filter on customer table, block confirm dialog [Module 3 – Nishtha]
    │   │   ├── bookings.js                  Calendar view toggle, status filter, reject reason modal [Module 4 – Afrina]
    │   │   ├── payments.js                  Payment status update confirm, filter by method/status [Module 5 – Nihap]
    │   │   └── reports.js                   Chart.js rendering (revenue trend, utilization bar), date range picker, export trigger [Module 5 – Nihap]
    │   │
    │   └── /customer/                       ── Customer JS ──
    │       ├── auth.js                      Registration password strength meter, confirm password match [Module 1 – Sahani]
    │       ├── booking.js                   Real-time availability check on date/time change, auto-calculate total/advance/balance amounts, package selector [Module 4 – Afrina]
    │       ├── calendar.js                  Render booking calendar with colour-coded status markers [Module 4 – Afrina]
    │       └── feedback.js                  Interactive star rating click handler, form submit validation [Module 4 – Afrina]
    │
    └── /images/
        ├── /hall/                           Uploaded hall gallery images
        ├── /profiles/                       Uploaded customer profile pictures
        └── /icons/                          UI icons (amenity icons, status icons, logo)
```

### 3.6 CSS & JS Loading Reference

| Module | Admin CSS | Admin JS | Customer CSS | Customer JS |
|---|---|---|---|---|
| 1 – Auth & Dashboard | `auth.css`, `dashboard.css` | `dashboard.js` | `auth.css` | `auth.js` |
| 2 – Hall & Packages | `hall.css`, `packages.css` | `hall.js`, `packages.js` | `hall.css` | *(none needed)* |
| 3 – Customer Mgmt | `customers.css` | `customers.js` | `profile.css` | *(none needed)* |
| 4 – Booking & Feedback | `bookings.css`, `feedback.css` | `bookings.js` | `bookings.css`, `feedback.css` | `booking.js`, `calendar.js`, `feedback.js` |
| 5 – Payment & Reports | `payments.css`, `reports.css` | `payments.js`, `reports.js` | *(read-only, uses bookings.css)* | *(none needed)* |

> All pages also load `style.css` + their respective `admin_global.css` or `customer_global.css`, and `main.js`.

---

## 4. System Modules

---

### Module 1 – Authentication & Admin Dashboard
**Owner:** Sahani

Handles all login, logout, session management, and the main admin dashboard that aggregates data across all modules.

#### Admin Pages

| Page | File | Description |
|---|---|---|
| Admin Login | `admin/auth/login.php` | Email + password form; validates credentials against `users` table (role = admin); creates session on success |
| Admin Logout | `admin/auth/logout.php` | Destroys session and all session variables; redirects to login page |
| Admin Dashboard | `admin/dashboard/dashboard.php` | Main landing page after login; shows stat cards (total bookings, pending approvals, total revenue, new customers), recent activity feed, upcoming bookings preview, and payment alerts |
| Admin Profile | `admin/dashboard/admin_profile.php` | View and edit admin's own name, email, phone; change password with current password verification |

#### Customer Pages

| Page | File | Description |
|---|---|---|
| Customer Login | `customer/auth/customer_login.php` | Separate login form for customers; creates customer session (different namespace from admin) |
| Customer Register | `customer/auth/register.php` | Registration form with full name, email, phone, address, password; validates email uniqueness; hashes password before insert |
| Customer Logout | `customer/auth/customer_logout.php` | Destroys customer session; redirects to login page |

#### Shared / Public Pages

| Page | File | Description |
|---|---|---|
| Landing Page | `index.php` | Public home page showing hall overview, packages, and call-to-action buttons to register or login |
| Access Denied | `includes/access_denied.php` | Shown when an unauthenticated user tries to access a protected page |

---

### Module 2 – Hall & Package Management
**Owner:** Riffna

Manages the hall's details, amenities, images, and the hierarchical package system (main packages → sub-packages).

#### Admin Pages

| Page | File | Description |
|---|---|---|
| Manage Hall | `admin/hall/manage_hall.php` | View current hall details (name, capacity, location, size, features, base price, status); entry point for editing |
| Edit Hall | `admin/hall/edit_hall.php` | Form to update hall name, description, capacity, location, size, features (checkboxes: AC, Stage, Parking, etc.), base price, and hall status (available / unavailable / maintenance) |
| Manage Hall Images | `admin/hall/manage_images.php` | Upload new images to hall gallery; set image display order; delete existing images |
| Manage Packages | `admin/packages/manage_packages.php` | List all main packages and their sub-packages with pricing, seat capacity, status; summary stats (total packages, active packages, bookings this month) |
| Add Package | `admin/packages/add_package.php` | Form to create a new package: package name, type (main/sub), parent package (if sub), price, seat capacity, parking capacity, description, inclusions, included services (checkboxes: Catering, AC, Decoration, Wi-Fi, Parking) |
| Edit Package | `admin/packages/edit_package.php` | Pre-filled form to update any package field; toggle active/inactive status |
| Delete Package | `admin/packages/delete_package.php` | Confirmation screen before deleting a package; checks for active bookings using that package before allowing deletion |

#### Customer Pages

| Page | File | Description |
|---|---|---|
| View Hall | `customer/hall/view_hall.php` | Public-facing hall detail page: hall images gallery, description, capacity, location, amenities list, and base price |
| View Packages | `customer/hall/view_packages.php` | Lists all active main packages with their sub-packages, pricing, inclusions, seat capacity, and a "Book Now" button for each sub-package |

---

### Module 3 – Customer Management & Registration
**Owner:** Nishtha

Handles all customer-facing account operations and the admin's ability to manage the customer base.

#### Admin Pages

| Page | File | Description |
|---|---|---|
| Manage Customers | `admin/customers/manage_customers.php` | Paginated list of all registered customers with columns: name, email, phone, registration date, status, total bookings; search by name/email/phone; filter by status |
| Customer Details | `admin/customers/customer_details.php` | Full profile view for a selected customer including personal details, booking history summary, payment records, and feedback given |
| Block / Unblock Customer | `admin/customers/block_customer.php` | Toggle customer account status between active and blocked; blocked customers cannot log in |
| Customer Statistics | `admin/customers/customer_stats.php` | Aggregated data: total registered, new this month, most frequent customers, average bookings per customer (feeds into dashboard) |

#### Customer Pages

| Page | File | Description |
|---|---|---|
| My Profile | `customer/profile/profile.php` | View own profile: full name, email, phone, address, profile picture, member since date |
| Edit Profile | `customer/profile/edit_profile.php` | Form to update name, phone, address, and profile picture upload |
| Change Password | `customer/profile/change_password.php` | Form requiring current password, new password, and confirm password; validates current password before updating |

---

### Module 4 – Booking Management & Feedback
**Owner:** Afrina

The core module managing the entire booking lifecycle from creation to completion, and the post-event feedback system.

#### Admin Pages

| Page | File | Description |
|---|---|---|
| Manage Bookings | `admin/bookings/manage_bookings.php` | Full booking list with calendar view and list view; filters by status (pending / approved / rejected / cancelled / completed), date range, and customer name; search bar |
| Booking Details | `admin/bookings/booking_details.php` | Complete booking record view: customer info, package selected, event date/time, guest count, special requests, amounts (total / advance / balance), current status, payment status, timestamps |
| Approve Booking | `admin/bookings/approve_booking.php` | Confirmation screen to approve a pending booking; updates status to `approved`; triggers payment record creation |
| Reject Booking | `admin/bookings/reject_booking.php` | Form to reject a booking with a mandatory rejection reason; updates status to `rejected`; stores reason in `rejection_reason` field |
| Complete Booking | `admin/bookings/complete_booking.php` | Mark an approved booking as completed after the event date; sets `completed_at` timestamp |
| Admin Add Booking | `admin/bookings/add_booking.php` | Admin can manually create a booking on behalf of a customer; runs availability check before saving |
| Manage Feedback | `admin/feedback/manage_feedback.php` | View all submitted feedback with filters by rating, date, and customer; shows average rating and total reviews |
| View Feedback | `admin/feedback/view_feedback.php` | Full view of a single feedback entry with customer name, booking reference, rating, and comments |
| Toggle Feedback Visibility | `admin/feedback/toggle_feedback.php` | Show or hide a feedback entry from the customer-facing display |

#### Customer Pages

| Page | File | Description |
|---|---|---|
| Book Hall | `customer/bookings/book_hall.php` | Main booking form: select event date (date picker), start/end time, select package, enter event type, guest count, special requests; shows real-time availability check; displays calculated total, advance, and balance amounts before submission |
| My Bookings | `customer/bookings/customer_bookings.php` | Calendar view showing the customer's own bookings with colour-coded status indicators (pending = yellow, approved = green, cancelled = grey) |
| Booking History | `customer/bookings/booking_history.php` | Table view of all past and current bookings with: package name, event date, status, total amount, advance paid, balance due, and action buttons |
| Booking Details (Customer) | `customer/bookings/booking_details.php` | Detailed view of a single booking for the customer; shows full info and current status; includes cancel button if status is pending |
| Cancel Booking | `customer/bookings/cancel_booking.php` | Confirmation form where customer provides a cancellation reason; only available for bookings in `pending` status |
| Submit Feedback | `customer/feedback/submit_feedback.php` | Feedback form with star rating (1–5) and comment text area; only accessible for bookings with `completed` status; one submission per booking enforced |
| My Feedback | `customer/feedback/my_feedback.php` | List of all feedback the customer has previously submitted with their ratings and comments |

---

### Module 5 – Payment Processing & Reports
**Owner:** Nihap

Tracks all financial transactions linked to bookings and generates management reports and analytics.

#### Admin Pages

| Page | File | Description |
|---|---|---|
| Manage Payments | `admin/payments/manage_payments.php` | Full payment list showing: transaction ID, customer name, booking reference, payment type (advance/balance/full), method, amount, status, date; search by customer or date; filter by status and payment method; summary cards (total revenue, pending payments, completed count, refunds) |
| Payment Details | `admin/payments/payment_details.php` | Full view of a single payment record including linked booking info, payment method, transaction reference, timestamps, and complete status change history from the `transactions` table |
| Add Payment | `admin/payments/add_payment.php` | Form for admin to record a new payment: select booking, payment type, enter amount, choose method, add transaction reference and notes |
| Update Payment Status | `admin/payments/update_payment.php` | Change payment status (e.g., pending → paid; paid → refunded); automatically creates a new entry in the `transactions` audit table with who changed it and when |
| Booking Report | `admin/reports/booking_report.php` | Booking summary report with date range filter; shows total bookings, status breakdown, popular event types, peak booking dates |
| Income Report | `admin/reports/income_report.php` | Revenue report with date range filter; shows total income, advance vs balance breakdown, income by payment method, and revenue trend line chart |
| Monthly Performance | `admin/reports/monthly_report.php` | Table view of monthly breakdown: month, total bookings, revenue, average booking value, cancellations, growth percentage |
| Hall Utilization Report | `admin/reports/utilization_report.php` | Shows booking frequency by date and time slot; identifies peak and low-demand periods |
| Customer Statistics Report | `admin/reports/customer_report.php` | New vs returning customers, most frequent bookers, average spending per customer |
| Export Report | `admin/reports/export_report.php` | Export any report as PDF or Excel for a selected date range |

#### Customer Pages

> Customers do not have direct access to payment entry forms. Payment records are created and managed by the admin. Customers can view payment status as part of their booking details.

| Page | File | Description |
|---|---|---|
| Payment Status (via Booking Details) | `customer/bookings/booking_details.php` | Within the booking detail view, customers can see: advance amount paid, balance amount due, payment method used, and current payment status — read-only |

---

## 5. Team Member Assignments

| Member | Module | Key Tables Owned | Integration Dependencies |
|---|---|---|---|
| Sahani | Auth & Admin Dashboard | `users` (admin role) | All modules (reads data for dashboard) |
| Riffna | Hall & Package Management | `hall`, `hall_images`, `packages` | Afrina (booking uses packages), Nihap (reports use hall data) |
| Nishtha | Customer Management | `users` (customer role) | Afrina (booking uses customer_id), Nihap (payment records) |
| Afrina | Booking & Feedback | `bookings`, `feedback` | All modules (central integration point) |
| Nihap | Payment & Reports | `payments`, `transactions` | Afrina (booking triggers payment), Sahani (dashboard stats) |

### Shared Responsibilities

All members are jointly responsible for:
- Phase 1 environment setup and repository configuration
- Creating and running the shared `bookmyhall.sql` database script
- Writing prepared statements for all database queries
- Sanitizing all user inputs before processing
- Attending daily standups and weekly review meetings

---

## 6. Development Phases & Timeline

### Phase 1 – Foundation Setup (Week 8 | Duration: 3–4 days)

**Goal:** Establish a shared, working development environment and project infrastructure.

**Tasks for All Members:**
- Install and configure XAMPP (Apache + MySQL + PHP)
- Clone GitHub repository and create individual feature branches
  - Branch naming: `feature/module-name` (e.g., `feature/bookings`, `feature/payments`)
- Execute shared database script to create `bookmyhall` schema
- Create shared files: `db_connection.php`, `functions.php`, `config.php`
- Set up project folder structure
- Hold kickoff meeting: review module assignments, agree on naming conventions

**Deliverable:** Configured development environment, shared base files, all members pushing to GitHub successfully.

---

### Phase 2 – Core Development (Weeks 9–11 | Duration: ~3 weeks)

**Goal:** Each member builds the core CRUD functionality of their assigned module independently.

#### Sahani – Auth & Dashboard
- [ ] Build admin login form with POST handling
- [ ] Implement `password_verify()` against hashed DB password
- [ ] Create and store PHP sessions on successful login
- [ ] Write `session_check.php` guard function (include on all admin pages)
- [ ] Build logout with complete session destruction
- [ ] Create dashboard layout (HTML/CSS)
- [ ] Add placeholder stat cards (total bookings, pending, revenue, customers)

#### Riffna – Hall & Package Management
- [ ] Create `hall` and `packages` tables (or verify from shared SQL)
- [ ] Build admin: add/edit/view hall details form
- [ ] Implement image upload to `/assets/images/hall/`
- [ ] Build package listing page (show main + sub-packages)
- [ ] Build add/edit/delete package forms
- [ ] Prepare `getPackagesByHall()` function for booking module

#### Nishtha – Customer Management
- [ ] Create customer registration form with validation
- [ ] Implement email duplicate check before insert
- [ ] Hash password with `password_hash(PASSWORD_BCRYPT)`
- [ ] Build customer login form with separate session namespace
- [ ] Create profile view and edit page
- [ ] Build admin customer list with search and filter
- [ ] Implement block/unblock functionality

#### Afrina – Booking & Feedback
- [ ] Create booking form (date picker, package selector, guest count, event type)
- [ ] Write `checkAvailability($date, $start, $end, $package_id)` function
- [ ] Calculate `total_amount`, `advance_amount`, `balance_amount`
- [ ] Insert booking with `status = 'pending'`
- [ ] Build admin booking list with status filters
- [ ] Implement approve/reject actions
- [ ] Build feedback submission form (for completed bookings only)
- [ ] Build admin feedback management view

#### Nihap – Payment & Reports
- [ ] Build payment recording form (linked to booking_id)
- [ ] Implement payment status update with transaction log entry
- [ ] Build admin payment list (searchable, filterable)
- [ ] Write SQL queries for: total revenue, pending count, monthly income
- [ ] Build booking summary report with date range filter
- [ ] Build monthly performance breakdown table

**Deliverable:** All core features functional in isolation with sample data. Each module passes its own unit tests.

---

### Phase 3 – Integration Checkpoint 1 (Week 12)

**Goal:** Verify cross-module data flow and resolve blocking dependencies.

**Tests to Run:**

| Flow | Members Involved |
|---|---|
| Customer registers → logs in → views hall | Nishtha → Sahani → Riffna |
| Customer creates booking → payment recorded | Afrina → Nihap |
| Admin approves booking → availability updates | Sahani → Afrina → Riffna |
| Dashboard shows live stats from all modules | All → Sahani |

**Process:**
1. Each member demos their module (15 min each)
2. Run cross-module test flows together
3. Log all integration bugs in shared Google Sheet
4. Assign fixes with 2-day resolution deadline

---

### Phase 4 – Advanced Features (Weeks 13–14)

**Goal:** Complete remaining features and polish the user experience.

- Sahani: Integrate live stats into dashboard from all modules; implement real-time activity feed
- Riffna: Add search/filter for packages; implement image gallery display on customer side
- Nishtha: Add customer statistics; complete admin export functionality
- Afrina: Add booking calendar view; complete cancellation workflow; admin booking modification
- Nihap: Complete PDF/Excel report export; finalize dashboard data API functions

---

### Phase 5 – Full Integration & Testing (Week 14–15)

**Goal:** End-to-end system testing across all user journeys.

**Complete User Journey Test:**
```
Customer registers (Nishtha)
  → Logs in → Views hall & packages (Riffna)
    → Checks availability → Creates booking (Afrina)
      → Advance payment recorded (Nihap)
        → Admin reviews & approves (Sahani/Afrina)
          → Balance payment recorded (Nihap)
            → Event date passes → Admin marks completed (Afrina)
              → Customer submits feedback (Afrina)
                → Admin views reports & analytics (Nihap)
```

---

### Phase 6 – Bug Fixing, Cleanup & Deployment Prep (Week 15–16)

- Fix all critical and high-severity bugs from testing
- Remove unused/debug code; standardize indentation
- Merge all feature branches into `main`
- Create final sample data for demonstration
- Write database setup instructions
- Prepare project demonstration screenshots

**Deliverable:** Fully tested, documented, and demo-ready system.

---

### Timeline Summary

| Week | Milestone | Status |
|---|---|---|
| Week 8 | Foundation setup complete | Environment, DB, shared files ready |
| Week 10 | Core functions done | Basic CRUD working per module |
| Week 12 | Integration Checkpoint 1 | Modules communicating, major features complete |
| Week 14 | Full integration | All modules working end-to-end |
| Week 15 | Bug fixing & refinement | All critical bugs resolved, system stable |
| Week 16 | Backend complete | Fully tested, documented, demo-ready |

---

## 7. Integration Plan

### 7.1 Shared Functions Library (`/includes/functions.php`)

All members must use these shared functions — do not duplicate logic:

| Function | Purpose | Owner |
|---|---|---|
| `sanitizeInput($data)` | Clean and escape user inputs | All |
| `validateEmail($email)` | Verify email format with regex | Nishtha |
| `hashPassword($password)` | Wrap `password_hash()` | Nishtha |
| `verifyPassword($password, $hash)` | Wrap `password_verify()` | Nishtha |
| `formatDate($date)` | Consistent `Y-m-d` formatting | All |
| `formatCurrency($amount)` | Format as LKR with 2 decimals | Nihap |
| `logActivity($action, $user_id)` | Write to activity log | Sahani |
| `checkAvailability($date, $start, $end, $pkg_id)` | Check booking conflicts | Afrina |
| `getPackagesByHall($hall_id)` | Fetch packages for booking form | Riffna |
| `getCustomerBookings($customer_id)` | Fetch booking history | Afrina |
| `getDashboardStats()` | Aggregate all dashboard counts | Nihap/All |

### 7.2 Database Connection (`/includes/db_connection.php`)

```php
<?php
$host = 'localhost';
$dbname = 'bookmyhall';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed. Please try again later.");
    // Never expose raw error to users
}
?>
```

### 7.3 Session Guard (`/includes/session_guard.php`)

Include at the top of every admin page:

```php
<?php
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /admin/auth/login.php");
    exit();
}
?>
```

### 7.4 Data Flow Between Modules

```
Booking Created (Afrina)
  └─ Reads: customer_id from users (Nishtha)
  └─ Reads: hall_id from hall (Riffna)
  └─ Reads: package_id from packages (Riffna)
  └─ Writes: bookings table

Booking Approved (Afrina/Sahani)
  └─ Triggers: payment record creation (Nihap)
  └─ Updates: availability calendar (Afrina)

Payment Recorded (Nihap)
  └─ Reads: booking_id from bookings (Afrina)
  └─ Writes: payments + transactions tables

Dashboard Loaded (Sahani)
  └─ Reads: booking counts (Afrina)
  └─ Reads: revenue totals (Nihap)
  └─ Reads: customer count (Nishtha)
  └─ Reads: hall status (Riffna)
```

---

## 8. Testing Strategy

### 8.1 Unit Testing (Each Member – Weeks 10–11)

Each member is responsible for testing their own module with the following scenarios:

- Valid inputs (happy path)
- Invalid/empty inputs (boundary/negative)
- Duplicate data (e.g., same email registration)
- SQL injection attempts (verify prepared statements block them)
- Unauthorized access attempts (verify session guard redirects)

### 8.2 Functional Test Cases (Shared – Week 14)

| Test ID | Module | Test Case | Expected Result |
|---|---|---|---|
| TC-AUTH-01 | Auth | Valid admin login | Redirected to dashboard |
| TC-AUTH-02 | Auth | Invalid password | Error message shown, no session created |
| TC-AUTH-03 | Auth | Session timeout after inactivity | Redirected to login page |
| TC-CUST-01 | Customer | Register with new email | Account created successfully |
| TC-CUST-02 | Customer | Register with duplicate email | Error: email already exists |
| TC-HALL-01 | Hall | Admin adds new package | Package visible in listing |
| TC-BOOK-01 | Booking | Book available date | Booking created with status 'pending' |
| TC-BOOK-02 | Booking | Book already-taken date/package | Error: slot unavailable |
| TC-BOOK-03 | Booking | Admin approves booking | Status changes to 'approved' |
| TC-PAY-01 | Payment | Record advance payment | Payment saved, transaction logged |
| TC-PAY-02 | Payment | Update payment status | Transaction audit entry created |
| TC-FB-01 | Feedback | Submit feedback for completed booking | Feedback saved with rating |
| TC-FB-02 | Feedback | Submit feedback for pending booking | Error: not eligible |

### 8.3 Integration Test Flows (Week 14–15)

1. **Full Booking Flow:** Registration → Login → Hall View → Booking → Payment → Approval → Completion → Feedback
2. **Conflict Prevention:** Two bookings for same package at same time — second must be rejected
3. **Dashboard Accuracy:** Verify all stats on dashboard match actual database counts
4. **Report Accuracy:** Cross-verify report totals against raw payment records

### 8.4 Bug Tracking

Use the shared Google Sheet with these fields:

| Field | Description |
|---|---|
| Bug ID | Sequential ID (e.g., BUG-001) |
| Module | Affected module |
| Severity | Critical / High / Medium / Low |
| Description | What the issue is |
| Steps to Reproduce | Exact steps to trigger the bug |
| Expected Behavior | What should happen |
| Actual Behavior | What actually happens |
| Assigned To | Team member responsible for fix |
| Status | Open / In Progress / Resolved |

---

## 9. Coding Standards & Conventions

### 9.1 Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Database tables | Plural, lowercase | `bookings`, `customers` |
| Table columns | snake_case | `booking_date`, `customer_id` |
| PHP functions | camelCase | `getBookingDetails()`, `approveBooking()` |
| PHP files | lowercase with underscores | `manage_bookings.php` |
| Git branches | `feature/module-name` | `feature/payments` |
| CSS classes | kebab-case | `.booking-card`, `.nav-item` |
| JS variables | camelCase | `totalAmount`, `selectedDate` |

### 9.2 Security Practices

All members **must** follow these rules without exception:

1. **Prepared Statements** — Never concatenate user input into SQL queries directly.
   ```php
   // ❌ WRONG
   $sql = "SELECT * FROM users WHERE email = '$email'";

   // ✅ CORRECT
   $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
   $stmt->execute([$email]);
   ```

2. **Password Hashing** — Always use `password_hash()` for storage and `password_verify()` for checking.

3. **Input Sanitization** — Call `sanitizeInput()` on all `$_POST` and `$_GET` values before processing.

4. **Session Security** — Regenerate session ID on login. Destroy session fully on logout.

5. **Never expose errors to users** — Log errors server-side; show generic messages to the front end.

### 9.3 Code Structure

- Maximum function length: ~40 lines (split if longer)
- Each PHP file should have a single responsibility
- All DB queries must include error handling (try/catch or check return value)
- Use comments for complex logic — not obvious operations

---

## 10. Risk Management

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Integration conflicts between modules | Medium | High | Define shared function signatures early; use integration checkpoints |
| Merge conflicts on GitHub | High | Medium | Members work on separate branches; merge only at checkpoints |
| Double booking scenario in production | Low | High | `checkAvailability()` function with database-level time overlap query |
| Session hijacking | Low | High | Use `session_regenerate_id()` on login; HTTPS in production |
| Data loss from missed backups | Low | High | Commit and push to GitHub daily; export DB weekly |
| Member falling behind schedule | Medium | Medium | Communicate early; reassign tasks at weekly meeting if needed |
| Scope creep | Medium | Medium | Stick to defined scope; log additional ideas for future versions |

---

## 11. Deliverables Checklist

### Per Module (Each Member)

- [ ] All planned features implemented and functional
- [ ] All CRUD operations working correctly
- [ ] Input validation on all forms (client + server side)
- [ ] Prepared statements used for all DB queries
- [ ] Error handling implemented (no raw errors shown to users)
- [ ] Module tested with valid, invalid, and edge-case inputs
- [ ] Feature branch merged into `main` after review

### Project-Wide

- [ ] Shared `db_connection.php` and `functions.php` completed
- [ ] Complete `bookmyhall.sql` script runs without errors
- [ ] All modules integrated and passing end-to-end test flows
- [ ] Admin dashboard shows live data from all modules
- [ ] Reports generate accurate figures matching DB records
- [ ] No critical or high-severity bugs remaining open
- [ ] GitHub repository clean with meaningful commit messages
- [ ] Sample/demo data loaded and ready for presentation
- [ ] SRS document updated to reflect final implementation
- [ ] Test case results documented in shared Google Sheet
- [ ] Final project report completed and submitted

---

## Appendix A – Daily Standup Format

Each member posts in the team WhatsApp group every morning:

> **Yesterday:** [What was completed]  
> **Today:** [What will be worked on]  
> **Blockers:** [Any issues blocking progress / help needed]

## Appendix B – Weekly Review Agenda (Fridays)

1. Each member shares progress against plan (5 min each)
2. Discuss and resolve integration blockers
3. Review open bugs in tracking sheet
4. Update timeline if adjustments are needed
5. Confirm priorities for next week

## Appendix C – GitHub Workflow

```bash
# Start new work
git checkout main
git pull origin main
git checkout -b feature/your-module

# Daily commits
git add .
git commit -m "feat(payments): add payment status update logic"
git push origin feature/your-module

# Integration checkpoint merge
git checkout main
git pull origin main
git merge feature/your-module
git push origin main
```

Commit message format: `type(module): short description`  
Types: `feat` | `fix` | `refactor` | `test` | `docs`

---

*This document is a living plan and will be updated as development progresses.*  
*GitHub Repository: https://github.com/NihapMrm/BookMyHall*  
*Trello Board: https://trello.com/b/XRmdAk6H/bookmyhall*