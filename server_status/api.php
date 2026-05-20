<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$configFile = $baseDir . DIRECTORY_SEPARATOR . 'config.json';
$logFile = $baseDir . DIRECTORY_SEPARATOR . 'logs.json';
$stateFile = $baseDir . DIRECTORY_SEPARATOR . 'status_state.json';
$changeFile = $baseDir . DIRECTORY_SEPARATOR . 'status_changes.json';

function readJsonFile($path, $fallback) {
    if (!file_exists($path)) {
        return $fallback;
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if ($data === null) return $fallback;
    return $data;
}

function writeJsonFile($path, $data) {
    $dir = dirname($path);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function appendLogEntry($logFile, $entry) {
    $logs = readJsonFile($logFile, []);
    if (!is_array($logs)) $logs = [];
    $logs[] = $entry;
    if (count($logs) > 500) {
        $logs = array_slice($logs, -500);
    }
    writeJsonFile($logFile, $logs);
}

function appendChangeEntry($changeFile, $entry) {
    $logs = readJsonFile($changeFile, []);
    if (!is_array($logs)) $logs = [];
    $logs[] = $entry;
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    writeJsonFile($changeFile, $logs);
}

function sanitizeHost($host) {
    $host = trim((string)$host);
    if ($host === '') return '';
    if (!preg_match('/^[A-Za-z0-9\.\-:]+$/', $host)) return '';
    return $host;
}

function pingHost($host) {
    $host = sanitizeHost($host);
    if ($host === '') return false;
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $cmd = $isWin
        ? 'ping -n 1 -w 1000 ' . escapeshellarg($host)
        : 'ping -c 1 -W 1 ' . escapeshellarg($host);
    $output = [];
    $status = 1;
    @exec($cmd, $output, $status);
    return $status === 0;
}

function normalizeDetails($details) {
    if (!is_array($details)) return (object)[];
    $isList = array_values($details) === $details;
    if ($isList) return (object)[];
    return $details;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'get_config') {
    $config = readJsonFile($configFile, ['groups' => ['PTA'=>[], 'PT4'=>[], 'PTE'=>[]]]);
    echo json_encode(['status' => 'success', 'data' => $config], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_config') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $profiles = $payload['profiles'] ?? null;
    $groups = $payload['groups'] ?? null;
    if ($profiles === null && $groups === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    $normalizeGroups = function($groupsInput) {
        $out = ['PTA'=>[], 'PT4'=>[], 'PTE'=>[]];
        foreach (['PTA','PT4','PTE'] as $g) {
            $items = $groupsInput[$g] ?? [];
            if (!is_array($items)) $items = [];
            $clean = [];
            foreach ($items as $item) {
                $name = trim((string)($item['name'] ?? ''));
                $ip = trim((string)($item['ip'] ?? ''));
                $type = strtolower(trim((string)($item['type'] ?? 'server')));
                if ($type === 'printer') $type = 'print';
                if (!in_array($type, ['server', 'hyperv', 'print', 'cctv', 'internet'], true)) $type = 'server';
                $details = normalizeDetails($item['details'] ?? null);
                if ($name === '' && $ip === '') continue;
                $clean[] = ['name' => $name, 'ip' => $ip, 'type' => $type, 'details' => $details];
            }
            $out[$g] = $clean;
        }
        return $out;
    };

    $normalized = ['profiles' => ['PTA'=>['groups'=>['PTA'=>[], 'PT4'=>[], 'PTE'=>[]]], 'PT4'=>['groups'=>['PTA'=>[], 'PT4'=>[], 'PTE'=>[]]], 'PTE'=>['groups'=>['PTA'=>[], 'PT4'=>[], 'PTE'=>[]]]]];
    if (is_array($profiles)) {
        foreach (['PTA','PT4','PTE'] as $p) {
            $profileGroups = $profiles[$p]['groups'] ?? ($profiles[$p] ?? []);
            if (!is_array($profileGroups)) $profileGroups = [];
            $normalized['profiles'][$p]['groups'] = $normalizeGroups($profileGroups);
        }
    } elseif (is_array($groups)) {
        $normalized['profiles']['PTA']['groups'] = $normalizeGroups($groups);
    }

    writeJsonFile($configFile, $normalized);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'get_logs') {
    $logs = readJsonFile($logFile, []);
    if (!is_array($logs)) $logs = [];
    $limit = intval($_GET['limit'] ?? 200);
    if ($limit < 1) $limit = 1;
    if ($limit > 1000) $limit = 1000;
    $logs = array_slice(array_reverse($logs), 0, $limit);
    echo json_encode(['status' => 'success', 'data' => $logs], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_changes') {
    $logs = readJsonFile($changeFile, []);
    if (!is_array($logs)) $logs = [];
    $limit = intval($_GET['limit'] ?? 200);
    if ($limit < 1) $limit = 1;
    if ($limit > 1000) $limit = 1000;
    $logs = array_slice(array_reverse($logs), 0, $limit);
    echo json_encode(['status' => 'success', 'data' => $logs], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'check_group') {
    $group = strtoupper(trim($_GET['group'] ?? 'ALL'));
    $profile = strtoupper(trim($_GET['profile'] ?? ''));
    $config = readJsonFile($configFile, ['profiles' => []]);
    $state = readJsonFile($stateFile, []);
    if (!is_array($state)) $state = [];
    $groups = ['PTA'=>[], 'PT4'=>[], 'PTE'=>[]];
    if ($profile && isset($config['profiles'][$profile])) {
        $groups = $config['profiles'][$profile]['groups'] ?? $groups;
    } elseif (isset($config['groups'])) {
        $groups = $config['groups'];
    }
    $targets = $group === 'ALL' ? ['PTA','PT4','PTE'] : [$group];
    $checkedAt = date('c');
    $resultPayload = ['groups' => [], 'time' => $checkedAt, 'profile' => $profile ?: null];

    foreach ($targets as $g) {
        if (!isset($groups[$g])) continue;
        $items = is_array($groups[$g]) ? $groups[$g] : [];
        $results = [];
        foreach ($items as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $ip = trim((string)($item['ip'] ?? ''));
            $type = strtolower(trim((string)($item['type'] ?? 'server')));
            if ($type === 'printer') $type = 'print';
            if (!in_array($type, ['server', 'hyperv', 'print', 'cctv', 'internet'], true)) $type = 'server';
            $details = normalizeDetails($item['details'] ?? null);
            $online = $ip !== '' ? pingHost($ip) : false;
            $results[] = ['name' => $name, 'ip' => $ip, 'type' => $type, 'details' => $details, 'online' => $online, 'checkedAt' => $checkedAt];
            $key = ($profile ?: '-') . '|' . $g . '|' . $name . '|' . $ip . '|' . $type;
            $prev = $state[$key] ?? null;
            if ($prev !== null && $prev !== $online) {
                appendChangeEntry($changeFile, [
                    'time' => $checkedAt,
                    'profile' => $profile ?: null,
                    'group' => $g,
                    'name' => $name,
                    'ip' => $ip,
                    'type' => $type,
                    'from' => $prev ? 'ONLINE' : 'OFFLINE',
                    'to' => $online ? 'ONLINE' : 'OFFLINE'
                ]);
            }
            $state[$key] = $online;
        }
        $resultPayload['groups'][$g] = $results;
        appendLogEntry($logFile, ['time' => $checkedAt, 'group' => $g, 'results' => $results, 'profile' => $profile ?: null, 'event' => 'check']);
    }
    writeJsonFile($stateFile, $state);

    echo json_encode(['status' => 'success', 'data' => $resultPayload], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
