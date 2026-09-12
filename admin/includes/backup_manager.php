<?php
/**
 * PartoCMS - Backup Manager
 * مدیریت بکاپ خودکار دیتابیس و فایل‌ها
 */

class BackupManager {

    private $pdo;
    private $backupDir;
    private $keepDays;
    private $dbName;
    private $dbUser;
    private $dbPass;
    private $dbHost;

    public function __construct($pdo, $backupDir = null) {
        $this->pdo = $pdo;
        $this->backupDir = $backupDir ?: __DIR__ . '/../../backups/database';
        $this->keepDays = 30;
        $this->dbName = defined('DB_NAME') ? DB_NAME : '';
        $this->dbUser = defined('DB_USER') ? DB_USER : 'root';
        $this->dbPass = defined('DB_PASS') ? DB_PASS : '';
        $this->dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0700, true);
        }
    }

    /**
     * اجرای بکاپ کامل دیتابیس با mysqldump
     */
    public function backupDatabase() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'db_' . $this->dbName . '_' . $timestamp . '.sql';
        $filepath = $this->backupDir . '/' . $filename;

        // پیدا کردن mysqldump
        $mysqldump = $this->findMysqldump();
        if (!$mysqldump) {
            return ['ok' => false, 'error' => 'mysqldump not found'];
        }

        // ساخت دستور
        $cmd = sprintf(
            'MYSQL_PWD=%s %s --host=%s --user=%s --single-transaction --quick --lock-tables=false %s 2>&1',
            escapeshellarg($this->dbPass),
            escapeshellarg($mysqldump),
            escapeshellarg($this->dbHost),
            escapeshellarg($this->dbUser),
            escapeshellarg($this->dbName)
        );

        // اجرا
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return ['ok' => false, 'error' => 'mysqldump failed: ' . implode("\n", $output)];
        }

        // ذخیره خروجی به فایل
        $sqlContent = implode("\n", $output);
        if (empty($sqlContent)) {
            return ['ok' => false, 'error' => 'mysqldump produced empty output'];
        }

        if (!file_put_contents($filepath, $sqlContent)) {
            return ['ok' => false, 'error' => 'Cannot write backup file'];
        }

        // فشرده‌سازی با gzip
        $gzPath = $filepath . '.gz';
        $fp = fopen($filepath, 'rb');
        $gz = gzopen($gzPath, 'wb9');
        if ($fp && $gz) {
            while (!feof($fp)) {
                gzwrite($gz, fread($fp, 1048576));
            }
            fclose($fp);
            gzclose($gz);
            @unlink($filepath); // حذف نسخه غیر فشرده

            $finalSize = filesize($gzPath);
            return [
                'ok' => true,
                'file' => basename($gzPath),
                'path' => $gzPath,
                'size' => $finalSize,
                'size_human' => $this->humanSize($finalSize),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return ['ok' => false, 'error' => 'Compression failed'];
    }

    /**
     * پیدا کردن mysqldump در سیستم
     */
    private function findMysqldump() {
        $candidates = [
            '/data/data/com.termux/files/usr/bin/mysqldump',
            '/data/data/com.termux/files/usr/bin/mariadb-dump',
            '/usr/bin/mysqldump',
            '/usr/bin/mariadb-dump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // جستجو در PATH
        $output = [];
        exec('which mysqldump 2>/dev/null', $output);
        if (!empty($output[0]) && is_executable($output[0])) {
            return $output[0];
        }

        exec('which mariadb-dump 2>/dev/null', $output);
        if (!empty($output[0]) && is_executable($output[0])) {
            return $output[0];
        }

        return null;
    }

    /**
     * لیست بکاپ‌های موجود
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . '/db_*.sql.gz');
        if (!$files) return $backups;

        rsort($files); // جدیدترین اول

        foreach ($files as $path) {
            $backups[] = [
                'file' => basename($path),
                'path' => $path,
                'size' => filesize($path),
                'size_human' => $this->humanSize(filesize($path)),
                'created_at' => date('Y-m-d H:i:s', filemtime($path)),
                'age_days' => floor((time() - filemtime($path)) / 86400),
            ];
        }
        return $backups;
    }

    /**
     * حذف بکاپ‌های قدیمی
     */
    public function cleanOldBackups() {
        $deleted = 0;
        $freed = 0;
        $cutoff = time() - ($this->keepDays * 86400);

        $files = glob($this->backupDir . '/db_*.sql.gz');
        if (!$files) return ['deleted' => 0, 'freed' => 0];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $freed += filesize($file);
                if (@unlink($file)) $deleted++;
            }
        }

        return ['deleted' => $deleted, 'freed' => $freed, 'freed_human' => $this->humanSize($freed)];
    }

    /**
     * حذف یک بکاپ خاص
     */
    public function deleteBackup($filename) {
        $filename = basename($filename); // جلوگیری از path traversal
        $path = $this->backupDir . '/' . $filename;

        if (!file_exists($path)) {
            return ['ok' => false, 'error' => 'File not found'];
        }

        if (!preg_match('/^db_.+\.sql\.gz$/', $filename)) {
            return ['ok' => false, 'error' => 'Invalid filename'];
        }

        if (@unlink($path)) {
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => 'Cannot delete'];
    }

    /**
     * دانلود بکاپ
     */
    public function downloadBackup($filename) {
        $filename = basename($filename);
        $path = $this->backupDir . '/' . $filename;

        if (!file_exists($path) || !preg_match('/^db_.+\.sql\.gz$/', $filename)) {
            return false;
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        return true;
    }

    /**
     * آمار بکاپ‌ها
     */
    public function getStats() {
        $backups = $this->listBackups();
        $totalSize = 0;
        foreach ($backups as $b) $totalSize += $b['size'];

        $lastBackup = !empty($backups) ? $backups[0] : null;

        return [
            'total_backups' => count($backups),
            'total_size' => $totalSize,
            'total_size_human' => $this->humanSize($totalSize),
            'last_backup' => $lastBackup,
            'last_backup_ago' => $lastBackup ? $this->timeAgo(strtotime($lastBackup['created_at'])) : 'هرگز',
            'keep_days' => $this->keepDays,
        ];
    }

    /**
     * تبدیل بایت به رشته خوانا
     */
    private function humanSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    /**
     * زمان گذشته به صورت خوانا
     */
    private function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'چند لحظه پیش';
        if ($diff < 3600) return floor($diff / 60) . ' دقیقه پیش';
        if ($diff < 86400) return floor($diff / 3600) . ' ساعت پیش';
        if ($diff < 604800) return floor($diff / 86400) . ' روز پیش';
        return date('Y-m-d', $timestamp);
    }
}
