-- SQL script to update the database schema for the Invoicer application

-- 1. Remove the company_id from the products table
-- Products should belong to the user, not a specific customer.
ALTER TABLE `products` DROP FOREIGN KEY `fk_product_to_company`;
ALTER TABLE `products` DROP COLUMN `company_id`;


-- 2. Create a table to track inventory history
-- This table will log every stock change for auditing purposes.
CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `change_quantity` int(11) NOT NULL COMMENT 'Negative for sale, positive for addition',
  `reason` varchar(255) NOT NULL COMMENT 'e.g., Invoice Sale, Manual Stock, Initial',
  `related_invoice_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  KEY `related_invoice_id` (`related_invoice_id`),
  CONSTRAINT `inventory_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_history_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_history_ibfk_3` FOREIGN KEY (`related_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 3. Create a table to handle sequential invoice numbering
-- This ensures that invoice numbers are sequential per user (e.g., INV-001, INV-002).
CREATE TABLE `invoice_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `last_invoice_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `invoice_sequences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Alter invoices table to handle new invoice number format
ALTER TABLE `invoices` MODIFY `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- Note: The logic to populate and use the invoice_sequences table will be handled in the PHP code.
-- Existing users will need an entry in this table to start generating new invoices.
INSERT INTO `invoice_sequences` (`user_id`, `last_invoice_number`)
SELECT `id`, 0 FROM `users` WHERE `id` NOT IN (SELECT `user_id` FROM `invoice_sequences`);

-- Also, update the payment_status to be an ENUM for data consistency.
ALTER TABLE `invoices` MODIFY `payment_status` ENUM('Paid','Overdue','Ongoing') NOT NULL DEFAULT 'Overdue';
