<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $config;
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer(): void
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->config['host'];
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $this->config['username'];
            $this->mailer->Password   = $this->config['password'];

            // Map secure to PHPMailer constants
            $secure = strtolower((string)($this->config['secure'] ?? ''));
            if ($secure === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = false;
            }

            $this->mailer->Port = (int)$this->config['port'];

            // Enable once if you need to see SMTP conversation in logs:
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;

            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name'] ?? '');
            if (!empty($this->config['reply_to'])) {
                $this->mailer->addReplyTo($this->config['reply_to'], $this->config['from_name'] ?? '');
            }

            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
        } catch (Throwable $e) {
            error_log('[MAIL] configureMailer error: ' . $e->getMessage());
        }
    }
    /**
     * Send verification code for customer registration
     */
    public function sendVerificationCode(string $toEmail, string $toName, string $code): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = 'Your RADS Tooling verification code';

            // ✅ Use your styled templates (blue theme, not the red reset style)
            $htmlBody = $this->getRegistrationEmailTemplate($toName, $code);
            $textBody = $this->getRegistrationEmailTextTemplate($toName, $code);

            $this->mailer->isHTML(true);
            $this->mailer->Body    = $htmlBody;
            $this->mailer->AltBody = $textBody;

            $ok = $this->mailer->send();
            if (!$ok) {
                error_log('[MAIL] send() returned false. ErrorInfo: ' . ($this->mailer->ErrorInfo ?? 'n/a'));
            }
            return $ok;
        } catch (Throwable $e) {
            error_log('[MAIL] Exception while sending to ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset code
     */
    public function sendPasswordResetCode(string $email, string $fullName, string $resetCode): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $fullName);

            $this->mailer->Subject = 'Password Reset Code - Rads Tooling';

            $htmlBody = $this->getPasswordResetEmailTemplate($fullName, $resetCode);
            $textBody = $this->getPasswordResetEmailTextTemplate($fullName, $resetCode);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody;

            $result = $this->mailer->send();

            if ($result) {
                error_log("Password reset email sent successfully to: {$email}");
            } else {
                error_log("Failed to send password reset email to: {$email}");
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error sending password reset email to {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get HTML template for registration verification email
     */
    private function getRegistrationEmailTemplate(string $fullName, string $verificationCode): string
    {
        // Fix HTML entities and improve formatting
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Your Account</title>
            <style>
                body { font-family: Poppins; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #007bff; }
                .content { margin-bottom: 30px; }
                .verification-code { background: #f8f9fa; border: 2px dashed #007bff; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; font-family: Poppins; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 14px; border-top: 1px solid #eee; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>RADS TOOLING</div>
                    <h2>Welcome to Rads Tooling!</h2>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                    
                    <p>Thank you for registering with Rads Tooling! To complete your account setup and start using our services, please verify your email address using the verification code below:</p>
                    
                    <div class='verification-code'>
                        <p style='margin: 0 0 10px 0; font-weight: bold;'>Your Verification Code:</p>
                        <div class='code'>{$verificationCode}</div>
                    </div>
                    
                    <div class='warning'>
                        <strong>Important:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>This code will expire in 10 minutes</li>
                            <li>Enter this code exactly as shown (6 digits)</li>
                            <li>Do not share this code with anyone</li>
                        </ul>
                    </div>
                    
                    <p>If you didn't create an account with Rads Tooling, please ignore this email.</p>
                    
                    <p>Need help? Contact our support team at <a href='mailto:moenpogi045@gmail.com'>moenpogi045@gmail.com</a></p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Rads Tooling. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Get text template for registration verification email
     */
    private function getRegistrationEmailTextTemplate(string $fullName, string $verificationCode): string
    {
        // Fix copyright symbol
        return "
RADS TOOLING - Account Verification

Hello {$fullName},

Thank you for registering with Rads Tooling! To complete your account setup, please verify your email address using the verification code below:

VERIFICATION CODE: {$verificationCode}

IMPORTANT:
- This code will expire in 10 minutes
- Enter this code exactly as shown (6 digits)
- Do not share this code with anyone

If you didn't create an account with Rads Tooling, please ignore this email.

Need help? Contact our support team at moenpogi045@gmail.com

(c) " . date('Y') . " Rads Tooling. All rights reserved.
This is an automated message, please do not reply to this email.";
    }

    /**
     * Get HTML template for password reset email
     */
    private function getPasswordResetEmailTemplate(string $fullName, string $resetCode): string
    {
        // Fix HTML entities and improve formatting
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset Code</title>
            <style>
                body { font-family: Poppins; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #dc3545; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #dc3545; }
                .content { margin-bottom: 30px; }
                .reset-code { background: #f8f9fa; border: 2px dashed #dc3545; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; color: #dc3545; letter-spacing: 5px; font-family: Poppins; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 14px; border-top: 1px solid #eee; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>RADS TOOLING</div>
                    <h2>Password Reset Request</h2>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                    
                    <p>We received a request to reset your password for your Rads Tooling account. Use the code below to reset your password:</p>
                    
                    <div class='reset-code'>
                        <p style='margin: 0 0 10px 0; font-weight: bold;'>Your Password Reset Code:</p>
                        <div class='code'>{$resetCode}</div>
                    </div>
                    
                    <div class='warning'>
                        <strong>Security Notice:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>This code will expire in 10 minutes</li>
                            <li>Enter this code exactly as shown (6 digits)</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Never share this code with anyone</li>
                        </ul>
                    </div>
                    
                    <p>After entering the code, you'll be able to set a new password for your account.</p>
                    
                    <p>If you didn't request a password reset, please contact our support team immediately at <a href='mailto:moenpogi045@gmail.com'>moenpogi045@gmail.com</a></p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Rads Tooling. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Get text template for password reset email
     */
    private function getPasswordResetEmailTextTemplate(string $fullName, string $resetCode): string
    {
        // Fix copyright symbol
        return "
RADS TOOLING - Password Reset Code

Hello {$fullName},

We received a request to reset your password for your Rads Tooling account. Use the code below to reset your password:

PASSWORD RESET CODE: {$resetCode}

SECURITY NOTICE:
- This code will expire in 10 minutes
- Enter this code exactly as shown (6 digits)
- If you didn't request this reset, please ignore this email
- Never share this code with anyone

After entering the code, you'll be able to set a new password for your account.

If you didn't request a password reset, please contact our support team immediately at moenpogi045@gmail.com

(c) " . date('Y') . " Rads Tooling. All rights reserved.
This is an automated message, please do not reply to this email.";
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->config['from_email'], 'Test User');
            $this->mailer->Subject = 'Test Email - Rads Tooling';
            $this->mailer->Body = '<h1>Test Email</h1><p>This is a test email to verify PHPMailer configuration.</p>';
            $this->mailer->AltBody = 'Test Email - This is a test email to verify PHPMailer configuration.';

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Test email failed: " . $e->getMessage());
            return false;
        }
    }
}

// ==================== WRAPPER FUNCTIONS ====================
// These functions allow auth.php to call sendVerificationEmail() 
// without needing to instantiate the Mailer class directly

/**
 * Send verification email wrapper function
 * This is called by auth.php during registration
 */
function sendVerificationEmail(string $email, string $fullName, string $verificationCode): bool
{
    try {
        $mailer = new Mailer();
        return $mailer->sendVerificationCode($email, $fullName, $verificationCode);
    } catch (Throwable $e) {
        error_log('[sendVerificationEmail] Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email wrapper function
 * This can be called by password.php or other files
 */
function sendPasswordResetEmail(string $email, string $fullName, string $resetCode): bool
{
    try {
        $mailer = new Mailer();
        return $mailer->sendPasswordResetCode($email, $fullName, $resetCode);
    } catch (Throwable $e) {
        error_log('[sendPasswordResetEmail] Error: ' . $e->getMessage());
        return false;
    }
}