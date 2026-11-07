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
    `bemerkungen` TEXT DEFAULT NULL,
    `verstorben` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    KEY `idx_huefte` (`huefte_id`),
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
