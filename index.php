<?php
/**
 * index.php  -  Public landing page for BookMyHall.
 * Shows hall overview, packages teaser, and CTA buttons.
 * Module 1  Sahani (landing page shell; hall/package data wired in Module 2)
 */
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn   = isset($_SESSION['customer_id']);
$isAdmin      = isset($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BookMyHall  -  Lee Maridean Banquet Hall</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css" />
    <style>
        /*  Hero  */
        .hero {
            background: linear-gradient(135deg, #1a1f4e 0%, #4d5dfb 100%);
            color: #fff;
            padding: 100px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("<?= BASE_URL ?>/assets/images/hall/hero-bg.jpg") center/cover no-repeat;
            opacity: .15;
        }
        .hero-content { position: relative; max-width: 760px; margin: 0 auto; }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 6px 18px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        .hero h1 { font-size: clamp(32px, 6vw, 56px); font-weight: 800; margin: 0 0 20px; line-height: 1.15; }
        .hero p  { font-size: 18px; opacity: .85; margin: 0 0 36px; line-height: 1.6; }
        .hero-actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: 700;
            background: #fff; color: #4d5dfb; text-decoration: none;
            transition: transform .2s, box-shadow .2s;
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,.2); }
        .btn-hero-outline {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: 600;
            border: 2px solid rgba(255,255,255,.7); color: #fff; text-decoration: none;
            transition: background .2s, border-color .2s;
        }
        .btn-hero-outline:hover { background: rgba(255,255,255,.1); border-color: #fff; }

        /*  Features strip  */
        .features-strip {
            display: flex;
            justify-content: center;
            gap: 0;
            background: #fff;
            border-bottom: 1px solid #eaedf7;
            flex-wrap: wrap;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 36px;
            border-right: 1px solid #eaedf7;
            font-size: 14px;
            font-weight: 500;
        }
        .feature-item:last-child { border-right: none; }
        .feature-item i { font-size: 22px; color: #4d5dfb; }

        /*  Section  */
        .section { padding: 80px 40px; max-width: 1100px; margin: 0 auto; }
        .section-label { font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #4d5dfb; margin-bottom: 12px; }
        .section-title { font-size: clamp(24px, 4vw, 36px); font-weight: 700; margin: 0 0 16px; line-height: 1.25; }
        .section-sub   { font-size: 16px; color: #6c6f83; max-width: 600px; line-height: 1.65; }

        /*  Hall card  */
        .hall-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            margin-top: 48px;
        }
        .hall-image-box {
            background: linear-gradient(135deg, #eef1ff, #c9d0fd);
            border-radius: 20px;
            height: 340px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 96px;
            color: #4d5dfb;
            opacity: .5;
        }
        .hall-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
        .hall-stat  { background: #f5f7fd; border-radius: 12px; padding: 16px 18px; }
        .hall-stat .val  { font-size: 22px; font-weight: 700; }
        .hall-stat .lbl  { font-size: 12px; color: #6c6f83; margin-top: 2px; }

        /*  Package cards  */
        .packages-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; }
        .pkg-card {
            background: #fff;
            border-radius: 18px;
            padding: 28px 24px;
            box-shadow: 0 4px 20px rgba(112,144,176,.12);
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 2px solid transparent;
            transition: border-color .2s, transform .2s;
        }
        .pkg-card:hover { border-color: #4d5dfb; transform: translateY(-4px); }
        .pkg-card.featured { border-color: #4d5dfb; background: linear-gradient(to bottom, #f5f7fd, #fff); }
        .pkg-icon  { font-size: 32px; }
        .pkg-name  { font-size: 18px; font-weight: 700; }
        .pkg-desc  { font-size: 14px; color: #6c6f83; line-height: 1.6; flex: 1; }
        .pkg-price { font-size: 22px; font-weight: 700; color: #4d5dfb; }
        .pkg-price span { font-size: 13px; font-weight: 400; color: #6c6f83; }

        /*  CTA  */
        .cta-section { background: linear-gradient(135deg, #1a1f4e, #4d5dfb); color: #fff; padding: 80px 40px; text-align: center; }
        .cta-section h2 { font-size: 36px; font-weight: 700; margin: 0 0 16px; }
        .cta-section p  { font-size: 17px; opacity: .85; margin: 0 0 36px; }

        /*  Responsive  */
        @media (max-width: 768px) {
            .hero { padding: 70px 20px; }
            .features-strip .feature-item { padding: 16px 20px; }
            .section  { padding: 56px 20px; }
            .hall-card { grid-template-columns: 1fr; }
            .packages-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/customer/includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'Welcome';
$pageSubtitle = 'Lee Maridean Banquet Hall';
include __DIR__ . '/customer/includes/customer_topbar.php';
?>

<div class="c-content-wrapper">

<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fa-solid fa-star"></i> Lee Maridean Banquet Hall
        </div>
        <h1>Your Perfect Event Venue, Booked in Minutes</h1>
        <p>Check real-time availability, choose your package, and secure your reservation  -  all online, all hassle-free.</p>
        <div class="hero-actions">
            <?php if ($isLoggedIn): ?>
                <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn-hero-primary">
                    <i class="fa-solid fa-calendar-plus"></i> Book Your Date
                </a>
                <a href="<?= BASE_URL ?>/customer/hall/view_packages.php" class="btn-hero-outline">
                    <i class="fa-solid fa-box-open"></i> View Packages
                </a>
            <?php elseif ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/admin/dashboard/dashboard.php" class="btn-hero-primary">
                    <i class="fa-solid fa-table-columns"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/customer/auth/register.php" class="btn-hero-primary">
                    <i class="fa-solid fa-user-plus"></i> Get Started  -  Free
                </a>
                <a href="<?= BASE_URL ?>/customer/hall/view_packages.php" class="btn-hero-outline">
                    <i class="fa-solid fa-box-open"></i> View Packages
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!--  Feature strip  -->
<div class="features-strip">
    <div class="feature-item"><i class="fa-solid fa-bolt"></i> Instant Confirmation</div>
    <div class="feature-item"><i class="fa-solid fa-calendar-check"></i> Real-Time Availability</div>
    <div class="feature-item"><i class="fa-solid fa-shield-halved"></i> Secure Booking</div>
    <div class="feature-item"><i class="fa-solid fa-headset"></i> Dedicated Support</div>
</div>

<!-- About the hall -->
<div class="section">
    <p class="section-label">About the Venue</p>
    <h2 class="section-title">Lee Maridean Banquet Hall</h2>
    <p class="section-sub">A premium event space designed for weddings, corporate gatherings, birthday celebrations, and every milestone in between.</p>

    <div class="hall-card">
        <div class="hall-image-box" aria-hidden="true">
            <i class="fa-solid fa-building-columns"></i>
        </div>
        <div>
            <div class="hall-stats">
                <div class="hall-stat"><div class="val">500+</div><div class="lbl">Guest Capacity</div></div>
                <div class="hall-stat"><div class="val">4,000</div><div class="lbl">Sq. Ft. Area</div></div>
                <div class="hall-stat"><div class="val">100+</div><div class="lbl">Parking Slots</div></div>
                <div class="hall-stat"><div class="val">24/7</div><div class="lbl">Support</div></div>
            </div>
            <p style="font-size:15px; color:#6c6f83; line-height:1.7;">
                Fully air-conditioned with a grand stage, professional sound system, dedicated bridal suite,
                ample parking, and expert catering options  -  everything your event deserves.
            </p>
            <a href="<?= BASE_URL ?>/customer/hall/view_hall.php" class="btn btn-outline" style="margin-top:16px;">
                <i class="fa-solid fa-arrow-right"></i> Learn More
            </a>
        </div>
    </div>
</div>


<!-- CTA  -->
<div class="cta-section">
    <h2>Ready to Book Your Date?</h2>
    <p>Join hundreds of happy clients who chose BookMyHall for their special events.</p>
    <?php if ($isLoggedIn): ?>
        <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn-hero-primary">
            <i class="fa-solid fa-calendar-plus"></i> Book Now
        </a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/customer/auth/register.php" class="btn-hero-primary">
            <i class="fa-solid fa-user-plus"></i> Create Free Account
        </a>
    <?php endif; ?>
</div>

<!--  Footer -->
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> BookMyHall  -  Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

</div>
</body>
</html>
