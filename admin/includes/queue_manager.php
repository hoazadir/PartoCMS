<?php
/**
 * PartoCMS - Translation Queue Manager
 * مدیریت صف ترجمه — مستقل از محیط سرور
 *
 * @version 1.0
 * @date 2026-09-16
 */
class QueueManager {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    //   افزودن به صف
    // ============================================================

    /**
     * افزودن یک محتوا به صف (همه زبان‌های فعال)
     *
     * @return array  نتیجه
     */
    public function enqueueContent(int $contentId, ?int $userId = null, int $priority = 5): array {
        // چک وجود محتوا
        try {
            $stmt = $this->pdo->prepare("SELECT id, status FROM content_items WHERE id = ? LIMIT 1");
            $stmt->execute([$contentId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return ['ok' => false, 'error' => 'content_not_found'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        // زبان‌های هدف
        $langs = $this->getTargetLanguages();
        if (empty($langs)) {
            return ['ok' => false, 'error' => 'no_target_languages'];
        }

        $added = 0;
        $skipped = 0;

        foreach ($langs as $lang) {
            // چک تکراری — اگه pending/processing داره، skip
            if ($this->hasPendingJob($contentId, $lang['code'])) {
                $skipped++;
                continue;
            }

            // چک: قبلاً ترجمه شده؟
            if ($this->hasTranslation($contentId, $lang['id'])) {
                $skipped++;
                continue;
            }

            if ($this->addJob($contentId, $lang['code'], $userId, $priority)) {
                $added++;
            }
        }

        return [
            'ok' => true,
            'content_id' => $contentId,
            'added' => $added,
            'skipped' => $skipped,
            'total_langs' => count($langs),
        ];
    }

    /**
     * افزودن یک job منفرد
     */
    public function addJob(int $contentId, string $langCode, ?int $userId = null, int $priority = 5, int $maxAttempts = 3): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO translation_queue
                    (content_id, language_code, user_id, priority, max_attempts, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            return $stmt->execute([$contentId, $langCode, $userId, $priority, $maxAttempts]);
        } catch (Throwable $e) {
            return false;
        }
    }

    // ============================================================
    //   چک‌های وضعیت
    // ============================================================

    public function hasPendingJob(int $contentId, string $langCode): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM translation_queue
                WHERE content_id = ? AND language_code = ?
                  AND status IN ('pending', 'processing')
                LIMIT 1
            ");
            $stmt->execute([$contentId, $langCode]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function hasTranslation(int $contentId, int $langId): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM content_translations
                WHERE content_id = ? AND language_id = ?
                LIMIT 1
            ");
            $stmt->execute([$contentId, $langId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    // ============================================================
    //   گرفتن job
    // ============================================================

    /**
     * گرفتن job بعدی برای پردازش (اتمیک)
     */
    public function getNextJob(): ?array {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->query("
                SELECT * FROM translation_queue
                WHERE status = 'pending'
                  AND attempts < max_attempts
                ORDER BY priority ASC, created_at ASC
                LIMIT 1
                FOR UPDATE
            ");
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $this->pdo->prepare("
                UPDATE translation_queue
                SET status = 'processing',
                    started_at = NOW(),
                    attempts = attempts + 1
                WHERE id = ?
            ")->execute([$job['id']]);

            $this->pdo->commit();

            return $job;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    /**
     * علامت‌گذاری job به عنوان done
     */
    public function markDone(int $jobId): bool {
        try {
            return $this->pdo->prepare("
                UPDATE translation_queue
                SET status = 'done',
                    completed_at = NOW(),
                    error_message = NULL
                WHERE id = ?
            ")->execute([$jobId]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * علامت‌گذاری job به عنوان failed (یا retry)
     */
    public function markFailed(int $jobId, string $error): bool {
        try {
            // چک attempts
            $stmt = $this->pdo->prepare("SELECT attempts, max_attempts FROM translation_queue WHERE id = ? LIMIT 1");
            $stmt->execute([$jobId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return false;

            $shouldRetry = (int)$row['attempts'] < (int)$row['max_attempts'];

            if ($shouldRetry) {
                return $this->pdo->prepare("
                    UPDATE translation_queue
                    SET status = 'pending',
                        error_message = ?
                    WHERE id = ?
                ")->execute([mb_substr($error, 0, 500), $jobId]);
            } else {
                return $this->pdo->prepare("
                    UPDATE translation_queue
                    SET status = 'failed',
                        completed_at = NOW(),
                        error_message = ?
                    WHERE id = ?
                ")->execute([mb_substr($error, 0, 500), $jobId]);
            }
        } catch (Throwable $e) {
            return false;
        }
    }

    // ============================================================
    //   آمار و مانیتور
    // ============================================================

    public function getStats(): array {
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total' => 0,
        ];

        try {
            $rows = $this->pdo->query("
                SELECT status, COUNT(*) as cnt
                FROM translation_queue
                GROUP BY status
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $stats[$row['status']] = (int)$row['cnt'];
                $stats['total'] += (int)$row['cnt'];
            }
        } catch (Throwable $e) {}

        return $stats;
    }

    /**
     * آخرین job ها برای مانیتور
     */
    public function getRecentJobs(int $limit = 50): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT q.*, ci.title as content_title, l.native_name as lang_name, l.flag as lang_flag
                FROM translation_queue q
                LEFT JOIN content_items ci ON ci.id = q.content_id
                LEFT JOIN languages l ON l.code = q.language_code
                ORDER BY q.id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // ============================================================
    //   عملیات مدیریتی
    // ============================================================

    /**
     * پاک کردن job های تمام‌شده (بیشتر از ۷ روز)
     */
    public function cleanOldJobs(int $daysOld = 7): int {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM translation_queue
                WHERE status IN ('done', 'failed')
                  AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * پاک کردن کل صف
     */
    public function clearAll(): int {
        try {
            return (int)$this->pdo->exec("TRUNCATE TABLE translation_queue");
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * ریست job های گیرکرده (processing > 10 دقیقه)
     */
    public function resetStuck(): int {
        try {
            $stmt = $this->pdo->exec("
                UPDATE translation_queue
                SET status = 'pending',
                    error_message = 'Reset (stuck)'
                WHERE status = 'processing'
                  AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            return $stmt;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * ریست job های failed (برای retry دستی)
     */
    public function retryFailed(): int {
        try {
            return (int)$this->pdo->exec("
                UPDATE translation_queue
                SET status = 'pending',
                    attempts = 0,
                    error_message = NULL
                WHERE status = 'failed'
            ");
        } catch (Throwable $e) {
            return 0;
        }
    }

    // ============================================================
    //   زبان‌های هدف
    // ============================================================

    public function getTargetLanguages(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT id, code, name, native_name, flag
                FROM languages
                WHERE is_active = 1 AND is_default = 0
                ORDER BY sort_order, id
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
