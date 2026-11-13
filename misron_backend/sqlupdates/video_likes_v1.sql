-- Reel likes: one row per visitor and product video. Run once in phpMyAdmin (SQL tab), inside the admin database.
-- Without this table the reels still work; likes then stay in the visitor's own browser and no count is shown.
CREATE TABLE IF NOT EXISTS `product_video_likes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `visitor_key` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_video_likes_product_visitor_unique` (`product_id`,`visitor_key`),
  KEY `product_video_likes_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
