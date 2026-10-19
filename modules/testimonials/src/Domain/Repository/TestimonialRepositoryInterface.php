<?php
/**
 * PartoCMS — Testimonials Module
 * Repository Interface: TestimonialRepositoryInterface
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Domain\Repository;

use PartoCMS\Testimonials\Domain\Entity\Testimonial;

interface TestimonialRepositoryInterface
{
    // ═══════════════════════════════════════════════════════════
    // Write Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * ایجاد تستیمونیال جدید
     * @return int شناسه ایجادشده
     */
    public function create(Testimonial $testimonial): int;

    /**
     * به‌روزرسانی تستیمونیال موجود
     */
    public function update(Testimonial $testimonial): bool;

    /**
     * حذف با شناسه
     */
    public function delete(int $id): bool;

    /**
     * تغییر وضعیت (pending/published/rejected)
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * تغییر وضعیت featured
     */
    public function toggleFeatured(int $id): bool;

    /**
     * تغییر ترتیب
     */
    public function updateSortOrder(int $id, int $order): bool;

    // ═══════════════════════════════════════════════════════════
    // Read Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * یافتن با شناسه
     */
    public function findById(int $id): ?Testimonial;

    /**
     * همه تستیمونیال‌ها با فیلترهای اختیاری
     *
     * @param array{
     *     status?: string,
     *     is_featured?: bool,
     *     language?: string,
     *     search?: string,
     *     limit?: int,
     *     offset?: int,
     *     order_by?: string,
     *     order_dir?: string
     * } $filters
     * @return Testimonial[]
     */
    public function findAll(array $filters = []): array;

    /**
     * فقط منتشرشده‌ها (برای فرانت‌اند)
     *
     * @param array{
     *     is_featured?: bool,
     *     language?: string,
     *     min_rating?: int,
     *     limit?: int,
     *     offset?: int
     * } $filters
     * @return Testimonial[]
     */
    public function findPublished(array $filters = []): array;

    /**
     * شمارش کل با فیلتر
     */
    public function count(array $filters = []): int;

    /**
     * آمار (برای داشبورد)
     *
     * @return array{
     *     total: int,
     *     pending: int,
     *     published: int,
     *     rejected: int,
     *     featured: int,
     *     avg_rating: float
     * }
     */
    public function getStats(): array;

    // ═══════════════════════════════════════════════════════════
    // Bulk Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * تغییر وضعیت چند تستیمونیال
     * @param int[] $ids
     */
    public function bulkUpdateStatus(array $ids, string $status): int;

    /**
     * حذف چند تستیمونیال
     * @param int[] $ids
     */
    public function bulkDelete(array $ids): int;

    // ═══════════════════════════════════════════════════════════
    // Spam Prevention
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی اینکه آیا IP در N دقیقه اخیر نظر داده؟
     */
    public function hasRecentSubmission(string $ip, int $minutes = 60): bool;

    /**
     * تعداد ارسال‌ها از یک IP
     */
    public function countByIp(string $ip, int $hours = 24): int;
}
