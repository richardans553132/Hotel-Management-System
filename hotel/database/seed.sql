-- =====================================================================
-- SMART HOTEL BOOKING MANAGEMENT SYSTEM
-- Comprehensive Seed Data
-- =====================================================================

USE `hotel_booking_system`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Seed Users (Bcrypt hashed passwords: 'admin123' for admin, 'password123' for customers)
-- ---------------------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password`, `phone`, `role`, `avatar`, `status`) VALUES
(1, 'System Administrator', 'admin', 'admin@josilhotel.com', '$2y$10$.KQdEiXPjQNDj4FZDeM7r.EwxOJRl5GIrR/pnVfc6buGacq.he/9m', '+233 24 123 4567', 'admin', 'default_admin.png', 'active'),
(2, 'Kwame Mensah', 'kwamemensah', 'kwame.mensah@gmail.com', '$2y$10$1LkyPAfptCr9MSLgrECOuODu7b/2rZfYOzCGLDdq6tI3zLBGJnQem', '+233 20 987 6543', 'customer', 'default_avatar.png', 'active'),
(3, 'Akosua Serwaa', 'akosuaserwaa', 'akosua.serwaa@yahoo.com', '$2y$10$1LkyPAfptCr9MSLgrECOuODu7b/2rZfYOzCGLDdq6tI3zLBGJnQem', '+233 27 555 1234', 'customer', 'default_avatar.png', 'active'),
(4, 'David Osei', 'davidosei', 'david.osei@hotmail.com', '$2y$10$1LkyPAfptCr9MSLgrECOuODu7b/2rZfYOzCGLDdq6tI3zLBGJnQem', '+233 55 432 1098', 'customer', 'default_avatar.png', 'active')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `email` = VALUES(`email`), `password` = VALUES(`password`), `role` = VALUES(`role`);

-- Ensure administrator link
INSERT INTO `administrators` (`user_id`, `department`, `access_level`) VALUES
(1, 'Executive Operations', 'super_admin')
ON DUPLICATE KEY UPDATE `department` = VALUES(`department`);

-- ---------------------------------------------------------------------
-- 2. Seed Hotels
-- ---------------------------------------------------------------------
INSERT INTO `hotels` (`id`, `name`, `slug`, `tagline`, `description`, `address`, `city`, `state`, `country`, `phone`, `email`, `star_rating`, `cover_image`, `amenities`, `status`) VALUES
(1, 'Josil Royal Luxury Hotel', 'josil-royal-luxury-hotel', 'Experience Luxury, Serenity, and World-Class Ghanaian Hospitality', 
 'Nestled in the prestigious heart of Accra, Josil Royal Luxury Hotel provides an oasis of tranquility for business travelers, couples, and vacationers. Featuring modern architecture, gourmet dining, rooftop infinity pool, and state-of-the-art spa facilities.',
 'Independence Avenue, Ridge', 'Accra', 'Greater Accra', 'Ghana', '+233 30 212 3456', 'reservations@josilhotel.com', 4.9, 'hotel_1.jpg',
 'Free High-Speed Wi-Fi, Rooftop Infinity Pool, 24/7 Room Service, Luxury Spa, Ocean View Restaurant, Airport Shuttle, Valet Parking, Gym & Fitness Center', 'active'),

(2, 'Palm Crest Ocean Resort', 'palm-crest-ocean-resort', 'Beachfront Paradise with Unmatched Sunset Views',
 'Located right on the golden sands of Kokrobite Beach, Palm Crest Ocean Resort offers private cabanas, fresh seafood, coconut groves, and tranquil ocean breezes. Ideal for family holidays and romantic weekend getaways.',
 'Beach Road, Kokrobite', 'Accra', 'Greater Accra', 'Ghana', '+233 24 555 7890', 'info@palmcrestresort.com', 4.7, 'hotel_2.jpg',
 'Private Beach Access, Swimming Pool, Beachfront Bar, Free Breakfast, Water Sports, Beach Volleyball, Family Suites, Live Music Nights', 'active'),

(3, 'Golden Horizon Heritage Hotel', 'golden-horizon-heritage-hotel', 'Traditional Elegance Meets Contemporary Comfort in the Garden City',
 'Set amid lush botanical gardens in Kumasi, Golden Horizon combines Ashanti royal architectural heritage with luxurious modern amenities, conference halls, and authentic culinary journeys.',
 'Ahodwo Roundabout, Nhyiaeso', 'Kumasi', 'Ashanti', 'Ghana', '+233 32 201 9876', 'info@goldenhorizon.com', 4.8, 'hotel_3.jpg',
 'Botanical Gardens, Conference Center, Cultural Lounge, Executive Boardrooms, Traditional & Continental Buffet, Tennis Court, High-Speed Wi-Fi', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `tagline` = VALUES(`tagline`), `description` = VALUES(`description`);

-- ---------------------------------------------------------------------
-- 3. Seed Rooms
-- ---------------------------------------------------------------------
INSERT INTO `rooms` (`id`, `hotel_id`, `room_number`, `type`, `price_per_night`, `capacity`, `amenities`, `description`, `image`, `status`) VALUES
(1, 1, '101', 'Deluxe Executive Suite', 350.00, 2, 'King Bed, Balcony, Smart TV, Mini Bar, Free Wi-Fi, Coffee Maker, Luxury Bathrobe, Air Conditioning', 
 'Spacious and elegantly decorated executive suite featuring a king-size plush bed, private panoramic balcony, and an Italian marble bathroom with soaking tub.', 'room_1.jpg', 'available'),

(2, 1, '102', 'Presidential Penthouse', 750.00, 4, 'Master King Bed + Twin Bed, Private Jacuzzi, Skyline View, Living Room, Kitchenette, Butler Service, 65-inch Smart TV',
 'The pinnacle of luxury living. Occupying the top floor with private elevator access, 360-degree city views, private jacuzzi, and dedicated 24-hour butler.', 'room_2.jpg', 'available'),

(3, 1, '103', 'Standard Royal Queen', 220.00, 2, 'Queen Bed, Work Desk, En-suite Shower, Free High-Speed Wi-Fi, Air Conditioning, Flat Screen TV',
 'Cozy and modern room optimized for solo business travelers or couples seeking premium comfort at an accessible rate.', 'room_3.jpg', 'available'),

(4, 2, '201', 'Ocean Front Cabana', 420.00, 2, 'King Bed, Direct Beach Walkout, Hammock, Outdoor Rain Shower, Ocean View, Breakfast Included',
 'Step right out onto the sands. This tropical cabana features panoramic ocean vistas, private hammock deck, and soothing sound of waves.', 'room_4.jpg', 'available'),

(5, 2, '202', 'Family Seaside Villa', 580.00, 5, '2 Queen Beds + 1 Single, Private Patio, Kitchenette, Dining Area, Beach View, Kids Play Corner',
 'Designed specifically for family getaways with ample living space, kitchen facilities, and direct proximity to the resort pool and beach.', 'room_5.jpg', 'available'),

(6, 3, '301', 'Heritage Royal Suite', 380.00, 3, 'King Bed, Handcrafted Wood Décor, Garden View Balcony, Workstation, Tea/Coffee Bar, Rain Shower',
 'Embellished with artisanal Ghanaian woodwork and gold motifs, overlooking tranquil private gardens with modern plush bedding.', 'room_6.jpg', 'available')
ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `price_per_night` = VALUES(`price_per_night`), `status` = VALUES(`status`);

-- ---------------------------------------------------------------------
-- 4. Seed Settings
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('hotel_name', 'Josil Hotel & Suites'),
('system_title', 'Smart Hotel Booking Management System'),
('contact_email', 'support@josilhotel.com'),
('contact_phone', '+233 30 212 3456'),
('address', 'Independence Avenue, Ridge, Accra, Ghana'),
('currency', 'GHS'),
('tax_rate', '12.5'),
('paystack_public_key', 'pk_test_d389a9f23fa46ec989c7711df7b51b2c4e12b7a8'),
('paystack_secret_key', 'sk_test_7a18f23fa46ec989c7711df7b51b2c4e12b7a9b0'),
('paystack_mode', 'sandbox'),
('smtp_host', 'smtp.mailtrap.io'),
('smtp_port', '2525'),
('smtp_user', 'sandbox_user'),
('smtp_pass', 'sandbox_password')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ---------------------------------------------------------------------
-- 5. Seed Reviews
-- ---------------------------------------------------------------------
INSERT INTO `reviews` (`id`, `hotel_id`, `room_id`, `user_id`, `rating`, `title`, `comment`, `status`) VALUES
(1, 1, 1, 2, 5, 'Exquisite experience in Ridge!', 'The customer service was unmatched from check-in to checkout. The rooftop infinity pool at night is simply breathtaking. Will definitely visit again!', 'approved'),
(2, 2, 4, 3, 5, 'Best beachfront weekend getaway', 'Waking up to the ocean sound in the beachfront cabana made our anniversary unforgettable. Food was fresh and delicious.', 'approved'),
(3, 3, 6, 4, 4, 'Peaceful and culturally inspiring', 'Loved the Ashanti heritage décor and lush gardens. Wi-Fi was fast and room service prompt. Highly recommend for business trips to Kumasi.', 'approved')
ON DUPLICATE KEY UPDATE `rating` = VALUES(`rating`), `comment` = VALUES(`comment`);

-- ---------------------------------------------------------------------
-- 6. Seed Sample Bookings & Payments
-- ---------------------------------------------------------------------
INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `hotel_id`, `room_id`, `check_in_date`, `check_out_date`, `total_guests`, `total_price`, `status`, `customer_name`, `customer_email`, `customer_phone`, `special_requests`) VALUES
(1, 'BK-2026-0814', 2, 1, 1, '2026-11-10', '2026-11-13', 2, 1050.00, 'confirmed', 'Kwame Mensah', 'kwame.mensah@gmail.com', '+233 20 987 6543', 'High floor preferred, late check-in at 8 PM'),
(2, 'BK-2026-0922', 3, 2, 4, '2026-11-15', '2026-11-18', 2, 1260.00, 'confirmed', 'Akosua Serwaa', 'akosua.serwaa@yahoo.com', '+233 27 555 1234', 'Anniversary celebration setup with flowers')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `payments` (`id`, `booking_id`, `user_id`, `payment_reference`, `transaction_id`, `payment_method`, `amount`, `currency`, `status`, `gateway_response`) VALUES
(1, 1, 2, 'PAY-2026-8812', 'pstk_txn_992147182', 'Paystack Sandbox', 1050.00, 'GHS', 'completed', '{"status":"success","message":"Approved","channel":"card"}'),
(2, 2, 3, 'PAY-2026-9934', 'pstk_txn_881273911', 'Paystack Sandbox', 1260.00, 'GHS', 'completed', '{"status":"success","message":"Approved","channel":"mobile_money"}')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- ---------------------------------------------------------------------
-- 7. Seed Sample Activity Logs
-- ---------------------------------------------------------------------
INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `ip_address`, `user_agent`) VALUES
(1, 'System Initialization', 'Database tables verified and seeded with initial demo data', '127.0.0.1', 'System Runner'),
(2, 'Booking Created', 'Reservation BK-2026-0814 created for Room 101', '127.0.0.1', 'Mozilla/5.0'),
(2, 'Payment Completed', 'Payment of GHS 1,050.00 completed via Paystack Sandbox for BK-2026-0814', '127.0.0.1', 'Mozilla/5.0');

-- ---------------------------------------------------------------------
-- 8. Seed Sample In-App Notifications
-- ---------------------------------------------------------------------
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(2, 'Booking Confirmed!', 'Your reservation #BK-2026-0814 at Josil Royal Luxury Hotel is confirmed. We look forward to hosting you on Nov 10, 2026.', 'booking', 0),
(2, 'Payment Receipt Available', 'Receipt for GHS 1,050.00 has been generated. You can download or print it from your dashboard.', 'payment', 0),
(1, 'New Online Reservation', 'Customer Kwame Mensah booked Room 101 for GHS 1,050.00.', 'system', 0);

SET FOREIGN_KEY_CHECKS = 1;
