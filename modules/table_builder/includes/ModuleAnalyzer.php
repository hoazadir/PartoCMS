<?php
/**
 * PartoCMS - Module Analyzer
 * تحلیل هوشمند جدول و تشخیص نوع ماژول + فیلدها
 *
 * @version 1.0
 * @date 2026-09-19
 */
class ModuleAnalyzer {

    private $pdo;
    private $tb;

    // ═══════════════════════════════════════════════════════════
    // الگوهای تشخیص نوع ماژول
    // ═══════════════════════════════════════════════════════════
    private static $modulePatterns = [
        'contact' => [
            'patterns' => ['contact', 'phonebook', 'address_book', 'مخاطب', 'دفتر تلفن'],
            'title'    => 'مخاطبین',
            'icon'     => '📇',
            'description' => 'مدیریت مخاطبین و دفترچه تلفن',
        ],
        'product' => [
            'patterns' => ['product', 'item', 'catalog', 'محصول', 'کالا'],
            'title'    => 'محصولات',
            'icon'     => '🛒',
            'description' => 'مدیریت محصولات و کالاها',
        ],
        'post' => [
            'patterns' => ['post', 'article', 'blog', 'news', 'مقاله', 'خبر'],
            'title'    => 'مقالات',
            'icon'     => '📝',
            'description' => 'مدیریت مقالات و اخبار',
        ],
        'user' => [
            'patterns' => ['user', 'customer', 'member', 'کاربر', 'مشتری', 'عضو'],
            'title'    => 'کاربران',
            'icon'     => '👥',
            'description' => 'مدیریت کاربران',
        ],
        'order' => [
            'patterns' => ['order', 'invoice', 'payment', 'سفارش', 'فاکتور', 'پرداخت'],
            'title'    => 'سفارشات',
            'icon'     => '💰',
            'description' => 'مدیریت سفارشات و فاکتورها',
        ],
        'task' => [
            'patterns' => ['task', 'todo', 'project', 'وظیفه', 'پروژه', 'کار'],
            'title'    => 'وظایف',
            'icon'     => '✅',
            'description' => 'مدیریت وظایف و پروژه‌ها',
        ],
        'comment' => [
            'patterns' => ['comment', 'review', 'feedback', 'نظر', 'دیدگاه', 'بازخورد'],
            'title'    => 'نظرات',
            'icon'     => '💬',
            'description' => 'مدیریت نظرات و بازخوردها',
        ],
        'event' => [
            'patterns' => ['event', 'calendar', 'appointment', 'رویداد', 'تقویم', 'قرار'],
            'title'    => 'رویدادها',
            'icon'     => '📅',
            'description' => 'مدیریت رویدادها و قرارها',
        ],
        'category' => [
            'patterns' => ['category', 'cat', 'دسته', 'گروه'],
            'title'    => 'دسته‌بندی‌ها',
            'icon'     => '🏷',
            'description' => 'مدیریت دسته‌بندی‌ها',
        ],
        'tag' => [
            'patterns' => ['tag', 'label', 'برچسب', 'تگ'],
            'title'    => 'برچسب‌ها',
            'icon'     => '🔖',
            'description' => 'مدیریت برچسب‌ها',
        ],
        'file' => [
            'patterns' => ['file', 'media', 'document', 'attachment', 'فایل', 'رسانه', 'سند'],
            'title'    => 'فایل‌ها',
            'icon'     => '📎',
            'description' => 'مدیریت فایل‌ها و اسناد',
        ],
        'transaction' => [
            'patterns' => ['transaction', 'payment', 'wallet', 'تراکنش', 'کیف'],
            'title'    => 'تراکنش‌ها',
            'icon'     => '💳',
            'description' => 'مدیریت تراکنش‌ها',
        ],
    ];

    // ═══════════════════════════════════════════════════════════
    // برچسب‌های فارسی پیش‌فرض برای فیلدها
    // ═══════════════════════════════════════════════════════════
    private static $fieldLabels = [
        'id'           => 'شناسه',
        'name'         => 'نام',
        'first_name'   => 'نام',
        'last_name'    => 'نام خانوادگی',
        'full_name'    => 'نام کامل',
        'title'        => 'عنوان',
        'subject'      => 'موضوع',
        'slug'         => 'نامک',
        'email'        => 'ایمیل',
        'phone'        => 'تلفن',
        'mobile'       => 'موبایل',
        'tel'          => 'تلفن',
        'fax'          => 'فکس',
        'address'      => 'آدرس',
        'city'         => 'شهر',
        'province'     => 'استان',
        'postal_code'  => 'کد پستی',
        'country'      => 'کشور',
        'website'      => 'وب‌سایت',
        'url'          => 'لینک',
        'link'         => 'لینک',
        'description'  => 'توضیحات',
        'content'      => 'محتوا',
        'body'         => 'متن',
        'text'         => 'متن',
        'summary'      => 'خلاصه',
        'notes'        => 'یادداشت',
        'note'         => 'یادداشت',
        'comment'      => 'نظر',
        'message'      => 'پیام',
        'status'       => 'وضعیت',
        'type'         => 'نوع',
        'category'     => 'دسته‌بندی',
        'category_id'  => 'دسته‌بندی',
        'parent_id'    => 'والد',
        'user_id'      => 'کاربر',
        'author_id'    => 'نویسنده',
        'image'        => 'تصویر',
        'photo'        => 'عکس',
        'avatar'       => 'آواتار',
        'thumbnail'    => 'بندانگشتی',
        'file'         => 'فایل',
        'attachment'   => 'پیوست',
        'price'        => 'قیمت',
        'amount'       => 'مبلغ',
        'cost'         => 'هزینه',
        'total'        => 'جمع کل',
        'quantity'     => 'تعداد',
        'count'        => 'تعداد',
        'stock'        => 'موجودی',
        'weight'       => 'وزن',
        'width'        => 'عرض',
        'height'       => 'ارتفاع',
        'length'       => 'طول',
        'date'         => 'تاریخ',
        'birth_date'   => 'تاریخ تولد',
        'start_date'   => 'تاریخ شروع',
        'end_date'     => 'تاریخ پایان',
        'expire_date'  => 'تاریخ انقضا',
        'due_date'     => 'تاریخ سررسید',
        'time'         => 'ساعت',
        'datetime'     => 'تاریخ و ساعت',
        'created_at'   => 'تاریخ ایجاد',
        'updated_at'   => 'تاریخ بروزرسانی',
        'deleted_at'   => 'تاریخ حذف',
        'published_at' => 'تاریخ انتشار',
        'is_active'    => 'فعال',
        'is_enabled'   => 'فعال',
        'is_visible'   => 'قابل نمایش',
        'is_featured'  => 'ویژه',
        'is_default'   => 'پیش‌فرض',
        'is_deleted'   => 'حذف شده',
        'sort_order'   => 'ترتیب',
        'order'        => 'ترتیب',
        'position'     => 'موقعیت',
        'color'        => 'رنگ',
        'icon'         => 'آیکون',
        'password'     => 'رمز عبور',
        'username'     => 'نام کاربری',
        'code'         => 'کد',
        'reference'    => 'مرجع',
        'gender'       => 'جنسیت',
        'age'          => 'سن',
        'national_id'  => 'کد ملی',
        'company'      => 'شرکت',
        'job'          => 'شغل',
        'position'     => 'سمت',
        'department'   => 'دپارتمان',
    ];

    public function __construct($pdo, $tb) {
        $this->pdo = $pdo;
        $this->tb  = $tb;
    }

    // ═══════════════════════════════════════════════════════════
    // متد اصلی: تحلیل کامل جدول
    // ═══════════════════════════════════════════════════════════
    public function analyze(string $table): array {
        $columns = $this->tb->getColumns($table);
        $foreignKeys = $this->tb->getForeignKeys($table);

        // اطلاعات پایه
        $moduleType = $this->detectModuleType($table, $columns);
        $primaryKey = $this->detectPrimaryKey($columns);
        $titleField = $this->detectTitleField($columns);
        $slugField  = $this->detectSlugField($columns);
        $bodyField  = $this->detectBodyField($columns);
        $sortInfo   = $this->detectSortField($columns);

        // تحلیل هر فیلد
        $fields = [];
        foreach ($columns as $col) {
            $fkInfo = $this->findForeignKey($col['Field'], $foreignKeys);
            $fields[] = $this->analyzeField($col, [
                'table'    => $table,
                'primary'  => $primaryKey,
                'fk'       => $fkInfo,
            ]);
        }

        // فیلدهای مشتق‌شده
        $listColumns = $this->getListColumns($fields);
        $formFields  = $this->getFormFields($fields);
        $searchable  = $this->getSearchableFields($fields);
        $required    = array_values(array_filter($fields, fn($f) => $f['required']));
        $unique      = array_values(array_filter($fields, fn($f) => $f['unique']));

        return [
            'table'          => $table,
            'module_type'    => $moduleType['type'],
            'module_title'   => $moduleType['title'],
            'module_icon'    => $moduleType['icon'],
            'module_desc'    => $moduleType['description'],
            'primary_key'    => $primaryKey,
            'title_field'    => $titleField,
            'slug_field'     => $slugField,
            'body_field'     => $bodyField,
            'sort_field'     => $sortInfo['field'],
            'sort_direction' => $sortInfo['direction'],
            'columns'        => $columns,
            'fields'         => $fields,
            'list_columns'   => $listColumns,
            'form_fields'    => $formFields,
            'searchable'     => $searchable,
            'required'       => $required,
            'unique'         => $unique,
            'total_columns'  => count($columns),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص نوع ماژول
    // ═══════════════════════════════════════════════════════════
    private function detectModuleType(string $table, array $columns): array {
        $tableLower = strtolower($table);

        // جمع‌آوری نام فیلدها برای تشخیص بهتر
        $fieldNames = array_map(fn($c) => strtolower($c['Field']), $columns);
        $allText = $tableLower . ' ' . implode(' ', $fieldNames);

        foreach (self::$modulePatterns as $type => $info) {
            foreach ($info['patterns'] as $pattern) {
                if (stripos($allText, $pattern) !== false) {
                    return [
                        'type'        => $type,
                        'title'       => $info['title'],
                        'icon'        => $info['icon'],
                        'description' => $info['description'],
                    ];
                }
            }
        }

        // پیش‌فرض
        return [
            'type'        => 'generic',
            'title'       => $this->humanize($table),
            'icon'        => '📦',
            'description' => 'مدیریت داده‌های ' . $table,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص کلید اصلی
    // ═══════════════════════════════════════════════════════════
    private function detectPrimaryKey(array $columns): string {
        foreach ($columns as $c) {
            if ($c['Key'] === 'PRI') return $c['Field'];
        }
        return $columns[0]['Field'] ?? 'id';
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص فیلد عنوان
    // ═══════════════════════════════════════════════════════════
    private function detectTitleField(array $columns): string {
        $candidates = ['title', 'name', 'subject', 'label', 'heading', 'caption', 'full_name', 'first_name'];
        $fieldNames = array_map(fn($c) => strtolower($c['Field']), $columns);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $fieldNames, true)) {
                // اگر first_name و last_name هر دو هستند، اولی را انتخاب کن
                // (در ادامه با ترکیب نمایش داده می‌شود)
                return $candidate;
            }
        }

        // اولین فیلد متنی
        foreach ($columns as $c) {
            $type = strtolower($c['Type']);
            if (strpos($type, 'varchar') !== false && (int) filter_var($type, FILTER_SANITIZE_NUMBER_INT) >= 50) {
                return $c['Field'];
            }
        }

        return $columns[0]['Field'] ?? 'id';
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص فیلد Slug
    // ═══════════════════════════════════════════════════════════
    private function detectSlugField(array $columns): ?string {
        foreach ($columns as $c) {
            if (in_array(strtolower($c['Field']), ['slug', 'permalink', 'url_key'], true)) {
                return $c['Field'];
            }
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص فیلد محتوا/توضیحات
    // ═══════════════════════════════════════════════════════════
    private function detectBodyField(array $columns): ?string {
        $candidates = ['content', 'body', 'description', 'text', 'summary', 'details', 'notes', 'address'];
        $fieldNames = array_map(fn($c) => strtolower($c['Field']), $columns);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $fieldNames, true)) {
                return $candidate;
            }
        }

        // اولین فیلد TEXT
        foreach ($columns as $c) {
            $type = strtolower($c['Type']);
            if (strpos($type, 'text') !== false) {
                return $c['Field'];
            }
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص فیلد مرتب‌سازی
    // ═══════════════════════════════════════════════════════════
    private function detectSortField(array $columns): array {
        $fieldNames = array_map(fn($c) => strtolower($c['Field']), $columns);

        // اولویت: created_at DESC
        foreach (['created_at', 'published_at', 'updated_at', 'date', 'datetime'] as $f) {
            if (in_array($f, $fieldNames, true)) {
                return ['field' => $f, 'direction' => 'DESC'];
            }
        }

        // sort_order ASC
        foreach (['sort_order', 'order', 'position'] as $f) {
            if (in_array($f, $fieldNames, true)) {
                return ['field' => $f, 'direction' => 'ASC'];
            }
        }

        return ['field' => $this->detectPrimaryKey($columns), 'direction' => 'DESC'];
    }

    // ═══════════════════════════════════════════════════════════
    // تحلیل یک فیلد
    // ═══════════════════════════════════════════════════════════
    private function analyzeField(array $column, array $context = []): array {
        $name = $column['Field'];
        $type = strtolower($column['Type']);
        $nullable = $column['Null'] === 'YES';
        $key = $column['Key'] ?? '';
        $default = $column['Default'] ?? null;
        $extra = $column['Extra'] ?? '';

        $isPrimary = ($key === 'PRI');
        $isUnique = ($key === 'UNI');
        $isFK = !empty($context['fk']);
        $isHidden = $this->isHiddenField($name, $isPrimary, $extra);
        $isSystem = $this->isSystemField($name);

        $htmlType = $this->detectHtmlType($column, $context);
        $label = $this->detectLabel($name, $context);
        $placeholder = $this->detectPlaceholder($name, $htmlType, $label);
        $options = $this->parseEnumValues($column['Type']);
        $maxLength = $this->detectMaxLength($column['Type']);
        $step = $this->detectStep($type);

        return [
            'name'         => $name,
            'type'         => $column['Type'],
            'html_type'    => $htmlType,
            'label'        => $label,
            'placeholder'  => $placeholder,
            'required'     => !$nullable && !$isPrimary && !$isSystem && $default === null,
            'nullable'     => $nullable,
            'unique'       => $isUnique,
            'primary'      => $isPrimary,
            'foreign_key'  => $context['fk'] ?? null,
            'hidden'       => $isHidden,
            'system'       => $isSystem,
            'default'      => $default,
            'max_length'   => $maxLength,
            'step'         => $step,
            'options'      => $options,
            'auto_increment' => strpos($extra, 'auto_increment') !== false,
            'icon'         => $this->detectFieldIcon($name, $htmlType),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص نوع HTML فیلد
    // ═══════════════════════════════════════════════════════════
    private function detectHtmlType(array $column, array $context): string {
        $name = strtolower($column['Field']);
        $type = strtolower($column['Type']);

        // اگر FK است → select
        if (!empty($context['fk'])) {
            return 'fk_select';
        }

        // نام‌های خاص
        if (strpos($name, 'email') !== false) return 'email';
        if (preg_match('/(^|_)(phone|mobile|tel|fax)($|_)/', $name)) return 'tel';
        if (strpos($name, 'password') !== false) return 'password';
        if (preg_match('/(^|_)(url|website|link)($|_)/', $name)) return 'url';
        if (preg_match('/(^|_)(color|colour)($|_)/', $name)) return 'color';
        if (preg_match('/(^|_)(image|photo|avatar|thumbnail|picture)($|_)/', $name)) return 'image';
        if (preg_match('/(^|_)(file|attachment|document|pdf)($|_)/', $name)) return 'file';
        if (preg_match('/^is_|^has_|^can_/', $name)) return 'checkbox';

        // بر اساس نوع MySQL
        if (strpos($type, 'tinyint(1)') !== false) return 'checkbox';
        if (strpos($type, 'int') !== false) return 'number';
        if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) return 'decimal';
        if (strpos($type, 'date') !== false && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) return 'date';
        if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) return 'datetime-local';
        if (strpos($type, 'time') !== false) return 'time';
        if (strpos($type, 'enum') !== false) return 'select';
        if (strpos($type, 'json') !== false) return 'json';

        // VARCHAR / TEXT
        if (strpos($type, 'varchar') !== false) {
            $len = (int) filter_var($type, FILTER_SANITIZE_NUMBER_INT);
            if ($len > 200) return 'textarea';
            return 'text';
        }
        if (strpos($type, 'text') !== false) return 'textarea';

        return 'text';
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص برچسب فارسی
    // ═══════════════════════════════════════════════════════════
    private function detectLabel(string $name, array $context): string {
        $lower = strtolower($name);

        // اگر FK است: «[نام جدول مرتبط]»
        if (!empty($context['fk'])) {
            $refTable = $context['fk']['ref_table'] ?? '';
            return $this->humanize($refTable);
        }

        // از دیکشنری
        if (isset(self::$fieldLabels[$lower])) {
            return self::$fieldLabels[$lower];
        }

        // الگوی `*_id` → نام جدول
        if (preg_match('/^(.+)_id$/', $lower, $m)) {
            return $this->humanize($m[1]);
        }

        // humanize
        return $this->humanize($name);
    }

    // ═══════════════════════════════════════════════════════════
    // Placeholder
    // ═══════════════════════════════════════════════════════════
    private function detectPlaceholder(string $name, string $htmlType, string $label): string {
        switch ($htmlType) {
            case 'email': return 'مثال: user@example.com';
            case 'tel': return 'مثال: 09123456789';
            case 'url': return 'مثال: https://example.com';
            case 'date': return '1400/01/01';
            case 'datetime-local': return '1400/01/01 12:00';
            case 'number':
            case 'decimal': return 'عدد وارد کنید';
            case 'password': return '••••••••';
            default: return '«' . $label . '» را وارد کنید';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // آیکون فیلد
    // ═══════════════════════════════════════════════════════════
    private function detectFieldIcon(string $name, string $htmlType): string {
        $lower = strtolower($name);
        $icons = [
            'email'   => '✉️', 'phone' => '📞', 'mobile' => '📱', 'address' => '📍',
            'date'    => '📅', 'birth' => '🎂', 'price' => '💰', 'amount' => '💵',
            'image'   => '🖼', 'file' => '📎', 'password' => '🔒', 'url' => '🔗',
            'name'    => '👤', 'title' => '📌', 'description' => '📝', 'notes' => '🗒',
            'category' => '🏷', 'status' => '⚡', 'color' => '🎨',
        ];
        foreach ($icons as $key => $icon) {
            if (strpos($lower, $key) !== false) return $icon;
        }
        switch ($htmlType) {
            case 'email': return '✉️';
            case 'tel': return '📞';
            case 'date': return '📅';
            case 'datetime-local': return '🕒';
            case 'number': return '🔢';
            case 'checkbox': return '☑️';
            case 'textarea': return '📄';
            case 'fk_select': return '🔗';
            default: return '📋';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // فیلدهای مخفی
    // ═══════════════════════════════════════════════════════════
    private function isHiddenField(string $name, bool $isPrimary, string $extra): bool {
        if ($isPrimary && strpos($extra, 'auto_increment') !== false) return true;
        $hidden = ['created_at', 'updated_at', 'deleted_at'];
        return in_array(strtolower($name), $hidden, true);
    }

    // ═══════════════════════════════════════════════════════════
    // فیلدهای سیستمی
    // ═══════════════════════════════════════════════════════════
    private function isSystemField(string $name): bool {
        $system = ['id', 'created_at', 'updated_at', 'deleted_at'];
        return in_array(strtolower($name), $system, true);
    }

    // ═══════════════════════════════════════════════════════════
    // جستجوی FK
    // ═══════════════════════════════════════════════════════════
    private function findForeignKey(string $field, array $foreignKeys): ?array {
        // TableBuilder::getForeignKeys() => [ field_name => [ref_table, ref_column, ...] ]
        if (isset($foreignKeys[$field])) {
            return $foreignKeys[$field];
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // Parse ENUM
    // ═══════════════════════════════════════════════════════════
    private function parseEnumValues(string $type): array {
        if (stripos($type, 'enum') === false) return [];
        if (preg_match("/enum\((.*)\)/i", $type, $m)) {
            $values = str_getcsv($m[1], ',', "'");
            return array_map('trim', $values);
        }
        return [];
    }

    // ═══════════════════════════════════════════════════════════
    // Max Length
    // ═══════════════════════════════════════════════════════════
    private function detectMaxLength(string $type): ?int {
        if (preg_match('/varchar\((\d+)\)/i', $type, $m)) return (int) $m[1];
        if (preg_match('/char\((\d+)\)/i', $type, $m))    return (int) $m[1];
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // Step
    // ═══════════════════════════════════════════════════════════
    private function detectStep(string $type): ?string {
        if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false) return '0.01';
        if (strpos($type, 'int') !== false) return '1';
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // ستون‌های لیست (۴ ستون اول غیرمخفی)
    // ═══════════════════════════════════════════════════════════
    private function getListColumns(array $fields): array {
        $result = [];
        foreach ($fields as $f) {
            if ($f['hidden'] || $f['html_type'] === 'textarea' || $f['html_type'] === 'json') continue;
            $result[] = $f['name'];
            if (count($result) >= 5) break;
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // فیلدهای فرم (غیرمخفی و غیرسیستمی)
    // ═══════════════════════════════════════════════════════════
    private function getFormFields(array $fields): array {
        return array_values(array_filter($fields, fn($f) => !$f['hidden'] && !$f['system']));
    }

    // ═══════════════════════════════════════════════════════════
    // فیلدهای قابل جستجو
    // ═══════════════════════════════════════════════════════════
    private function getSearchableFields(array $fields): array {
        $result = [];
        foreach ($fields as $f) {
            if (in_array($f['html_type'], ['text', 'email', 'tel', 'url', 'textarea'], true)) {
                $result[] = $f['name'];
            }
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // دیکشنری نام جداول (برای FK labels)
    // ═══════════════════════════════════════════════════════════
    private static $tableLabels = [
        'categories' => 'دسته‌بندی', 'category' => 'دسته‌بندی',
        'users' => 'کاربر', 'user' => 'کاربر',
        'products' => 'محصول', 'product' => 'محصول',
        'posts' => 'مقاله', 'post' => 'مقاله',
        'comments' => 'دیدگاه', 'comment' => 'دیدگاه',
        'tags' => 'برچسب', 'tag' => 'برچسب',
        'roles' => 'نقش', 'role' => 'نقش',
        'orders' => 'سفارش', 'order' => 'سفارش',
        'payments' => 'پرداخت', 'payment' => 'پرداخت',
        'contacts' => 'مخاطب', 'contact' => 'مخاطب',
        'menus' => 'منو', 'menu' => 'منو',
        'pages' => 'صفحه', 'page' => 'صفحه',
        'files' => 'فایل', 'file' => 'فایل',
        'media' => 'رسانه',
    ];

    // ═══════════════════════════════════════════════════════════
    // humanize: my_field_name → «My Field Name»
    // ═══════════════════════════════════════════════════════════
    private function humanize(string $str): string {
        // چک دیکشنری نام جداول
        $lower = strtolower($str);
        if (isset(self::$tableLabels[$lower])) {
            return self::$tableLabels[$lower];
        }

        $str = str_replace(['_', '-'], ' ', $str);
        $str = preg_replace('/([a-z])([A-Z])/', '$1 $2', $str);
        $str = ucwords(trim($str));
        // اگر فارسی نبود، برگردان به فارسی ساده
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $str)) {
            // تلاش برای ترجمه کلمات رایج
            $trans = [
                'First Name' => 'نام', 'Last Name' => 'نام خانوادگی',
                'Full Name'  => 'نام کامل', 'Email' => 'ایمیل',
                'Phone' => 'تلفن', 'Mobile' => 'موبایل', 'Address' => 'آدرس',
                'Notes' => 'یادداشت', 'Is Active' => 'فعال',
                'Created At' => 'تاریخ ایجاد', 'Updated At' => 'تاریخ بروزرسانی',
            ];
            return $trans[$str] ?? $str;
        }
        return $str;
    }
}
