# 03 — Registers, dynamic search, folder layout and modal QA

Prepare 30+ Softcopy and Hardcopy records with overlapping names, diverse status, owner, folder and date values.
Use browser Developer Tools Network + Console.

| ID | Manual steps | Expected result |
| --- | --- | --- |
| UI-01 | Type quickly into Search; pause after each phrase. | Filter updates without full reload, uses latest query only and does not lose input focus. |
| UI-02 | Start a slow network request, then type a different phrase. | Stale response never replaces the newest results. |
| UI-03 | Turn Network offline just before search; then reconnect and Retry. | Visible error and functional Retry. Existing data remains until valid results load. |
| UI-04 | Change status, owner, type, date filters, row limit, sort and page. | Results, counts and URL reflect current filters; 10/25/50/100 pagination works. |
| UI-05 | Search for a term with zero matches and clear all filters. | Clear empty-state message, no stale records, search clears correctly. |
| UI-06 | Visit page 2, apply a search, and use Back/Forward. | Search starts at page 1, history navigation restores previous result sets. |
| UI-07 | Switch document Table ↔ Folder after a search. | Search criteria carry across layouts and folder paths/breadcrumbs are consistent. |
| UI-08 | Browse Area → Specific → optional Asset → Location and Softcopy nested Category. | Accurate document counts, no unauthorized ancestor or leaf names. |
| UI-09 | From freshly updated search results click View on first, middle and last row. | Shared modal opens with correct row, no empty dialog, and stays read-only. |
| UI-10 | Click record cells and use keyboard Enter/Space on the focusable cell. | Opens same View as the action button. |
| UI-11 | Open request View, switch Overview/Approval Process Timeline/Workflow Activity Log/Document File. | Tabs update immediately and content matches the selected request. |
| UI-12 | Close/reopen modal after selecting a different record, including after live update. | Cleared prior detail, correct heading/status and no duplicated overlay. |
| UI-13 | Test with Bootstrap CDN blocked while local app remains reachable. | View modal, dialogs, close/cancel and dropdowns still work. |
| UI-14 | Resize to 375px and 768px; zoom to 200%; use keyboard only. | No clipping of modal actions, tab controls or row buttons; scroll usable. |
| UI-15 | Test search with quote, emoji, accented characters and 100-character term. | Correct escaped content and stable filter request; no HTML injection. |
| UI-16 | Trigger date-range validation with end before start. | Clear validation error and no partial search transition. |
| UI-17 | Verify row View/Edit/Print/History/Download actions after dynamic refresh. | Delegated actions still work; restricted actions remain hidden. |

If View fails: capture console stack trace, the row's rendered data attributes, the active script URLs,
and whether Bootstrap fallback was active. Do not send real private document contents with the report.
