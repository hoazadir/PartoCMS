<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);
$modelName = trim($_GET['model'] ?? '');
if (empty($modelName) || !preg_match('/^[a-zA-Z0-9._:\/-]+$/', $modelName)) {
    echo "data: " . json_encode(['error' => 'bad model']) . "\n\n";
    exit;
}
$endpoint = 'http://localhost:11434';
echo "data: " . json_encode(['status' => 'connecting']) . "\n\n";
if (ob_get_level() > 0) ob_flush();
flush();
$ch = curl_init("{$endpoint}/api/pull");
$chunkCount = 0;
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['name' => $modelName, 'stream' => true]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$chunkCount) {
        $chunkCount++;
        $lines = explode("\n", trim($data));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $decoded = json_decode($line, true);
            if (!$decoded) continue;
            echo "data: " . json_encode($decoded) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
        return strlen($data);
    },
    CURLOPT_TIMEOUT => 3600,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$result = curl_exec($ch);
$error = curl_error($ch);
if ($result === false) {
    echo "data: " . json_encode(['error' => "curl error"]) . "\n\n";
} else {
    echo "data: " . json_encode(['done' => true, 'chunks' => $chunkCount]) . "\n\n";
}
flush();
