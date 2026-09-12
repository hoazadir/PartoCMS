USE grapesjs_cms;

-- ==================== جدول دسته‌بندی‌ها ====================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    icon VARCHAR(20),
    color VARCHAR(20) DEFAULT '#3498db',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== جدول برچسب‌ها ====================
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== جدول رسانه‌ها ====================
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    filepath VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT,
    width INT,
    height INT,
    alt_text VARCHAR(255),
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== اتصال محتوا به دسته ====================
ALTER TABLE content_items 
    ADD COLUMN category_id INT DEFAULT NULL AFTER type_id,
    ADD COLUMN featured_image_id INT DEFAULT NULL AFTER featured_image,
    ADD COLUMN views INT DEFAULT 0 AFTER status;

-- ایندکس‌ها
ALTER TABLE content_items 
    ADD INDEX idx_category (category_id);

-- ==================== اتصال محتوا به برچسب ====================
CREATE TABLE IF NOT EXISTS content_tags (
    content_id INT,
    tag_id INT,
    PRIMARY KEY (content_id, tag_id),
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== دسته‌های پیش‌فرض ====================
INSERT IGNORE INTO categories (id, name, slug, description, icon, color) VALUES
(1, 'دسته‌بندی نشده', 'uncategorized', 'مطالب بدون دسته‌بندی', '📁', '#95a5a6'),
(2, 'اخبار', 'news', 'اخبار و اطلاعیه‌های سایت', '📰', '#e74c3c'),
(3, 'آموزش', 'tutorials', 'مقالات آموزشی', '📚', '#3498db'),
(4, 'اخبار فناوری', 'tech-news', 'اخبار دنیای فناوری', '💻', '#9b59b6'),
(5, 'اخبار ورزشی', 'sports-news', 'اخبار ورزشی', '⚽', '#27ae60');

SELECT '=== categories ===' as '';
SELECT id, name, slug, icon FROM categories;
