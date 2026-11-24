Production email (Hostinger)
===========================

The application uses smtp.hostinger.com:465 with implicit TLS and authenticated SMTP.
The sender and SMTP username are contactus@metroasiaarena.com.

Deployment
----------
1. Upload includes/mailer.php, config/mail.php, config/mail.example.php,
   admin/test-email.php, the vendor/phpmailer directory, and the updated root .htaccess.
   Include the updated admin/site-config.php to show the Test Email link.
2. In the production hosting file manager, copy config/mail.example.php to
   config/mail.local.php. Set `password` to the password for the mailbox
   contactus@metroasiaarena.com (not the hosting account password).
   If it contains a single quote or backslash, escape it in the PHP single-quoted string.
   Keep this file private; it is excluded from Git and blocked by the root .htaccess.
   Set permissions to 600 where supported by your hosting PHP configuration.
3. Alternatively set SMTP_USERNAME and SMTP_PASSWORD in the PHP server environment.
   Environment values override the local file. A .env file is not automatically loaded.
4. Confirm PHP 8.0+ with OpenSSL is enabled and outgoing port 465 is permitted.
   Upload the vendor directory; production does not need Composer or changes to php.ini mail settings.
5. Sign in with an admin account allowed to access Site Config. Open
   Admin > Site Config > Test Email, enter a recipient, and click Send test email.
   Confirm receipt in the inbox or spam folder. SMTP acceptance alone is not proof of delivery.

If credentials are rejected, verify that the mailbox can sign into Hostinger webmail.
If the server cannot connect, check outbound port 465 and the PHP CA certificate setup;
do not disable certificate validation. If accepted mail goes to spam or does not arrive,
check the domain's SPF/DKIM records and mailbox delivery status in Hostinger.

No credentials or SMTP debug transcripts are displayed or logged by the application.
The test page uses the new shared sender. Other application features are not automatically
given email notifications by this change.

References:
- https://www.hostinger.com/support/4305847-set-up-hostinger-email-on-your-applications-and-devices/
- https://github.com/PHPMailer/PHPMailer/tree/v7.2.0
