# Proxy Teacher Management System

A comprehensive web-based solution for managing teacher substitutions, attendance tracking, and timetable management in educational institutions.

## 🎯 System Overview

This system automates the process of assigning substitute (proxy) teachers when regular teachers are absent. It features intelligent allocation algorithms, real-time dashboards, and comprehensive reporting capabilities.

## ✨ Key Features

### 📊 Dashboard & Analytics
- Real-time attendance visualization
- Daily proxy allocation statistics
- Teacher workload metrics
- Monthly trends and insights

### 👥 Teacher Management
- Complete teacher profile management
- Section/department assignment
- Subject expertise tracking
- Timetable transfer functionality

### 📅 Attendance Management
- Daily attendance marking interface
- API integration with biometric systems (eTime Office)
- Bulk import/export capabilities
- ERP integration for attendance sync
- Attendance locking mechanism

### 🔄 Proxy Allocation
- **Automated proxy assignment** with intelligent scoring:
  - Section/department matching
  - Subject expertise prioritization
  - Load balancing across teachers
  - Free period optimization
- Manual override capabilities
- Collision detection for group classes
- Daily overrides (teacher duties, class trips)

### 📋 Timetable Management
- Full timetable import (JSON, PDF, JPG)
- Class and teacher schedule views
- Group class support
- Period blocking configuration

### 📈 Reports & Exports
- Date-range proxy assignment reports
- Teacher-wise workload analysis
- Class-wise coverage statistics
- Excel export functionality

### ⚙️ Master Data
- Class/Section management
- Subject catalog
- Blocked periods (weekly schedule)
- System configuration

## 🚀 Getting Started

### Prerequisites
- PHP 7.4+ 
- SQLite3
- Apache/XAMPP
- Composer (for dependencies)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd proxy-teacher
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure the application**
   - Copy `config/app.php.example` to `config/app.php` (if exists)
   - Update `BASE_URL` in `config/app.php` if needed
   - Ensure `proxy_teacher.db` has write permissions

4. **Set up the database**
   ```bash
   # Create database
   mysql -u root -p
   CREATE DATABASE proxy_teacher_db;
   exit;
   
   # Import schema
   mysql -u root -p proxy_teacher_db < sql/schema.sql
   
   # Import seed data (creates default admin user)
   mysql -u root -p proxy_teacher_db < sql/seed_data.sql
   ```

5. **Set folder permissions**
   ```bash
   chmod 755 assets/uploads
   chmod 755 exports
   chmod 644 proxy_teacher.db
   ```

6. **Access the application**
   - Open `http://localhost/proxy-teacher/`
   - Or configure your Apache virtual host

## 🔐 Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

> **Note:** These credentials are created when you import `sql/seed_data.sql` during setup. If you're setting up a fresh installation, this file creates the initial admin user. **Change this password immediately** after first login via Settings → Security.

## 📁 Project Structure

```
proxy-teacher/
├── config/              # Configuration files
│   ├── app.php         # App constants & settings
│   └── database.php    # Database connection
├── includes/           # Common includes (header, footer)
├── models/             # Data models (Teacher, Attendance, etc.)
├── services/           # Business logic (ProxyAllocationService, etc.)
├── scripts/            # Utility scripts & AJAX endpoints
├── sql/                # Database schema & migrations
├── assets/             # Uploaded files (logos, etc.)
├── exports/            # Generated reports
├── reports/            # Report PDFs
└── *.php              # Page controllers
```

## 🗂️ Main Pages

| Page | Description |
|------|-------------|
| `dashboard.php` | Overview dashboard with analytics |
| `attendance.php` | Daily attendance marking interface |
| `proxy_allocation.php` | Manual & automatic proxy assignment |
| `timetable.php` | Teacher & class timetable management |
| `masters.php` | Master data (teachers, classes, subjects) |
| `reports.php` | Proxy assignment reports |
| `settings.php` | System configuration & profile |

## 🔧 Configuration

### API Integration (Settings → Attendance API)
Configure eTime Office biometric system integration:
- Corporate ID
- API Username & Password
- Base URL

### ERP Integration (Settings → ERP Integration)
Push attendance data to external ERP (Entab):
- ERP API URL
- Authentication Header Key

### Email Settings (Settings → Email Settings)
Configure SMTP for password reset emails:
- SMTP Host & Port
- Username & Password
- From Name

### System Configuration (Settings → Configuration)
- Total periods per day
- Max proxies per day (per teacher)
- Max proxies per week (per teacher)

### Schedule Configuration (Settings → Schedule Config)
Define blocked periods (holidays, after school hours) globally or per-class.

## 📊 Database Schema

The database schema is available in `sql/schema.sql`. Key tables include:

- `teachers` - Teacher profiles
- `teacher_attendance` - Daily attendance records
- `timetable` - Weekly timetable entries
- `proxy_assignments` - Proxy allocation records
- `classes` - Class/division definitions
- `subjects` - Subject catalog
- `sections` - Department/section definitions
- `blocked_periods` - Weekly blocked period configuration
- `daily_overrides` - Day-specific overrides (duties, trips)

## 🛠️ Development

### Running Scripts
Utility scripts are located in `scripts/`:
- `auto_allocate_proxies.php` - AJAX endpoint for auto-allocation
- `import_all_timetables.php` - Bulk timetable import
- `push_attendance_to_erp.php` - ERP sync endpoint
- `update_attendance_ajax.php` - AJAX attendance update

### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable/function names
- Add comments for complex logic
- Keep functions focused and single-purpose

## 🐛 Troubleshooting

### Database Locked Error
```bash
# Fix permissions
chmod 644 proxy_teacher.db
chmod 755 .
```

### File Upload Issues
```bash
# Ensure upload directory exists and is writable
mkdir -p assets/uploads
chmod 755 assets/uploads
```

### Performance Issues
- Enable bulk mode in proxy allocation (already enabled)
- Use database indexes (already configured)
- Clear old attendance data periodically

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Support

For support or feature requests, contact your system administrator.

---

**Version:** 2.0  
**Last Updated:** January 2026
