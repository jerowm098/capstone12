<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = 'jjjjema098@gmail.com';
    $mail->Password = 'thyn haox nxhm vfml';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('jjjjema098@gmail.com', 'PUPBC CareLink');
    $mail->addAddress('receiver@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = '<h2>PHPMailer Working!</h2>';

    $mail->send();

    echo "Email Sent Successfully";

} catch (Exception $e) {
    echo $mail->ErrorInfo;
}