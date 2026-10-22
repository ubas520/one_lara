# Registration email verification

New residents upload proof during registration, verify their email once with a six-digit code, then wait for admin approval of their proof. Email verification does not activate the account or sign the resident in. Admin approval is blocked until email verification is complete. After approval, sign-in uses username and password only. Admin-created and existing accounts do not receive a new registration verification requirement.

If delivery fails or the browser closes, the registration and uploaded proof remain saved. Enter the username and password on the sign-in page to resume email verification, then request a new code. Rejected registrations remain blocked. Verification is stored against the saved email address; a changed email cannot reuse the old verification.

## Setup

Email is sent directly through PHPMailer over authenticated, encrypted SMTP. XAMPP Sendmail, PHP `mail()`, and `sendmail_path` are no longer used. PHPMailer is installed and pinned using Composer (`composer.json` and `composer.lock`).

1. Install dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`. PHP 8.1+ and OpenSSL are required. On the current Windows machine, the equivalent command is `C:\xampp\php\php.exe -d extension=zip bin\composer.phar install --no-dev --prefer-dist --optimize-autoloader`.
2. Run `php bin/setup_registration_email.php` once for an existing database. Fresh installations must also run this command after importing the database. The migration does not mark old emails as verified or change existing account access.
3. Set these environment variables in your hosting provider's application settings. They must be visible to the PHP web process, not just your terminal:

| Variable | Gmail example |
| --- | --- |
| `ONELARA_SMTP_HOST` | `smtp.gmail.com` |
| `ONELARA_SMTP_PORT` | `587` |
| `ONELARA_SMTP_ENCRYPTION` | `tls` (STARTTLS); use `ssl` for implicit TLS on port 465 |
| `ONELARA_SMTP_USERNAME` | Your full sender Gmail address |
| `ONELARA_SMTP_PASSWORD` | Your Gmail App Password, without spaces |
| `ONELARA_MAIL_FROM` | The same sender Gmail address |
| `ONELARA_MAIL_FROM_NAME` | `OneLara` |

4. Run `php bin/test_email_delivery.php your-address@example.com` to test delivery. This sends a test code without modifying accounts or creating a login challenge. SMTP acceptance does not guarantee inbox delivery; check the inbox and spam folder.
5. Register a new test resident with proof, verify its email, then approve its proof through an administrator account. Confirm subsequent sign-ins reach the dashboard without another code.

For local development, copy `email.local.example.php` to `email.local.php` and fill in the values there. This PHP file is excluded from Git. Hosting environment variables override local values (including explicitly empty environment variables). The Windows helper `bin/configure_gmail.ps1` saves Gmail settings in this local file and tests SMTP; it no longer edits XAMPP. Close any old setup dialog before running the updated helper.

The app does not automatically load `.env` files. Configure actual environment variables through your host, PHP-FPM pool, Apache configuration, or container secret settings. Restart/reload the PHP service if your hosting environment requires that to apply changes. Never upload local credentials, `.env` files, or development setup files to a public directory.

On hosts without Composer, run Composer during your build and upload the generated `vendor/` directory along with the app. Hosts must allow outbound SMTP on the configured port and have a valid CA certificate store. Certificate verification stays enabled; fix the server CA setup instead of disabling verification. TLS and SMTP authentication are required. There is no unencrypted or PHP-mail fallback.

The recipient is the registering resident's saved email. The sender is the service mailbox used by OneLara to deliver codes. Both are needed. Without outgoing mail, new residents cannot finish email verification; existing accounts can still sign in.

Codes expire in 10 minutes, allow five verification attempts, and are single use. Resends replace the previous code, require 60 seconds between requests, and allow up to five sends per account per hour (including failed sends). Limits persist in the database across browsers. Verification sessions expire after 30 minutes and can be resumed using the password. Only hashes of the code and browser challenge are stored in the database. The existing `login_email_otps` table name is retained for compatibility; it now holds registration challenges.

Run automated checks with `php tests/registration_email_test.php`, `php tests/registration_review_test.php`, `php tests/smtp_mail_test.php`, `php tests/email_otp_test.php`, and `php tests/auth_session_test.php`. These do not send external email. SMTP tests inspect PHPMailer's message generation and encryption/authentication settings; OTP tests use an in-memory database and a fake sender.
