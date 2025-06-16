# IndoWapBuilder - Modernized

IndoWapBuilder is a mobile-first WAP site builder designed to allow users to easily create and manage their own websites using a powerful templating system. This version has been significantly modernized for improved security, performance, and maintainability.

Originally created by Achunk JealousMan (http://facebook.com/achunks). Modernized and refactored by the current development team.

## Features

*   **User Account Management:** Secure registration, login, profile editing, and password management.
*   **Site Creation & Management:** Users can create and manage multiple websites from a central panel.
*   **File Manager:** A web-based file manager for users to upload, edit, and manage their site files.
*   **Twig Templating Engine:** User sites are rendered using the modern and secure Twig templating engine (v3.x).
*   **Module System:** Extendable architecture allowing for custom modules to add functionality (e.g., Blog, Chat - specific modules may vary).
*   **Admin Panel:** For site-wide settings and administration.
*   **Modern Technology Stack:**
    *   PHP 8.x compatibility.
    *   Bootstrap 5.3.x for responsive frontend design.
    *   jQuery 3.7.x.
    *   InnoDB database engine recommended for data integrity.
*   **Security Enhancements:**
    *   Uses `password_hash()` and `password_verify()` for secure password storage.
    *   Prepared statements (PDO) to prevent SQL injection.
    *   Autoescaping enabled in Twig for user-generated site templates by default.
*   **Localization Ready:** Core application is now in English, with a centralized language file system (`iwbx-includes/languages/en.php`) to facilitate future translations.

## Requirements

*   **PHP:** Version 8.0 or newer.
*   **Database:** MySQL or MariaDB. InnoDB storage engine is highly recommended.
*   **Web Server:** Apache, Nginx, or any other web server that supports PHP.
*   **Composer:** Required for managing PHP dependencies (specifically Twig).
*   **Wildcard Subdomains (Optional but Recommended for Full Functionality):** For users to create sites like `username.yourdomain.com`, your server needs to be configured to handle wildcard subdomains, pointing them to the IndoWapBuilder installation directory.
*   **PHP Extensions:**
    *   `pdo_mysql`
    *   `mbstring`
    *   `json`
    *   `intl` (often needed by Twig for advanced localization features, though not strictly enforced by Twig core for basic usage)

## Installation

1.  **Clone or Download:**
    *   Clone this repository to your web server or download the source code.

2.  **Install Dependencies (Composer):**
    *   Navigate to the project root directory using your terminal.
    *   If you don't have Composer installed, download it from [getcomposer.org](https://getcomposer.org/).
    *   Run the following command to install Twig (and any other future dependencies if a `composer.json` file is provided with the project):
        ```bash
        composer require twig/twig:~3.0
        ```
    *   This will create a `vendor` directory containing Twig and an `autoload.php` file. Ensure your PHP environment can access classes from this `vendor/autoload.php`.

3.  **Database Setup:**
    *   Create a new MySQL/MariaDB database for IndoWapBuilder (e.g., `indowapbuilder_db`).
    *   Create a database user (e.g., `iwb_user`) and grant it appropriate privileges (SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE, DROP) on the newly created database.

4.  **Configuration (`db.ini`):**
    *   The installation script (`install.php`) will attempt to create the `iwbx-includes/db.ini` file for you. For this to work, the `iwbx-includes/` directory must be writable by the web server during the installation process.
    *   If automatic creation fails, you will need to manually create `iwbx-includes/db.ini` with the following content after a successful installation attempt (the installer will typically show you the values or guide you):
        ```ini
        host = "your_database_host"      ; e.g., localhost
        database = "your_database_name"
        user = "your_database_user"
        password = "your_database_password"
        ```

5.  **Run Installation Script:**
    *   Open your web browser and navigate to `install.php` in your IndoWapBuilder directory (e.g., `http://yourdomain.com/install.php`).
    *   Follow the on-screen instructions:
        *   **Step 1: Database Setup:** Enter your database host, name, username, and password.
        *   **Step 2: Site & Admin Setup:** Configure the main site URL for this IndoWapBuilder installation and create the primary administrator account.
    *   The script will import the database schema from `indowapbuilder.sql` and save your configuration.

6.  **IMPORTANT: Delete `install.php`**
    *   **After successfully completing the installation, you MUST delete the `install.php` file from your server immediately.** Leaving it accessible is a major security risk.

7.  **Web Server Configuration (for Pretty URLs and Wildcard Subdomains):**
    *   For user-friendly URLs (e.g., `http://yourdomain.com/login` instead of `http://yourdomain.com/index.php/site/login`), you need to configure URL rewriting on your web server.
    *   **Apache:** Ensure `mod_rewrite` is enabled. Create or update your `.htaccess` file in the IndoWapBuilder root directory with rules like:
        ```apacheconf
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php/$1 [L,QSA]
        ```
    *   **Nginx:** Configuration will vary. You'll typically use a `try_files` directive in your server block:
        ```nginx
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }
        ```
    *   **Wildcard Subdomains:** If you plan to use the `username.yourdomain.com` feature:
        1.  Configure your DNS to point `*.yourdomain.com` (or your chosen domain) to your server's IP address.
        2.  Configure your web server's virtual host to accept these wildcard subdomains and route them to the IndoWapBuilder installation directory. The application logic in `index.php` is designed to handle requests based on the `Host` header to serve the correct user site.
        3.  Ensure the "Available Domains for Sites" setting in the Admin Panel is configured correctly.

## Basic Usage

*   **Admin Panel:** Access the admin panel via `http://yourdomain.com/admin` (assuming pretty URLs are configured). Log in with the admin credentials created during installation. Here you can manage site-wide settings, such as default theme, domain options, user site limits, etc.
*   **User Panel:** Users can register and log in via the main site (e.g., `http://yourdomain.com/site/login`). After logging in, they access their panel via `http://yourdomain.com/panel`.
*   **Creating a Site:** From their user panel, users can create new websites. These sites will typically be accessible via subdomains (e.g., `usersite.yourmaindomain.com`) if wildcard DNS and server configuration are correctly set up and the domain is listed in the admin settings.
*   **Site Templates:** User sites are built using Twig templates. Users can manage their site files (HTML, CSS, JS, Twig templates, images) via the File Manager in their user panel for the selected site.
    *   All output in Twig templates is HTML-escaped by default for security (e.g., `{{ variable }}`).
    *   If raw HTML output is needed (e.g., from a module that generates its own HTML, or for user-input HTML content), use the `|raw` filter with caution: `{{ variable|raw }}`.

## Modules

IndoWapBuilder supports modules to extend its functionality. Modules are located in the `iwbx-includes/modules/` directory. Each module can provide its own admin panel interface, which can be accessed from the user's site dashboard.

## Development

*   **Twig Cache:** For development, Twig cache is disabled by default in the provided setup. For a production environment, it's highly recommended to enable and configure the Twig cache for significantly better performance. This can be done by setting a writable cache path in `iwbx-includes/components/Base.php` (for the main application's Twig instance) and in `index.php` (for the user sites' Twig instance).
    ```php
    // Example for Base.php or index.php Twig Environment setup:
    'cache' => ROOTPATH . 'iwbx-cache/twig', // Ensure this directory exists and is writable
    ```
*   **Debugging:** Twig debug extension is enabled by default (if Twig's debug mode is on). This allows use of the `{{ dump() }}` function in Twig templates. Disable this in production.

## License

IndoWapBuilder (Modernized) is released under the MIT License. (It's assumed to be MIT unless a `LICENSE` file in the repository specifies otherwise).
This means you are free to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the software, provided the original copyright and permission notice are included.
