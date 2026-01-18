-- Seed data for initial setup
-- Creates default admin user
-- Password: admin123 (hashed)

INSERT INTO `users` (`username`, `email`, `full_name`, `password_hash`, `role`, `created_at`) 
VALUES 
('admin', 'admin@example.com', 'System Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());

-- Note: The above password hash is for 'admin123'
-- Change this password immediately after first login via Settings → Security
