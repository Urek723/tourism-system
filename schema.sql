-- =============================================================
--  Tupi Tourism System — Database Schema
--  Run this file once to set up your database.
--  Usage: mysql -u root -p < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS tourism_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tourism_system;

-- ── users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(100) NOT NULL,
    email      VARCHAR(254) NOT NULL,
    password   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash — never plain text',
    role       ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── locations ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS locations (
    id          INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(300) NOT NULL,
    description TEXT         NOT NULL,
    cost        VARCHAR(100) NOT NULL DEFAULT 'Free entry',
    category    VARCHAR(100) NOT NULL DEFAULT 'General',
    image_url   VARCHAR(500)          DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_category  (category),
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── inquiries ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inquiries (
    id          INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(254) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    message     TEXT         NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── login_attempts (brute-force protection) ───────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT         UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45) NOT NULL,
    attempts     TINYINT     NOT NULL DEFAULT 1,
    last_attempt DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Sample Data
-- =============================================================

-- Admin user — password is: Admin@1234
-- Generated with: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO users (username, email, password, role) VALUES
(
    'admin',
    'admin@tourism.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);

-- Demo regular user — password is: User@1234
INSERT INTO users (username, email, password, role) VALUES
(
    'visitor',
    'visitor@tourism.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'user'
);

-- Sample tourism locations
INSERT INTO locations (title, description, cost, category, image_url) VALUES
(
    'Lake Sebu',
    'Lake Sebu is a stunning freshwater lake nestled in the highlands of South Cotabato. Home to the indigenous T''boli people, this pristine mountain lake sits at 1,000 meters above sea level and is surrounded by lush vegetation and rolling hills. Visitors can enjoy boat rides, explore T''boli cultural villages, and witness the spectacular Seven Falls nearby.',
    'Free entry',
    'Nature',
    'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800'
),
(
    'Seven Falls (Hikong Alu)',
    'The Seven Falls is a breathtaking series of cascading waterfalls located near Lake Sebu. Each fall has its own name and character, tumbling down lush tropical mountainsides. Adventure seekers can experience the famous South Cotabato Zipline — one of the longest dual ziplines in Asia at 1.4 kilometers — soaring over the canyon and falls.',
    '₱800 – ₱1,200',
    'Adventure',
    'https://images.unsplash.com/photo-1564419431-78d306367b0d?w=800'
),
(
    'T''boli Cultural Village',
    'Immerse yourself in the vibrant culture of the T''boli people, an indigenous community renowned for their intricate T''nalak cloth woven from abaca fibers, traditional brass jewelry, and colorful beadwork. Guided cultural tours let you meet local weavers, witness traditional dances, and learn about centuries-old craftsmanship passed down through generations.',
    '₱200 – ₱500',
    'Culture',
    'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800'
),
(
    'Strawberry Farm Agri-Tourism',
    'Experience agritourism at its finest in Tupi''s cool mountain climate. Visit local strawberry farms where you can pick your own fresh, sweet strawberries straight from the plant. The farms also grow other highland vegetables and fruits, offering a wonderful educational experience about sustainable farming practices in South Cotabato.',
    '₱100 – ₱300',
    'Agri-Tourism',
    'https://images.unsplash.com/photo-1518635017498-87f514b751ba?w=800'
),
(
    'Punta Isla Lake Resort',
    'Punta Isla is a premier lakeside resort situated on the shores of Lake Sebu offering comfortable lodging with panoramic lake views, floating cottage dining, and recreational activities. Guests can enjoy fresh tilapia straight from the lake, pedal boat rides, and stunning sunrise and sunset views over the water.',
    '₱500 – ₱1,500',
    'Accommodation',
    'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800'
),
(
    'Kalibobong Falls',
    'A hidden gem tucked deep in the forests of Tupi, Kalibobong Falls rewards adventurous hikers with a pristine multi-tiered waterfall surrounded by untouched tropical jungle. The trek to the falls takes approximately 45 minutes and passes through scenic farmlands and native forests. The cool, clear water is perfect for a refreshing dip.',
    'Free entry',
    'Nature',
    'https://images.unsplash.com/photo-1583511655826-05700442b316?w=800'
);
