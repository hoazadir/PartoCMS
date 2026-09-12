CREATE DATABASE IF NOT EXISTS grapesjs_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;
USE grapesjs_cms;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'viewer') DEFAULT 'viewer',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    css LONGTEXT,
    components LONGTEXT,
    styles LONGTEXT,
    thumbnail VARCHAR(255),
    is_active TINYINT(1) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    author_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT,
    name VARCHAR(255) NOT NULL,
    html LONGTEXT,
    css LONGTEXT,
    components LONGTEXT,
    styles LONGTEXT,
    settings TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

INSERT INTO users (username, email, password, role, is_active) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO settings (setting_key, setting_value) VALUES 
('site_name', 'وب‌سایت من'),
('site_description', 'ساخته شده با GrapesJS CMS'),
('admin_email', 'admin@example.com'),
('theme_color', '#3b97e3'),
('logo', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
