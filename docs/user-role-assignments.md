Users can have a primary role plus additional assigned roles. An action is allowed if any active role assigned to that user grants it. Roles assigned only to other users do not grant access. An assigned Administrator role provides full access.

Existing installations use `database/migrations/20261013_user_role_assignments.sql` after a verified database backup. The migration creates `user_roles` and copies existing primary assignments without changing users, roles, or permission grants. It is safe to rerun. New installations apply it after the base schema.

In User Management, keep a primary role and select the other assigned roles. Saving the form updates assignments atomically and invalidates that user's existing sessions. Permission changes to a role invalidate sessions for its primary and secondary members. Approval queues and file review use all active assigned roles.

Checks: `php tests/user_role_permissions.php`, `php tests/user_role_permissions.php --database`, and `php tests/user_role_assignments.php`. Database checks use connection-local temporary tables and leave installed accounts untouched.
