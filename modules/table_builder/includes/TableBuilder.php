<?php
/**
 * PartoCMS - Table Builder v3.0
 * مدیریت کامل ساخت و ویرایش جدول‌های دیتابیس
 *
 * v3.0:
 * - Foreign Keys
 * - Composite Indexes
 * - Pivot/Many-to-Many
 * - ALTER TABLE
 * - Row CRUD (getRow, insertRow, updateRow, deleteRow)
 * - Form Helpers (getFormFieldType, parseEnumValues)
 * - Foreign Key dropdown (getForeignKeyValues)
 *
 * @version 3.0
 * @date 2026-09-18
 */
class TableBuilder {

    private $pdo;
    private $dbName;

    private $reservedWords = [
        'select','insert','update','delete','from','where','table','database',
        'index','key','primary','foreign','unique','references','order','group',
        'by','having','limit','offset','join','inner','outer','left','right',
        'on','as','and','or','not','null','default','int','varchar','text',
        'char','date','time','datetime','timestamp','decimal','float','double',
        'create','alter','drop','truncate','user','password','file','data',
        'add','change','modify','column','columns','constraint','check','view',
    ];

    public static $columnTypes = [
        // انواع عددی
        'INT' => '🔢 عدد صحیح',
        'BIGINT' => '🔢 عدد بزرگ',
        'TINYINT' => '🔢 عدد کوچک',
        'TINYINT(1)' => '☑️ بولین (0/1)',
        'DECIMAL' => '💰 اعشار دقیق',
        'FLOAT' => '💰 اعشار اعشاری',
        // انواع متنی
        'VARCHAR' => '📝 متن کوتاه',
        'TEXT' => '📄 متن بلند',
        'LONGTEXT' => '📚 متن خیلی بلند',
        'ENUM' => '📋 لیست انتخاب',
        'JSON' => '🔧 JSON',
        // انواع معنادار (VARCHAR تخصصی)
        'EMAIL' => '✉️ ایمیل',
        'URL' => '🔗 لینک',
        'PHONE' => '📞 تلفن',
        'MOBILE' => '📱 موبایل',
        'IMAGE' => '🖼️ تصویر',
        'FILE' => '📎 فایل',
        'COLOR' => '🎨 رنگ (HEX)',
        'SLUG' => '🔤 Slug',
        'PASSWORD' => '🔒 رمز عبور',
        'USERNAME' => '👤 نام کاربری',
        'IP' => '🌐 آدرس IP',
        'CODE' => '💻 کد',
        // انواع تاریخ/زمان
        'DATE' => '📅 تاریخ',
        'TIME' => '⏰ ساعت',
        'DATETIME' => '📆 تاریخ و ساعت',
        'TIMESTAMP' => '⏱️ Timestamp',
    ];

    /**
     * نقشه‌برداری انواع معنادار به انواع MySQL
     */
    public static $typeMapping = [
        'EMAIL'    => ['type' => 'VARCHAR', 'length' => 150],
        'URL'      => ['type' => 'VARCHAR', 'length' => 500],
        'PHONE'    => ['type' => 'VARCHAR', 'length' => 20],
        'MOBILE'   => ['type' => 'VARCHAR', 'length' => 20],
        'IMAGE'    => ['type' => 'VARCHAR', 'length' => 255],
        'FILE'     => ['type' => 'VARCHAR', 'length' => 255],
        'COLOR'    => ['type' => 'VARCHAR', 'length' => 7],
        'SLUG'     => ['type' => 'VARCHAR', 'length' => 200],
        'PASSWORD' => ['type' => 'VARCHAR', 'length' => 255],
        'USERNAME' => ['type' => 'VARCHAR', 'length' => 50],
        'IP'       => ['type' => 'VARCHAR', 'length' => 45],
        'CODE'     => ['type' => 'VARCHAR', 'length' => 100],
    ];

    /**
     * تبدیل نوع معنادار به نوع MySQL واقعی
     */
    public static function resolveType(string $type): array {
        $type = strtoupper($type);
        if (isset(self::$typeMapping[$type])) {
            return self::$typeMapping[$type];
        }
        return ['type' => $type, 'length' => null];
    }

    public static $fkActions = [
        'RESTRICT'  => 'محدود کن (RESTRICT)',
        'CASCADE'   => 'آبشاری (CASCADE)',
        'SET NULL'  => 'خالی کن (SET NULL)',
        'NO ACTION' => 'بدون عمل (NO ACTION)',
    ];

    public static $indexTypes = [
        'INDEX'    => 'ایندکس معمولی',
        'UNIQUE'   => 'یکتا',
        'FULLTEXT' => 'متن کامل (Fulltext)',
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        try {
            $this->dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        } catch (Throwable $e) {
            $this->dbName = defined('DB_NAME') ? DB_NAME : 'grapesjs_cms';
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   لیست جدول‌ها
    // ═══════════════════════════════════════════════════════════

    public function getTables(): array {
        try {
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
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

    public function getTableNames(): array {
        try {
            return $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getTableInfo(string $table): ?array {
        try {
            $rowCount = $this->pdo->query("SELECT COUNT(*) FROM `" . $this->escape($table) . "`")->fetchColumn();

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

    // ═══════════════════════════════════════════════════════════
    //   ستون‌ها + ایندکس‌ها + Foreign Keys
    // ═══════════════════════════════════════════════════════════

    public function getColumns(string $table): array {
        try {
            $stmt = $this->pdo->query("SHOW FULL COLUMNS FROM `" . $this->escape($table) . "`");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

            $fks = $this->getForeignKeys($table);

            foreach ($cols as &$c) {
                $c['indexes'] = $indexes[$c['Field']] ?? [];
                $c['foreign_key'] = $fks[$c['Field']] ?? null;
            }

            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getForeignKeys(string $table): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kcu.CONSTRAINT_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.DELETE_RULE,
                    rc.UPDATE_RULE
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                    ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                    AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
                WHERE kcu.TABLE_SCHEMA = ?
                  AND kcu.TABLE_NAME = ?
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $stmt->execute([$this->dbName, $table]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $result[$r['COLUMN_NAME']] = [
                    'name' => $r['CONSTRAINT_NAME'],
                    'ref_table' => $r['REFERENCED_TABLE_NAME'],
                    'ref_column' => $r['REFERENCED_COLUMN_NAME'],
                    'on_delete' => $r['DELETE_RULE'] ?? 'RESTRICT',
                    'on_update' => $r['UPDATE_RULE'] ?? 'RESTRICT',
                ];
            }
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getCompositeIndexes(string $table): array {
        try {
            $stmt = $this->pdo->query("SHOW INDEX FROM `" . $this->escape($table) . "`");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($all as $idx) {
                $name = $idx['Key_name'];
                if ($name === 'PRIMARY') continue;

                if (!isset($grouped[$name])) {
                    $grouped[$name] = [
                        'name' => $name,
                        'unique' => $idx['Non_unique'] == 0,
                        'columns' => [],
                    ];
                }
                $grouped[$name]['columns'][] = $idx['Column_name'];
            }

            return array_filter($grouped, fn($i) => count($i['columns']) > 1);
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

    // ═══════════════════════════════════════════════════════════
    //   ساخت جدول
    // ═══════════════════════════════════════════════════════════

    /**
     * ترجمه خطاهای MySQL به فارسی معنادار
     */
    public function translateError(string $rawError, array $context = []): array {
        $patterns = [
            '/BLOB\/TEXT column \'([^\']+)\' can\'t have a default value/i' => [
                'msg' => 'ستون «{1}» از نوع TEXT نمی‌تواند مقدار پیش‌فرض داشته باشد.',
                'hint' => 'راه‌حل: گزینه «Null» را برای این ستون فعال کنید یا نوع را به VARCHAR تغییر دهید.',
                'field' => '{1}',
            ],
            '/BLOB\/TEXT column \'([^\']+)\' can\'t be NOT NULL/i' => [
                'msg' => 'ستون «{1}» از نوع TEXT نمی‌تواند NOT NULL باشد.',
                'hint' => 'راه‌حل: گزینه «Null» را برای این ستون فعال کنید.',
                'field' => '{1}',
            ],
            '/Duplicate column name \'([^\']+)\'/i' => [
                'msg' => 'ستون «{1}» تکراری است. این نام قبلاً استفاده شده.',
                'hint' => 'راه‌حل: نام ستون را تغییر دهید.',
                'field' => '{1}',
            ],
            '/Duplicate entry \'([^\']+)\' for key/i' => [
                'msg' => 'مقدار «{1}» تکراری است (فیلد Unique).',
                'hint' => 'راه‌حل: مقدار دیگری وارد کنید یا گزینه Unique را غیرفعال کنید.',
                'field' => '',
            ],
            '/Unknown column \'([^\']+)\'/i' => [
                'msg' => 'ستون «{1}» ناشناخته است.',
                'hint' => 'راه‌حل: نام ستون را بررسی کنید.',
                'field' => '{1}',
            ],
            '/Incorrect default value for column \'([^\']+)\'/i' => [
                'msg' => 'مقدار پیش‌فرض ستون «{1}» نامعتبر است.',
                'hint' => 'راه‌حل: مقدار پیش‌فرض را با نوع ستون هماهنگ کنید.',
                'field' => '{1}',
            ],
            '/Table \'([^\']+)\' already exists/i' => [
                'msg' => 'جدول «{1}» از قبل وجود دارد.',
                'hint' => 'راه‌حل: نام جدول را تغییر دهید.',
                'field' => '',
            ],
            '/You have an error in your SQL syntax/i' => [
                'msg' => 'خطای نگارش SQL. یکی از تعریف‌های ستون‌ها نادرست است.',
                'hint' => 'راه‌حل: نوع ستون‌ها، طول و مقادیر پیش‌فرض را بررسی کنید. توجه: ستون‌های TEXT نمی‌توانند NOT NULL باشند.',
                'field' => '',
            ],
            '/Specified key was too long/i' => [
                'msg' => 'طول کلید خیلی زیاد است.',
                'hint' => 'راه‌حل: طول VARCHAR را کاهش دهید (حداکثر 191 کاراکتر برای utf8mb4).',
                'field' => '',
            ],
            '/Incorrect table name/i' => [
                'msg' => 'نام جدول نامعتبر است.',
                'hint' => 'راه‌حل: فقط حروف انگلیسی، عدد و آندرلاین استفاده کنید.',
                'field' => '',
            ],
            '/Cannot add foreign key constraint/i' => [
                'msg' => 'خطا در تعریف Foreign Key.',
                'hint' => 'راه‌حل: مطمئن شوید ستون مرجع، کلید اصلی یا Unique باشد و نوع دو ستون یکسان باشد.',
                'field' => '',
            ],
        ];

        foreach ($patterns as $pattern => $info) {
            if (preg_match($pattern, $rawError, $m)) {
                $msg = $info['msg'];
                $hint = $info['hint'];
                $field = $info['field'];
                // جایگزینی {1} با مقدار match
                for ($i = 1; $i < count($m); $i++) {
                    $msg = str_replace('{' . $i . '}', $m[$i], $msg);
                    $hint = str_replace('{' . $i . '}', $m[$i], $hint);
                    $field = str_replace('{' . $i . '}', $m[$i], $field);
                }
                return [
                    'translated' => true,
                    'message'    => $msg,
                    'hint'       => $hint,
                    'field'      => $field,
                    'raw'        => $rawError,
                ];
            }
        }

        return [
            'translated' => false,
            'message'    => 'خطای دیتابیس رخ داد.',
            'hint'       => 'لطفاً با پشتیبانی تماس بگیرید یا لاگ را بررسی کنید.',
            'field'      => '',
            'raw'        => $rawError,
        ];
    }

    public function createTable(string $tableName, array $columns, array $compositeIndexes = []): array {
        $check = $this->validateIdentifier($tableName, 'table');
        if (!$check['ok']) return $check;

        if (empty($columns)) {
            return ['ok' => false, 'error' => 'حداقل یک ستون الزامی است'];
        }

        $colDefs = [];
        $primaryKey = null;
        $uniques = [];
        $indexes = [];
        $fks = [];

        foreach ($columns as $i => $col) {
            $colCheck = $this->validateColumn($col);
            if (!$colCheck['ok']) {
                return [
                    'ok' => false,
                    'error' => "ستون #" . ($i + 1) . ": " . $colCheck['error'],
                    'hint' => $colCheck['hint'] ?? '',
                    'error_field' => $colCheck['error_field'] ?? ($col['name'] ?? ''),
                    'translated' => true,
                ];
            }

            // اعتبارسنجی پیش‌از‌SQL: TEXT/BLOB + NOT NULL ممنوع
            $typeUpper = strtoupper($col['type'] ?? '');
            if (in_array($typeUpper, ['TEXT', 'LONGTEXT', 'BLOB', 'JSON'], true)
                && empty($col['nullable'])) {
                return [
                    'ok' => false,
                    'error' => "ستون «{$col['name']}» از نوع {$typeUpper} نمی‌تواند NOT NULL باشد.",
                    'hint' => "راه‌حل: گزینه «Null» را برای این ستون فعال کنید (چون MySQL برای TEXT مقدار پیش‌فرض نمی‌پذیرد).",
                    'error_field' => $col['name'],
                    'translated' => true,
                ];
            }

            $name = $col['name'];
            $originalType = strtoupper($col['type']);

            // تبدیل نوع معنادار به MySQL
            $resolved = self::resolveType($originalType);
            $type = $resolved['type'];
            if (!empty($resolved['length']) && empty($col['length'])) {
                $col['length'] = $resolved['length'];
            }

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

            if (!empty($col['unsigned'])) $sqlType .= " UNSIGNED";

            $def = "`" . $this->escape($name) . "` $sqlType";
            $def .= !empty($col['nullable']) ? " NULL" : " NOT NULL";

            if (isset($col['default']) && $col['default'] !== '' && $col['default'] !== null) {
                $default = $col['default'];
                if (in_array(strtoupper($default), ['CURRENT_TIMESTAMP', 'NOW()'])) {
                    $def .= " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $def .= " DEFAULT " . $this->pdo->quote($default);
                }
            }

            if (!empty($col['auto_increment'])) {
                $def .= " AUTO_INCREMENT";
                $primaryKey = $name;
            }

            if (!empty($col['primary']) && !$primaryKey) $primaryKey = $name;
            if (!empty($col['unique'])) $uniques[] = $name;
            if (!empty($col['index']) && empty($col['unique'])) $indexes[] = $name;
            if (!empty($col['comment'])) $def .= " COMMENT " . $this->pdo->quote($col['comment']);

            $colDefs[] = $def;

            if (!empty($col['foreign_key'])) {
                $fk = $col['foreign_key'];
                if (!empty($fk['ref_table']) && !empty($fk['ref_column'])) {
                    $fks[] = [
                        'column' => $name,
                        'ref_table' => $fk['ref_table'],
                        'ref_column' => $fk['ref_column'],
                        'on_delete' => in_array($fk['on_delete'] ?? '', array_keys(self::$fkActions)) ? $fk['on_delete'] : 'RESTRICT',
                        'on_update' => in_array($fk['on_update'] ?? '', array_keys(self::$fkActions)) ? $fk['on_update'] : 'RESTRICT',
                    ];
                }
            }
        }

        if ($primaryKey) {
            $colDefs[] = "PRIMARY KEY (`" . $this->escape($primaryKey) . "`)";
        }

        foreach ($uniques as $u) {
            $colDefs[] = "UNIQUE KEY `idx_" . $this->escape($u) . "` (`" . $this->escape($u) . "`)";
        }

        foreach ($indexes as $ix) {
            $colDefs[] = "KEY `idx_" . $this->escape($ix) . "` (`" . $this->escape($ix) . "`)";
        }

        foreach ($compositeIndexes as $ci) {
            $ciName = trim($ci['name'] ?? '');
            $ciCols = $ci['columns'] ?? [];
            $ciType = strtoupper($ci['type'] ?? 'INDEX');

            if (empty($ciName)) $ciName = 'idx_' . implode('_', $ciCols);
            if (empty($ciCols)) continue;

            $check = $this->validateIdentifier($ciName, 'ایندکس');
            if (!$check['ok']) {
                return ['ok' => false, 'error' => 'ایندکس: ' . $check['error']];
            }

            $escapedCols = array_map(fn($c) => '`' . $this->escape($c) . '`', $ciCols);
            $colList = implode(', ', $escapedCols);

            if ($ciType === 'UNIQUE') {
                $colDefs[] = "UNIQUE KEY `" . $this->escape($ciName) . "` ($colList)";
            } elseif ($ciType === 'FULLTEXT') {
                $colDefs[] = "FULLTEXT KEY `" . $this->escape($ciName) . "` ($colList)";
            } else {
                $colDefs[] = "KEY `" . $this->escape($ciName) . "` ($colList)";
            }
        }

        foreach ($fks as $fk) {
            $fkName = 'fk_' . $this->escape($tableName) . '_' . $this->escape($fk['column']);
            $colDefs[] = "CONSTRAINT `$fkName` FOREIGN KEY (`" . $this->escape($fk['column']) . "`)"
                       . " REFERENCES `" . $this->escape($fk['ref_table']) . "` (`" . $this->escape($fk['ref_column']) . "`)"
                       . " ON DELETE " . $fk['on_delete']
                       . " ON UPDATE " . $fk['on_update'];
        }

        $sql = "CREATE TABLE `" . $this->escape($tableName) . "` (\n    " . implode(",\n    ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
            return [
                'ok' => true,
                'table' => $tableName,
                'sql' => $sql,
                'columns' => count($columns),
                'fks' => count($fks),
                'composite_indexes' => count($compositeIndexes),
            ];
        } catch (Throwable $e) {
            // ترجمه خطای MySQL به فارسی
            $translated = $this->translateError($e->getMessage(), [
                'table' => $tableName,
                'columns' => $columns,
            ]);

            return [
                'ok' => false,
                'error' => $translated['message'],
                'hint' => $translated['hint'],
                'error_field' => $translated['field'],
                'translated' => $translated['translated'],
                'raw_error' => $e->getMessage(),
                'sql' => $sql,
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   Pivot
    // ═══════════════════════════════════════════════════════════

    public function createPivotTable(string $tableA, string $colA, string $tableB, string $colB, ?string $pivotName = null, array $extraColumns = []): array {
        if (!$this->tableExists($tableA)) return ['ok' => false, 'error' => "جدول $tableA یافت نشد"];
        if (!$this->tableExists($tableB)) return ['ok' => false, 'error' => "جدول $tableB یافت نشد"];

        if (!$pivotName) {
            $a = substr($tableA, 0, 20);
            $b = substr($tableB, 0, 20);
            $pivotName = $a . '_' . $b;
        }

        $check = $this->validateIdentifier($pivotName, 'جدول pivot');
        if (!$check['ok']) return $check;

        if ($this->tableExists($pivotName)) {
            return ['ok' => false, 'error' => "جدول $pivotName از قبل وجود دارد"];
        }

        $fkA = 'fk_' . substr($tableA, 0, 10) . '_' . substr($colA, 0, 10);
        $fkB = 'fk_' . substr($tableB, 0, 10) . '_' . substr($colB, 0, 10);

        $extraDefs = '';
        foreach ($extraColumns as $ec) {
            $ecCheck = $this->validateColumn($ec);
            if (!$ecCheck['ok']) continue;

            $ecName = $ec['name'];
            $ecType = strtoupper($ec['type']);
            $sqlType = $ecType;
            if (!empty($ec['length']) && in_array($ecType, ['INT','BIGINT','VARCHAR','TINYINT'])) {
                $sqlType .= "(" . (int)$ec['length'] . ")";
            }

            $extraDefs .= ",\n    `" . $this->escape($ecName) . "` $sqlType";
            $extraDefs .= !empty($ec['nullable']) ? " NULL" : " NOT NULL";
        }

        $sql = "CREATE TABLE `" . $this->escape($pivotName) . "` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `" . $this->escape($tableA) . "_id` INT NOT NULL,
    `" . $this->escape($tableB) . "_id` INT NOT NULL" . $extraDefs . ",
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_" . $this->escape($pivotName) . "` (`" . $this->escape($tableA) . "_id`, `" . $this->escape($tableB) . "_id`),
    KEY `idx_" . $this->escape($tableA) . "` (`" . $this->escape($tableA) . "_id`),
    KEY `idx_" . $this->escape($tableB) . "` (`" . $this->escape($tableB) . "_id`),
    CONSTRAINT `$fkA` FOREIGN KEY (`" . $this->escape($tableA) . "_id`)"
        . " REFERENCES `" . $this->escape($tableA) . "` (`" . $this->escape($colA) . "`) ON DELETE CASCADE,"
        . " CONSTRAINT `$fkB` FOREIGN KEY (`" . $this->escape($tableB) . "_id`)"
        . " REFERENCES `" . $this->escape($tableB) . "` (`" . $this->escape($colB) . "`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
            return ['ok' => true, 'pivot' => $pivotName, 'sql' => $sql];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   ALTER TABLE
    // ═══════════════════════════════════════════════════════════

    public function addColumn(string $table, array $col, string $after = ''): array {
        if (!$this->tableExists($table)) return ['ok' => false, 'error' => 'جدول یافت نشد'];

        $check = $this->validateColumn($col);
        if (!$check['ok']) return $check;

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
            $vals = array_map(fn($v) => $this->pdo->quote(trim($v)), $col['enum_values']);
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
                $def .= " DEFAULT " . $this->pdo->quote($d);
            }
        }

        $sql = "ALTER TABLE `" . $this->escape($table) . "` ADD COLUMN $def";
        if ($after) {
            $sql .= " AFTER `" . $this->escape($after) . "`";
        }

        try {
            $this->pdo->exec($sql);
            return ['ok' => true, 'sql' => $sql];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    public function dropColumn(string $table, string $column): array {
        if (!$this->tableExists($table)) return ['ok' => false, 'error' => 'جدول یافت نشد'];

        $sql = "ALTER TABLE `" . $this->escape($table) . "` DROP COLUMN `" . $this->escape($column) . "`";

        try {
            $this->pdo->exec($sql);
            return ['ok' => true, 'sql' => $sql];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    public function addIndex(string $table, array $columns, string $type = 'INDEX', ?string $name = null): array {
        if (!$this->tableExists($table)) return ['ok' => false, 'error' => 'جدول یافت نشد'];
        if (empty($columns)) return ['ok' => false, 'error' => 'حداقل یک ستون'];

        if (!$name) $name = 'idx_' . implode('_', $columns);

        $check = $this->validateIdentifier($name, 'ایندکس');
        if (!$check['ok']) return $check;

        $escapedCols = array_map(fn($c) => '`' . $this->escape($c) . '`', $columns);
        $colList = implode(', ', $escapedCols);

        $type = strtoupper($type);
        if ($type === 'UNIQUE') {
            $sql = "ALTER TABLE `" . $this->escape($table) . "` ADD UNIQUE KEY `" . $this->escape($name) . "` ($colList)";
        } elseif ($type === 'FULLTEXT') {
            $sql = "ALTER TABLE `" . $this->escape($table) . "` ADD FULLTEXT KEY `" . $this->escape($name) . "` ($colList)";
        } else {
            $sql = "ALTER TABLE `" . $this->escape($table) . "` ADD KEY `" . $this->escape($name) . "` ($colList)";
        }

        try {
            $this->pdo->exec($sql);
            return ['ok' => true, 'sql' => $sql];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    public function dropIndex(string $table, string $indexName): array {
        if (!$this->tableExists($table)) return ['ok' => false, 'error' => 'جدول یافت نشد'];

        $sql = "ALTER TABLE `" . $this->escape($table) . "` DROP INDEX `" . $this->escape($indexName) . "`";

        try {
            $this->pdo->exec($sql);
            return ['ok' => true, 'sql' => $sql];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   Relations
    // ═══════════════════════════════════════════════════════════

    public function getIncomingRelations(string $table): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kcu.TABLE_NAME,
                    kcu.COLUMN_NAME,
                    kcu.CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE kcu
                WHERE kcu.REFERENCED_TABLE_SCHEMA = ?
                  AND kcu.REFERENCED_TABLE_NAME = ?
            ");
            $stmt->execute([$this->dbName, $table]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getPrimaryKeyColumns(string $table): array {
        try {
            $stmt = $this->pdo->query("SHOW KEYS FROM `" . $this->escape($table) . "` WHERE Key_name = 'PRIMARY'");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows, 'Column_name');
        } catch (Throwable $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   حذف جدول
    // ═══════════════════════════════════════════════════════════

    public function dropTable(string $table): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        $protected = [
            'users','roles','permissions','languages','settings',
            'content_items','categories','comments','modules',
            'content_types','content_translations','content_tags',
            'role_permissions','role_modules','menus','menu_items',
            'security_logs','login_attempts','login_blocks','user_2fa',
            'user_2fa_backup','file_hashes','backup_settings','backups',
            'translation_keys','translations','user_preferences',
            'translation_cache','translation_versions','provider_stats',
            'category_translations','module_files','translation_queue',
            'activity_log','seo_settings','rate_limits','tags','views',
            'form_submissions','forms','media','projects','templates',
        ];
        if (in_array($table, $protected)) {
            return ['ok' => false, 'error' => 'این جدول محافظت‌شده است'];
        }

        try {
            $this->pdo->exec("DROP TABLE `" . $this->escape($table) . "`");
            return ['ok' => true, 'table' => $table];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   Export
    // ═══════════════════════════════════════════════════════════

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
                $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
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
                $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

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

    // ═══════════════════════════════════════════════════════════
    //   Row CRUD
    // ═══════════════════════════════════════════════════════════

    public function getRow(string $table, string $pkCol, $pkVal): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `" . $this->escape($table) . "` WHERE `" . $this->escape($pkCol) . "` = ? LIMIT 1");
            $stmt->execute([$pkVal]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function insertRow(string $table, array $data): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        $cols = array_column($this->getColumns($table), 'Field');
        $data = array_intersect_key($data, array_flip($cols));

        if (empty($data)) {
            return ['ok' => false, 'error' => 'هیچ داده‌ای برای درج نیست'];
        }

        $escaped = array_map(fn($c) => "`" . $this->escape($c) . "`", array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `" . $this->escape($table) . "` (" . implode(', ', $escaped) . ") VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            return [
                'ok' => true,
                'id' => $this->pdo->lastInsertId(),
                'rows' => $stmt->rowCount(),
                'sql' => $sql,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    public function updateRow(string $table, string $pkCol, $pkVal, array $data): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        $cols = array_column($this->getColumns($table), 'Field');
        $data = array_intersect_key($data, array_flip($cols));
        unset($data[$pkCol]);

        if (empty($data)) {
            return ['ok' => false, 'error' => 'هیچ داده‌ای برای آپدیت نیست'];
        }

        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = "`" . $this->escape($c) . "` = ?";
        }

        $sql = "UPDATE `" . $this->escape($table) . "` SET " . implode(', ', $sets) . " WHERE `" . $this->escape($pkCol) . "` = ? LIMIT 1";

        $params = array_values($data);
        $params[] = $pkVal;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return [
                'ok' => true,
                'rows' => $stmt->rowCount(),
                'sql' => $sql,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    public function deleteRow(string $table, string $pkCol, $pkVal): array {
        if (!$this->tableExists($table)) {
            return ['ok' => false, 'error' => 'جدول یافت نشد'];
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM `" . $this->escape($table) . "` WHERE `" . $this->escape($pkCol) . "` = ? LIMIT 1");
            $stmt->execute([$pkVal]);
            return ['ok' => true, 'rows' => $stmt->rowCount()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function getForeignKeyValues(string $refTable, string $refColumn, string $displayColumn = 'name', int $limit = 500): array {
        try {
            if (!$this->tableExists($refTable)) return [];

            $cols = array_column($this->getColumns($refTable), 'Field');
            if (!in_array($displayColumn, $cols)) {
                $displayColumn = $refColumn;
            }

            $sql = "SELECT `" . $this->escape($refColumn) . "` as id, `" . $this->escape($displayColumn) . "` as label
                    FROM `" . $this->escape($refTable) . "`
                    ORDER BY `" . $this->escape($displayColumn) . "` ASC
                    LIMIT " . (int)$limit;
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //   Form Helpers
    // ═══════════════════════════════════════════════════════════

    public static function getFormFieldType(string $sqlType): string {
        $type = strtoupper($sqlType);

        if (preg_match('/^TINYINT\(1\)/', $type)) return 'checkbox';
        if (preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)/', $type)) return 'number';
        if (preg_match('/^(DECIMAL|FLOAT|DOUBLE)/', $type)) return 'number';
        if (preg_match('/^DATE$/', $type)) return 'date';
        if (preg_match('/^TIME$/', $type)) return 'time';
        if (preg_match('/^(DATETIME|TIMESTAMP)/', $type)) return 'datetime-local';
        if (preg_match('/^(TEXT|LONGTEXT|MEDIUMTEXT|JSON)/', $type)) return 'textarea';
        if (preg_match('/^ENUM/', $type)) return 'enum';

        return 'text';
    }

    /**
     * استخراج مقادیر ENUM
     * نسخه اصلاح‌شده — بدون warning
     */
    public static function parseEnumValues(string $sqlType): array {
        if (!preg_match('/^enum\((.*)\)$/i', trim($sqlType), $m)) return [];

        $inner = trim($m[1], "'");
        if ($inner === '') return [];

        $parts = preg_split("/','/", $inner);
        return array_map('trim', $parts ?: []);
    }

    // ═══════════════════════════════════════════════════════════
    //   Helpers
    // ═══════════════════════════════════════════════════════════

    public function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ");
            $stmt->execute([$this->dbName, $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function validateIdentifier(string $name, string $type = 'identifier'): array {
        if (empty($name)) {
            return ['ok' => false, 'error' => "نام {$type} الزامی است"];
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            return [
                'ok' => false,
                'error' => "نام {$type} فقط می‌تواند شامل حروف انگلیسی، عدد و آندرلاین باشد (شروع با حرف یا _)",
                'hint' => "راه‌حل: نام را با حرف انگلیسی شروع کنید. مثال: my_field, data_1, user_name",
            ];
        }
        if (strlen($name) > 64) {
            return ['ok' => false, 'error' => "نام {$type} حداکثر ۶۴ کاراکتر می‌تواند باشد"];
        }
        if (in_array(strtolower($name), $this->reservedWords)) {
            return [
                'ok' => false,
                'error' => "نام «{$name}» کلمه رزروشده MySQL است",
                'hint' => "راه‌حل: نام دیگری انتخاب کنید. مثلاً اگر «data» است، از «data_json» یا «extra_data» استفاده کنید. لیست کلمات رزرو: data, file, order, group, user, key, status, type, index, table, ...",
                'error_field' => $name,
                'translated' => true,
            ];
        }
        return ['ok' => true];
    }

    public function validateColumn(array $col): array {
        if (empty($col['name'])) {
            return ['ok' => false, 'error' => 'نام ستون الزامی است'];
        }
        $check = $this->validateIdentifier($col['name'], 'ستون');
        if (!$check['ok']) {
            // انتقال hint و error_field
            $check['error_field'] = $col['name'] ?? '';
            return $check;
        }

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
        return str_replace('`', '', $identifier);
    }

    public function getDbName(): string {
        return $this->dbName;
    }

    // ═══════════════════════════════════════════════════════════
    //   SQL Preview
    // ═══════════════════════════════════════════════════════════

    public function previewCreateSQL(string $tableName, array $columns, array $compositeIndexes = []): string {
        $check = $this->validateIdentifier($tableName, 'table');
        if (!$check['ok']) return "-- خطا: " . $check['error'];
        if (empty($columns)) return "-- هیچ ستونی تعریف نشده";

        $colDefs = [];
        $primaryKey = null;
        $uniques = [];
        $indexes = [];
        $fks = [];

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

            if (!empty($col['foreign_key'])) {
                $fk = $col['foreign_key'];
                if (!empty($fk['ref_table']) && !empty($fk['ref_column'])) {
                    $fks[] = [
                        'column' => $name,
                        'ref_table' => $fk['ref_table'],
                        'ref_column' => $fk['ref_column'],
                        'on_delete' => $fk['on_delete'] ?? 'RESTRICT',
                        'on_update' => $fk['on_update'] ?? 'RESTRICT',
                    ];
                }
            }
        }

        if ($primaryKey) $colDefs[] = "PRIMARY KEY (`" . $this->escape($primaryKey) . "`)";
        foreach ($uniques as $u) $colDefs[] = "UNIQUE KEY `idx_$u` (`$u`)";
        foreach ($indexes as $ix) $colDefs[] = "KEY `idx_$ix` (`$ix`)";

        foreach ($compositeIndexes as $ci) {
            $ciName = trim($ci['name'] ?? '');
            $ciCols = $ci['columns'] ?? [];
            $ciType = strtoupper($ci['type'] ?? 'INDEX');
            if (empty($ciName)) $ciName = 'idx_' . implode('_', $ciCols);
            if (empty($ciCols)) continue;

            $colList = implode(', ', array_map(fn($c) => "`$c`", $ciCols));
            if ($ciType === 'UNIQUE') {
                $colDefs[] = "UNIQUE KEY `$ciName` ($colList)";
            } elseif ($ciType === 'FULLTEXT') {
                $colDefs[] = "FULLTEXT KEY `$ciName` ($colList)";
            } else {
                $colDefs[] = "KEY `$ciName` ($colList)";
            }
        }

        foreach ($fks as $fk) {
            $fkName = 'fk_' . $tableName . '_' . $fk['column'];
            $colDefs[] = "CONSTRAINT `$fkName` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['ref_table']}` (`{$fk['ref_column']}`) ON DELETE {$fk['on_delete']} ON UPDATE {$fk['on_update']}";
        }

        return "CREATE TABLE `" . $this->escape($tableName) . "` (\n    " . implode(",\n    ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
