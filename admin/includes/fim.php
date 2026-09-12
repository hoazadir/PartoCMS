<?php
/**
 * PartoCMS - لایه امنیتی سطح ۲: تشخیص دستکاری فایل‌ها
 * روش: ایجاد اثر انگشت SHA-512 از تمام فایل‌های PHP
 * سپس مقایسه دوره‌ای با هش ذخیره‌شده
 * بر اساس پروژه WHICH (farahpoor/WHICH)
 */

class FileIntegrityMonitor {
    private $pdo;
    private $rootPath;
    private $hashTable = 'file_hashes';

    public function __construct($pdo, $rootPath = null) {
        $this->pdo = $pdo;
        $this->rootPath = $rootPath ?? realpath(__DIR__ . '/../..');
    }

    // ==================== ایجاد جدول هش ====================
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->hashTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_path VARCHAR(500) NOT NULL,
            file_hash VARCHAR(128) NOT NULL,
            file_size BIGINT DEFAULT 0,
            last_checked DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('clean', 'modified', 'deleted', 'added') DEFAULT 'clean',
            UNIQUE KEY unique_path (file_path(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        return $this->pdo->exec($sql);
    }

    // ==================== اسکن و ایجاد هش اولیه ====================
    public function generateHashes() {
        $this->createTable();
        $files = $this->getAllPhpFiles();
        $count = 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->hashTable} (file_path, file_hash, file_size, last_checked, status)
             VALUES (:path, :hash, :size, NOW(), 'clean')
             ON DUPLICATE KEY UPDATE file_hash = :hash2, file_size = :size2, last_checked = NOW(), status = 'clean'"
        );

        foreach ($files as $file) {
            $relativePath = str_replace($this->rootPath, '', $file);
            $hash = hash_file('sha512', $file);
            $size = filesize($file);
            $stmt->execute([
                ':path' => $relativePath,
                ':hash' => $hash,
                ':size' => $size,
                ':hash2' => $hash,
                ':size2' => $size,
            ]);
            $count++;
        }
        return $count;
    }

    // ==================== بررسی یکپارچگی ====================
    public function checkIntegrity() {
        $files = $this->getAllPhpFiles();
        $currentFiles = [];
        $suspicious = [];

        foreach ($files as $file) {
            $relativePath = str_replace($this->rootPath, '', $file);
            $currentFiles[] = $relativePath;
            $currentHash = hash_file('sha512', $file);

            // جستجوی هش قبلی در دیتابیس
            $stmt = $this->pdo->prepare(
                "SELECT file_hash FROM {$this->hashTable} WHERE file_path = :path LIMIT 1"
            );
            $stmt->execute([':path' => $relativePath]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                // فایل جدید (احتمالاً آپلود شده توسط هکر)
                $suspicious[] = [
                    'file' => $relativePath,
                    'type' => 'ADDED',
                    'message' => 'فایل جدید شناسایی شد — احتمال backdoor',
                ];
                $this->updateStatus($relativePath, 'added', $currentHash);
            } elseif ($row['file_hash'] !== $currentHash) {
                // فایل دستکاری شده
                $suspicious[] = [
                    'file' => $relativePath,
                    'type' => 'MODIFIED',
                    'message' => 'محتوای فایل تغییر کرده — احتمال تزریق کد',
                ];
                $this->updateStatus($relativePath, 'modified', $currentHash);
            }
        }

        // بررسی فایل‌های حذف‌شده
        $stmt = $this->pdo->query(
            "SELECT file_path FROM {$this->hashTable} WHERE status = 'clean'"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fullPath = $this->rootPath . $row['file_path'];
            if (!file_exists($fullPath)) {
                $suspicious[] = [
                    'file' => $row['file_path'],
                    'type' => 'DELETED',
                    'message' => 'فایل حذف شده است',
                ];
                $this->updateStatus($row['file_path'], 'deleted', '');
            }
        }

        return $suspicious;
    }

    // ==================== اسکن برای الگوهای مخرب (Signature Scan) ====================
    public function scanForMaliciousPatterns() {
        $patterns = [
            '/eval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i' => 'Remote Code Execution via eval',
            '/base64_decode\s*\(\s*\$_/i' => 'Obfuscated code injection',
            '/system\s*\(\s*\$_/i' => 'Command injection',
            '/shell_exec\s*\(\s*\$_/i' => 'Shell command injection',
            '/passthru\s*\(\s*\$_/i' => 'Command execution',
            '/preg_replace\s*\(\s*[\'"]\/.*\/e/i' => 'Regex code execution',
            '/file_put_contents\s*\(\s*\$_(GET|POST)/i' => 'File write via user input',
            '/\bassert\s*\(\s*\$_/i' => 'Assert code execution',
            '/\$\{\$.*\}/' => 'Variable variable injection',
            '/create_function\s*\(/i' => 'Deprecated code execution',
            '/<script[^>]*>.*document\.cookie/i' => 'Cookie theft script',
            '/str_rot13\s*\(/i' => 'Obfuscation technique',
            '/gzinflate\s*\(\s*base64_decode/i' => 'Compressed obfuscated payload',
            '/\\\$GLOBALS\[/i' => 'Global variable manipulation',
            '/\binclude\s*\(\s*\$_/i' => 'File inclusion via user input',
            '/\brequire\s*\(\s*\$_/i' => 'File inclusion via user input',
        ];

        $findings = [];
        $files = $this->getAllPhpFiles();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            $relativePath = str_replace($this->rootPath, '', $file);

            foreach ($patterns as $pattern => $description) {
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    // محاسبه شماره خط
                    $lineNum = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                    $findings[] = [
                        'file' => $relativePath,
                        'line' => $lineNum,
                        'pattern' => $description,
                        'snippet' => substr($matches[0][0], 0, 100),
                    ];
                }
            }
        }
        return $findings;
    }

    // ==================== توابع کمکی ====================
    private function getAllPhpFiles() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->rootPath,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'phtml', 'php5', 'php7', 'inc'])) {
                // پوشه‌های قابل اغماض
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') !== false) continue;
                if (strpos($path, '/node_modules/') !== false) continue;
                if (strpos($path, '/.git/') !== false) continue;
                $files[] = $path;
            }
        }
        return $files;
    }

    private function updateStatus($path, $status, $hash) {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->hashTable} SET status = :status, file_hash = :hash, last_checked = NOW()
             WHERE file_path = :path"
        );
        $stmt->execute([':status' => $status, ':hash' => $hash, ':path' => $path]);
    }
}
?>
