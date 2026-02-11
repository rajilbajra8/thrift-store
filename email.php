<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'rajilbajracharya234@gmail.com';
    $mail->Password   = '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender & receiver
    $mail->setFrom('rajilbajracharya234@gmail.com', 'Thrift Store');
   // $mail->addAddress('receiver@example.com');

    // Email content
    $mail->isHTML(true);
//    $mail->Subject = 'Test Email';
//    $mail->Body    = '<h1>Hello</h1><p>This is a test email.</p>';
//    $mail->AltBody = 'Hello - This is a test email';

//    $mail->send();
//    echo 'Email sent successfully';
} catch (Exception $e) {
    echo "Email failed: {$mail->ErrorInfo}";
}