<?php
/**
 * PartoCMS - Ads Module - AdTargeting
 * بررسی هدف‌گیری محتوا برای تبلیغات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 *
 * سه حالت هدف‌گیری:
 *   - all    → همه صفحات
 *   - manual → انتخاب دستی (دسته/مقاله/برگه/برچسب)
 *   - rules  → قواعد پیشرفته با AND/OR
 */

class AdTargeting
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ═══════════════════════════════════════════════════════════
    // API اصلی
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی می‌کند آیا تبلیغ باید در صفحه فعلی نمایش داده شود
     *
     * @param array $ad تبلیغ (شامل target_mode, target_logic, rules)
     * @param array $context اطلاعات صفحه فعلی:
     *   - content_id   : ID محتوا (اگر در صفحه محتوا هستیم)
     *   - category_ids : آرایه ID دسته‌بندی‌های محتوا
     *   - tag_ids      : آرایه ID برچسب‌های محتوا
     *   - author_id    : ID نویسنده
     *   - content_type : نوع محتوا (post, page, ...)
     * @return bool
     */
    public function shouldShow(array $ad, array $context = []): bool
    {
        $mode = $ad['target_mode'] ?? 'all';

        switch ($mode) {
            case 'all':
                return true;

            case 'manual':
                return $this->checkManual($ad, $context);

            case 'rules':
                return $this->checkRules($ad, $context);

            default:
                return true;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // حالت Manual — انتخاب دستی
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی حالت manual
     * قاعده: تبلیغ نمایش داده می‌شود اگر حداقل یکی از قواعد "include" مطابقت کند
     *        و هیچ‌کدام از قواعد "exclude" مطابقت نکنند
     */
    private function checkManual(array $ad, array $context): bool
    {
        $rules = $ad['target_rules'] ?? [];

        if (empty($rules)) {
            return true; // اگر قاعده‌ای نیست → همه‌جا نمایش
        }

        // جدا کردن قواعد
        $includeRules = [];
        $excludeRules = [];

        foreach ($rules as $rule) {
            if (($rule['include_exclude'] ?? 'include') === 'exclude') {
                $excludeRules[] = $rule;
            } else {
                $includeRules[] = $rule;
            }
        }

        // بررسی exclude — اگر یکی مطابقت کند → نمایش نده
        foreach ($excludeRules as $rule) {
            if ($this->matchRule($rule, $context)) {
                return false;
            }
        }

        // بررسی include — اگر لیست خالی است → همه‌جا (فقط exclude چک شد)
        if (empty($includeRules)) {
            return true;
        }

        // اگر حداقل یکی مطابقت کند → نمایش بده
        foreach ($includeRules as $rule) {
            if ($this->matchRule($rule, $context)) {
                return true;
            }
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت Rules — قواعد پیشرفته
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی حالت rules با منطق AND/OR
     */
    private function checkRules(array $ad, array $context): bool
    {
        $rules = $ad['target_rules'] ?? [];
        $logic = $ad['target_logic'] ?? 'AND';

        if (empty($rules)) {
            return true;
        }

        // جدا کردن include و exclude
        $includeRules = [];
        $excludeRules = [];

        foreach ($rules as $rule) {
            if (($rule['include_exclude'] ?? 'include') === 'exclude') {
                $excludeRules[] = $rule;
            } else {
                $includeRules[] = $rule;
            }
        }

        // اول exclude چک شود
        foreach ($excludeRules as $rule) {
            if ($this->matchRule($rule, $context)) {
                return false;
            }
        }

        // اگر include خالی است → همه‌جا
        if (empty($includeRules)) {
            return true;
        }

        // حالا include با منطق AND/OR
        if ($logic === 'AND') {
            foreach ($includeRules as $rule) {
                if (!$this->matchRule($rule, $context)) {
                    return false;
                }
            }
            return true;
        }

        // OR
        foreach ($includeRules as $rule) {
            if ($this->matchRule($rule, $context)) {
                return true;
            }
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════
    // بررسی یک قاعده
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا یک قاعده با context فعلی مطابقت دارد؟
     */
    private function matchRule(array $rule, array $context): bool
    {
        $type = $rule['entity_type'] ?? '';
        $id = (int) ($rule['entity_id'] ?? 0);

        if ($id < 1) return false;

        switch ($type) {
            case 'category':
                $categoryIds = $context['category_ids'] ?? [];
                return in_array($id, $categoryIds, true);

            case 'post':
                return (int) ($context['content_id'] ?? 0) === $id
                    && ($context['content_type'] ?? '') === 'post';

            case 'page':
                return (int) ($context['content_id'] ?? 0) === $id
                    && ($context['content_type'] ?? '') === 'page';

            case 'tag':
                $tagIds = $context['tag_ids'] ?? [];
                return in_array($id, $tagIds, true);

            case 'author':
                return (int) ($context['author_id'] ?? 0) === $id;

            default:
                return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // مدیریت قواعد (CRUD)
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت قواعد یک تبلیغ
     */
    public function getRulesByAdId(int $adId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ad_target_rules
            WHERE ad_id = ?
            ORDER BY include_exclude ASC, sort_order ASC, id ASC
        ");
        $stmt->execute([$adId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * افزودن قاعده
     */
    public function createRule(int $adId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_target_rules
            (ad_id, logic_op, entity_type, entity_id, include_exclude, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $adId,
            $data['logic_op'] ?? 'AND',
            $data['entity_type'] ?? 'category',
            (int) $data['entity_id'],
            $data['include_exclude'] ?? 'include',
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * حذف قاعده
     */
    public function deleteRule(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM ad_target_rules WHERE id = ?")->execute([$id]);
    }

    /**
     * حذف همه قواعد یک تبلیغ
     */
    public function deleteRulesByAdId(int $adId): int
    {
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM ad_target_rules WHERE ad_id = $adId")->fetchColumn();
        $this->pdo->prepare("DELETE FROM ad_target_rules WHERE ad_id = ?")->execute([$adId]);
        return $count;
    }

    /**
     * ذخیره گروهی قواعد (جایگزینی کامل)
     */
    public function replaceRules(int $adId, array $rules): bool
    {
        try {
            $this->pdo->beginTransaction();
            $this->deleteRulesByAdId($adId);

            foreach ($rules as $i => $rule) {
                if (empty($rule['entity_type']) || empty($rule['entity_id'])) continue;
                $this->createRule($adId, [
                    'logic_op'        => $rule['logic_op'] ?? 'AND',
                    'entity_type'     => $rule['entity_type'],
                    'entity_id'       => (int) $rule['entity_id'],
                    'include_exclude' => $rule['include_exclude'] ?? 'include',
                    'sort_order'      => $i,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('AdTargeting replaceRules error: ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Context Builder — ساخت context از صفحه فعلی
    // ═══════════════════════════════════════════════════════════

    /**
     * ساخت context از یک content_item
     * (برای استفاده در frontend)
     */
    public function buildContextFromContent(int $contentId, string $contentType = 'post'): array
    {
        $context = [
            'content_id'   => $contentId,
            'content_type' => $contentType,
            'category_ids' => [],
            'tag_ids'      => [],
            'author_id'    => 0,
        ];

        try {
            // اطلاعات محتوا
            $stmt = $this->pdo->prepare("
                SELECT category_id, author_id 
                FROM content_items 
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$contentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if (!empty($row['category_id'])) {
                    $context['category_ids'][] = (int) $row['category_id'];
                }
                $context['author_id'] = (int) ($row['author_id'] ?? 0);
            }

            // برچسب‌ها
            $stmt = $this->pdo->prepare("
                SELECT tag_id FROM content_tags WHERE content_id = ?
            ");
            $stmt->execute([$contentId]);
            $tagIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $context['tag_ids'] = array_map('intval', $tagIds);

        } catch (Throwable $e) {
            error_log('AdTargeting buildContext error: ' . $e->getMessage());
        }

        return $context;
    }

    /**
     * ساخت context خالی (برای صفحات بدون محتوا — مثل home، category)
     */
    public function buildEmptyContext(): array
    {
        return [
            'content_id'   => 0,
            'content_type' => '',
            'category_ids' => [],
            'tag_ids'      => [],
            'author_id'    => 0,
        ];
    }

    /**
     * ساخت context از صفحه category
     */
    public function buildContextFromCategory(int $categoryId): array
    {
        return [
            'content_id'   => 0,
            'content_type' => 'category',
            'category_ids' => [$categoryId],
            'tag_ids'      => [],
            'author_id'    => 0,
        ];
    }
}
