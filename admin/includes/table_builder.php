<?php
/**
 * PartoCMS - Table Builder
 * مدیریت ساخت و ویرایش جدول‌های دیتابیس
 *
 * @version 1.0
 * @date 2026-09-17
 */
class TableBuilder {

    private $pdo;
    private $dbName;

    // کلمات رزروشده MySQL که نمی‌تونه اسم جدول/ستون باشه
    private $reservedWords = [
        'select','insert','update','delete','from','where','table','database',
        'index','key','primary','foreign','unique','references','order','group',
        'by','having','limit','offset','join','inner','outer','left','right',
        'on','as','and','or','not','null','default','int','varchar','text',
        'char','date','time','datetime','timestamp','decimal','float','double',
        'create','alter','drop','truncate','user','password','file','data',
    ];

    // انواع داده پشتیبانی‌شده
    public static $columnTypes = [
        'INT' => 'عدد صحیح',
        'BIGINT' => 'عدد بزرگ',
        'VARCHAR' => 'متن کوتاه',
        'TEXT' => 'متن بلند',
        'LONGTEXT' => 'متن خیلی بلند',
        'DATE' => 'تاریخ',
        'TIME' => 'ساعت',
        'DATETIME' => 'تاریخ و ساعت',
        'TIMESTAMP' => 'Timestamp',
        'DECIMAL' => 'اعشار دقیق',
        'FLOAT' => 'اعشار اعشاری',
        'TINYINT' => 'عدد کوچک',
        'TINYINT(1)' => 'بولین (0/1)',
        'ENUM' => 'لیست انتخاب',
        'JSON' => 'JSON',
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        try {
            $this->dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        } catch (Throwable $e) {
            $this->dbName = DB_NAME;
        }
    }

    // ============================================================
    //   لیست جدول‌ها
    // ============================================================

    public function getTables(): array {
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $result = [];
            foreach ($tables as $t) {
                $info = $this->getTableInfo($t);
                if ($info) $result[] = $info;
            }
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getTableInfo(string $table): ?array {
        try {
            // تعداد ردیف
            $rowCount = $this->pdo->query("SELECT COUNT(*) FROM `" . $this->escape($table) . "`")->fetchColumn();

            // اندازه
            $stmt = $this->pdo->prepare("
                SELECT 
                    TABLE_ROWS,
                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024), 2) AS size_kb,
                    ENGINE,
                    TABLE_COLLATION,
                    CREATE_TIME,
                    UPDATE_TIME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ");
            $stmt->execute([$this->dbName, $table]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'name' => $table,
                'rows' => (int)$rowCount,
                'size_kb' => (float)($meta['size_kb'] ?? 0),
                'engine' => $meta['ENGINE'] ?? '—',
                'collation' => $meta['TABLE_COLLATION'] ?? '—',
                'created' => $meta['CREATE_TIME'] ?? null,
                'updated' => $meta['UPDATE_TIME'] ?? null,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    // ============================================================
    //   ساختار ستون‌ها
    // ============================================================

    public function getColumns(string $table): array {
        try {
            $stmt = $this->pdo->query("SHOW FULL COLUMNS FROM `" . $this->escape($table) . "`");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexes
            $indexes = [];
            $stmtIdx = $this->pdo->query("SHOW INDEX FROM `" . $this->escape($table) . "`");
            foreach ($stmtIdx->fetchAll(PDO::FETCH_ASSOC) as $idx) {
                $col = $idx['Column_name'];
                if (!isset($indexes[$col])) $indexes[$col] = [];
                $indexes[$col][] = [
                    'name' => $idx['Key_name'],
                    'unique' => $idx['Non_unique'] == 0,
                    'primary' => $idx['Key_name'] === 'PRIMARY',
                ];
            }

            foreach ($cols as &$c) {
                $c['indexes'] = $indexes[$c['Field']] ?? [];
            }

            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getRowCount(string $table): int {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM `" . $this->escape($table) . "`")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function getRows(string $table, int $limit = 25, int $offset = 0, string $orderBy = '', string $orderDir = 'ASC'): array {
        try {
            $sql = "SELECT * FROM `" . $this->escape($table) . "`";

            if ($orderBy) {
                $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
                $sql .= " ORDER BY `" . $this->escape($orderBy) . "` $orderDir";
            }

            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    // ============================================================
    //   ساخت جدول
    // ============================================================

    /**
     * ساخت جدول جدید
     *
     * @param string $tableName
     * @param array $columns  آرایه‌ای از:
     *   [
     *     'name' => 'id',
     *     'type' => 'INT',
     *     'length' => 11,        // اختیاری
     *     'nullable' => false,
     *     'default' => null,
     *     'auto_increment' => true,
     *     'primary' => true,
     *     'unique' => false,
     *     'index' => false,
     *     'unsigned' => false,
     *     'enum_values' => [],   // برای ENUM
     *     'comment' => '',
     *   ]
     */
    public function createTable(string $tableName, array $columns): array {
        // اعتبارسنجی نام جدول
        $check = $this->validateIdentifier($tableName, 'table');
        if (!$check['ok']) return $check;

        if (empty($columns)) {
            return ['ok' => false, 'error' => 'حداقل یک ستون الزامی است'];
        }

        // ساخت SQL
        $colDefs = [];
        $primaryKey = null;
        $indexes = [];
        $uniques = [];

        foreach ($columns as $i => $col) {
            $colCheck = $this->validateColumn($col);
            if (!$colCheck['ok']) {
                return ['ok' => false, 'error' => "ستون #$i: " . $colCheck['error']];
            }

            $name = $col['name'];
            $type = strtoupper($col['type']);

            // تعریف نوع
            $sqlType = $type;
            if (!empty($col['length']) && in_array($type, ['INT','BIGINT','VARCHAR','DECIMAL','FLOAT','TINYINT'])) {
                $len = (int)$col['length'];
                if ($type === 'DECIMAL' && !empty($col['decimals'])) {
                    $sqlType .= "($len," . (int)$col['decimals'] . ")";
                } else {
                    $sqlType .= "($len)";
                }
            } elseif ($type === 'ENUM' && !empty($col['enum_values'])) {
                $vals = array_map(fn($v) => $this->pdo->quote(trim($v)), $col['enum_values']);
                $sqlType = "ENUM(" . implode(',', $vals) . ")";
            }

            if (!empty($col['unsigned'])) {
                $sqlType .= " UNSIGNED";
            }

            $def = "`" . $this->escape($name) . "` $sqlType";

            // NULL / NOT NULL
            $def .= !empty($col['nullable']) ? " NULL" : " NOT NULL";

            // DEFAULT
            if (isset($col['default']) && $col['default'] !== '' && $col['default'] !== null) {
                $default = $col['default'];
                // مقادیر خاص
                if (in_array(strtoupper($default), ['CURRENT_TIMESTAMP', 'NOW()'])) {
                    $def .= " DEFAULT CURRENT_TIMESTAMP";
                } elseif (is_numeric($default) && !in_array($type, ['VARCHAR','TEXT','LONGTEXT','DATE','TIME','DATETIME','ENUM'])) {
                    $def .= " DEFAULT " . $this->pdo->quote($default);
                } else {
                    $def .= " DEFAULT " . $this->pdo->quote($default);
                }
            }

            // AUTO_INCREMENT
            if (!empty($col['auto_increment'])) {
                $def .= " AUTO_INCREMENT";
                $primaryKey = $name;
            }

            // PRIMARY
            if (!empty($col['primary']) && !$primaryKey) {
                $primaryKey = $name;
            }

            // UNIQUE
            if (!empty($col['unique'])) {
                $uniques[] = $name;
            }

            // INDEX (غیر یکتا)
            if (!empty($col['index']) && empty($col['unique'])) {
                $indexes[] = $name;
            }

            // COMMENT
            if (!empty($col['comment'])) {
                $def .= " COMMENT " . $this->pdo->quote($col['comment']);
            }

            $colDefs[] = $def;
        }

        // Primary key
        if ($primaryKey) {
            $colDefs[] = "PRIMARY KEY (`" . $this->escape($primaryKey) . "`)";
        }

        // Uniques
        foreach ($uniques as $u) {
            $colDefs[] = "UNIQUE KEY `idx_" . $this->escape($u) . "` (`" . $this->escape($u) . "`)";
        }

        // Indexes
        foreach ($indexes as $ix) {
            $colDefs[] = "KEY `idx_" . $this->escape($ix) . "` (`" . $this->escape($ix) . "`)";
        }

        $sql = "CREATE TABLE `" . $this->escape($tableName) . "` (\n    " . implode(",\n    ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
            return [
                'ok' => true,
                'table' => $tableName,
                'sql' => $sql,
                'columns' => count($columns),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'sql' => $sql,
            ];
        }
    }

    // ============================================================
    //   حذف جدول
    // ============================================================

    public function dropTable(string $table): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        // جدول‌های محافظت‌شده
        $protected = [
            'users','roles','permissions','languages','settings',
            'content_items','categories','comments','modules',
        ];
        if (in_array($table, $protected)) {
            return ['ok' => false, 'error' => 'این جدول محافظت‌شده است و حذف نمی‌شود'];
        }

        try {
            $this->pdo->exec("DROP TABLE `" . $this->escape($table) . "`");
            return ['ok' => true, 'table' => $table];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================
    //   Export
    // ============================================================

    public function exportTable(string $table, string $format = 'sql'): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        try {
            $createStmt = $this->pdo->query("SHOW CREATE TABLE `" . $this->escape($table) . "`")->fetch(PDO::FETCH_ASSOC);
            $createSQL = $createStmt['Create Table'] ?? '';

            $rows = $this->pdo->query("SELECT * FROM `" . $this->escape($table) . "`")->fetchAll(PDO::FETCH_ASSOC);
            $cols = $this->getColumns($table);
            $colNames = array_column($cols, 'Field');

            if ($format === 'sql') {
                $sql = "-- Export of $table\n";
                $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                $sql .= "DROP TABLE IF EXISTS `" . $this->escape($table) . "`;\n";
                $sql .= $createSQL . ";\n\n";

                if (!empty($rows)) {
                    $sql .= "INSERT INTO `" . $this->escape($table) . "` (`" . implode('`,`', $colNames) . "`) VALUES\n";
                    $values = [];
                    foreach ($rows as $r) {
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : $this->pdo->quote($v), array_values($r));
                        $values[] = "(" . implode(',', $vals) . ")";
                    }
                    $sql .= implode(",\n", $values) . ";\n";
                }

                return ['ok' => true, 'content' => $sql, 'filename' => "$table-" . date('Ymd-His') . ".sql", 'mime' => 'application/sql'];
            }

            if ($format === 'json') {
                $data = [
                    'table' => $table,
                    'exported_at' => date('c'),
                    'columns' => $colNames,
                    'row_count' => count($rows),
                    'rows' => $rows,
                ];
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                return ['ok' => true, 'content' => $json, 'filename' => "$table-" . date('Ymd-His') . ".json", 'mime' => 'application/json'];
            }

            return ['ok' => false, 'error' => 'فرمت پشتیبانی نمی‌شود'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================
    //   Helpers
    // ============================================================

    public function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function validateIdentifier(string $name, string $type = 'identifier'): array {
        if (empty($name)) {
            return ['ok' => false, 'error' => "نام $type الزامی است"];
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            return ['ok' => false, 'error' => "نام $type فقط حروف، عدد و آندرلاین (و شروع با حرف)"];
        }

        if (strlen($name) > 64) {
            return ['ok' => false, 'error' => "نام $type حداکثر ۶۴ کاراکتر"];
        }

        if (in_array(strtolower($name), $this->reservedWords)) {
            return ['ok' => false, 'error' => "«$name» یک کلمه رزروشده MySQL است"];
        }

        return ['ok' => true];
    }

    public function validateColumn(array $col): array {
        if (empty($col['name'])) {
            return ['ok' => false, 'error' => 'نام ستون الزامی است'];
        }

        $check = $this->validateIdentifier($col['name'], 'ستون');
        if (!$check['ok']) return $check;

        if (empty($col['type'])) {
            return ['ok' => false, 'error' => 'نوع ستون الزامی است'];
        }

        if (!isset(self::$columnTypes[strtoupper($col['type'])])) {
            return ['ok' => false, 'error' => 'نوع نامعتبر: ' . $col['type']];
        }

        if (strtoupper($col['type']) === 'ENUM') {
            if (empty($col['enum_values']) || count($col['enum_values']) < 2) {
                return ['ok' => false, 'error' => 'ENUM حداقل ۲ مقدار نیاز دارد'];
            }
        }

        return ['ok' => true];
    }

    public function escape(string $identifier): string {
        // حذف backtick برای امنیت
        return str_replace('`', '', $identifier);
    }

    public function getDbName(): string {
        return $this->dbName;
    }

    // ============================================================
    //   SQL Preview
    // ============================================================

    public function previewCreateSQL(string $tableName, array $columns): string {
        // ساخت SQL بدون اجرا (برای پیش‌نمایش)
        $check = $this->validateIdentifier($tableName, 'table');
        if (!$check['ok']) return "-- خطا: " . $check['error'];

        if (empty($columns)) return "-- هیچ ستونی تعریف نشده";

        $colDefs = [];
        $primaryKey = null;
        $uniques = [];
        $indexes = [];

        foreach ($columns as $col) {
            $check = $this->validateColumn($col);
            if (!$check['ok']) {
                return "-- خطا در ستون {$col['name']}: " . $check['error'];
            }

            $name = $col['name'];
            $type = strtoupper($col['type']);

            $sqlType = $type;
            if (!empty($col['length']) && in_array($type, ['INT','BIGINT','VARCHAR','DECIMAL','FLOAT','TINYINT'])) {
                $len = (int)$col['length'];
                if ($type === 'DECIMAL' && !empty($col['decimals'])) {
                    $sqlType .= "($len," . (int)$col['decimals'] . ")";
                } else {
                    $sqlType .= "($len)";
                }
            } elseif ($type === 'ENUM' && !empty($col['enum_values'])) {
                $vals = array_map(fn($v) => "'" . str_replace("'", "''", trim($v)) . "'", $col['enum_values']);
                $sqlType = "ENUM(" . implode(',', $vals) . ")";
            }

            if (!empty($col['unsigned'])) $sqlType .= " UNSIGNED";

            $def = "`" . $this->escape($name) . "` $sqlType";
            $def .= !empty($col['nullable']) ? " NULL" : " NOT NULL";

            if (isset($col['default']) && $col['default'] !== '' && $col['default'] !== null) {
                $d = $col['default'];
                if (in_array(strtoupper($d), ['CURRENT_TIMESTAMP', 'NOW()'])) {
                    $def .= " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $def .= " DEFAULT '" . str_replace("'", "''", $d) . "'";
                }
            }

            if (!empty($col['auto_increment'])) {
                $def .= " AUTO_INCREMENT";
                $primaryKey = $name;
            }
            if (!empty($col['primary']) && !$primaryKey) $primaryKey = $name;
            if (!empty($col['unique'])) $uniques[] = $name;
            if (!empty($col['index']) && empty($col['unique'])) $indexes[] = $name;
            if (!empty($col['comment'])) $def .= " COMMENT '" . str_replace("'", "''", $col['comment']) . "'";

            $colDefs[] = $def;
        }

        if ($primaryKey) $colDefs[] = "PRIMARY KEY (`" . $this->escape($primaryKey) . "`)";
        foreach ($uniques as $u) $colDefs[] = "UNIQUE KEY `idx_$u` (`$u`)";
        foreach ($indexes as $ix) $colDefs[] = "KEY `idx_$ix` (`$ix`)";

        return "CREATE TABLE `" . $this->escape($tableName) . "` (\n    " . implode(",\n    ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
