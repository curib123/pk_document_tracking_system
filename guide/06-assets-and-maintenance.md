# 06 — CSS / JavaScript cleanup review

Never delete a selector simply because a static scanner marks it unused.
PK DTS generates classes dynamically for roles, statuses, modals, icons, folder nodes and workflow states.

1. On the checked-out project run \`node tools/audit_assets.cjs > qa-assets.json\`.
2. Review each \`possiblyUnusedClasses\` candidate in its original CSS file. Search PHP templates, JS, CSS and generated markup in browser.
3. Disable one confirmed-unused rule at a time on a disposable copy. Check Administrator/restricted roles, mobile, offline Bootstrap fallback, modal state, table/folder layouts, and form errors.
4. Only remove rules that are provably unused across these states. Commit small isolated diffs with before/after screenshots.
5. Run \`composer test\` plus the full GitHub CI matrix after each cleanup group.

For JS, review the <script> includes in \`application/view/layout/footer.php\` and \`application/view/pages/files/print.php\` before concluding a module is unused.
Asset audit output is advisory, not a passing functional test. Record each deliberate removal in \`05-results-template.md\`.
