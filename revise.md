Major Revisions
Remove the Hardcopy Request
Remove the Hardcopy Request option from both the Create and Revise processes.
Add Automatic Document Number Generation Based on Category
Add a new Code Pattern field under each category to define the document numbering format.
Example: For the PPM Records category, the code pattern could be PPM-.
When a user selects a category, the system should automatically generate the next document number based on the previously used code pattern and sequence.
Examples: PPM-001, PPM-01, or PPM-001, depending on the configured numbering format.
Document numbers should be automatically generated rather than manually entered.
Add a Series Number Checker toggle, with the default setting set to OFF.
When the toggle is ON, users can manually select a document number from a searchable dropdown instead of automatically using the next available number.
After selecting a document number, users can enter the corresponding series number.
If previous series numbers exist, display them as a reference or guide to help users determine the next appropriate series number.
Add a New Document Type: External Documents
Add a new document type called External Documents, separate from Softcopy and Hardcopy.
Include the following fields:
Document Title
Retention Period
Attach File
Provide the following options for External Documents:
Direct External
External Create Request
External Revised Request
Automatic Revision Level Increment
Automatically increment the revision level if a previous revision exists for the selected document.
If no previous revision exists, assign the default revision level.