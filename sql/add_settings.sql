-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Insert default total_periods if not exists
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('total_periods', '8');

SELECT 'System settings table created' as status;
