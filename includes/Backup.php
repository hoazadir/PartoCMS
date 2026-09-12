<?php
/**
 * Backup - سیستم پشتیبان‌گیری و بازیابی
 */
class Backup {
    private $pdo;
    private $backupDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->backupDir = __DIR__ . '/../assets/backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * ساخت پشتیبان از دیتابیس
     */
    public function backupDatabase($description = '') {
        $filename = 'db_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $filepath = $this->backupDir . $filename;

        $tables = $this->getTables();
        $sql = $this->generateSqlDump($tables);

        if (file_put_contents($filepath, $sql) === false) {
            throw new Exception('خطا در نوشتن فایل پشتیبان');
        }

        // محاسبه تعداد رکوردها
        $recordsCount = 0;
        foreach ($tables as $table) {
            try {
                $recordsCount += (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            } catch (Exception $e) {}
        }

        // ذخیره در دیتابیس
        $stmt = $this->pdo->prepare("
            INSERT INTO backups (filename, filepath, type, size, tables_count, records_count, description, created_by, created_at)
            VALUES (?, ?, 'database', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $filename,
            'assets/backups/' . $filename,
            filesize($filepath),
            count($tables),
            $recordsCount,
            $description,
            $_SESSION['user_id'] ?? null
        ]);

        $backupId = $this->pdo->lastInsertId();

        // لاگ
        if (function_exists('getSecurity')) {
            getSecurity()->log('backup_created', 'backup', $backupId, 'پشتیبان دیتابیس ساخته شد');
        }

        return [
            'id' => $backupId,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'tables' => count($tables),
            'records' => $recordsCount,
        ];
    }

    /**
     * ساخت پشتیبان کامل (فایل‌ها + دیتابیس)
     */
    public function backupFull($description = '') {
        if (!class_exists('ZipArchive')) {
            throw new Exception('افزونه ZipArchive نصب نیست');
        }

        $filename = 'full_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
        $filepath = $this->backupDir . $filename;

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('خطا در ساخت فایل ZIP');
        }

        // ۱. دیتابیس
        $tables = $this->getTables();
        $sql = $this->generateSqlDump($tables);
        $zip->addFromString('database.sql', $sql);

        // ۲. فایل‌های پروژه
        $projectRoot = __DIR__ . '/../';
        $excludeDirs = ['assets/backups', 'node_modules', '.git', 'vendor'];
        
        $this->addDirectoryToZip($zip, $projectRoot, '', $excludeDirs, $projectRoot);

        // ۳. اطلاعات پشتیبان
        $info = "GrapesJS CMS Backup\n";
        $info .= "تاریخ: " . date('Y-m-d H:i:s') . "\n";
        $info .= "تعداد جداول: " . count($tables) . "\n";
        $info .= "نسخه PHP: " . phpversion() . "\n";
        $info .= "آدرس سایت: " . SITE_URL . "\n";
        $zip->addFromString('backup-info.txt', $info);

        $zip->close();

        // محاسبه تعداد رکوردها
        $recordsCount = 0;
        foreach ($tables as $table) {
            try {
                $recordsCount += (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            } catch (Exception $e) {}
        }

        // ذخیره در دیتابیس
        $stmt = $this->pdo->prepare("
            INSERT INTO backups (filename, filepath, type, size, tables_count, records_count, description, created_by, created_at)
            VALUES (?, ?, 'full', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $filename,
            'assets/backups/' . $filename,
            filesize($filepath),
            count($tables),
            $recordsCount,
            $description,
            $_SESSION['user_id'] ?? null
        ]);

        $backupId = $this->pdo->lastInsertId();

        if (function_exists('getSecurity')) {
            getSecurity()->log('backup_full_created', 'backup', $backupId, 'پشتیبان کامل ساخته شد');
        }

        return [
            'id' => $backupId,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'tables' => count($tables),
            'records' => $recordsCount,
        ];
    }

    /**
     * افزودن پوشه به ZIP به صورت بازگشتی
     */
    private function addDirectoryToZip($zip, $dir, $basePath, $excludeDirs, $projectRoot) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $fullPath = $dir . '/' . $file;
            $relativePath = $basePath . $file;

            // بررسی پوشه‌های استثنا
            $skip = false;
            foreach ($excludeDirs as $ex) {
                if (strpos($relativePath, $ex) === 0 || strpos($fullPath, $ex) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (is_dir($fullPath)) {
                $this->addDirectoryToZip($zip, $fullPath, $relativePath . '/', $excludeDirs, $projectRoot);
            } else {
                $zip->addFile($fullPath, $relativePath);
            }
        }
    }

    /**
     * دریافت لیست جداول
     */
    private function getTables() {
        $tables = [];
        $result = $this->pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    /**
     * تولید SQL Dump
     */
    private function generateSqlDump($tables) {
        $sql = "-- GrapesJS CMS Backup\n";
        $sql .= "-- تاریخ: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- تعداد جداول: " . count($tables) . "\n";
        $sql .= "-- نسخه PHP: " . phpversion() . "\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $sql .= $this->dumpTable($table);
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    /**
     * Dump یک جدول
     */
    private function dumpTable($table) {
        $sql = "--\n-- Table: `{$table}`\n--\n\n";

        // ساختار
        $create = $this->pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $create[1] . ";\n\n";

        // داده‌ها
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($count > 0) {
            // دسته‌بندی برای جلوگیری از timeout در جداول بزرگ
            $batchSize = 500;
            $offset = 0;

            while ($offset < $count) {
                $rows = $this->pdo->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    // گرفتن نام ستون‌ها
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';

                    foreach ($rows as $row) {
                        $values = array_map(function($v) {
                            if ($v === null) return 'NULL';
                            return $this->pdo->quote($v);
                        }, $row);

                        $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }

                $offset += $batchSize;
            }
        }

        $sql .= "\n";
        return $sql;
    }

    /**
     * دریافت لیست پشتیبان‌ها
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT b.*, u.username as creator_name
            FROM backups b
            LEFT JOIN users u ON u.id = b.created_by
            ORDER BY b.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * دریافت یک پشتیبان
     */
    public function get($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM backups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * حذف پشتیبان
     */
    public function delete($id) {
        $backup = $this->get($id);
        if (!$backup) return false;

        $filepath = __DIR__ . '/../' . $backup['filepath'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $stmt = $this->pdo->prepare("DELETE FROM backups WHERE id = ?");
        $stmt->execute([$id]);

        if (function_exists('getSecurity')) {
            getSecurity()->log('backup_deleted', 'backup', $id, 'پشتیبان حذف شد: ' . $backup['filename']);
        }

        return true;
    }

    /**
     * بازیابی از فایل SQL
     */
    public function restoreDatabase($backupId) {
        $backup = $this->get($backupId);
        if (!$backup) throw new Exception('پشتیبان یافت نشد');

        $filepath = __DIR__ . '/../' . $backup['filepath'];
        if (!file_exists($filepath)) throw new Exception('فایل پشتیبان وجود ندارد');

        $sql = file_get_contents($filepath);
        if ($sql === false) throw new Exception('خطا در خواندن فایل');

        // تقسیم به دستورات
        $statements = $this->splitSql($sql);

        // غیرفعال کردن FK
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $executed = 0;
        $errors = [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;

            try {
                $this->pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                $errors[] = substr($stmt, 0, 100) . '... → ' . $e->getMessage();
            }
        }

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        if (function_exists('getSecurity')) {
            getSecurity()->log('backup_restored', 'backup', $backupId, 'پشتیبان بازیابی شد');
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
        ];
    }

    /**
     * تقسیم SQL به دستورات
     */
    private function splitSql($sql) {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $escape = false;

        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($escape) {
                $current .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escape = true;
                continue;
            }

            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($inString && $char === $stringChar) {
                $inString = false;
                $stringChar = '';
                $current .= $char;
                continue;
            }

            if (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            // حذف کامنت‌های خطی
            if (!$inString && $char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            $current .= $char;
        }

        if (trim($current)) $statements[] = $current;

        return $statements;
    }

    /**
     * آپلود و بازیابی فایل SQL
     */
    public function uploadAndRestore($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطا در آپلود فایل (کد ' . $file['error'] . ')');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'txt'])) {
            throw new Exception('فقط فایل SQL مجاز است');
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception('حجم فایل بیش از ۵۰ مگابایت است');
        }

        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false) throw new Exception('خطا در خواندن فایل');

        $statements = $this->splitSql($sql);

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $executed = 0;
        $errors = [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;

            try {
                $this->pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                $errors[] = substr($stmt, 0, 80) . '... → ' . $e->getMessage();
            }
        }

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        if (function_exists('getSecurity')) {
            getSecurity()->log('backup_uploaded', null, null, 'فایل پشتیبان آپلود و بازیابی شد');
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
        ];
    }

    /**
     * حذف پشتیبان‌های قدیمی
     */
    public function cleanOld($keepDays = 30) {
        $stmt = $this->pdo->prepare("
            SELECT id, filepath FROM backups 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$keepDays]);
        $old = $stmt->fetchAll();

        $count = 0;
        foreach ($old as $b) {
            $this->delete($b['id']);
            $count++;
        }

        return $count;
    }

    /**
     * فرمت حجم فایل
     */
    public static function formatSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 2) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }
}
