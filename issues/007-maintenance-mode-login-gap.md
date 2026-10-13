# Issue 007: Login can query the database while restore maintenance is active

Severity: HIGH (concurrency) | Status: Confirmed guard placement | Implemented: NO

Affected: application/core/MY_Controller.php; application/controllers/Auth.php.

The maintenance guard is in MY_Controller::authenticate(), but Auth::login() does not invoke authenticate(). Additionally, MY_Controller::__construct() fetches Identity_model and current account data before the guard runs. Logins can therefore read/write restored tables while the database is being replaced.

## Copyable fix

Immediately after parent::__construct() and BEFORE the first Identity_model load in MY_Controller::__construct(), add:

~~~php
if (is_file(PK_ROOT.'/storage/.restore-in-progress')) {
    $controller = strtolower((string)$this->router->fetch_class());
    $method = strtolower((string)$this->router->fetch_method());
    if ($controller !== 'backups' || $method !== 'restore') {
        show_error('Restore maintenance is in progress.', 503);
        exit;
    }
}
~~~

Keep the existing authenticate() check. For strict concurrency isolation, store a random restore token and allow ONLY the original restore request; the controller/method exception above still relies on the existing administrator check and backup lock.

## Verify
On a cloned database, create the maintenance marker. Login, Dashboard, Documents and user-management requests should return 503. Remove the marker and confirm the same routes work again. Test a real restore in the test environment before making this change live.
