-- Add Max Proxy Limits to Settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('max_daily_proxy', '2');
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('max_weekly_proxy', '10');

SELECT * FROM system_settings;
