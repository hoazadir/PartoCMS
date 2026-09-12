USE grapesjs_cms;

-- ==================== جدول نقش‌ها ====================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== جدول دسترسی‌ها ====================
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    module VARCHAR(50),
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== نقش → دسترسی ====================
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== جدول ماژول‌ها ====================
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20) DEFAULT '1.0.0',
    is_enabled TINYINT(1) DEFAULT 0,
    config JSON,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== انواع محتوا ====================
CREATE TABLE IF NOT EXISTS content_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(20),
    fields JSON,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== محتوای واقعی ====================
CREATE TABLE IF NOT EXISTS content_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT,
    title VARCHAR(255),
    slug VARCHAR(255),
    content LONGTEXT,
    excerpt TEXT,
    featured_image VARCHAR(500),
    meta JSON,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    author_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES content_types(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== فرم‌ها ====================
CREATE TABLE IF NOT EXISTS forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    fields JSON,
    settings JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('new','read','replied','archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== به‌روزرسانی users ====================
ALTER TABLE users ADD COLUMN role_id INT DEFAULT 1 AFTER role;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- ==================== داده‌های پیش‌فرض ====================
INSERT IGNORE INTO roles (name, slug, description, is_system) VALUES
('مدیر کل', 'admin', 'دسترسی کامل به همه بخش‌ها', 1),
('ویرایشگر', 'editor', 'ایجاد و ویرایش محتوا', 1),
('نویسنده', 'author', 'فقط محتوای خودش', 1),
('کاربر عادی', 'user', 'دسترسی محدود', 1);

INSERT IGNORE INTO permissions (name, slug, module) VALUES
('مشاهده داشبورد', 'dashboard.view', 'dashboard'),
('مدیریت کاربران', 'users.manage', 'users'),
('مدیریت نقش‌ها', 'roles.manage', 'roles'),
('مشاهده محتوا', 'content.view', 'content'),
('ایجاد محتوا', 'content.create', 'content'),
('ویرایش محتوا', 'content.edit', 'content'),
('حذف محتوا', 'content.delete', 'content'),
('انتشار محتوا', 'content.publish', 'content'),
('مدیریت قالب‌ها', 'templates.manage', 'templates'),
('مدیریت ماژول‌ها', 'modules.manage', 'modules'),
('مدیریت فرم‌ها', 'forms.manage', 'forms'),
('مشاهده ارسال‌ها', 'submissions.view', 'forms'),
('تنظیمات سایت', 'settings.manage', 'settings');

-- مدیر کل همه دسترسی‌ها را دارد
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- ویرایشگر
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN ('dashboard.view', 'content.view', 'content.create', 'content.edit', 'content.publish');

-- نویسنده
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug IN ('dashboard.view', 'content.view', 'content.create', 'content.edit');

-- به‌روزرسانی کاربر admin
UPDATE users SET role_id = 1 WHERE username = 'admin';

-- انواع محتوا
INSERT IGNORE INTO content_types (slug, name, description, icon) VALUES
('post', 'مقاله', 'مقالات وبلاگ', '📝'),
('page', 'صفحه', 'صفحات ثابت', '📄'),
('product', 'محصول', 'محصولات فروشگاه', '📦');
