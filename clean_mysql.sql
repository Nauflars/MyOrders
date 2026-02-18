SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE customer_materials;
TRUNCATE TABLE sync_progress;
TRUNCATE TABLE messenger_messages;
SET FOREIGN_KEY_CHECKS = 1;
SELECT 'MySQL tables cleaned successfully' as Status;
