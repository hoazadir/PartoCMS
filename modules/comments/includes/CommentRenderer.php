<?php
/**
 * PartoCMS - Comments Module - CommentRenderer
 * نمایش دیدگاه‌ها در فرانت‌اند
 */

require_once __DIR__ . '/CommentManager.php';

class CommentRenderer
{
    private CommentManager $manager;

    public function __construct(CommentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * رندر کل بخش دیدگاه‌ها
     */
    public function render(int $contentId, array $options = []): string
    {
        $opts = array_merge([
            'show_title'    => true,
            'show_form'     => true,
            'show_list'     => true,
            'form_action'   => SITE_URL . '/modules/comments/ajax/submit.php',
        ], $options);

        $comments = $opts['show_list'] ? $this->manager->getByContent($contentId) : [];
        $count = $this->manager->countByContent($contentId);

        $html  = '<div id="comments-section" style="margin-top:50px;padding-top:40px;border-top:3px solid #f0f0f0;">';

        if ($opts['show_title']) {
            $html .= '<h3 style="color:#2c3e50;margin-bottom:25px;">';
            $html .= '💬 دیدگاه‌ها <span class="badge bg-secondary" style="font-size:14px;">' . $count . '</span>';
            $html .= '</h3>';
        }

        $html .= $this->renderList($comments);
        if ($opts['show_form']) {
            $html .= $this->renderForm($contentId, $opts['form_action']);
        }

        return $html . '</div>';
    }

    /**
     * رندر لیست دیدگاه‌ها
     */
    public function renderList(array $comments): string
    {
        if (empty($comments)) {
            return '<div class="alert alert-info text-center">'
                 . '<i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>'
                 . 'هنوز دیدگاهی ثبت نشده. اولین نفر باشید!</div>';
        }
        $html = '';
        foreach ($comments as $c) {
            $html .= $this->renderItem($c);
        }
        return $html;
    }

    /**
     * رندر یک دیدگاه (با پاسخ‌ها)
     */
    public function renderItem(array $c): string
    {
        $name    = htmlspecialchars($c['author_name'] ?? 'ناشناس');
        $initial = mb_substr($name, 0, 1);
        $text    = nl2br(htmlspecialchars($c['comment'] ?? ''));
        $date    = $c['created_at'] ?? '';

        $html  = '<div class="comment-item" id="comment-' . (int)$c['id'] . '" style="background:#f8f9fa;border-radius:12px;padding:20px;margin-bottom:15px;border-right:4px solid #3498db;">';
        $html .= '  <div style="display:flex;gap:15px;">';
        $html .= '    <div class="comment-avatar" style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:20px;flex-shrink:0;">' . $initial . '</div>';
        $html .= '    <div style="flex:1;">';
        $html .= '      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;margin-bottom:10px;">';
        $html .= '        <div><strong style="color:#2c3e50;font-size:15px;">' . $name . '</strong>';
        $html .= '        <small style="color:#95a5a6;margin-right:10px;">' . htmlspecialchars($date) . '</small></div>';
        $html .= '      </div>';
        $html .= '      <div style="color:#555;line-height:1.8;">' . $text . '</div>';

        // پاسخ‌ها
        $replies = $this->manager->getReplies((int) $c['id']);
        if (!empty($replies)) {
            $html .= '<div class="comment-replies" style="margin-top:15px;padding-right:20px;border-right:3px solid #e0e0e0;">';
            foreach ($replies as $r) {
                $rName = htmlspecialchars($r['author_name'] ?? 'ناشناس');
                $rInit = mb_substr($rName, 0, 1);
                $rText = nl2br(htmlspecialchars($r['comment'] ?? ''));
                $isAdmin = !empty($r['user_id']) && empty($r['author_email']);
                $badge = $isAdmin ? ' <span style="background:#3498db;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">مدیر</span>' : '';

                $html .= '<div style="display:flex;gap:10px;margin-bottom:12px;">';
                $html .= '  <div style="width:35px;height:35px;border-radius:50%;background:' . ($isAdmin ? '#3498db' : '#95a5a6') . ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0;">' . $rInit . '</div>';
                $html .= '  <div style="flex:1;"><strong>' . $rName . '</strong>' . $badge;
                $html .= '  <small style="color:#95a5a6;margin-right:10px;">' . htmlspecialchars($r['created_at'] ?? '') . '</small>';
                $html .= '  <div style="color:#555;margin-top:5px;">' . $rText . '</div></div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * رندر فرم ثبت دیدگاه
     */
    public function renderForm(int $contentId, string $action): string
    {
        $csrf = function_exists('csrf_token') ? csrf_token() : '';
        $siteUrl = SITE_URL;

        $html  = '<div id="comment-form-wrapper" style="margin-top:30px;padding:30px;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);">';
        $html .= '  <h4 style="margin-bottom:20px;">✏️ دیدگاه خود را بنویسید</h4>';
        $html .= '  <form id="comment-form" method="post" action="' . htmlspecialchars($action) . '">';
        $html .= '    <input type="hidden" name="content_id" value="' . $contentId . '">';
        $html .= '    <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">';
        $html .= '    <input type="hidden" name="parent_id" id="comment-parent-id" value="">';

        $html .= '    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">';
        $html .= '      <div><label class="form-label" style="display:block;margin-bottom:6px;color:#2c3e50;">نام <span style="color:red;">*</span></label>';
        $html .= '      <input type="text" name="author_name" class="form-control" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;" placeholder="نام شما"></div>';
        $html .= '      <div><label class="form-label" style="display:block;margin-bottom:6px;color:#2c3e50;">ایمیل <span style="color:red;">*</span></label>';
        $html .= '      <input type="email" name="author_email" class="form-control" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;" placeholder="you@example.com"></div>';
        $html .= '    </div>';

        $html .= '    <div style="margin-bottom:15px;">';
        $html .= '      <label class="form-label" style="display:block;margin-bottom:6px;color:#2c3e50;">وب‌سایت (اختیاری)</label>';
        $html .= '      <input type="url" name="author_website" class="form-control" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;" placeholder="https://...">';
        $html .= '    </div>';

        $html .= '    <div style="margin-bottom:15px;">';
        $html .= '      <label class="form-label" style="display:block;margin-bottom:6px;color:#2c3e50;">دیدگاه شما <span style="color:red;">*</span></label>';
        $html .= '      <textarea name="comment" rows="5" class="form-control" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;" placeholder="نظر خود را بنویسید..."></textarea>';
        $html .= '    </div>';

        $html .= '    <button type="submit" class="btn btn-primary" style="background:#3498db;color:#fff;padding:10px 25px;border:none;border-radius:8px;cursor:pointer;">';
        $html .= '      📤 ارسال دیدگاه</button>';
        $html .= '    <small class="text-muted d-block mt-2" style="color:#888;display:block;margin-top:8px;">ℹ️ دیدگاه شما پس از تأیید مدیر نمایش داده می‌شود</small>';
        $html .= '  </form>';
        $html .= '  <div id="comment-form-message" style="margin-top:15px;display:none;"></div>';
        $html .= '</div>';

        return $html;
    }
}
