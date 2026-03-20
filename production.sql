-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2026 at 03:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tourism_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `created_at`) VALUES
(1, 3, 'login', '2026-03-20 01:27:08'),
(2, 3, 'rating_submitted', '2026-03-20 01:33:48'),
(3, 3, 'logout', '2026-03-20 01:34:01'),
(4, 3, 'login', '2026-03-20 01:37:56'),
(5, 3, 'logout', '2026-03-20 01:38:07'),
(6, 3, 'login', '2026-03-20 01:38:42'),
(7, 3, 'favorite_added', '2026-03-20 01:38:56'),
(8, 3, 'trip_added', '2026-03-20 01:39:16'),
(9, 1, 'admin_login', '2026-03-20 07:11:00'),
(10, 1, 'admin_comment_approved', '2026-03-20 07:11:46'),
(11, 1, 'comment_posted', '2026-03-20 07:14:31'),
(12, 1, 'admin_comment_approved', '2026-03-20 07:14:45'),
(13, 1, 'comment_posted', '2026-03-20 07:17:10'),
(14, 1, 'admin_comment_approved', '2026-03-20 07:17:20'),
(15, 1, 'admin_logout', '2026-03-20 07:21:08'),
(16, 1, 'admin_login', '2026-03-20 07:23:22'),
(17, 1, 'admin_logout', '2026-03-20 07:54:30'),
(18, 1, 'admin_login', '2026-03-20 07:55:57'),
(19, 1, 'admin_location_added', '2026-03-20 08:00:45'),
(20, 1, 'admin_login', '2026-03-20 08:23:45'),
(21, 1, 'logout', '2026-03-20 08:24:40'),
(22, 1, 'admin_login', '2026-03-20 08:25:19'),
(23, 1, 'logout', '2026-03-20 08:25:49'),
(24, 1, 'admin_login', '2026-03-20 08:26:15'),
(25, 1, 'admin_login', '2026-03-20 08:39:29'),
(26, 1, 'admin_logout', '2026-03-20 08:43:41'),
(27, 1, 'admin_login', '2026-03-20 08:43:48'),
(28, 1, 'admin_login', '2026-03-20 08:44:06'),
(29, 1, 'admin_login', '2026-03-20 08:44:20'),
(30, 1, 'admin_login', '2026-03-20 08:52:03'),
(31, 1, 'admin_login', '2026-03-20 08:52:18'),
(32, 1, 'admin_login', '2026-03-20 08:52:34'),
(33, 1, 'admin_login', '2026-03-20 08:53:31'),
(34, 1, 'admin_login', '2026-03-20 08:58:23'),
(35, 1, 'admin_login', '2026-03-20 08:58:34'),
(36, 1, 'admin_login', '2026-03-20 08:59:32'),
(37, 1, 'logout', '2026-03-20 09:00:31'),
(38, 3, 'login', '2026-03-20 09:00:54'),
(39, 3, 'logout', '2026-03-20 09:01:57'),
(40, 3, 'login', '2026-03-20 09:02:25'),
(41, 3, 'logout', '2026-03-20 09:02:27'),
(42, 3, 'login', '2026-03-20 09:58:31'),
(43, 3, 'preference_updated', '2026-03-20 09:58:42'),
(44, 3, 'logout', '2026-03-20 09:59:37'),
(45, 1, 'admin_login', '2026-03-20 10:00:02');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending' COMMENT 'pending = awaiting moderation, approved = public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `location_id`, `comment`, `created_at`, `status`) VALUES
(1, 3, 2, '123123', '2026-03-20 00:48:36', 'approved'),
(2, 1, 2, '123123', '2026-03-20 07:14:31', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `location_id`, `created_at`) VALUES
(1, 3, 1, '2026-03-20 01:38:56');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(300) NOT NULL,
  `description` text NOT NULL,
  `cost` varchar(100) NOT NULL DEFAULT 'Free entry',
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `image_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'GPS latitude  — e.g. 6.33330000',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'GPS longitude — e.g. 124.95000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `title`, `description`, `cost`, `category`, `image_url`, `is_active`, `created_at`, `latitude`, `longitude`) VALUES
(1, 'Lake Sebu', 'Lake Sebu is a stunning freshwater lake nestled in the highlands of South Cotabato. Home to the indigenous T\'boli people, this pristine mountain lake sits at 1,000 meters above sea level and is surrounded by lush vegetation and rolling hills. Visitors can enjoy boat rides, explore T\'boli cultural villages, and witness the spectacular Seven Falls nearby.', 'Free entry', 'Nature', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800', 1, '2026-03-20 00:22:24', 6.29330000, 124.77690000),
(2, 'Seven Falls (Hikong Alu)', 'The Seven Falls is a breathtaking series of cascading waterfalls located near Lake Sebu. Each fall has its own name and character, tumbling down lush tropical mountainsides. Adventure seekers can experience the famous South Cotabato Zipline — one of the longest dual ziplines in Asia at 1.4 kilometers — soaring over the canyon and falls.', '₱800 – ₱1,200', 'Adventure', 'https://images.unsplash.com/photo-1564419431-78d306367b0d?w=800', 1, '2026-03-20 00:22:24', 6.28800000, 124.77200000),
(3, 'T\'boli Cultural Village', 'Immerse yourself in the vibrant culture of the T\'boli people, an indigenous community renowned for their intricate T\'nalak cloth woven from abaca fibers, traditional brass jewelry, and colorful beadwork. Guided cultural tours let you meet local weavers, witness traditional dances, and learn about centuries-old craftsmanship passed down through generations.', '₱200 – ₱500', 'Culture', 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800', 1, '2026-03-20 00:22:24', 6.29500000, 124.77900000),
(4, 'Strawberry Farm Agri-Tourism', 'Experience agritourism at its finest in Tupi\'s cool mountain climate. Visit local strawberry farms where you can pick your own fresh, sweet strawberries straight from the plant. The farms also grow other highland vegetables and fruits, offering a wonderful educational experience about sustainable farming practices in South Cotabato.', '₱100 – ₱300', 'Agri-Tourism', 'https://images.unsplash.com/photo-1518635017498-87f514b751ba?w=800', 1, '2026-03-20 00:22:24', 6.30100000, 124.78100000),
(5, 'Punta Isla Lake Resort', 'Punta Isla is a premier lakeside resort situated on the shores of Lake Sebu offering comfortable lodging with panoramic lake views, floating cottage dining, and recreational activities. Guests can enjoy fresh tilapia straight from the lake, pedal boat rides, and stunning sunrise and sunset views over the water.', '₱500 – ₱1,500', 'Accommodation', 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800', 1, '2026-03-20 00:22:24', 6.29200000, 124.77500000),
(6, 'Kalibobong Falls', 'A hidden gem tucked deep in the forests of Tupi, Kalibobong Falls rewards adventurous hikers with a pristine multi-tiered waterfall surrounded by untouched tropical jungle. The trek to the falls takes approximately 45 minutes and passes through scenic farmlands and native forests. The cool, clear water is perfect for a refreshing dip.', 'Free entry', 'Nature', 'https://images.unsplash.com/photo-1583511655826-05700442b316?w=800', 1, '2026-03-20 00:22:24', 6.31000000, 124.78500000),
(7, '123123', '123123', '123', 'Nature', NULL, 1, '2026-03-20 08:00:45', 90.00000000, 123.00000000),
(8, 'Lake Sebu', 'Lake Sebu is a stunning freshwater lake nestled in the highlands of South Cotabato. Home to the indigenous T\'boli people, this pristine mountain lake sits at 1,000 meters above sea level and is surrounded by lush vegetation and rolling hills. Visitors can enjoy boat rides, explore T\'boli cultural villages, and witness the spectacular Seven Falls nearby.', 'Free entry', 'Nature', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800', 1, '2026-03-20 08:18:20', 6.29330000, 124.77690000),
(9, 'Seven Falls (Hikong Alu)', 'The Seven Falls is a breathtaking series of cascading waterfalls located near Lake Sebu. Each fall has its own name and character, tumbling down lush tropical mountainsides. Adventure seekers can experience the famous South Cotabato Zipline — one of the longest dual ziplines in Asia at 1.4 kilometers — soaring over the canyon and falls.', '₱800 – ₱1,200', 'Adventure', 'https://images.unsplash.com/photo-1564419431-78d306367b0d?w=800', 1, '2026-03-20 08:18:20', 6.27500000, 124.76000000),
(10, 'T\'boli Cultural Village', 'Immerse yourself in the vibrant culture of the T\'boli people, an indigenous community renowned for their intricate T\'nalak cloth woven from abaca fibers, traditional brass jewelry, and colorful beadwork. Guided cultural tours let you meet local weavers, witness traditional dances, and learn about centuries-old craftsmanship passed down through generations.', '₱200 – ₱500', 'Culture', 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800', 1, '2026-03-20 08:18:20', NULL, NULL),
(11, 'Strawberry Farm Agri-Tourism', 'Experience agritourism at its finest in Tupi\'s cool mountain climate. Visit local strawberry farms where you can pick your own fresh, sweet strawberries straight from the plant. The farms also grow other highland vegetables and fruits, offering a wonderful educational experience about sustainable farming practices in South Cotabato.', '₱100 – ₱300', 'Agri-Tourism', 'https://images.unsplash.com/photo-1518635017498-87f514b751ba?w=800', 1, '2026-03-20 08:18:20', NULL, NULL),
(12, 'Punta Isla Lake Resort', 'Punta Isla is a premier lakeside resort situated on the shores of Lake Sebu offering comfortable lodging with panoramic lake views, floating cottage dining, and recreational activities. Guests can enjoy fresh tilapia straight from the lake, pedal boat rides, and stunning sunrise and sunset views over the water.', '₱500 – ₱1,500', 'Accommodation', 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800', 1, '2026-03-20 08:18:20', NULL, NULL),
(13, 'Kalibobong Falls', 'A hidden gem tucked deep in the forests of Tupi, Kalibobong Falls rewards adventurous hikers with a pristine multi-tiered waterfall surrounded by untouched tropical jungle. The trek to the falls takes approximately 45 minutes and passes through scenic farmlands and native forests. The cool, clear water is perfect for a refreshing dip.', 'Free entry', 'Nature', 'https://images.unsplash.com/photo-1583511655826-05700442b316?w=800', 1, '2026-03-20 08:18:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` tinyint(4) NOT NULL DEFAULT 1,
  `last_attempt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL COMMENT '1 to 5 stars',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `user_id`, `location_id`, `rating`, `created_at`) VALUES
(1, 3, 2, 5, '2026-03-20 01:33:48');

-- --------------------------------------------------------

--
-- Table structure for table `trip_plans`
--

CREATE TABLE `trip_plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED NOT NULL,
  `trip_date` date NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash — never plain text',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@tourism.local', '$2y$12$pUUVViiVf820h11FKVkGIOmQFeZ4IxpXUgcdsG4UeJx7j19FKTJKe', 'admin', '2026-03-20 00:22:24'),
(2, 'visitor', 'visitor@tourism.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-03-20 00:22:24'),
(3, 'rey123', 'rey@gmail.com', '$2y$12$OaAQSrxmz3JPrBcrYMVTx.GcTkQ62IU6GE1B/LX8Y5lLMUJ1mw.Di', 'user', '2026-03-20 00:26:08');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `category`, `created_at`) VALUES
(1, 2, 'Nature', '2026-03-20 00:59:45'),
(2, 2, 'Adventure', '2026-03-20 00:59:45'),
(9, 3, 'Adventure', '2026-03-20 09:58:42'),
(10, 3, 'Agri-Tourism', '2026-03-20 09:58:42'),
(11, 3, 'Culture', '2026-03-20 09:58:42'),
(12, 3, 'Nature', '2026-03-20 09:58:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_user` (`user_id`),
  ADD KEY `idx_booking_loc` (`location_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location` (`location_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fav` (`user_id`,`location_id`),
  ADD KEY `fk_fav_loc` (`location_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ip` (`ip_address`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_location` (`user_id`,`location_id`),
  ADD KEY `idx_location_rating` (`location_id`);

--
-- Indexes for table `trip_plans`
--
ALTER TABLE `trip_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trip_user` (`user_id`),
  ADD KEY `fk_trip_loc` (`location_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_category` (`user_id`,`category`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_plans`
--
ALTER TABLE `trip_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_book_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_book_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comment_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_fav_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `fk_rating_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trip_plans`
--
ALTER TABLE `trip_plans`
  ADD CONSTRAINT `fk_trip_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trip_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
