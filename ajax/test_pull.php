<?php
// تست ساده بدون auth و بدون AiAssistant
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

echo "data: " . json_encode(['status' => 'شروع']) . "\n\n";
flush();

// اتصال مستقیم به Ollama (بدون کلاس)
$ch = curl_init("http://localhost:11434/api/pull");
$chunkCount = 0;

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['name' => 'qwen2.5:0.5b', 'stream' => true]),
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
    CURLOPT_TIMEOUT => 30,
]);

curl_exec($ch);
echo "data: " . json_encode(['done' => true, 'chunks' => $chunkCount]) . "\n\n";
