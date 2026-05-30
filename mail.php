<?php
/**
 * mail.php - Advanced Contact Form Handler with PHPMailer
 * 
 * Features:
 * - Full UTF-8
 * - Secure input sanitization & validation
 * - SMTP or PHP mail() support
 * - Professional error handling & JSON response
 * 
 * @author  InversWeb
 * @version 1.0.0
 * @date    2025-09-18
 */

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1. Load PHPMailer classes manually (no Composer)
// -----------------------------------------------------------------------------
require_once 'assets/PHPMailer/PHPMailer.php';
require_once 'assets/PHPMailer/SMTP.php';
require_once 'assets/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// -----------------------------------------------------------------------------
// 2. Configuration
// -----------------------------------------------------------------------------
$config = [
    'use_smtp'         => true,                                    // Set false to use PHP mail()
    'smtp_host'        => 'smtp.gmail.com',                        // SMTP server
    'smtp_username'    => 'your-email@gmail.com',                  // SMTP username
    'smtp_password'    => 'your-app-password-here',                // App Password (Gmail)
    'smtp_port'        => 587,                                     // 587 (TLS) or 465 (SSL)
    'smtp_secure'      => 'tls',                                   // 'tls' or 'ssl'
    
    'recipient_email'  => 'recipient@example.com',                  // Where form is sent
    'recipient_name'   => 'Website Admin',
    
    'min_message_len'  => 10,                                      // Minimum message length
];

// -----------------------------------------------------------------------------
// 3. Helper Functions
// -----------------------------------------------------------------------------
/**
 * Sanitize input data to prevent XSS
 * 
 * @param string $data
 * @return string
 */
function sanitize(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 * 
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false 
        && preg_match('/@.+\.[a-zA-Z]{2,}$/', $email);
}

/**
 * Send JSON response and exit
 * 
 * @param bool $success
 * @param string $message
 * @param string $field
 * @return never
 */
function sendResponse(bool $success, string $message = '', string $field = '')
{
    $response = [
        'code'    => $success,
        'success' => $success ? $message : '',
        'err'     => $success ? '' : $message,
        'field'   => $field
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------------------
// 4. Request Validation
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

// -----------------------------------------------------------------------------
// 5. Extract and Sanitize Inputs
// -----------------------------------------------------------------------------
$name    = sanitize($_POST['contact-name'] ?? '');
$phone   = sanitize($_POST['contact-phone'] ?? '');
$email   = sanitize($_POST['contact-email'] ?? '');
$subject = sanitize($_POST['subject'] ?? '');
$message = sanitize($_POST['contact-message'] ?? '');

// -----------------------------------------------------------------------------
// 6. Server-Side Validation
// -----------------------------------------------------------------------------
if (empty($name)) {
    sendResponse(false, 'Please enter your name.', 'contact-name');
}

if (empty($email)) {
    sendResponse(false, 'Please enter your email.', 'contact-email');
}

if (!isValidEmail($email)) {
    sendResponse(false, 'Please enter a valid email address.', 'contact-email');
}

if (empty($message)) {
    sendResponse(false, 'Please write your message.', 'contact-message');
}

if (strlen($message) < $config['min_message_len']) {
    sendResponse(
        false, 
        "Message must be at least {$config['min_message_len']} characters.", 
        'contact-message'
    );
}

// -----------------------------------------------------------------------------
// 7. Initialize PHPMailer
// -----------------------------------------------------------------------------
$mail = new PHPMailer(true);

try {
    // Server settings
    if ($config['use_smtp']) {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = $config['smtp_port'];
    } else {
        $mail->isMail();
    }

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Recipients
    $mail->setFrom($config['smtp_username'], "Contact Form - {$name}");
    $mail->addAddress($config['recipient_email'], $config['recipient_name']);
    $mail->addReplyTo($email, $name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject ?: 'New Contact Form Submission';

    // HTML Email Body
    $htmlBody = "
    <!DOCTYPE html>
    <html lang='fa' dir='rtl'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Tahoma, sans-serif; background:#f9f9f9; color:#333; }
            .container { max-width: 600px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #2c3e50; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
            td:first-child { font-weight: bold; color: #555; width: 120px; }
            .footer { font-size: 12px; color: #7f8c8d; text-align: center; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>پیام جدید از فرم تماس</h2>
            <table>
                <tr><td>نام:</td><td>{$name}</td></tr>
                <tr><td>تلفن:</td><td>" . ($phone ?: '—') . "</td></tr>
                <tr><td>ایمیل:</td><td>{$email}</td></tr>
                <tr><td>موضوع:</td><td>" . ($subject ?: '—') . "</td></tr>
                <tr><td>پیام:</td><td>" . nl2br($message) . "</td></tr>
            </table>
            <div class='footer'>
                ارسال شده در: " . date('Y-m-d H:i:s') . " | IP: {$_SERVER['REMOTE_ADDR']}
            </div>
        </div>
    </body>
    </html>";

    // Plain text fallback
    $plainBody = "پیام جدید از فرم تماس\n\n";
    $plainBody .= "نام: {$name}\n";
    $plainBody .= "تلفن: " . ($phone ?: '—') . "\n";
    $plainBody .= "ایمیل: {$email}\n";
    $plainBody .= "موضوع: " . ($subject ?: '—') . "\n";
    $plainBody .= "پیام: {$message}\n\n";
    $plainBody .= "ارسال شده در: " . date('Y-m-d H:i:s') . "\n";
    $plainBody .= "IP: {$_SERVER['REMOTE_ADDR']}\n";

    $mail->Body    = $htmlBody;
    $mail->AltBody = $plainBody;

    // ---------------------------------------------------------------------
    // 8. Send Email
    // ---------------------------------------------------------------------
    $mail->send();

    // Success response
    sendResponse(true, 'Your message has been sent successfully. Thank you!');

} catch (Exception $e) {
    // Log error securely (never expose to user)
    error_log('PHPMailer Error: ' . $mail->ErrorInfo . ' | IP: ' . $_SERVER['REMOTE_ADDR']);

    // User-friendly error
    sendResponse(false, 'Failed to send message. Please try again later.');
}