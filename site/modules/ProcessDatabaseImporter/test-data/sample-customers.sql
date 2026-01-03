-- Sample customer database for testing
-- This is a simple customer table with various field types

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `customer_type` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `customers` VALUES
(1, 'Müller GmbH', 'info@mueller-gmbh.de', '+49 30 12345678', 'Müller GmbH', 'https://www.mueller-gmbh.de', 'Hauptstraße 123', 'Berlin', 'Deutschland', '10115', 'active', 'business', 50000.00, 1, 'Wichtiger Geschäftskunde seit 2020', '2020-01-15 10:30:00', '2024-01-10 14:22:00'),
(2, 'Schmidt Consulting', 'kontakt@schmidt-consulting.de', '+49 40 98765432', 'Schmidt Consulting', 'https://schmidt-consulting.de', 'Alsterweg 45', 'Hamburg', 'Deutschland', '20095', 'active', 'business', 25000.00, 1, 'Regelmäßiger Kunde', '2020-03-22 09:15:00', '2024-01-08 11:30:00'),
(3, 'Anna Weber', 'anna.weber@example.com', '+49 89 11223344', NULL, NULL, 'Marienplatz 7', 'München', 'Deutschland', '80331', 'active', 'private', 5000.00, 1, NULL, '2021-05-10 16:45:00', '2023-12-20 09:10:00'),
(4, 'Tech Solutions AG', 'info@techsolutions.ch', '+41 44 9876543', 'Tech Solutions AG', 'https://techsolutions.ch', 'Bahnhofstrasse 100', 'Zürich', 'Schweiz', '8001', 'active', 'business', 100000.00, 1, 'Premium Kunde mit Sonderkonditionen', '2019-11-05 13:20:00', '2024-01-12 16:45:00'),
(5, 'Maria Schneider', 'maria.schneider@email.at', '+43 1 2345678', NULL, NULL, 'Stephansplatz 5', 'Wien', 'Österreich', '1010', 'inactive', 'private', 3000.00, 0, 'Kunde inaktiv seit 2023', '2022-02-28 11:00:00', '2023-06-15 10:00:00'),
(6, 'Fischer & Partner', 'info@fischer-partner.de', '+49 69 55667788', 'Fischer & Partner', NULL, 'Zeil 120', 'Frankfurt', 'Deutschland', '60313', 'pending', 'business', 15000.00, 0, 'Neukunde in Prüfung', '2024-01-05 08:30:00', NULL),
(7, 'Klein Industries', 'sales@klein-ind.com', '+49 711 3344556', 'Klein Industries', 'https://www.klein-industries.com', 'Industriestraße 88', 'Stuttgart', 'Deutschland', '70565', 'active', 'business', 75000.00, 1, 'Internationaler Kunde', '2018-07-12 14:10:00', '2024-01-11 15:20:00'),
(8, 'Hans Bauer', 'hans.bauer@web.de', '0221 9988776', NULL, NULL, 'Domplatz 1', 'Köln', 'Deutschland', '50667', 'active', 'private', 2000.00, 1, NULL, '2023-04-18 10:20:00', '2023-12-28 14:30:00'),
(9, 'Innovate GmbH', 'contact@innovate.de', '+49 351 6677889', 'Innovate GmbH', 'https://innovate.de', 'Neumarkt 15', 'Dresden', 'Deutschland', '01067', 'active', 'business', 35000.00, 1, 'Startup Kunde', '2022-09-01 09:00:00', '2024-01-09 12:15:00'),
(10, 'Petra Hoffmann', 'petra.h@gmail.com', '0511 4455667', NULL, NULL, 'Kröpcke 3', 'Hannover', 'Deutschland', '30159', 'pending', 'private', 1000.00, 0, 'Verifizierung ausstehend', '2024-01-10 16:00:00', NULL);

-- Sample products table to test relationships
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` VALUES
(1, 'Premium Widget', 'Hochwertiges Widget für professionelle Anwendungen', 299.99, 150, 'Hardware', 1, '2020-01-01 00:00:00'),
(2, 'Standard Widget', 'Standardausführung für normale Anforderungen', 149.99, 320, 'Hardware', 1, '2020-01-01 00:00:00'),
(3, 'Software Lizenz Pro', 'Professionelle Software-Lizenz (1 Jahr)', 499.00, 9999, 'Software', 1, '2020-06-15 00:00:00'),
(4, 'Support Paket Basic', '12 Monate Support Basic', 199.00, 9999, 'Service', 1, '2021-01-01 00:00:00'),
(5, 'Support Paket Premium', '12 Monate Support Premium', 599.00, 9999, 'Service', 1, '2021-01-01 00:00:00');

-- Sample orders table to test foreign keys
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `orders` VALUES
(1, 1, 'ORD-2024-001', '2024-01-05 10:15:00', 1499.95, 'completed', 'Expresslieferung'),
(2, 1, 'ORD-2024-002', '2024-01-08 14:30:00', 599.00, 'processing', NULL),
(3, 2, 'ORD-2024-003', '2024-01-06 09:20:00', 898.00, 'completed', NULL),
(4, 3, 'ORD-2024-004', '2024-01-07 16:45:00', 299.99, 'completed', NULL),
(5, 4, 'ORD-2024-005', '2024-01-09 11:00:00', 2499.00, 'processing', 'Großbestellung'),
(6, 7, 'ORD-2024-006', '2024-01-10 13:30:00', 1998.00, 'pending', NULL),
(7, 8, 'ORD-2024-007', '2024-01-11 15:20:00', 149.99, 'completed', NULL),
(8, 9, 'ORD-2024-008', '2024-01-12 10:10:00', 699.00, 'processing', 'Rechnung gewünscht');
