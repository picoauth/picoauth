<?php

namespace PicoAuth\Mail;

/**
 * A simple Mailer Interface PicoAuth uses to send mail.
 */
interface MailerInterface
{

    /**
     * Initialize the mailer before sending mail
     */
    public function setup();

    /**
     * Set email recipient
     * @param string $mail
     */
    public function setTo($mail);

    /**
     * Set email subject
     * @param string $subject
     */
    public function setSubject($subject);
    
    /**
     * Set email body
     * @param string $body
     */
    public function setBody($body);

    /**
     * Send email with the previous configuration
     * @return bool true on success, false on failure
     */
    public function send();
    
    /**
     * Gets error message if send() returned false
     * @return string Error message
     */
    public function getError();
}
