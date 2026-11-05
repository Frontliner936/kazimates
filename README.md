# KaziMates PHP + MySQL

KaziMates is a PHP/MySQL community app for job seekers. It includes account registration and login, career profiles, member search, connection requests, and private messaging for accepted connections.

## Requirements

- PHP 8.1 or newer
- MySQL 8+ or compatible MariaDB
- PHP extensions: PDO MySQL and mbstring
- Apache with `mod_rewrite` is not required by the current app; the PHP files can be served directly

## Local setup (XAMPP)

1. Copy this repository into `C:\xampp\htdocs\kazimates`.
2. Start Apache and MySQL.
3. Import `database.sql` using phpMyAdmin.
4. Copy `config.example.php` to `config.php` and set the database host, database name, user, and password. Do not commit `config.php` or real credentials.
5. Open `http://localhost/kazimates/` and register an account.

If you already have a previous KaziMates database, back it up before applying `migration_v2.sql` as needed. Do not re-import `database.sql` into a live database because it creates fresh application tables.

## Deployment checklist

1. Choose a PHP host that supports PHP 8.1+, MySQL/MariaDB, PDO MySQL, and mbstring.
2. Create a dedicated database and a database user with access only to that database; import `database.sql` for a fresh installation.
3. Upload the application files, excluding `.git` and any local `config.php`, and configure the host's document root to the application directory.
4. Create `config.php` on the server from `config.example.php` and enter the production database credentials. Keep it outside version control and restrict its filesystem permissions.
5. Enable HTTPS. The session cookie is marked Secure when requests arrive over HTTPS.
6. Test registration, login, profile updates, discovery, a declined-request cooldown, accept/decline actions, and messaging with two test accounts before inviting users.
7. Set up database backups, error logging, and a privacy policy. This starter does not include email verification, password reset, abuse reporting/blocking, or account deletion; add these before a broad public launch.

## Security and behavior notes

- Passwords are stored with PHP's `password_hash()` and checked with `password_verify()`.
- Database operations use PDO prepared statements. Forms use CSRF tokens and HTML output is escaped.
- Registration/profile choices are validated against server-side allowed lists. Message length is enforced server-side.
- A declined connection request can be retried by its original sender after the seven-day cooldown, measured from the decline action. A declined recipient cannot immediately create a reverse request.
- Chat is page-refresh based, not real-time. The current chat view displays the latest 300 messages.
- Member search uses user-selected regions only; the app does not collect GPS or expose home addresses.

This project is a functional starter, not a fully production-hardened public service. Review hosting configuration and complete the deployment checklist before launch.
