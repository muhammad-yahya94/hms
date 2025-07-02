SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `booking_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
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
(2, 5, 2, 4, '2025-05-25 11:29:00', '2025-05-25 16:29:00', 3, 1, 1000.00, 'completed', '2025-05-27 04:38:53', 'confirmed', '2025-05-25 11:29:00', '2025-05-25 16:29:00'),
(33, 3, 1, 1, '2025-05-30 03:46:00', '2025-05-31 03:46:00', 2, 0, 115200.00, 'cancelled', '2025-05-30 01:47:39', 'pending', NULL, NULL),
(34, 2, 1, 1, '2025-05-31 04:17:00', '2025-06-01 04:17:00', 2, 0, 115200.00, 'cancelled', '2025-05-31 02:17:57', 'pending', NULL, NULL),
(35, 3, 1, 1, '2025-06-01 04:21:00', '2025-06-02 04:21:00', 2, 0, 4800.00, 'cancelled', '2025-05-31 02:21:46', 'pending', NULL, NULL),
(36, 3, 1, 1, '2025-06-11 13:25:00', '2025-06-12 13:25:00', 2, 0, 115200.00, 'completed', '2025-06-11 11:25:11', 'pending', NULL, NULL),
(37, 3, 2, 3, '2025-06-30 07:31:00', '2025-06-30 11:34:00', 2, 0, 1000.00, 'completed', '2025-06-30 05:31:12', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user_id`, `hotel_id`, `created_at`, `updated_at`) VALUES
(1, 4, 2, '2025-06-30 08:06:24', '2025-06-30 09:16:39'),
(2, 4, 1, '2025-06-30 08:25:11', '2025-06-30 08:25:11'),
(3, 2, 2, '2025-06-30 08:43:32', '2025-06-30 08:43:32'),
(4, 3, 2, '2025-06-30 09:09:02', '2025-06-30 09:09:02'),
(5, 3, 1, '2025-06-30 09:09:13', '2025-06-30 09:09:13');

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
(4, 3, 1, 4, 1, 200.00, 'delivered', '2025-06-11 11:44:07'),
(5, 3, 2, 1, 2, 400.00, 'delivered', '2025-06-30 05:38:06'),
(6, 3, 2, 2, 1, 150.00, 'delivered', '2025-06-30 05:38:06'),
(7, 3, 2, 1, 4, 800.00, 'delivered', '2025-06-30 05:49:21'),
(8, 3, 2, 2, 2, 300.00, 'delivered', '2025-06-30 05:49:21'),
(9, 3, 2, 1, 2, 400.00, 'delivered', '2025-06-30 06:03:32');

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
  `vendor_id` int(11) NOT NULL,
  `average_rating` decimal(3,2) DEFAULT NULL COMMENT 'Stores the average rating (1.00-5.00) for the hotel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `name`, `description`, `address`, `city`, `phone`, `email`, `website`, `image_url`, `created_at`, `updated_at`, `vendor_id`, `average_rating`) VALUES
(1, 'Ali Hotel No. 1', 'A luxurious hotel in Jhang owned by Ali.', '1010 St, Jhang', 'Jhang', '0300000101', 'alihotelno.1@jhanghotels.com', 'www.alihotelno.1.com', 'includes/images/ali_hotel_no._1.jpg', '0000-00-00 00:00:00', '2025-06-30 07:38:37', 1, 5.00),
(2, 'Zainab Hotel No. 1', 'A luxurious hotel in Jhang owned by Zainab.', '2010 St, Jhang', 'Jhang', '0300000201', 'zainabhotelno.1@jhanghotels.com', 'www.zainabhotelno.1.com', 'includes/images/zainab_hotel_no._1.jpg', '0000-00-00 00:00:00', '2025-06-30 07:57:26', 2, 2.33);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_employee`
--

CREATE TABLE `hotel_employee` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `designation` varchar(50) NOT NULL,
  `department` varchar(50) NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `joining_date` date NOT NULL,
  `shift_timing` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','on_leave','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel_employee`
--

INSERT INTO `hotel_employee` (`id`, `user_id`, `hotel_id`, `employee_id`, `designation`, `department`, `salary`, `joining_date`, `shift_timing`, `status`, `created_at`, `updated_at`) VALUES
(1, 18, 1, 'EMP328774985043', 'manager', 'operation', 989842.00, '2025-06-30', '9:00 AM to 5:00 PM', 'active', '2025-06-30 03:52:51', '2025-06-30 03:52:51');

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
(2, 2, 'pulao', 150.00, 1, 'Uploads/food/food_68496149bebc6.jpg'),
(3, 1, 'biryani', 500.00, 1, 'Uploads/food/food_68496925b76ae.jpg'),
(4, 1, 'alo gosht', 200.00, 1, 'Uploads/food/food_68496a95e22b4.jpg'),
(5, 1, 'alo matar', 400.00, 1, 'Uploads/food/food_68496a95e31a1.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `sender_type`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 4, 'user', 'hi', 1, '2025-06-30 08:21:56'),
(2, 1, 4, 'user', 'hlw', 1, '2025-06-30 08:22:02'),
(3, 1, 4, 'user', 'how are you', 1, '2025-06-30 08:24:15'),
(4, 1, 4, 'user', 'hi', 1, '2025-06-30 08:26:22'),
(5, 1, 4, 'user', 'hoho', 1, '2025-06-30 08:26:27'),
(6, 1, 4, 'user', 'hi', 1, '2025-06-30 08:37:14'),
(7, 1, 2, 'admin', 'yes ?', 1, '2025-06-30 08:54:57'),
(8, 1, 2, 'admin', 'nothing', 0, '2025-06-30 09:16:39');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `hotel_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 3, 5, 'nice', '2025-06-30 07:38:37'),
(2, 2, 3, 1, 'avg', '2025-06-30 07:41:14'),
(3, 2, 4, 1, '1 star', '2025-06-30 07:57:09'),
(4, 2, 4, 5, '5 stars', '2025-06-30 07:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `room_type` enum('standard','deluxe','suite','presidential_suite') NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `status` enum('available','not available','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `hotel_id`, `room_type`, `description`, `price_per_hour`, `capacity`, `image_url`, `amenities`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'standard', 'standard room in Ali Hotel No. 1.', 4800.00, 2, 'includes/images/room_standard_1_1.jpg', 'WiFi, TV, AC', 'available', '2025-05-27 04:38:53', '2025-06-30 06:51:01'),
(2, 1, 'deluxe', 'deluxe room in Ali Hotel No. 1.', 7200.00, 3, 'includes/images/room_deluxe_1_2.jpg', 'WiFi, TV, AC, Minibar', 'available', '2025-05-27 04:38:53', '2025-05-27 04:43:08'),
(3, 2, 'standard', 'standard room in Zainab Hotel No. 1.', 4800.00, 2, 'includes/images/room_standard_2_1.jpg', 'WiFi, TV, AC', 'available', '2025-05-27 04:38:53', '2025-06-30 06:52:14'),
(4, 2, 'deluxe', 'deluxe room in Zainab Hotel No. 1.', 7200.00, 3, 'includes/images/room_deluxe_2_2.jpg', 'WiFi, TV, AC, Minibar', 'available', '2025-05-27 04:38:53', '2025-06-30 06:47:38');

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
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`, `created_at`, `first_name`, `last_name`, `phone`, `address`, `profile_image`, `vendor_id`) VALUES
(1, 'admin1', 'admin1@jhanghotels.com', '$2y$10$vWs6QJ7nVuzUMt/YNzqXfO/81d41A2yIiEKohP.4zmhL6GYm77odS', 'admin', 'active', '2025-05-27 04:38:53', 'Ali', 'Hassan', '03001234567', 'Jhang, Punjab, Pakistan', NULL, 0),
(2, 'admin2', 'admin2@jhanghotels.com', '$2y$10$7rAI1.3IYUCXc2DZSI/rDeOLdxotDlN.PG7.jFyvKIldBsN0pteXK', 'admin', 'active', '2025-05-27 04:38:53', 'Zainab', 'Khan', '03001234568', 'Jhang, Punjab, Pakistan', NULL, 0),
(3, 'user1', 'user1@jhanghotels.com', '$2y$10$ItbdF9RUjQa9xwDtPjJZw.zd9NcWEE9Z3l.yqcle7zRcp5HQHoz5y', 'user', 'active', '2025-05-27 04:38:53', 'Ahmed', 'Khan', '03111234567', '123 Main St, Jhang', NULL, 0),
(4, 'user2', 'user2@jhanghotels.com', '$2y$10$PtLab4G5FXAEHVersk91JOi4wtC38tvsBbhyHl9WCtuc3eNLGOd76', 'user', 'active', '2025-05-27 04:38:53', 'Sara', 'Malik', '03211234567', '456 Garden Rd, Jhang', NULL, 0),
(5, 'user3', 'user3@jhanghotels.com', '$2y$10$ZAu/yLhUqqDRusv5dobMU.gWDOuye4UFW741o86qv/PdBiMJa.XSi', 'user', 'active', '2025-05-27 04:38:53', 'Usman', 'Riaz', '03311234567', '789 Park Ave, Jhang', NULL, 0),
(17, 'ali899677', 'ali998@gmail.com', '$2y$10$3zYqvWwcVMyOXBGC.3o9Vun2rPXq8Lo9gqY0Jm/nQTsZ0Vq1SYSDK', 'user', 'active', '2025-06-29 18:26:31', 'ali', 'hassan', '0000000000', 'cvbnm', NULL, 1),
(18, 'employee82309504', 'tedowih547@godsigma.com', '$2y$10$hA7pf8//aq7uJ8fpCVgLlO6NDbBM4N.XBsUJgS5jDo9wvggSDulKS', 'user', 'active', '2025-06-30 03:52:51', '56yui', 'iuytghj', '0000000000', '1234567iuytsa\\zxcvbn00', NULL, 1);

COMMIT;