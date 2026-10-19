<?php
/**
 * PartoCMS — Testimonials Module
 * Domain Service: TestimonialService
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Domain\Service;

use InvalidArgumentException;
use PartoCMS\Testimonials\Domain\Entity\Testimonial;
use PartoCMS\Testimonials\Domain\Exception\TestimonialException;
use PartoCMS\Testimonials\Domain\Repository\TestimonialRepositoryInterface;

final class TestimonialService
{
    public function __construct(
        private readonly TestimonialRepositoryInterface $repository
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Submission (from frontend or admin)
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت نظر جدید از فرانت‌اند با کنترل spam
     *
     * @param array{
     *     name: string,
     *     email?: ?string,
     *     company?: ?string,
     *     position?: ?string,
     *     content: string,
     *     rating?: ?int,
     *     avatar_url?: ?string,
     *     language?: string
     * } $data
     * @param string|null $ip آدرس IP کاربر (برای ضد spam)
     * @return int شناسه نظر ایجاد‌شده
     * @throws TestimonialException
     */
    public function submitFromFrontend(array $data, ?string $ip = null): int
    {
        // ضد spam: بررسی ارسال‌های اخیر
        if ($ip !== null && $this->repository->hasRecentSubmission($ip, 5)) {
            throw new TestimonialException(
                'شما به‌تازگی نظر ثبت کرده‌اید. لطفاً چند دقیقه صبر کنید.',
                429
            );
        }

        // محدودیت IP در ۲۴ ساعت
        if ($ip !== null && $this->repository->countByIp($ip, 24) >= 10) {
            throw new TestimonialException(
                'حداکثر ۱۰ نظر در روز مجاز است.',
                429
            );
        }

        // اعتبارسنجی محتوای نظر
        $this->validateContent($data['content'] ?? '');

        $testimonial = new Testimonial(
            null,
            (string) ($data['name'] ?? ''),
            $data['email'] ?? null,
            $data['company'] ?? null,
            $data['position'] ?? null,
            (string) ($data['content'] ?? ''),
            isset($data['rating']) ? (int) $data['rating'] : null,
            $data['avatar_url'] ?? null,
            Testimonial::STATUS_PENDING,
            false,
            0,
            (string) ($data['language'] ?? 'fa-IR'),
            $ip
        );

        return $this->repository->create($testimonial);
    }

    /**
     * ثبت توسط ادمین (بدون ضد spam، می‌تواند مستقیماً published)
     *
     * @param array $data
     * @param bool $publishImmediately
     * @return int
     */
    public function createByAdmin(array $data, bool $publishImmediately = false): int
    {
        $testimonial = new Testimonial(
            null,
            (string) ($data['name'] ?? ''),
            $data['email'] ?? null,
            $data['company'] ?? null,
            $data['position'] ?? null,
            (string) ($data['content'] ?? ''),
            isset($data['rating']) ? (int) $data['rating'] : null,
            $data['avatar_url'] ?? null,
            $publishImmediately ? Testimonial::STATUS_PUBLISHED : Testimonial::STATUS_PENDING,
            (bool) ($data['is_featured'] ?? false),
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['language'] ?? 'fa-IR'),
            null
        );

        return $this->repository->create($testimonial);
    }

    /**
     * به‌روزرسانی از پنل ادمین
     */
    public function updateByAdmin(int $id, array $data): bool
    {
        $testimonial = $this->getByIdOrFail($id);

        if (isset($data['name']))         $testimonial->setName((string) $data['name']);
        if (array_key_exists('email', $data))       $testimonial->setEmail($data['email']);
        if (array_key_exists('company', $data))     $testimonial->setCompany($data['company']);
        if (array_key_exists('position', $data))    $testimonial->setPosition($data['position']);
        if (isset($data['content']))      $testimonial->setContent((string) $data['content']);
        if (array_key_exists('rating', $data))      $testimonial->setRating(isset($data['rating']) ? (int) $data['rating'] : null);
        if (array_key_exists('avatar_url', $data))  $testimonial->setAvatarUrl($data['avatar_url']);
        if (isset($data['language']))     $testimonial->setLanguage((string) $data['language']);
        if (isset($data['sort_order']))   $testimonial->setSortOrder((int) $data['sort_order']);

        if (isset($data['is_featured']) && (bool) $data['is_featured'] !== $testimonial->isFeatured()) {
            $testimonial->toggleFeatured();
        }

        if (isset($data['status'])) {
            $testimonial->setStatus((string) $data['status']);
        }

        return $this->repository->update($testimonial);
    }

    // ═══════════════════════════════════════════════════════════
    // Moderation
    // ═══════════════════════════════════════════════════════════

    public function approve(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Testimonial::STATUS_PUBLISHED);
    }

    public function reject(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Testimonial::STATUS_REJECTED);
    }

    public function markPending(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Testimonial::STATUS_PENDING);
    }

    public function toggleFeatured(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->toggleFeatured($id);
    }

    public function updateSortOrder(int $id, int $order): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateSortOrder($id, $order);
    }

    public function delete(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->delete($id);
    }

    // ═══════════════════════════════════════════════════════════
    // Bulk Moderation
    // ═══════════════════════════════════════════════════════════

    /**
     * @param int[] $ids
     */
    public function bulkApprove(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, Testimonial::STATUS_PUBLISHED);
    }

    /**
     * @param int[] $ids
     */
    public function bulkReject(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, Testimonial::STATUS_REJECTED);
    }

    /**
     * @param int[] $ids
     */
    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    // ═══════════════════════════════════════════════════════════
    // Read Operations
    // ═══════════════════════════════════════════════════════════

    public function getById(int $id): ?Testimonial
    {
        return $this->repository->findById($id);
    }

    public function getByIdOrFail(int $id): Testimonial
    {
        $t = $this->repository->findById($id);
        if ($t === null) {
            throw TestimonialException::notFound($id);
        }
        return $t;
    }

    /**
     * @return Testimonial[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    /**
     * @return Testimonial[]
     */
    public function getPublished(array $filters = []): array
    {
        return $this->repository->findPublished($filters);
    }

    /**
     * نظرات ویژه برای صفحه اصلی
     * @return Testimonial[]
     */
    public function getFeatured(int $limit = 6): array
    {
        return $this->repository->findPublished([
            'is_featured' => true,
            'limit' => $limit,
        ]);
    }

    /**
     * نظرات با امتیاز بالا (۴-۵ ستاره)
     * @return Testimonial[]
     */
    public function getTopRated(int $limit = 10): array
    {
        return $this->repository->findPublished([
            'min_rating' => 4,
            'order_by' => 'rating',
            'order_dir' => 'DESC',
            'limit' => $limit,
        ]);
    }

    public function getCount(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    // ═══════════════════════════════════════════════════════════
    // Business Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * فرمت داده برای نمایش در ویجت
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     company: ?string,
     *     position: ?string,
     *     content: string,
     *     rating: ?int,
     *     avatar_url: ?string,
     *     is_featured: bool
     * }
     */
    public function formatForWidget(Testimonial $t): array
    {
        return [
            'id'         => $t->getId() ?? 0,
            'name'       => $t->getName(),
            'company'    => $t->getCompany(),
            'position'   => $t->getPosition(),
            'content'    => $t->getContent(),
            'rating'     => $t->getRating(),
            'avatar_url' => $t->getAvatarUrl(),
            'is_featured' => $t->isFeatured(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════

    private function validateContent(string $content): void
    {
        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('متن نظر نمی‌تواند خالی باشد');
        }

        if (mb_strlen($content) < 10) {
            throw new InvalidArgumentException('متن نظر باید حداقل ۱۰ کاراکتر باشد');
        }

        if (mb_strlen($content) > 5000) {
            throw new InvalidArgumentException('متن نظر حداکثر ۵۰۰۰ کاراکتر');
        }

        // تشخیص لینک‌های زیاد (spam)
        $linkCount = preg_match_all('/https?:\/\//i', $content);
        if ($linkCount > 2) {
            throw new InvalidArgumentException('حداکثر ۲ لینک در نظر مجاز است');
        }

        // تشخیص کلمات رایج spam
        $spamWords = ['viagra', 'casino', 'porn', 'bitcoin giveaway'];
        foreach ($spamWords as $word) {
            if (stripos($content, $word) !== false) {
                throw new TestimonialException('محتوای نظر مشکوک است', 400);
            }
        }
    }
}
