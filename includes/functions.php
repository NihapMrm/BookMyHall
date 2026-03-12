<?php
/**
 * functions.php — Shared utility library for BookMyHall
 * All modules must use these functions; never duplicate logic.
 */

// ─── Input & Output ──────────────────────────────────────────────────────────

/** Strip tags, trim, and HTML-encode a value. */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/** Validate an e-mail address format. */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ─── Password ────────────────────────────────────────────────────────────────

/** Hash a plain-text password with bcrypt. */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

/** Verify a plain-text password against a bcrypt hash. */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// ─── Formatting ──────────────────────────────────────────────────────────────

/** Return a date string formatted as Y-m-d. */
function formatDate(string $date): string
{
    return date('Y-m-d', strtotime($date));
}

/** Return a human-readable date (e.g., March 10, 2026). */
function formatDateReadable(string $date): string
{
    return date('F j, Y', strtotime($date));
}

/** Format a number as LKR currency (e.g., LKR 1,200.00). */
function formatCurrency(float $amount): string
{
    return 'LKR ' . number_format($amount, 2);
}

// ─── Flash Messages ──────────────────────────────────────────────────────────

/** Store a one-time flash message in the session. */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 * Returns an array ['type' => '...', 'message' => '...'] or null.
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Redirect ────────────────────────────────────────────────────────────────

/** Send a Location redirect header and exit. */
function redirect(string $url): void
{
    header("Location: $url");
    exit();
}

// ─── Availability (stub — implemented fully by Afrina in Module 4) ───────────

/**
 * Check whether a sub-package is available for a given date/time range.
 * Returns true if the slot is free, false if a conflict exists.
 */
function checkAvailability(PDO $pdo, string $startDate, string $startTime, string $endTime, int $packageId, string $endDate = ''): bool
{
    if ($endDate === '') $endDate = $startDate;
    try {
        // Conflict if any existing booking's date range overlaps the requested range
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE sub_package_id = ?
               AND event_date <= ?
               AND COALESCE(end_date, event_date) >= ?
               AND status NOT IN ('rejected','cancelled')
               AND is_deleted = 0"
        );
        $stmt->execute([$packageId, $endDate, $startDate]);
        return (int) $stmt->fetchColumn() === 0;
    } catch (PDOException $e) {
        error_log("checkAvailability error: " . $e->getMessage());
        return false;
    }
}

// ─── Dashboard stats (populated by Nihap in Module 5) ───────────────────────

/**
 * Returns aggregate counts/totals for the admin dashboard.
 * Fails gracefully if tables haven't been created yet.
 */
function getDashboardStats(PDO $pdo): array
{
    $stats = [
        'total_bookings'   => 0,
        'pending_approvals' => 0,
        'total_revenue'    => 0.0,
        'new_customers'    => 0,
    ];

    $queries = [
        'total_bookings'    => "SELECT COUNT(*) FROM bookings WHERE is_deleted = 0",
        'pending_approvals' => "SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND is_deleted = 0",
        'total_revenue'     => "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'",
        'new_customers'     => "SELECT COUNT(*) FROM users WHERE role = 'customer'
                                  AND MONTH(created_at) = MONTH(NOW())
                                  AND YEAR(created_at)  = YEAR(NOW())",
    ];

    foreach ($queries as $key => $sql) {
        try {
            $stats[$key] = $pdo->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            // Table doesn't exist yet — leave default 0
        }
    }

    return $stats;
}

// ─── Activity logging (stub — Sahani integrates fully in Module 1 Phase 4) ──

/** Write an action to the activity log (placeholder until log table is built). */
function logActivity(PDO $pdo, string $action, int $userId): void
{
    // Will be wired to an `activity_log` table in Phase 4
    error_log("[Activity] user={$userId} action={$action}");
}

// ─── Package helpers ─────────────────────────────────────────────────────────

/**
 * Fetch all active packages for a hall, grouped by main → sub-packages.
 * Used by: booking form (Afrina, Module 4) and customer packages page (Riffna, Module 2).
 *
 * @return array  Array of main-package rows, each with a 'sub_packages' key
 *                containing its active children. Services are decoded to an array
 *                at 'services_arr' on each sub-package row.
 */
function getPackagesByHall(PDO $pdo, int $hallId): array
{
    $mainStmt = $pdo->prepare(
        "SELECT * FROM packages
         WHERE hall_id = ? AND type = 'main' AND is_active = 1
         ORDER BY package_id ASC"
    );
    $mainStmt->execute([$hallId]);
    $mains = $mainStmt->fetchAll();

    $result = [];
    foreach ($mains as $main) {
        $subStmt = $pdo->prepare(
            "SELECT * FROM packages
             WHERE parent_package_id = ? AND type = 'sub' AND is_active = 1
             ORDER BY price ASC"
        );
        $subStmt->execute([$main['package_id']]);
        $subs = $subStmt->fetchAll();

        foreach ($subs as &$s) {
            $s['services_arr'] = [];
            if (!empty($s['services'])) {
                $decoded = json_decode($s['services'], true);
                if (is_array($decoded)) $s['services_arr'] = $decoded;
            }
        }
        unset($s);

        $result[] = array_merge($main, ['sub_packages' => $subs]);
    }

    return $result;
}
