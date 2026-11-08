-- VKH General: initial schema (MySQL / MariaDB).
-- For manual execution, replace wp_ with your WordPress table prefix if needed.
-- The plugin substitutes the prefix and charset/collation during activation.
-- n:m relations are stored in the two junction tables, not in Hund columns.

CREATE TABLE IF NOT EXISTS `wp_vkh_championtitel` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titel` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_jlpp_huu` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jlpp_huu` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_huefte` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `befund` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_hund` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `geburtsdatum` DATE DEFAULT NULL,
    `huefte_id` BIGINT UNSIGNED DEFAULT NULL,
    `foto_id` BIGINT UNSIGNED DEFAULT NULL,
    `ahnentafel_id` BIGINT UNSIGNED DEFAULT NULL,
    `bemerkungen` TEXT DEFAULT NULL,
    `verstorben` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    KEY `idx_huefte` (`huefte_id`),
    KEY `idx_foto` (`foto_id`),
    KEY `idx_ahnentafel` (`ahnentafel_id`),
    FOREIGN KEY (`foto_id`) REFERENCES `wp_posts` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`ahnentafel_id`) REFERENCES `wp_posts` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`huefte_id`) REFERENCES `wp_vkh_huefte` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_hund_championtitel` (
    `hund_id` BIGINT UNSIGNED NOT NULL,
    `championtitel_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`hund_id`, `championtitel_id`),
    KEY `idx_championtitel` (`championtitel_id`),
    FOREIGN KEY (`hund_id`) REFERENCES `wp_vkh_hund` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`championtitel_id`) REFERENCES `wp_vkh_championtitel` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_hund_jlpp_huu` (
    `hund_id` BIGINT UNSIGNED NOT NULL,
    `jlpp_huu_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`hund_id`, `jlpp_huu_id`),
    KEY `idx_jlpp_huu` (`jlpp_huu_id`),
    FOREIGN KEY (`hund_id`) REFERENCES `wp_vkh_hund` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`jlpp_huu_id`) REFERENCES `wp_vkh_jlpp_huu` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_wurf` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bezeichnung` VARCHAR(30) NOT NULL,
    `wurfdatum` DATE DEFAULT NULL,
    `angelegt_am` DATETIME NOT NULL,
    `beispiel` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_bezeichnung` (`bezeichnung`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_welpe` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wurf_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `geschlecht` ENUM('ruede','huendin') NOT NULL,
    `verkaufsstatus` ENUM('nein','vorgemerkt','verkauft') NOT NULL DEFAULT 'nein',
    PRIMARY KEY (`id`),
    KEY `idx_wurf` (`wurf_id`),
    FOREIGN KEY (`wurf_id`) REFERENCES `wp_vkh_wurf` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_interessent` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nachname` VARCHAR(100) NOT NULL,
    `vorname` VARCHAR(100) NOT NULL,
    `telefon` VARCHAR(50) NOT NULL DEFAULT '',
    `email` VARCHAR(254) NOT NULL,
    `angelegt_am` DATETIME DEFAULT NULL COMMENT 'UTC - NULL for records created before this field existed',
    `hundeerfahrung` BOOLEAN NOT NULL DEFAULT FALSE,
    `hund_id` BIGINT UNSIGNED DEFAULT NULL,
    `welpe_id` BIGINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`nachname`, `vorname`),
    KEY `idx_email` (`email`(191)),
    KEY `idx_hund` (`hund_id`),
    KEY `idx_welpe` (`welpe_id`),
    FOREIGN KEY (`welpe_id`) REFERENCES `wp_vkh_welpe` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`hund_id`) REFERENCES `wp_vkh_hund` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_vkh_anmeldung` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `interessent_id` BIGINT UNSIGNED NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `email_hash` CHAR(64) NOT NULL,
    `newsletter` BOOLEAN NOT NULL DEFAULT FALSE,
    `weitere_infos` BOOLEAN NOT NULL DEFAULT FALSE,
    `eingegangen_am` DATETIME NOT NULL,
    `einwilligung_version` VARCHAR(20) NOT NULL,
    `einwilligung_text` TEXT NOT NULL,
    `bestaetigt_am` DATETIME DEFAULT NULL,
    `doi_token_hash` CHAR(64) DEFAULT NULL,
    `doi_gueltig_bis` DATETIME DEFAULT NULL,
    `doi_mail_versuch_am` DATETIME DEFAULT NULL,
    `doi_mail_versendet_am` DATETIME DEFAULT NULL,
    `doi_mail_status` VARCHAR(20) NOT NULL DEFAULT 'ausstehend',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_email_hash` (`email_hash`),
    UNIQUE KEY `uniq_interessent` (`interessent_id`),
    FOREIGN KEY (`interessent_id`) REFERENCES `wp_vkh_interessent` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

