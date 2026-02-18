SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE customer_materials;
TRUNCATE TABLE customers;
TRUNCATE TABLE materials;
TRUNCATE TABLE sync_progress;
TRUNCATE TABLE messenger_messages;
SET FOREIGN_KEY_CHECKS = 1;
SELECT 'All tables cleaned successfully' as Status;
