<?php
/**
 * PartoCMS - VirusTotal Scanner
 * Uploads files to VirusTotal for scanning with 70+ antivirus engines
 */

class VirusTotalScanner {

    private $apiKey;
    private $baseUrl = 'https://www.virustotal.com/api/v3';
    private $lastError = null;
    private $maxFileSize = 33554432; // 32 MB
    private $timeout = 90;

    public function __construct($apiKey) {
        $this->apiKey = trim($apiKey);
    }

    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Upload and scan a file
     * @return array ['ok' => bool, 'safe' => bool, 'analysis_id' => string, 'error' => string, 'stats' => array]
     */
    public function scanFile($filePath) {
        if (!file_exists($filePath)) {
            return ['ok' => false, 'safe' => false, 'error' => 'File not found'];
        }

        if (!is_readable($filePath)) {
            return ['ok' => false, 'safe' => false, 'error' => 'File not readable'];
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->maxFileSize) {
            return ['ok' => false, 'safe' => false, 'error' => 'File too large: ' . round($fileSize / 1024 / 1024, 2) . ' MB (max: 32 MB)'];
        }

        // Step 1: Upload to VirusTotal
        $upload = $this->uploadFile($filePath);
        if (empty($upload['ok'])) {
            return $upload;
        }

        $analysisId = $upload['analysis_id'] ?? null;
        if (!$analysisId) {
            return ['ok' => false, 'safe' => false, 'error' => 'No analysis ID returned'];
        }

        // Step 2: Wait for analysis to complete (with polling)
        return $this->waitForAnalysis($analysisId, 90);
    }

    /**
     * Upload file to VirusTotal
     */
    private function uploadFile($filePath) {
        $url = $this->baseUrl . '/files';

        $cfile = new CURLFile($filePath);
        $postData = ['file' => $cfile];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'x-apikey: ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->lastError = 'Network: ' . $err;
            return ['ok' => false, 'safe' => false, 'error' => $this->lastError];
        }

        $data = json_decode($res, true);

        if ($httpCode === 200 || $httpCode === 201) {
            $analysisId = $data['data']['id'] ?? null;
            return ['ok' => true, 'analysis_id' => $analysisId, 'raw' => $data];
        }

        // Handle errors
        if ($httpCode === 401) {
            return ['ok' => false, 'safe' => false, 'error' => 'Invalid API Key (401)'];
        }
        if ($httpCode === 429) {
            return ['ok' => false, 'safe' => false, 'error' => 'Rate limit exceeded (429) - try later'];
        }

        $apiMsg = $data['error']['message'] ?? substr($res, 0, 200);
        $this->lastError = "HTTP $httpCode: " . $apiMsg;
        return ['ok' => false, 'safe' => false, 'error' => $this->lastError];
    }

    /**
     * Wait for analysis to complete
     */
    private function waitForAnalysis($analysisId, $maxWaitSeconds = 90) {
        $start = time();
        $lastStats = null;

        while ((time() - $start) < $maxWaitSeconds) {
            $result = $this->getAnalysis($analysisId);
            if (empty($result['ok'])) {
                return $result;
            }

            $status = $result['status'] ?? 'unknown';
            $lastStats = $result['stats'] ?? [];

            if ($status === 'completed') {
                return $result;
            }

            // Wait before polling again
            sleep(3);
        }

        // Timeout - return last known status
        return [
            'ok' => false,
            'safe' => false, // Fail-closed on timeout
            'status' => 'timeout',
            'error' => 'Analysis timeout - file may still be scanning',
            'stats' => $lastStats,
            'analysis_id' => $analysisId,
        ];
    }

    /**
     * Get analysis result
     */
    private function getAnalysis($analysisId) {
        $url = $this->baseUrl . '/analyses/' . urlencode($analysisId);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'x-apikey: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['ok' => false, 'safe' => false, 'error' => "HTTP $httpCode fetching analysis"];
        }

        $data = json_decode($res, true);
        $attrs = $data['data']['attributes'] ?? [];
        $status = $attrs['status'] ?? 'unknown';
        $stats = $attrs['stats'] ?? [];

        // Determine if file is safe
        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $safe = ($malicious === 0 && $suspicious === 0);

        return [
            'ok' => true,
            'safe' => $safe,
            'status' => $status,
            'stats' => $stats,
            'malicious' => $malicious,
            'suspicious' => $suspicious,
            'analysis_id' => $analysisId,
        ];
    }

    /**
     * Check by file hash (fast - no upload needed for known files)
     */
    public function checkByHash($filePath) {
        if (!file_exists($filePath)) {
            return ['ok' => false, 'safe' => false, 'error' => 'File not found'];
        }

        $sha256 = hash_file('sha256', $filePath);
        $url = $this->baseUrl . '/files/' . $sha256;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'x-apikey: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            // Unknown file - need to upload
            return ['ok' => false, 'safe' => false, 'error' => 'unknown', 'sha256' => $sha256];
        }

        if ($httpCode !== 200) {
            return ['ok' => false, 'safe' => false, 'error' => "HTTP $httpCode"];
        }

        $data = json_decode($res, true);
        $attrs = $data['data']['attributes'] ?? [];
        $stats = $attrs['last_analysis_stats'] ?? [];

        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $safe = ($malicious === 0 && $suspicious === 0);

        return [
            'ok' => true,
            'safe' => $safe,
            'status' => 'cached',
            'stats' => $stats,
            'malicious' => $malicious,
            'suspicious' => $suspicious,
            'sha256' => $sha256,
        ];
    }
}
