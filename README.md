# KaziMates PHP + MySQL

KaziMates is a PHP/MySQL community app for job seekers. It includes registration and login, career profiles, member discovery, connection requests, and private messaging for accepted connections.

## Deploy to Railway

The repository includes a Dockerfile. Railway detects a root-level file named `Dockerfile` and uses it to build the service. The container runs Nginx with PHP 8.3-FPM and the PDO MySQL and mbstring extensions. Its startup script configures Nginx to listen on Railway's injected `PORT`; Apache is not used.

1. In Railway, create a project and add a **MySQL** service.
2. Add a service from the `Frontliner936/kazimates` GitHub repository and deploy the `main` branch. Railway should detect the Dockerfile automatically.
3. In the PHP app service's **Variables**, add references to the MySQL service. Replace `MySQL` below with the exact Railway service name if yours differs:

   ```text
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```

   Railway resolves these references using the database service's variables. Do not paste database passwords into GitHub or source files. The app can also read the `MYSQL_URL` or `DATABASE_URL` connection string if supplied.

4. Import `database.sql` into Railway's MySQL database. The SQL creates tables inside the currently selected database; it does not create or switch databases. To import from your computer, temporarily enable a MySQL TCP Proxy in the database service's **Settings → Networking**. Use the generated public host and port with a MySQL client, and enter the password only at its prompt:

   ```sh
   mysql --host=<MYSQL_PUBLIC_HOST> --port=<MYSQL_PUBLIC_PORT> --user=<MYSQLUSER> --password <MYSQLDATABASE> < database.sql
   ```

   Use values shown by Railway for `MYSQL_PUBLIC_URL`, `MYSQLUSER`, and `MYSQLDATABASE`. Turn off the TCP Proxy after the import if you do not need outside database access. Confirm the app service's `DB_*` variables point to the same MySQL service.
5. After the app deploys, open the app service's **Settings → Networking** and generate a Railway domain. Railway provides HTTPS for its public domain.
6. Open the generated domain and test registration, login, profile editing, member search, connection requests, and messaging with two test accounts.
7. Enable a database backup plan before using the app with real user data. Railway's MySQL is a single-node service by default; review Railway's backup and high-availability options for production use.

### Enable chat file attachments on an existing database

Before deploying the attachment update, connect HeidiSQL to the KaziMates MySQL service, select the app's database, and run `migration_attachments.sql`. This additive migration creates the attachment table without changing existing accounts or messages. New installs already get the table from `database.sql`. Chat accepts one JPG, PNG, GIF, WebP, PDF, or TXT attachment per message, up to 5 MB; files are stored in MySQL and downloads are limited to the connected conversation participants.

### Enable password reset and profile pictures on an existing database

Run `migration_reset_avatar.sql` against the KaziMates database. It adds the `password_resets` and `profile_pictures` tables, is additive, and is safe to run more than once. New installs already get both tables from `database.sql`. Until it is run, the profile picture box is hidden and password reset emails cannot be issued.

**Password reset email.** The "Forgot your password?" link on the login page emails a single-use link that expires after 60 minutes (at most 3 requests per account and 10 per IP per hour). Choose one way to send email and add the variables to the app service:

```text
APP_URL=https://your-site.up.railway.app     # used to build the reset link
MAIL_FROM=KaziMates <no-reply@yourdomain.com>

# Option A (recommended, works on every Railway plan): Resend HTTPS API
RESEND_API_KEY=re_xxxxxxxx

# Option B: any SMTP provider
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your-smtp-user
SMTP_PASSWORD=your-smtp-password
SMTP_SECURE=tls        # tls (port 587), ssl (port 465) or none
```

Railway blocks outbound SMTP on its Free, Trial and Hobby plans, so use Option A there. With Resend, verify a sending domain so mail can reach any member; without one it can only deliver to your own address. If neither option is configured, the forgot-password page tells members that reset email is not set up yet. Errors are written to the deploy logs as `KaziMates mail ...`.

**Profile pictures.** Members can optionally upload a JPG, PNG, GIF or WebP (up to 5 MB) on the Profile page. It is cropped to a square, resized to 400x400, re-encoded as JPEG (this also removes photo metadata such as GPS location) and stored in MySQL, like chat attachments. Members without a picture show their initials. Pictures are only served to signed-in users, and only for members who are discoverable, connected to the viewer, or the viewer themselves. The Dockerfile now installs the PHP `gd` and `exif` extensions for this.

### Enable the admin area on an existing database

Run `migration_admin.sql` against the KaziMates database before using the admin area. Then promote an existing account by replacing the email below with the exact email used to register that account:

```sql
UPDATE users SET role='admin' WHERE email='your-admin-email@example.com';
```

The promoted person signs in through the normal login page and then sees the **Admin** link. The dashboard shows basic counts, lets admins suspend or restore member accounts, and review or dismiss reports. Role assignment is kept out of the website UI. Admin changes are recorded in `admin_audit`; use an account with a strong unique password and promote only trusted people.

If deployment fails, check the app service's build and deploy logs. Common causes are missing variable references, importing the SQL schema into a different database, or not adding a public domain.

## Local setup (XAMPP)

1. Copy the repository into `C:\xampp\htdocs\kazimates` and start Apache and MySQL.
2. Create a database named `kazimates` using phpMyAdmin, then select it and import `database.sql`.
3. Copy `config.example.php` to `config.php` and set your local database credentials. Never commit a real `config.php`.
4. Open `http://localhost/kazimates/` and register an account.

## Requirements and security notes

The app requires PHP 8.1+ with PDO MySQL and mbstring and MySQL 8+ or compatible MariaDB. Railway's Dockerfile uses PHP 8.3-FPM and Nginx. Configuration is read from `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` (or Railway MySQL variables), with `config.php` as a fallback for conventional PHP hosts.

Passwords are stored using PHP password hashing. Database operations use PDO prepared statements, forms use CSRF tokens, and rendered values are escaped. Registration/profile choices and message lengths are validated server-side. A declined connection can be retried by its original sender after the seven-day period from the decline. Chat is page-refresh based and shows the latest 300 messages. Chat files have a 5 MB limit and an image/PDF/plain-text allowlist.

This remains a starter app, not a fully production-hardened public service. Before a broad launch, add email verification, password reset, member blocking, account deletion, a privacy policy, and a tested backup/restore process. Member location is user-selected region only; the app does not collect GPS or expose home addresses.
