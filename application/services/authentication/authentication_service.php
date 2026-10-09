<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Password policy shared by onboarding, account creation and password reset. */
class Authentication_service
{
    public static function temporary_password()
    {
        return bin2hex(random_bytes(12));
    }

    public static function is_setup_action($controller, $method)
    {
        return strtolower((string)$controller) === 'auth' &&
            in_array(strtolower((string)$method), ['setup','change_password','logout'], TRUE);
    }

    public static function validate_new_password($current, $password, $confirmation)
    {
        if (strlen($password) < 12 || strlen($password) > 72 || trim($password) === '') {
            throw new DomainException('Use a password between 12 and 72 bytes.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new DomainException('The new password and confirmation do not match.');
        }
        if (hash_equals($current, $password)) {
            throw new DomainException('Choose a new password, not your temporary or current password.');
        }
    }
}
