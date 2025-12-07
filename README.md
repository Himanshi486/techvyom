# TechVyom Alumni Management System

A comprehensive PHP-based alumni management system with Google Sheets integration, featuring an interactive map, placement tracking, higher education tracking, and admin dashboard.

## 🚀 Features

### Public Features
- **Alumni Network**: Interactive map showing alumni locations (Leaflet.js)
- **Placements**: View alumni who are currently working
- **Higher Studies**: View alumni pursuing higher education
- **Responsive Design**: Mobile-friendly interface

### Admin Features
- **Google Sheets Integration**: Direct integration with Google Sheets for form submissions
- **Pending Verification**: Review and approve/reject alumni submissions
- **Verified Alumni Management**: View, edit, and manage approved alumni
- **Database Management**: Four-table structure for comprehensive data storage
- **Unapprove Functionality**: Move verified entries back to pending

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)
- Google Cloud Platform account (for Google Sheets API)

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/techvyom.git
cd techvyom
```

### 2. Install Dependencies

```bash
composer install
```

This will install the Google API PHP client library and other dependencies.

### 3. Database Setup

1. Create a MySQL database named `techvyom`
2. Import the database schema:

```bash
mysql -u root -p techvyom < techvyom.sql
```

Or import `techvyom.sql` using phpMyAdmin.

### 4. Configure Database Connection

1. Copy the example configuration file:
```bash
cp connect.php.example connect.php
```

2. Edit `connect.php` with your database credentials:
```php
$host = "localhost";
$user = "your_username";
$pass = "your_password";
$dbname = "techvyom";
```

### 5. Google Sheets API Setup

1. Follow the detailed instructions in [GOOGLE_SHEETS_SETUP.md](GOOGLE_SHEETS_SETUP.md)
2. Create a service account in Google Cloud Console
3. Download the JSON credentials file
4. Place it in `credentials/alumni-service.json`
5. Share your Google Sheet with the service account email

### 6. Web Server Configuration

Point your web server document root to the project directory:

**For XAMPP:**
```
/Applications/XAMPP/xamppfiles/htdocs/techvyom
```

**Access the site:**
```
http://localhost/techvyom
```

## 📁 Project Structure

```
techvyom/
├── admin/                      # Admin panel files
│   ├── approve.php            # Approve Google Sheets entries
│   ├── reject_sheets_entry.php # Reject entries
│   ├── unapprove_sheets_entry.php # Move verified to pending
│   ├── view_sheets_entry.php  # View Google Sheets entry
│   ├── edit_sheets_entry.php  # Edit Google Sheets entry
│   ├── view_verified.php      # View verified alumni (DB)
│   ├── edit_verified.php      # Edit verified alumni (DB)
│   ├── sheets_helper.php      # Google Sheets helper functions
│   └── sync_status.php        # Sync database status
├── credentials/                # Credentials folder (protected)
│   ├── .htaccess              # Security protection
│   ├── README.md              # Setup instructions
│   └── alumni-service.json    # Google service account (not in repo)
├── vendor/                     # Composer dependencies
├── about-us.php               # About page
├── alumni-network.php         # Interactive alumni map
├── dashboard.php              # Admin dashboard
├── format_helpers.php         # Formatting helper functions
├── geocode.php                # Geocoding proxy for map
├── higher-studies.php         # Higher education page
├── index.php                  # Homepage
├── placements.php             # Placements page
├── connect.php.example        # Database config template
├── techvyom.sql               # Database schema
└── README.md                  # This file
```

## 🗄️ Database Structure

The system uses four main tables:

1. **alumni_basic**: Basic alumni information
2. **alumni_education**: Higher education details
3. **alumni_employment**: Employment information
4. **alumni_extras**: Additional information (exams, achievements, etc.)

See `techvyom.sql` for the complete schema.

## 🔐 Security Features

- Admin authentication required for all admin pages
- Protected credentials folder (`.htaccess`)
- SQL injection protection via prepared statements
- Input validation and sanitization
- Sensitive files excluded from version control (`.gitignore`)

## 📝 Usage

### Admin Dashboard

1. Log in at `http://localhost/techvyom/login.php`
2. View pending alumni submissions from Google Sheets
3. Approve/reject entries
4. Manage verified alumni

### Google Sheets Integration

- Form submissions automatically appear in the pending queue
- Admin can approve entries to insert into database
- Status column tracks approval state

### Public Pages

- **Home**: `index.php`
- **Placements**: `placements.php`
- **Higher Studies**: `higher-studies.php`
- **Alumni Network**: `alumni-network.php` (with interactive map)

## 🔧 Configuration

### Google Sheets Configuration

Edit these files to change spreadsheet settings:
- `admin/approve.php`
- `admin/view_sheets_entry.php`
- `admin/edit_sheets_entry.php`
- `dashboard.php`

Look for these variables:
```php
$spreadsheetId = 'YOUR_SPREADSHEET_ID';
$sheetName = 'Form responses 1';
```

## 🌐 Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Libraries**:
  - Google API PHP Client
  - Leaflet.js (interactive maps)
  - Font Awesome (icons)

## 📄 License

This project is proprietary software. All rights reserved.

## 👥 Contributing

This is a private project. Please contact the project maintainers for contribution guidelines.

## 🐛 Troubleshooting

### Common Issues

1. **Composer dependencies not installed**
   ```bash
   composer install
   ```

2. **Database connection failed**
   - Check `connect.php` credentials
   - Verify MySQL is running
   - Check database exists

3. **Google Sheets API errors**
   - Verify credentials file exists
   - Check service account has sheet access
   - Ensure API is enabled in Google Cloud Console

4. **Map not showing locations**
   - Check `geocode.php` is accessible
   - Verify OpenStreetMap geocoding proxy is working

See [GOOGLE_SHEETS_SETUP.md](GOOGLE_SHEETS_SETUP.md) and [QUICK_START.md](QUICK_START.md) for more detailed troubleshooting.

## 📞 Support

For issues or questions:
1. Check the documentation files
2. Review error logs
3. Contact the development team

## 🔄 Version History

- **v1.0.0**: Initial release with Google Sheets integration, admin dashboard, and public pages

---

**Note**: Make sure to never commit sensitive files like `connect.php` or `credentials/alumni-service.json` to version control. Use the provided `.gitignore` file.

