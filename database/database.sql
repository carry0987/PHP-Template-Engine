SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/* create template */
CREATE TABLE IF NOT EXISTS template (
  tpl_id int(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  tpl_path varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  tpl_name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  tpl_type varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  tpl_md5 varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  tpl_expire_time int(20) UNSIGNED NOT NULL,
  tpl_verhash varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tpl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
