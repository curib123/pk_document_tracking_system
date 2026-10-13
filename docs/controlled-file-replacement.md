After a softcopy revision receives final approval, its attachment becomes the
current controlled file. Earlier approved files on that document become
`superseded` in the same transaction. The revision records, file metadata,
checksums, and approval history remain intact.

The replacement bytes must exist and match their uploaded size and SHA-256 hash.
Intermediate approvals, returned/rejected requests, unavailable attachments, and
failed commits leave the previous current file and its bytes intact. Direct
revision approval uses the same validation and replacement behavior.

Old bytes are deleted only after the transaction commits. Cleanup is restricted
to validated private storage names, refuses symlinks and paths outside document
storage, and preserves any bytes still referenced by an approved or pending
attachment. Nested transactions defer deletion until the outer transaction ends.
Temporarily locked files remain superseded and unavailable for download; cleanup
is retried when the current approved file is downloaded or another revision is
approved. The cleanup method can also be retried after an outer transaction commits.

File history lists superseded records without download links. The current
approved revision is the only controlled file downloadable through the file route.

Run `php tests/controlled_file_replacement.php` for isolated database/filesystem
checks. For a real multipart direct upload, serve that test as a temporary local
PHP router and POST an `attachment` text file. Both modes use temporary database
tables and remove only their owned test blobs; installed documents stay unchanged.
