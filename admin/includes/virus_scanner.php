<?php
/**
 * PartoCMS - لایه امنیتی سطح ۳: اسکن آنتی‌ویروس فایل‌های آپلودی
 * استفاده از Cloudmersive Virus Scan API (رایگان - ۸۰۰ درخواست در ماه)
 * اسکن بیش از ۱۷ میلیون ویروس و تهدید شناخته‌شده
 */

class VirusScanner {
    private $apiKey;
    private $apiUrl = 'https://api.cloudmersive.com/virus/scan/file';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * اسکن یک فایل آپلودشده
     * @param string $filePath مسیر فایل روی سرور
     * @return array نتیجه اسکن
     */
    public function scanFile($filePath) {
        if (!file_exists($filePath)) {
            return ['clean' => false, 'error' => 'فایل پیدا نشد'];
        }

        $fileSize = filesize($filePath);
        if ($fileSize > 50 * 1024 * 1024) { // ۵۰ مگابایت
            return ['clean' => false, 'error' => 'فایل بیش از حد بزرگ است'];
        }

        // آماده‌سازی درخواست
        $cfile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));
        $postData = [
            'inputFile' => $cfile,
            'allowExecutables' => 'false',    // فایل اجرایی ممنوع
            'allowInvalidFiles' => 'false',   // فایل نامعتبر ممنوع
            'allowScripts' => 'false',        // اسکریپت ممنوع
            'allowPasswordProtectedFiles' => 'false',
            'allowMacros' => 'false',
            'allowXmlExternalEntities' => 'false',
            'allowInsecureDeserialization' => 'false',
            'allowHtml' => 'false',
            'allowUnsafeArchives' => 'false',
            'allowOleEmbeddedObject' => 'false',
            'restrictFileTypes' => 'true',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Apikey: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'clean' => false,
                'error' => 'خطای API - کد وضعیت: ' . $httpCode,
                'raw' => $response,
            ];
        }

        $result = json_decode($response, true);
        return $this->parseResult($result);
    }

    /**
     * تحلیل نتیجه API
     */
    private function parseResult($result) {
        $threats = [];

        if (!empty($result['FoundViruses'])) {
            foreach ($result['FoundViruses'] as $virus) {
                $threats[] = 'ویروس: ' . ($virus['VirusName'] ?? 'ناشناخته');
            }
        }
        if (!empty($result['ContainsExecutable']) && $result['ContainsExecutable']) {
            $threats[] = 'فایل اجرایی شناسایی شد';
        }
        if (!empty($result['ContainsScript']) && $result['ContainsScript']) {
            $threats[] = 'اسکریپت مخرب شناسایی شد';
        }
        if (!empty($result['ContainsMacros']) && $result['ContainsMacros']) {
            $threats[] = 'ماکرو مخرب شناسایی شد';
        }
        if (!empty($result['ContainsInsecureDeserialization'])) {
            $threats[] = 'Deserialization ناامن';
        }
        if (!empty($result['ContainsXmlExternalEntities'])) {
            $threats[] = 'XXE Attack شناسایی شد';
        }

        $isClean = !empty($result['CleanResult']) && $result['CleanResult'] === true;

        return [
            'clean' => $isClean,
            'threats' => $threats,
            'verified_type' => $result['VerifiedFileFormat'] ?? 'نامشخص',
            'raw' => $result,
        ];
    }
}
?>
