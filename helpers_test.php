<?php
/**
 * Helpers - contacts
 * تولید خودکار توسط PartoCMS Table Builder
 * @date 2026-09-19 10:54
 */

if (!defined('CRUD_TABLE')) {
    define('CRUD_TABLE', 'contacts');
    define('CRUD_PK', 'id');
    define('CRUD_SORT_FIELD', 'created_at');
    define('CRUD_SORT_DIR', 'DESC');
    define('CRUD_SEARCHABLE', ['first_name', 'last_name', 'email', 'phone', 'mobile', 'address', 'notes']);
}

if (!function_exists('crud_db')) {
    function crud_db() {
        if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo']) {
            if (function_exists('getDB')) $GLOBALS['pdo'] = getDB();
        }
        return $GLOBALS['pdo'];
    }
}

if (!function_exists('crud_safeId')) {
    function crud_safeId($id) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$id);
    }
}

if (!function_exists('crud_getAll')) {
    function crud_getAll(array $opts = []): array {
        $pdo = crud_db();
        if (!$pdo) return [];

        $table = CRUD_TABLE;
        $pk = CRUD_PK;
        $limit = max(1, (int)($opts['limit'] ?? 20));
        $offset = max(0, (int)($opts['offset'] ?? 0));
        $search = trim((string)($opts['search'] ?? ''));
        $orderBy = $opts['order_by'] ?? CRUD_SORT_FIELD;
        $orderDir = strtoupper($opts['order_dir'] ?? CRUD_SORT_DIR);
        $orderDir = $orderDir === 'ASC' ? 'ASC' : 'DESC';

        $where = '';
        $params = [];
        if ($search !== '' && !empty(CRUD_SEARCHABLE)) {
            $parts = [];
            foreach (CRUD_SEARCHABLE as $col) {
                $parts[] = "`$col` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where = ' WHERE (' . implode(' OR ', $parts) . ')';
        }

        try {
            $sql = "SELECT * FROM `$table`$where ORDER BY `$orderBy` $orderDir LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crud_count')) {
    function crud_count(string $search = ''): int {
        $pdo = crud_db();
        if (!$pdo) return 0;

        $table = CRUD_TABLE;
        $where = '';
        $params = [];
        $search = trim($search);
        if ($search !== '' && !empty(CRUD_SEARCHABLE)) {
            $parts = [];
            foreach (CRUD_SEARCHABLE as $col) {
                $parts[] = "`$col` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where = ' WHERE (' . implode(' OR ', $parts) . ')';
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`$where");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('crud_getOne')) {
    function crud_getOne($id): ?array {
        $pdo = crud_db();
        if (!$pdo) return null;
        $table = CRUD_TABLE;
        $pk = CRUD_PK;
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('crud_insert')) {
    function crud_insert(array $data): array {
        $pdo = crud_db();
        if (!$pdo) return ['ok' => false, 'error' => 'DB error'];
        $table = CRUD_TABLE;
        $pk = CRUD_PK;
        // حذف PK از داده
        unset($data[$pk]);
        if (empty($data)) return ['ok' => false, 'error' => 'داده‌ای ارسال نشد'];
        try {
            $cols = array_keys($data);
            $placeholders = array_fill(0, count($cols), '?');
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            return ['ok' => true, 'id' => $pdo->lastInsertId()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('crud_update')) {
    function crud_update($id, array $data): array {
        $pdo = crud_db();
        if (!$pdo) return ['ok' => false, 'error' => 'DB error'];
        $table = CRUD_TABLE;
        $pk = CRUD_PK;
        unset($data[$pk]);
        if (empty($data)) return ['ok' => false, 'error' => 'داده‌ای ارسال نشد'];
        try {
            $sets = [];
            foreach (array_keys($data) as $col) $sets[] = "`$col` = ?";
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?";
            $params = array_values($data);
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('crud_delete')) {
    function crud_delete($id): array {
        $pdo = crud_db();
        if (!$pdo) return ['ok' => false, 'error' => 'DB error'];
        $table = CRUD_TABLE;
        $pk = CRUD_PK;
        try {
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
            $stmt->execute([$id]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('crud_fkOptions')) {
    function crud_fkOptions(string $refTable, string $refCol = 'id', int $limit = 500): array {
        $pdo = crud_db();
        if (!$pdo) return [];
        $refTable = crud_safeId($refTable);
        $refCol = crud_safeId($refCol);
        try {
            // پیدا کردن ستون نمایش
            $cols = $pdo->query("SHOW COLUMNS FROM `$refTable`")->fetchAll(PDO::FETCH_ASSOC);
            $displayCol = null;
            foreach (['name', 'title', 'label', 'subject'] as $cand) {
                foreach ($cols as $c) {
                    if ($c['Field'] === $cand) { $displayCol = $cand; break 2; }
                }
            }
            if (!$displayCol) $displayCol = $cols[0]['Field'] ?? $refCol;

            $stmt = $pdo->query("SELECT `$refCol` as id, `$displayCol` as label FROM `$refTable` ORDER BY `$displayCol` ASC LIMIT $limit");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crud_sanitize')) {
    function crud_sanitize(array $data, array $fields): array {
        $out = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            if (!array_key_exists($name, $data)) continue;
            $val = $data[$name];
            $type = $f['html_type'];
            $isNull = ($val === '' || $val === null);

            // اگر خالی و اجباری نیست، NULL
            if ($isNull) {
                if (!empty($f['required'])) continue;
                if ($type === 'checkbox') { $out[$name] = 0; continue; }
                $out[$name] = null;
                continue;
            }
            switch ($type) {
                case 'number': case 'fk_select':
                    $out[$name] = ($val === '') ? null : (int)$val;
                    break;
                case 'decimal':
                    $out[$name] = ($val === '') ? null : (float)$val;
                    break;
                case 'checkbox':
                    $out[$name] = ($val == '1' || $val === true) ? 1 : 0;
                    break;
                case 'date': case 'datetime-local': case 'time':
                    $out[$name] = ($val === '') ? null : $val;
                    break;
                default:
                    $out[$name] = is_string($val) ? trim($val) : $val;
            }
        }
        return $out;
    }
}
