# E-Library Digital Library System

A web-based digital library management system built with PHP and MySQL. This system allows users to browse, search, and download educational resources, while administrators can manage resources, users, and categories.

## Features

- **User Management**: Register, login, and manage user profiles
- **Resource Management**: Browse, search, and download resources
- **Categories**: Organize resources by categories
- **Admin Dashboard**: Comprehensive admin panel for system management
- **File Management**: Secure file upload and download functionality
- **PDF Viewer**: Built-in PDF viewer for online resource viewing

## Requirements

- PHP 7.4+
- MySQL 5.7+
- Apache with mod_rewrite (optional)
- Bootstrap 5.x
- PDF.js for PDF viewing

## Installation

1. Clone the repository
2. Import the database schema from `elibrary.sql`
3. Configure database connection in `includes/config.php`
4. Ensure `uploads/` directory has write permissions
5. Access the application at `http://localhost/htdocs`

## Directory Structure

```
├── admin/              # Admin dashboard and management
├── assets/             # CSS, JS, fonts, and third-party libraries
├── data/               # Data files
├── includes/           # Core PHP includes (auth, config, functions)
├── logs/               # Application logs
├── sql/                # Database migration files
├── uploads/            # User uploads (avatars, covers, resources)
├── index.php           # Homepage
├── login.php           # Login page
├── logout.php          # Logout handler
├── download.php        # File download handler
└── viewer.php          # Resource viewer
```

## Database

The application uses MySQL database defined in `elibrary.sql`. Key tables include:
- users
- categories
- resources
- resource_categories

## License

This project is open source and available under the MIT License.

## Author

Digital Library Team
