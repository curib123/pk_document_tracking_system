# LibreOffice setup for PDF previews

PK DTS uses LibreOffice (headless) to turn Word (`.docx`) and Excel (`.xlsx`)
attachments into PDF. The PDF is what users see in the read-only preview and
what the print page prints. The uploaded original is never changed; authorized
roles (for example Administrator, or Document Control with the *Download
Locked Files* permission) can still download it.

Without LibreOffice the rest of the system works. Only Word and Excel previews
and printing fail, with the message *"PDF preview is not available because
LibreOffice is not installed on the server."* PDF, image and text files are not
affected.

## How it works

1. A user opens a preview or print page for a `.docx` or `.xlsx` file.
2. The server copies the original to a temporary folder, runs
   `soffice --headless --convert-to pdf`, and saves the result as
   `storage/previews/<storage-name>.pdf`.
3. Later requests reuse that saved PDF. Nothing is converted twice.
4. When an original file is removed by the retention cleanup, its saved PDF is
   removed too.

Conversions run one at a time (a lock file in `storage/previews`) because one
LibreOffice profile cannot be shared by two processes. A conversion that takes
longer than 90 seconds is stopped. The first view of a file usually takes 2 to
6 seconds.

## Requirements

- LibreOffice 7.x or newer. The "Writer" and "Calc" components are needed.
  A full install includes both.
- PHP `proc_open` and `exec` must not be listed in `disable_functions`
  (`exec` is only used on Windows to stop a stuck conversion).
- The web server account must be able to **run** LibreOffice and **write** to
  `storage/` (it already does for uploads).
- About 300 MB of disk for LibreOffice, plus roughly the size of the PDFs you
  preview in `storage/previews`.

## Windows (XAMPP)

1. Install LibreOffice from <https://www.libreoffice.org/download/> using the
   default folder, `C:\Program Files\LibreOffice`. The application finds it
   there automatically; no setting is needed.
2. If you installed it somewhere else, set `PK_LIBREOFFICE_PATH` to the full
   path of `soffice.exe` (see [Custom location](#custom-location)).
3. Make sure the account that runs Apache can run LibreOffice:
   - If Apache runs as your Windows user (a normal XAMPP control panel
     start), nothing more is needed.
   - If Apache runs as a Windows service under another account, give that
     account **Read & execute** on `C:\Program Files\LibreOffice` and **Modify**
     on the project's `storage` folder.
4. Restart Apache.
5. Run the [check](#check-the-installation).

## Linux (Debian / Ubuntu)

```bash
sudo apt update
sudo apt install --no-install-recommends libreoffice-writer libreoffice-calc fonts-liberation fonts-dejavu-core
```

On RHEL, Rocky or Alma:

```bash
sudo dnf install libreoffice-writer libreoffice-calc liberation-fonts
```

The application looks for `/usr/bin/soffice`, `/usr/bin/libreoffice`,
`/usr/local/bin/soffice` and `/opt/libreoffice/program/soffice`. Check which one
you have with `which soffice`.

The web server user (`www-data`, `apache` or `nginx`) needs write access to
`storage/`. LibreOffice also writes a small profile under
`storage/previews/.lo-profile`, so it does not need a home directory.

If SELinux is enforcing (RHEL family), allow the web server to run programs it
writes next to:

```bash
sudo setsebool -P httpd_execmem 1
```

Check `sudo ausearch -m avc -ts recent` if conversions still fail.

## Custom location

Set `PK_LIBREOFFICE_PATH` to the full path of the `soffice` program. Like the
other settings in the [readme](../readme.md#configure-the-connection), it must
reach the **Apache/PHP process**, not only your terminal.

- **Apache (Linux):** `SetEnv PK_LIBREOFFICE_PATH /opt/lo/program/soffice` in
  the virtual host, or `Environment=` in a systemd unit.
- **PHP-FPM:** `env[PK_LIBREOFFICE_PATH] = /opt/lo/program/soffice` in the pool
  file, with `clear_env = no` if you rely on the system environment.
- **Windows service:** set it as a system environment variable, then restart
  Apache (a running Apache does not see new variables).

## Check the installation

Run this from the project folder. It converts a small document and prints the
result. Adjust the `soffice` path if yours differs.

```bash
# Linux
soffice --headless --convert-to pdf --outdir /tmp /path/to/any.docx

# Windows PowerShell
& "C:\Program Files\LibreOffice\program\soffice.exe" --headless --convert-to pdf --outdir $env:TEMP C:\path\to\any.docx
```

A PDF should appear in the output folder within a few seconds. Then, in the
application, open a softcopy document with a `.docx` or `.xlsx` file, open
**View Details → Document Files**, and the preview should load. After the first
view, `storage/previews/` contains a `.pdf` named after the stored file.

## Troubleshooting

Application errors are written to `storage/logs/log-YYYY-MM-DD.log` (search for
`PDF preview`).

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| "LibreOffice is not installed on the server" | Program not found | Install it, or set `PK_LIBREOFFICE_PATH` and restart Apache |
| "PDF preview is temporarily unavailable" | `storage/previews` cannot be created or locked | Give the web server account write access to `storage/` |
| "could not be converted to PDF" | Conversion failed, timed out, or the file is damaged | Check the log; run the manual conversion above as the web server user |
| Works in terminal, fails in the browser | Apache runs as a different account, or `proc_open` is disabled | Check `disable_functions`; give that account access (see above) |
| Preview text uses the wrong font or layout | The font used in the document is not installed | Install the missing fonts (see below) |
| Wide spreadsheets split across several pages | Normal for PDF output of large sheets | Set the sheet's page setup to *Fit to 1 page wide* in Excel before uploading |
| First preview is slow | LibreOffice starts on first use | Expected; later views use the saved PDF |

### Fonts

LibreOffice substitutes fonts it does not have, which can change line breaks.
Install the fonts your documents use (for example, copy company fonts into
`C:\Windows\Fonts` on Windows, or `/usr/local/share/fonts` followed by
`fc-cache -f` on Linux). On Linux, `fonts-crosextra-carlito` and
`fonts-crosextra-caladea` give metric-compatible substitutes for Calibri and
Cambria.

## Maintenance

- **Saved PDFs are disposable.** You can delete everything in
  `storage/previews` at any time; PDFs are created again on the next view.
  Keep `storage/documents`, which holds the originals.
- **A stuck conversion** is stopped after 90 seconds. If Windows Task Manager
  shows leftover `soffice.bin` processes, end them; the next conversion works
  normally.
- **Backups** do not need `storage/previews`.
- **Upgrading LibreOffice** needs no change here. Delete `storage/previews` if
  you want existing previews re-rendered by the new version.

## Security notes

- Conversion runs with LibreOffice's default macro security (unsigned macros
  are not run). Only `.docx` and `.xlsx` files are converted, and uploads are already
  limited to 15 MB and checked by content type.
- The program is started without a shell (arguments are passed as a list), and
  the file names handed to it are generated, never taken from the upload.
- Saved PDFs are served only through the application, after the same access
  and lock checks as the original. Do not expose `storage/` through the web
  server; the project `.htaccess` already blocks it.
