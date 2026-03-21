ALTER TABLE `inquiries`
  ADD COLUMN `user_id` int(10) UNSIGNED NULL AFTER `id`,
  ADD COLUMN `location_id` int(10) UNSIGNED NULL AFTER `user_id`,
  ADD COLUMN `status` enum('pending','resolved') NOT NULL DEFAULT 'pending' AFTER `is_read`,
  ADD COLUMN `admin_reply` text NULL AFTER `status`,
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD CONSTRAINT `fk_inq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inq_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;