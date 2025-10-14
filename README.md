# Elonara Social

A modern community and event management platform that brings people together through shared experiences and conversations.

## Overview

Elonara Social is a PHP-based web application designed to help communities organize events, facilitate discussions, and build meaningful connections. Whether you're hosting dinner parties, organizing community gatherings, or managing group activities, Elonara Social provides the tools you need to bring people together.

## Features

### 🎉 Event Management
- Create and manage public or private events
- RSVP tracking with guest limits
- Venue information and event details
- Host tools for managing attendees

### 💬 Community Discussions
- Organized conversations around events and topics
- Community-based discussion threads
- Reply and engagement tracking
- Privacy controls for sensitive discussions

### 👥 Community Building
- Create and join communities of interest
- Member roles and permissions
- Public and private community options
- Community-specific events and discussions

### 🔒 Privacy & Security
- Circle-based privacy controls (Inner, Trusted, Extended)
- Secure authentication and session management
- CSRF protection and input validation
- Role-based access control

## Technology Stack

- **Backend**: PHP 8.1+ with custom MVC framework
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Web Server**: Apache with mod_rewrite
- **Security**: Built-in CSRF protection, input sanitization, secure sessions

## Quick Start

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ljholiday/social_elonara.git
   cd social_elonara
   ```

2. **Create database**
   ```bash
   mysql -u root -p -e "CREATE DATABASE social_elonara;"
   ```

3. **Configure database**
   ```bash
   cp config/database.php.sample config/database.php
   # Edit config/database.php with your database credentials
   ```

4. **Run installation**
   ```bash
   chmod +x install.sh
   ./install.sh
   ```

5. **Configure web server** (see [INSTALL.md](INSTALL.md) for details)

### Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB equivalent
- Apache web server with mod_rewrite
- Command line access for installation

## Documentation

- **[Installation Guide](INSTALL.md)** - Complete setup instructions
- **[Development Guidelines](dev/)** - Coding standards and best practices

## Project Structure

```
social_elonara/
├── assets/           # CSS, JavaScript, and static files
├── config/           # Database and application configuration
├── includes/         # Core PHP classes and application logic
├── templates/        # HTML templates and views
├── uploads/          # User-uploaded files
├── dev/             # Development files and standards (gitignored)
├── install.sh       # Automated installation script
└── migrate.php      # Database migration runner
```

## Development

### Coding Standards

This project follows strict coding standards defined in the `dev/` directory:

- **Language Separation**: PHP for logic, HTML for structure, CSS for presentation, JS for behavior
- **Security First**: All input validated, output escaped, CSRF protection
- **Modern PHP**: PHP 8.1+ features, strict typing, PSR-12 compliance
- **Semantic CSS**: `.vt-` prefixed classes, BEM methodology where appropriate

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow the coding standards in `dev/`
4. Make your changes
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

The project includes development standards and guidelines in the `dev/` directory:
- `code.xml` - General code organization principles
- `php.xml` - PHP-specific standards and security practices
- `css.xml` - CSS naming conventions and structure
- `database.xml` - Database design and query standards
- `security.xml` - Application security guidelines

## Security

Elonara Social takes security seriously:

- **Input Validation**: All user input is validated and sanitized
- **Output Escaping**: All dynamic content is properly escaped
- **CSRF Protection**: Forms include CSRF tokens
- **Secure Sessions**: HTTP-only, secure session cookies
- **SQL Injection Prevention**: PDO prepared statements
- **Password Security**: bcrypt hashing with secure defaults

## License

This project is open source. See the LICENSE file for details.

## Support

- **Issues**: Report bugs and request features via GitHub Issues
- **Documentation**: Check [INSTALL.md](INSTALL.md) for setup help
- **Security**: Report security issues privately to the maintainers

## Acknowledgments

Elonara Social was built to foster real-world connections and community building in an increasingly digital world. It draws inspiration from the simple pleasure of gathering around a table to share food, stories, and experiences.

---

**Ready to bring your community together?** [Get started with the installation guide](INSTALL.md)