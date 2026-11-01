LATEST EMAIL FEATURE - RESORT LOST & FOUND

Files included:
- process_lost_report.php
- process_found_report.php
- reporter_dashboard.php
- email_helper.php
- email_config.php
- email_test.php
- composer.json
- SETUP_EMAIL.md

SETUP:
1. Copy all PHP/config files into the same XAMPP project folder.
2. Open Command Prompt in that project folder.
3. Run: composer install
   (or: composer require phpmailer/phpmailer)
4. Edit email_config.php with your Gmail SMTP account and Google App Password.
5. Log in as Reporter and open email_test.php first.
6. If the test succeeds, submit a Lost Item or Found Item. The dashboard will show whether the confirmation email was sent.

IMPORTANT:
- Do not use your normal Gmail password. Use a Google App Password.
- Do not commit or upload email_config.php containing real credentials.
- Database/schema changes are NOT required.
