-- Safe Migration Script - Won't fail if already run
-- Run this to ensure all API integration tables exist

-- Create api_sync_log table (if not exists)
CREATE TABLE IF NOT EXISTS api_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_date DATE NOT NULL,
    fetch_type ENUM('DAILY', 'INCREMENTAL') DEFAULT 'DAILY',
    status ENUM('SUCCESS', 'FAILED') NOT NULL,
    records_processed INT DEFAULT 0,
    last_record VARCHAR(50) NULL COMMENT 'For incremental sync tracking (e.g., 092020$456)',
    error_message TEXT NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add empcode to teachers if it doesn't exist (safe to run multiple times)
SET @dbname = DATABASE();
SET @tablename = 'teachers';
SET @columnname = 'empcode';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE teachers ADD COLUMN empcode VARCHAR(20) NULL COMMENT "Employee code from eTime Office biometric system" AFTER name, ADD UNIQUE KEY unique_empcode (empcode)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SELECT 'Migration completed successfully!' as status;
