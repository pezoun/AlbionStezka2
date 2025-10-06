<?php
function smtp_mailer($to, $subject, $msg) {

    $phpmailerPath = __DIR__ . '/smtp/PHPMailerAutoload.php';
    if (!file_exists($phpmailerPath)) {
        return false;
    }
    
    include($phpmailerPath);
    
    try {
        $mail = new PHPMailer(); 
        $mail->IsSMTP(); 
        $mail->SMTPAuth = true; 
        $mail->SMTPSecure = 'tls'; 
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 587; 
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // Vypnuto debugování
        $mail->SMTPDebug = 0;
        
        // TVŮJ GMAIL ÚČET
        $mail->Username = "tomaskotik08@gmail.com";
        $mail->Password = "cjtlprfmnakatmph"; // App password bez mezer!
        
        $mail->SetFrom("tomaskotik08@gmail.com", "Albion stezka");
        $mail->Subject = $subject;
        $mail->Body = $msg;
        $mail->AddAddress($to);
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => false
            )
        );
        
        if(!$mail->Send()) {
            return false;
        } else {
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
}
?>