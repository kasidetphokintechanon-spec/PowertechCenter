<?php
// api/actions/dashboard/getDashboardStats.php
// Lightweight totals for the IT Portal home page (it_home.html) stat pills.
// Note: this is a different, simpler endpoint from get_dashboard_stats.php
// (which returns the full monthly/company breakdown for dashboard.html).

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$openTickets = (int)($conn->query(
    "SELECT COUNT(*) c FROM it_logs WHERE status NOT IN ('Completed','Cancelled')"
)->fetch_assoc()['c'] ?? 0);

$totalAssets = (int)($conn->query("SELECT COUNT(*) c FROM it_assets")->fetch_assoc()['c'] ?? 0);

$totalSetups = 0;
$setupCheck = $conn->query("SHOW TABLES LIKE 'setup_records'");
if ($setupCheck && $setupCheck->num_rows > 0) {
    $totalSetups = (int)($conn->query("SELECT COUNT(*) c FROM setup_records")->fetch_assoc()['c'] ?? 0);
}

// IT Links device count — read from the same server-stored config as
// get_it_links_config.php (not localStorage, which it_home.html used to read
// directly and which is no longer the source of truth for that data).
$totalItLinksDevices = 0;
$settingsCheck = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($settingsCheck && $settingsCheck->num_rows > 0) {
    $row = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'it_links_config' LIMIT 1")->fetch_assoc();
    if ($row && $row['setting_value']) {
        $cfg = json_decode($row['setting_value'], true);
        if (is_array($cfg) && isset($cfg['companies'])) {
            foreach ($cfg['companies'] as $company) {
                foreach (($company['sections'] ?? []) as $section) {
                    $totalItLinksDevices += count($section['devices'] ?? []);
                }
            }
        }
    }
}

echo json_encode([
    'status'          => 'success',
    'open_tickets'    => $openTickets,
    'total_assets'    => $totalAssets,
    'total_setups'    => $totalSetups,
    'total_it_links'  => $totalItLinksDevices,
]);
