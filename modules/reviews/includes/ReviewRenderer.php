<?php
/**
 * 🆕 PartoCMS - Reviews Module - ReviewRenderer
 *
 * رندر UI نظرات و امتیازدهی
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

require_once __DIR__ . '/ReviewManager.php';

class ReviewRenderer
{
    private ReviewManager $manager;
    private string $baseUrl;

    public function __construct(ReviewManager $manager)
    {
        $this->manager = $manager;
        $this->baseUrl = defined('SITE_URL') ? SITE_URL : '';
    }

    // ═══════════════════════════════════════════════════════════
    // ۱. ستاره‌های امتیاز (Static)
    // ═══════════════════════════════════════════════════════════

    /**
     * نمایش ستاره‌ها (فقط خواندنی)
     */
    public static function stars(float $rating, int $max = 5, string $size = 'md'): string
    {
        $rating = max(0, min($max, $rating));
        $sizes = ['sm' => '14px', 'md' => '18px', 'lg' => '24px', 'xl' => '32px'];
        $fontSize = $sizes[$size] ?? $sizes['md'];

        $html = '<span class="review-stars" style="display:inline-flex;gap:2px;direction:ltr;font-size:' . $fontSize . ';">';

        for ($i = 1; $i <= $max; $i++) {
            if ($rating >= $i) {
                $html .= '<span style="color:#fbbf24;">★</span>';
            } elseif ($rating >= $i - 0.5) {
                $html .= '<span style="color:#fbbf24;">⯨</span>';
            } else {
                $html .= '<span style="color:#e2e8f0;">★</span>';
            }
        }

        $html .= '</span>';
        return $html;
    }

    /**
     * ستاره‌های قابل کلیک (برای فرم)
     */
    public static function starsInput(string $name = 'rating', int $current = 0, int $max = 5): string
    {
        $html = '<div class="review-stars-input" data-name="' . htmlspecialchars($name) . '" style="display:inline-flex;gap:4px;direction:ltr;font-size:32px;cursor:pointer;">';

        for ($i = 1; $i <= $max; $i++) {
            $color = ($i <= $current) ? '#fbbf24' : '#e2e8f0';
            $html .= '<span class="star" data-value="' . $i . '" style="color:' . $color . ';transition:all 0.15s;">★</span>';
        }

        $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . $current . '">';
        $html .= '</div>';

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // ۲. خلاصه آمار
    // ═══════════════════════════════════════════════════════════

    /**
     * نمایش خلاصه امتیازها (نمودار)
     */
    public function renderSummary(string $entityType, int $entityId): string
    {
        $stats = $this->manager->getStats($entityType, $entityId);

        if (!$stats['has_reviews']) {
            return '<div class="review-summary-empty" style="padding:20px;text-align:center;color:#64748b;background:#f8fafc;border-radius:12px;">
                هنوز نظری ثبت نشده است. اولین نفر باشید!
            </div>';
        }

        $avg = $stats['average'];
        $count = $stats['count'];
        $dist = $stats['distribution'];

        $html = '<div class="review-summary" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:20px;">';
        $html .= '<div style="display:grid;grid-template-columns:auto 1fr;gap:30px;align-items:center;">';

        // ─── میانگین ───
        $html .= '<div style="text-align:center;min-width:120px;">';
        $html .= '<div style="font-size:48px;font-weight:bold;color:#1e293b;line-height:1;">' . number_format($avg, 1) . '</div>';
        $html .= '<div style="margin:8px 0;">' . self::stars($avg, 5, 'md') . '</div>';
        $html .= '<div style="color:#64748b;font-size:13px;">از ' . $count . ' نظر</div>';
        $html .= '</div>';

        // ─── توزیع ───
        $html .= '<div>';
        for ($star = 5; $star >= 1; $star--) {
            $starCount = $dist[$star] ?? 0;
            $percent = $count > 0 ? ($starCount / $count) * 100 : 0;

            $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;font-size:13px;">';
            $html .= '<span style="color:#64748b;min-width:30px;direction:ltr;">' . $star . ' ★</span>';
            $html .= '<div style="flex:1;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">';
            $html .= '<div style="height:100%;width:' . round($percent, 1) . '%;background:#fbbf24;"></div>';
            $html .= '</div>';
            $html .= '<span style="color:#64748b;min-width:35px;text-align:left;">' . $starCount . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div></div>';

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // ۳. فرم ثبت نظر
    // ═══════════════════════════════════════════════════════════

    /**
     * نمایش فرم ثبت نظر
     */
    public function renderForm(string $entityType, int $entityId, array $options = []): string
    {
        $settings = $this->manager->getSettings($entityType);
        $user = $options['user'] ?? null;
        $isLoggedIn = !empty($user);

        $action = $this->baseUrl . '/modules/reviews/ajax/submit.php';

        $html = '<div class="review-form-wrapper" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:25px;margin-bottom:20px;">';
        $html .= '<h3 style="margin:0 0 20px;font-size:18px;color:#1e293b;">✍️ نظر خود را بنویسید</h3>';

        // ─── امتیاز ───
        if (!empty($settings['max_rating'])) {
            $html .= '<div style="margin-bottom:15px;">';
            $html .= '<label style="display:block;font-weight:bold;margin-bottom:8px;font-size:14px;color:#334155;">امتیاز شما:</label>';
            $html .= self::starsInput('rating', 0, (int) $settings['max_rating']);
            $html .= '</div>';
        }

        // ─── نام ───
        $nameValue = $isLoggedIn ? htmlspecialchars($user['username'] ?? '') : '';
        $nameReadonly = $isLoggedIn ? 'readonly' : '';
        $html .= '<div style="margin-bottom:15px;">';
        $html .= '<label style="display:block;font-weight:bold;margin-bottom:6px;font-size:14px;color:#334155;">نام <span style="color:#dc2626;">*</span></label>';
        $html .= '<input type="text" id="reviewAuthorName" class="form-control" value="' . $nameValue . '" ' . $nameReadonly . ' placeholder="نام شما" maxlength="100" style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;">';
        $html .= '</div>';

        // ─── ایمیل ───
        if (!empty($settings['require_email']) && !$isLoggedIn) {
            $html .= '<div style="margin-bottom:15px;">';
            $html .= '<label style="display:block;font-weight:bold;margin-bottom:6px;font-size:14px;color:#334155;">ایمیل <span style="color:#dc2626;">*</span></label>';
            $html .= '<input type="email" id="reviewAuthorEmail" class="form-control" placeholder="you@example.com" style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;direction:ltr;">';
            $html .= '</div>';
        }

        // ─── متن نظر ───
        $html .= '<div style="margin-bottom:15px;">';
        $html .= '<label style="display:block;font-weight:bold;margin-bottom:6px;font-size:14px;color:#334155;">متن نظر <span style="color:#dc2626;">*</span></label>';
        $html .= '<textarea id="reviewContent" class="form-control" rows="5" placeholder="نظر خود را بنویسید..." maxlength="5000" style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;"></textarea>';
        $html .= '</div>';

        // ─── دکمه ───
        $html .= '<div style="display:flex;gap:10px;align-items:center;">';
        $html .= '<button type="button" class="review-submit-btn" onclick="submitReview()" style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;padding:12px 30px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:14px;">📤 ارسال نظر</button>';
        $html .= '<span id="reviewFormMsg" style="font-size:13px;"></span>';
        $html .= '</div>';

        $html .= '</div>';

        // ─── JavaScript ───
        $html .= $this->getFormJs($entityType, $entityId, $action);

        return $html;
    }

    /**
     * JavaScript فرم
     */
    private function getFormJs(string $entityType, int $entityId, string $action): string
    {
        $entityType = htmlspecialchars($entityType);
        $entityId = (int) $entityId;
        $action = htmlspecialchars($action);

        return <<<JS
<script>
(function() {
    // ─── ستاره‌های کلیک‌پذیر ───
    document.querySelectorAll('.review-stars-input').forEach(function(wrapper) {
        const stars = wrapper.querySelectorAll('.star');
        const input = wrapper.querySelector('input');

        stars.forEach(function(star) {
            star.addEventListener('mouseenter', function() {
                const val = parseInt(this.dataset.value);
                stars.forEach(function(s) {
                    s.style.color = (parseInt(s.dataset.value) <= val) ? '#fbbf24' : '#e2e8f0';
                });
            });

            star.addEventListener('click', function() {
                const val = parseInt(this.dataset.value);
                input.value = val;
                wrapper.dataset.selected = val;
            });
        });

        wrapper.addEventListener('mouseleave', function() {
            const selected = parseInt(wrapper.dataset.selected || input.value || 0);
            stars.forEach(function(s) {
                s.style.color = (parseInt(s.dataset.value) <= selected) ? '#fbbf24' : '#e2e8f0';
            });
        });
    });
})();

function submitReview() {
    const formMsg = document.getElementById('reviewFormMsg');
    const ratingInput = document.querySelector('.review-stars-input input');
    const nameInput = document.getElementById('reviewAuthorName');
    const emailInput = document.getElementById('reviewAuthorEmail');
    const contentInput = document.getElementById('reviewContent');
    const btn = document.querySelector('.review-submit-btn');

    const data = {
        entity_type: '{$entityType}',
        entity_id: {$entityId},
        author_name: nameInput ? nameInput.value.trim() : '',
        author_email: emailInput ? emailInput.value.trim() : '',
        content: contentInput ? contentInput.value.trim() : '',
        rating: ratingInput && ratingInput.value ? parseInt(ratingInput.value) : null
    };

    // ─── اعتبارسنجی ───
    if (!data.author_name) {
        showMsg('نام الزامی است', 'error');
        return;
    }
    if (!data.content || data.content.length < 5) {
        showMsg('متن نظر حداقل ۵ کاراکتر', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '⏳ در حال ارسال...';
    showMsg('', '');

    const formData = new FormData();
    Object.keys(data).forEach(k => formData.append(k, data[k]));

    fetch('{$action}', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(function(res) {
        if (res.ok) {
            showMsg('✅ ' + (res.message || 'نظر شما ثبت شد'), 'success');
            if (contentInput) contentInput.value = '';
            if (ratingInput) ratingInput.value = '';
            document.querySelectorAll('.review-stars-input .star').forEach(s => s.style.color = '#e2e8f0');
        } else {
            showMsg('❌ ' + (res.error || 'خطا در ثبت'), 'error');
        }
    })
    .catch(function(err) {
        showMsg('❌ خطای شبکه: ' + err.message, 'error');
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '📤 ارسال نظر';
    });

    function showMsg(text, type) {
        if (!formMsg) return;
        formMsg.textContent = text;
        formMsg.style.color = type === 'success' ? '#16a34a' : (type === 'error' ? '#dc2626' : '#64748b');
    }
}
</script>
JS;
    }

    // ═══════════════════════════════════════════════════════════
    // ۴. لیست نظرات
    // ═══════════════════════════════════════════════════════════

    /**
     * نمایش لیست نظرات
     */
    public function renderList(string $entityType, int $entityId, array $options = []): string
    {
        $settings = $this->manager->getSettings($entityType);
        $limit = (int) ($options['limit'] ?? $settings['items_per_page'] ?? 10);
        $sort = $options['sort'] ?? 'newest';

        $reviews = $this->manager->getByEntity($entityType, $entityId, [
            'status' => 'approved',
            'limit'  => $limit,
            'sort'   => $sort,
        ]);

        $total = $this->manager->countByEntity($entityType, $entityId, 'approved');

        $html = '<div class="reviews-list">';

        // ─── Header ───
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:10px;">';
        $html .= '<h3 style="margin:0;font-size:17px;color:#1e293b;">💬 نظرات کاربران (' . $total . ')</h3>';

        if ($total > 1) {
            $html .= '<select onchange="sortReviews(this.value)" style="padding:8px 12px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;">';
            $html .= '<option value="newest"' . ($sort === 'newest' ? ' selected' : '') . '>جدیدترین</option>';
            $html .= '<option value="oldest"' . ($sort === 'oldest' ? ' selected' : '') . '>قدیمی‌ترین</option>';
            $html .= '<option value="highest"' . ($sort === 'highest' ? ' selected' : '') . '>بیشترین امتیاز</option>';
            $html .= '<option value="lowest"' . ($sort === 'lowest' ? ' selected' : '') . '>کمترین امتیاز</option>';
            $html .= '<option value="helpful"' . ($sort === 'helpful' ? ' selected' : '') . '>مفیدترین</option>';
            $html .= '</select>';
        }
        $html .= '</div>';

        // ─── Empty ───
        if (empty($reviews)) {
            $html .= '<div style="padding:30px;text-align:center;background:#f8fafc;border-radius:12px;color:#64748b;">';
            $html .= '<div style="font-size:40px;margin-bottom:10px;">💭</div>';
            $html .= '<div>هنوز نظری ثبت نشده. اولین نفر باشید!</div>';
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        }

        // ─── Reviews ───
        foreach ($reviews as $review) {
            $html .= $this->renderReview($review, $settings);
        }

        // ─── Load More ───
        if ($total > count($reviews)) {
            $html .= '<div style="text-align:center;margin-top:20px;">';
            $html .= '<button onclick="loadMoreReviews()" style="background:#f1f5f9;color:#334155;border:none;padding:12px 30px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:14px;">نمایش نظرات بیشتر</button>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // ─── JS برای sort/load ───
        $entityTypeEscaped = htmlspecialchars($entityType);
        $html .= <<<JS
<script>
function sortReviews(sort) {
    const url = new URL(window.location.href);
    url.searchParams.set('review_sort', sort);
    window.location.href = url.toString();
}

function loadMoreReviews() {
    const currentUrl = new URL(window.location.href);
    const limit = parseInt(currentUrl.searchParams.get('review_limit') || '{$limit}');
    currentUrl.searchParams.set('review_limit', limit + {$limit});
    window.location.href = currentUrl.toString();
}
</script>
JS;

        return $html;
    }

    /**
     * رندر یک نظر
     */
    public function renderReview(array $review, array $settings = []): string
    {
        $authorName = htmlspecialchars($review['author_name'] ?? '');
        $username = htmlspecialchars($review['user_username'] ?? '');
        $content = nl2br(htmlspecialchars($review['content'] ?? ''));
        $title = htmlspecialchars($review['title'] ?? '');
        $rating = (int) ($review['rating'] ?? 0);
        $createdAt = $review['created_at'] ?? '';
        $isVerified = !empty($review['is_verified_purchase']);
        $helpful = (int) ($review['helpful_count'] ?? 0);
        $notHelpful = (int) ($review['not_helpful_count'] ?? 0);
        $reviewId = (int) ($review['id'] ?? 0);
        $userId = (int) ($review['user_id'] ?? 0);

        // فرمت تاریخ
        $date = $createdAt ? date('Y/m/d', strtotime($createdAt)) : '';

        $html = '<div class="review-item" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:15px;" data-review-id="' . $reviewId . '">';

        // ─── Header ───
        $html .= '<div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;">';

        // آواتار
        $initial = mb_substr($authorName, 0, 1);
        $html .= '<div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18px;flex-shrink:0;">' . $initial . '</div>';

        // اطلاعات نویسنده
        $html .= '<div style="flex:1;">';
        $html .= '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
        $html .= '<strong style="color:#1e293b;font-size:14.5px;">' . $authorName . '</strong>';

        if ($isVerified) {
            $html .= '<span style="background:#dcfce7;color:#166534;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:bold;">✅ خرید تأیید شده</span>';
        }

        $html .= '</div>';
        $html .= '<div style="display:flex;align-items:center;gap:10px;margin-top:4px;">';
        if ($rating > 0) {
            $html .= self::stars($rating, 5, 'sm');
        }
        $html .= '<span style="color:#94a3b8;font-size:12px;">' . $date . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        // ─── Title ───
        if ($title) {
            $html .= '<h4 style="margin:10px 0 8px;font-size:15px;color:#1e293b;">' . $title . '</h4>';
        }

        // ─── Content ───
        $html .= '<div style="color:#475569;font-size:14px;line-height:1.8;margin-bottom:15px;">' . $content . '</div>';

        // ─── Actions ───
        if (!empty($settings['allow_helpful_votes'])) {
            $html .= '<div style="display:flex;gap:15px;font-size:12.5px;color:#64748b;padding-top:10px;border-top:1px solid #f1f5f9;">';
            $html .= '<button onclick="voteReview(' . $reviewId . ', \'helpful\')" style="background:none;border:none;color:#64748b;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:inherit;font-size:12.5px;padding:4px 8px;border-radius:6px;">👍 مفید (' . $helpful . ')</button>';
            $html .= '<button onclick="voteReview(' . $reviewId . ', \'not_helpful\')" style="background:none;border:none;color:#64748b;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:inherit;font-size:12.5px;padding:4px 8px;border-radius:6px;">👎 غیرمفید (' . $notHelpful . ')</button>';
            $html .= '</div>';
        }

        // ─── Replies ───
        if (!empty($review['replies'])) {
            $html .= '<div style="margin-top:15px;padding-right:20px;border-right:3px solid #e2e8f0;">';
            foreach ($review['replies'] as $reply) {
                $replyAuthor = htmlspecialchars($reply['author_name'] ?? '');
                $replyContent = nl2br(htmlspecialchars($reply['content'] ?? ''));
                $replyDate = $reply['created_at'] ? date('Y/m/d', strtotime($reply['created_at'])) : '';

                $html .= '<div style="background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:8px;">';
                $html .= '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">';
                $html .= '<strong style="font-size:13px;color:#1e293b;">' . $replyAuthor . '</strong>';
                $html .= '<span style="font-size:11.5px;color:#94a3b8;">' . $replyDate . '</span>';
                $html .= '</div>';
                $html .= '<div style="font-size:13px;color:#475569;line-height:1.7;">' . $replyContent . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // ۵. Vote AJAX
    // ═══════════════════════════════════════════════════════════

    /**
     * JavaScript برای رأی
     */
    public static function getVoteJs(): string
    {
        $action = (defined('SITE_URL') ? SITE_URL : '') . '/modules/reviews/ajax/vote.php';

        return <<<JS
<script>
function voteReview(reviewId, type) {
    const formData = new FormData();
    formData.append('review_id', reviewId);
    formData.append('vote_type', type);

    fetch('{$action}', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(function(res) {
        if (res.ok) {
            const item = document.querySelector('[data-review-id="' + reviewId + '"]');
            if (item) {
                const btns = item.querySelectorAll('button');
                btns.forEach(b => b.disabled = true);
            }
        } else {
            alert(res.error || 'خطا در ثبت رأی');
        }
    })
    .catch(function(err) {
        alert('خطای شبکه');
    });
}
</script>
JS;
    }
}
