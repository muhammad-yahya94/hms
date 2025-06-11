-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2025 at 02:08 PM
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
-- Database: `hms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` datetime NOT NULL,
  `check_out_date` datetime NOT NULL,
  `adults` int(11) NOT NULL,
  `children` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending',
  `check_in` datetime DEFAULT NULL,
  `check_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `hotel_id`, `room_id`, `check_in_date`, `check_out_date`, `adults`, `children`, `total_price`, `booking_status`, `created_at`, `status`, `check_in`, `check_out`) VALUES
(1, 5, 1, 1, '2025-05-26 13:41:00', '2025-05-26 16:41:00', 3, 1, 600.00, 'pending', '2025-05-27 04:38:53', 'pending', NULL, NULL),
(2, 5, 2, 4, '2025-05-25 11:29:00', '2025-05-25 16:29:00', 3, 1, 1000.00, 'confirmed', '2025-05-27 04:38:53', 'confirmed', '2025-05-25 11:29:00', '2025-05-25 16:29:00'),
(33, 3, 1, 1, '2025-05-30 03:46:00', '2025-05-31 03:46:00', 2, 0, 115200.00, 'cancelled', '2025-05-30 01:47:39', 'pending', NULL, NULL),
(34, 2, 1, 1, '2025-05-31 04:17:00', '2025-06-01 04:17:00', 2, 0, 115200.00, 'cancelled', '2025-05-31 02:17:57', 'pending', NULL, NULL),
(35, 3, 1, 1, '2025-06-01 04:21:00', '2025-06-02 04:21:00', 2, 0, 4800.00, 'cancelled', '2025-05-31 02:21:46', 'pending', NULL, NULL),
(36, 3, 1, 1, '2025-06-11 13:25:00', '2025-06-12 13:25:00', 2, 0, 115200.00, 'confirmed', '2025-06-11 11:25:11', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `food_orders`
--

CREATE TABLE `food_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `order_status` enum('pending','confirmed','cancelled','delivered') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_orders`
--

INSERT INTO `food_orders` (`id`, `user_id`, `hotel_id`, `menu_item_id`, `quantity`, `total_price`, `order_status`, `created_at`) VALUES
(3, 3, 1, 4, 1, 200.00, 'delivered', '2025-06-11 11:40:48'),
(4, 3, 1, 4, 1, 200.00, 'delivered', '2025-06-11 11:44:07');

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vendor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `name`, `description`, `address`, `city`, `phone`, `email`, `website`, `image_url`, `created_at`, `updated_at`, `vendor_id`) VALUES
(1, 'Ali Hotel No. 1', 'A luxurious hotel in Jhang owned by Ali.', '1010 St, Jhang', 'Jhang', '0300000101', 'alihotelno.1@jhanghotels.com', 'www.alihotelno.1.com', 'includes/images/ali_hotel_no._1.jpg', '0000-00-00 00:00:00', '2025-05-27 04:38:53', 1),
(2, 'Zainab Hotel No. 1', 'A luxurious hotel in Jhang owned by Zainab.', '2010 St, Jhang', 'Jhang', '0300000201', 'zainabhotelno.1@jhanghotels.com', 'www.zainabhotelno.1.com', 'includes/images/zainab_hotel_no._1.jpg', '0000-00-00 00:00:00', '2025-05-27 04:38:53', 2);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_menu`
--

CREATE TABLE `hotel_menu` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel_menu`
--

INSERT INTO `hotel_menu` (`id`, `hotel_id`, `item_name`, `price`, `is_available`, `image_url`) VALUES
(1, 2, 'biryani', 200.00, 1, 'Uploads/food/food_68496149bcfce.jpg'),
(2, 2, 'pulao', 150.00, 0, 'Uploads/food/food_68496149bebc6.jpg'),
(3, 1, 'biryani', 500.00, 1, 'Uploads/food/food_68496925b76ae.jpg'),
(4, 1, 'alo gosht', 200.00, 1, 'Uploads/food/food_68496a95e22b4.jpg'),
(5, 1, 'alo matar', 400.00, 1, 'Uploads/food/food_68496a95e31a1.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `room_type` enum('standard','deluxe','suite','presidential_suite') NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `hotel_id`, `room_type`, `description`, `price_per_night`, `capacity`, `image_url`, `amenities`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'standard', 'standard room in Ali Hotel No. 1.', 4800.00, 2, 'includes/images/room_standard_1_1.jpg', 'WiFi, TV, AC', 'booked', '2025-05-27 04:38:53', '2025-06-11 12:05:03'),
(2, 1, 'deluxe', 'deluxe room in Ali Hotel No. 1.', 7200.00, 3, 'includes/images/room_deluxe_1_2.jpg', 'WiFi, TV, AC, Minibar', 'available', '2025-05-27 04:38:53', '2025-05-27 04:43:08'),
(3, 2, 'standard', 'standard room in Zainab Hotel No. 1.', 4800.00, 2, 'includes/images/room_standard_2_1.jpg', 'WiFi, TV, AC', 'available', '2025-05-27 04:38:53', '2025-05-31 02:17:07'),
(4, 2, 'deluxe', 'deluxe room in Zainab Hotel No. 1.', 7200.00, 3, 'includes/images/room_deluxe_2_2.jpg', 'WiFi, TV, AC, Minibar', 'booked', '2025-05-27 04:38:53', '2025-05-31 02:17:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `vendor_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `first_name`, `last_name`, `phone`, `address`, `vendor_id`) VALUES
(1, 'admin1', 'admin1@jhanghotels.com', '$2y$10$vWs6QJ7nVuzUMt/YNzqXfO/81d41A2yIiEKohP.4zmhL6GYm77odS', 'admin', '2025-05-27 04:38:53', 'Ali', 'Hassan', '03001234567', 'Jhang, Punjab, Pakistan', 0),
(2, 'admin2', 'admin2@jhanghotels.com', '$2y$10$7rAI1.3IYUCXc2DZSI/rDeOLdxotDlN.PG7.jFyvKIldBsN0pteXK', 'admin', '2025-05-27 04:38:53', 'Zainab', 'Khan', '03001234568', 'Jhang, Punjab, Pakistan', 0),
(3, 'user1', 'user1@jhanghotels.com', '$2y$10$ItbdF9RUjQa9xwDtPjJZw.zd9NcWEE9Z3l.yqcle7zRcp5HQHoz5y', 'user', '2025-05-27 04:38:53', 'Ahmed', 'Khan', '03111234567', '123 Main St, Jhang', 0),
(4, 'user2', 'user2@jhanghotels.com', '$2y$10$PtLab4G5FXAEHVersk91JOi4wtC38tvsBbhyHl9WCtuc3eNLGOd76', 'user', '2025-05-27 04:38:53', 'Sara', 'Malik', '03211234567', '456 Garden Rd, Jhang', 0),
(5, 'user3', 'user3@jhanghotels.com', '$2y$10$ZAu/yLhUqqDRusv5dobMU.gWDOuye4UFW741o86qv/PdBiMJa.XSi', 'user', '2025-05-27 04:38:53', 'Usman', 'Riaz', '03311234567', '789 Park Ave, Jhang', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hotel_id` (`hotel_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hotel_id` (`hotel_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vendor_id` (`vendor_id`);

--
-- Indexes for table `hotel_menu`
--
ALTER TABLE `hotel_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `hotel_menu`
--
ALTER TABLE `hotel_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD CONSTRAINT `food_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `food_orders_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`),
  ADD CONSTRAINT `food_orders_ibfk_3` FOREIGN KEY (`menu_item_id`) REFERENCES `hotel_menu` (`id`);

--
-- Constraints for table `hotels`
--
ALTER TABLE `hotels`
  ADD CONSTRAINT `fk_vendor_id` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `hotel_menu`
--
ALTER TABLE `hotel_menu`
  ADD CONSTRAINT `hotel_menu_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
