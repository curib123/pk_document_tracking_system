PK DTS WPS opener for Windows (install once per Windows user)

1. Extract every file in this ZIP to one folder.
2. Open PowerShell in that folder and run:
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\install.ps1
3. Return to PK DTS > Review File > Open in WPS and allow the browser prompt.

WPS Office must already be installed. No administrator account is needed.
Only signed, unexpired review links from the configured PK DTS site are accepted.
Review copies are read-only and saved under %LOCALAPPDATA%\PK DTS\ReviewCopies.
Closing WPS does not upload changes to PK DTS. Remove local review copies when finished.

To unregister the opener:
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\install.ps1 -Uninstall

For a source checkout, install with the explicit site URL:
   .\install.ps1 -BaseUrl http://localhost/pk_document_tracking_system/
