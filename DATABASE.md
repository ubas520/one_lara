# Database setup

`onelara_database.sql` was exported from the local MySQL/MariaDB `onelara` database and includes:

- All 28 table definitions, indexes, and foreign keys, checked against the live database on October 22, 2026.
- The four application roles and five document types with their configured fees.

The file contains no user accounts, passwords, resident records, uploaded proofs, or transaction history. It is a setup file, not a full backup.

## Import with XAMPP

1. Start MySQL in the XAMPP Control Panel and open phpMyAdmin.
2. For a fresh installation, import `onelara_database.sql`. It creates and selects the `onelara` database, creates the tables, and adds the starting data in one import.
3. Confirm the connection settings in `config.php` match your MySQL installation.

Use this file for an empty database. It is not an upgrade script; importing into an existing installation can produce duplicate-table or duplicate-key errors. The export does not include DROP TABLE statements.

No default administrator login is included. Existing installations keep their accounts; a fresh installation needs its staff/admin accounts provisioned separately. Facilities and medicine inventory also need to be configured for a fresh installation.

The schema already includes `resident_registrations`, `residency_proofs`, `login_email_otps`, and `registration_email_verifications`, so their setup scripts in `bin/` are not needed after a fresh import.

For an existing installation that lacks the email verification tables, run `php bin/setup_registration_email.php`. It creates both tables without changing existing accounts. Fresh imports already include them. See `EMAIL_OTP.md` for outgoing mail setup.
