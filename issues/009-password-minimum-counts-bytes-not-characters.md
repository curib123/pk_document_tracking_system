# ISSUE 009 — Password policy says 9 characters but accepts fewer Unicode characters

**Severity:** Medium (policy correctness)  
**Type:** Confirmed length-unit mismatch  
**Status:** Proposed fix only; NOT applied

## Evidence
- Authentication_service::validate_new_password() uses strlen($password) < 9.
- strlen counts bytes rather than UTF-8 characters. A password with five accented characters can be at least nine bytes and pass even though the UI promises more than eight characters.
- The 72-byte upper limit remains relevant to PASSWORD_DEFAULT implementations using bcrypt.

## Copyable fix
Inside application/services/authentication/authentication_service.php, replace the first IF guard with:

~~~php
if (!is_string($password) ||
    mb_strlen($password,'UTF-8') < 9 ||
    strlen($password) > 72 ||
    trim($password) === '') {
    throw new DomainException(
        'Use a password longer than 8 characters (maximum 72 bytes).'
    );
}
~~~

Inside tools/create_admin.php, change the minimum check to the same UTF-8 character policy; also check the 72-byte maximum:

~~~php
if (!$first || !$last ||
    mb_strlen($password,'UTF-8') < 9 ||
    strlen($password) > 72) {
    exit("Name and a 9+ character password (72 bytes maximum) required\n");
}
~~~

Keep constant-time comparison, password_verify and hashing unchanged. Note that the HTML maxlength=72 attribute counts characters rather than bytes, so server-side byte checking remains mandatory.

## Verify
Test 8 ASCII characters (reject), 9 ASCII characters (accept), 5 repeated accented UTF-8 characters (reject), 9 accented characters under 72 bytes (accept), and 73 ASCII bytes (reject). Test both first-login password setup and CLI administrator creation.
