<?php

// O envio de recuperação é opcional no ambiente de demonstração. A ausência
// do Composer/SMTP não deve impedir o restante da API de iniciar.

/**
 * Email
 * Envolve o PHPMailer para enviar e-mails via SMTP.
 * Configure as constantes SMTP_* em backend/config/config.php.
 */
class Email
{
    /**
     * Envia um e-mail em texto simples.
     * Retorna true em caso de sucesso e false em caso de falha
     * (a mensagem de erro real fica registrada no error_log do PHP).
     */
    public static function enviar(string $destinatario, string $assunto, string $mensagem): bool
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload) || SMTP_HOST === '' || SMTP_FROM_EMAIL === '') return false;
        require_once $autoload;
        $classe = 'PHPMailer\\PHPMailer\\PHPMailer';
        if (!class_exists($classe)) return false;
        $mail = new $classe(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = $classe::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NOME);
            $mail->addAddress($destinatario);

            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Falha ao enviar e-mail: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
