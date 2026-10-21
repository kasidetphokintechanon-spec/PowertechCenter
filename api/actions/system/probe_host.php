<?php
header('Content-Type: application/json');

// This action never reads or writes $_SESSION, but PHP's default file-based session
// handler keeps an exclusive lock on the session for the whole request. checkAll() in
// it_links.html fires one probe per device concurrently from the same browser session —
// without releasing the lock immediately, those requests queue up and run one at a time
// server-side (each taking up to ~1-1.5s), turning a 1-second parallel check into many
// seconds serialized. Closing the session here lets them actually run in parallel.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$ip       = $_GET['ip']   ?? '';
$port     = (int)($_GET['port'] ?? 0);
$hasPort  = ($port >= 1 && $port <= 65535);

function isPrivateIPv4(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    // Fails validation under NO_PRIV_RANGE|NO_RES_RANGE exactly when the IP IS private/reserved —
    // restrict probing to internal LAN ranges so this endpoint can't be used as an open port scanner
    // against arbitrary public hosts.
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

if (!isPrivateIPv4($ip)) {
    echo json_encode(['online' => false, 'error' => 'invalid_or_non_private_ip']);
    exit;
}

$online = false;

if ($hasPort) {
    // A specific port was requested (e.g. AD=3389, LDAP=389) — trust the socket result only.
    // Do NOT fall back to ICMP ping here: the host can be up while the service on that port
    // is down, and falling back to ping would silently mask that as "online".
    $fp = @fsockopen($ip, $port, $errno, $errstr, 1.5);
    if ($fp) { fclose($fp); $online = true; }
} else {
    // No port specified — the IT Links UI describes this as "HTTP probe" (most devices here
    // are NAS/printer web admin panels), so try TCP 80 first. This is also more reliable than
    // ICMP ping in practice: production has shown ping failing via exec() (disabled, sandboxed,
    // or ICMP blocked outbound) even when the same host is clearly reachable over TCP.
    $fp = @fsockopen($ip, 80, $errno, $errstr, 1.5);
    if ($fp) {
        fclose($fp);
        $online = true;
    } elseif (function_exists('exec')) {
        // Fallback for devices with no web port at all (e.g. AD servers, network gear) —
        // general "is the host up" check via ICMP ping.
        $escaped = escapeshellarg($ip);
        // Windows: ping -n 1 -w 1000   |   Linux: ping -c 1 -W 1
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("ping -n 1 -w 1000 $escaped", $out, $code);
        } else {
            exec("ping -c 1 -W 1 $escaped", $out, $code);
        }
        $online = ($code === 0);
    }
}

echo json_encode(['online' => $online]);
exit;
