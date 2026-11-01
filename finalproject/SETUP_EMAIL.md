# Email Notification Setup

These files are based on the latest uploaded project files:
- `process_lost_report(1).php`
- `process_found_report(1).php`
- `reporter_dashboard(2).php`

## 1. Install PHPMailer

Open Command Prompt/Terminal in the folder containing these PHP files and run:

```bash
composer require phpmailer/phpmailer
```

This creates the `vendor` folder.

## 2. Configure SMTP

Open `email_config.php` and replace the placeholders:

```php
'username'   => 'YOUR_GMAIL@gmail.com',
'password'   => 'YOUR_GMAIL_APP_PASSWORD',
'from_email' => 'YOUR_GMAIL@gmail.com',
```

For Gmail, use a **Google App Password**, not your normal Gmail password.

## 3. Put the files in your XAMPP project folder

Replace your existing:
- `process_lost_report.php`
- `process_found_report.php`
- `reporter_dashboard.php`

Add:
- `email_helper.php`
- `email_config.php`
- `vendor/` (created by Composer)

## 4. What happens

Lost item submission:

`Report Lost Item → Save report → Find reporter email → Send confirmation → Dashboard`

Found item submission:

`Report Found Item → Save item → Find reporter email → Send confirmation → Dashboard`

If the email cannot be sent, the report is still saved. The email failure is written to the PHP error log so the user's report is not lost.
