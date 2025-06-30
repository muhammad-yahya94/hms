-- Create reviews table
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hotel_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`hotel_id`) REFERENCES `hotels`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add average_rating column to hotels table
ALTER TABLE `hotels` ADD COLUMN IF NOT EXISTS `average_rating` DECIMAL(3,2) DEFAULT 0.00;

-- Create a view for hotel ratings summary
CREATE OR REPLACE VIEW `hotel_ratings` AS
SELECT 
    h.id AS hotel_id,
    COUNT(r.id) AS total_reviews,
    COALESCE(AVG(r.rating), 0) AS average_rating
FROM 
    hotels h
LEFT JOIN 
    reviews r ON h.id = r.hotel_id
GROUP BY 
    h.id;
