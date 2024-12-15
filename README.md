# COLLECT & CONNECT

A secure feedback collection and management system. This web application allows for easy submission of feedback entries and provides a secure admin interface for managing and viewing the collected data.

## 🚀 Features

- **Feedback Submission**
  - Simple and intuitive input form
  - Category selection (Auswahl)
  - Text input for detailed feedback
  - Automatic timestamp recording
![input](https://raw.githubusercontent.com/kssmagister/collectandconnect/main/img/login.png)
- **Admin Panel**
  - Secure login system
    ![input](https://raw.githubusercontent.com/kssmagister/collectandconnect/main/img/input.png)
  - View all entries in a tabular format
  - Download data in multiple formats:
    - CSV export for complete dataset
    - Text export for feedback entries
  - Database management tools
  - Real-time data refresh
![input](https://raw.githubusercontent.com/kssmagister/collectandconnect/main/img/admin.png)
- **Public View Page**
  - Clean, card-based layout
  - Easy-to-read format
  - Automatic refresh every 5 minutes
  - Mobile-responsive design
![input](https://raw.githubusercontent.com/kssmagister/collectandconnect/main/img/view.png)
## 📋 Prerequisites

- PHP 7.0 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- SSL certificate (recommended for production)

## 🔧 Installation

1. Clone the repository:
```bash
git clone https://github.com/kssmagister/collectandconnect.git
```

2. Copy `.env.example` to `.env` and configure your database settings:
```bash
cp .env.example .env
```

3. Update the `.env` file with your credentials:
```env
DB_HOST=your_database_host
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_database_password

ADMIN_USERNAME=your_admin_username
ADMIN_PASSWORD=your_admin_password
```

4. Set up the database schema:
```sql
CREATE TABLE memoranda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auswahl VARCHAR(255) NOT NULL,
    texteingabe TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

5. Ensure proper file permissions:
```bash
chmod 644 .env
chmod 755 *.php
chmod 755 *.html
```

## 🔒 Security Features

- Environment-based configuration
- Session-based authentication
- SQL injection prevention
- XSS protection
- CSRF protection
- Secure password handling

## 📁 Project Structure

```
feedback-forms/
├── .env                 # Environment configuration
├── .env.example        # Example environment file
├── .gitignore         # Git ignore rules
├── admin.html         # Admin interface
├── view.html          # Public view interface
├── input.html         # Feedback submission form
├── login.html         # Admin login page
├── config.php         # Configuration loader
├── submit.php         # Form submission handler
├── login.php          # Authentication handler
├── getEntries.php     # Data retrieval script
├── download.php       # CSV export handler
├── download_text.php  # Text export handler
├── clearDB.php        # Database cleanup handler
└── README.md          # Project documentation
```

## 🌐 Page Overview

- `input.html`: Public feedback submission form
- `login.html`: Admin authentication page
- `admin.html`: Secure admin dashboard
- `view.html`: Public feedback display page

## 💻 Usage

1. **Submitting Feedback**
   - Navigate to `input.html`
   - Select a category from the dropdown
   - Enter your feedback text
   - Submit the form

2. **Accessing Admin Panel**
   - Go to `login.html`
   - Enter admin credentials
   - Access the full admin dashboard

3. **Viewing Feedback**
   - Visit `view.html` for a clean, public view
   - No authentication required
   - Automatically refreshes every 5 minutes

## 🔐 Admin Features

- View all feedback entries
- Download data in CSV format
- Download text entries separately
- Clear database when needed
- Navigate between admin and view interfaces

## 🛠️ Maintenance

- Regularly backup your database
- Update admin credentials periodically
- Monitor disk space for log files
- Check error logs for issues

## 🔄 Updates

The system is designed to be easily maintainable and updatable. Follow these steps for updates:

1. Pull the latest changes
2. Check `.env.example` for new variables
3. Update your `.env` file if needed
4. Clear browser cache

## 📝 License

This project is licensed under the GNU General Public License v3.0. You are free to use, modify, and distribute this software under the terms of the license. However, any derivative works must also be licensed under the GPL.

For more details, refer to the full license text included in the LICENSE file or visit [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).
