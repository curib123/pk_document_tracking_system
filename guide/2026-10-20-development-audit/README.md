# PK DTS — Development Branch Bug Audit and Learning Guide

**Audited branch:** development  
**Baseline commit:** 29e7be5dd74977f930a7e7cefab13d0338256333  
**Audited:** 20 October 2026  
**Source:** https://github.com/curib123/pk_document_tracking_system

**Principle: REPRODUCE THE ERROR MANUALLY FIRST. Never fix based on a guess.**

This guide is a documented code-and-CI inspection, not a completed production browser audit. It distinguishes confirmed GitHub Actions failures, visible code limitations, and high-risk scenarios that still require manual experiments.

## Issue register

| ID | Severity | Evidence | Finding | Main location |
| --- | --- | --- | --- | --- |
| CI-01 | P1 | **Confirmed CI failure** | Workflow Builder contract says "Workflow Builder must provide setup guidance"; required workflow-setup-guide marker is absent | tests/plan_contract.php; application/view/pages/workflow_builder/index.php |
| CI-02 | P1 | **Confirmed CI failure** | Fresh-install SQL snapshot test exits with a MySQL subprocess error; actual MySQL stderr is hidden | tests/schema_install.py |
| DB-01 | P1 | **Confirmed source mismatch** | Clean-install test expects empty users, requests, files and documents, but database/pk_dts.sql includes inserts of operational/account data | database/pk_dts.sql; tests/schema_install.py |
| QA-01 | P2 | **Confirmed diagnostic flaw** | SQL test captures stderr but does not display sanitized SQL error on failure | tests/schema_install.py |
| REQ-01 | P2 | **Confirmed code limitation; browser untested** | My Requests/My Tasks search searches reference, type, subject but not selected document title | application/models/Request_model.php, scope() |
| REQ-02 | P3 | **Confirmed code limitation; UX requirement unconfirmed** | Document Title appears in columns, but not the list of sortable keys | application/view/pages/request/shared_index.php; Request_model::listing |
| TR-01 | P1 | **High-confidence code-path risk, test required** | Single direct hardcopy assignment can change holder_id during active transfer, causing later recipient Accept to reject holder mismatch | Administration::save_document_assignment; Document_access_service::apply; Transfer_service::accept |
| TR-02 | P2 | **Hypothesis, test required** | Different requesters can submit separate transfer requests for same hardcopy under per-requester conflict policy; physical custody could conflict | Request_model::has_submitted_related_request; Request_service::applyApproved |
| ADM-01 | P2 | **Hypothesis, test required** | Empty Location bulk assignment constructs a where_in with empty hardcopy ID list before checking empty set | Administration::assign_location |
| REG-01 | P1 | **Incomplete integration verification** | Previous title/duplicate-submitted-request/dispatch fixes passed focused contracts but require full multi-user browser verification | guide/2026-10-20-plan-fixes/manual-tests.md |

**Important:** CI-01/CI-02 were already failing on the pre-fix development commit. Node reported 98 passing tests and the focused October 20 regression also passed, but the full QA workflow is red. Do not say all issues are fixed.

## Read in this order

1. [01 - Setup and reproduce errors first](01-reproduce-first.md): build a safe QA clone and collect actual error evidence.
2. [02 - Confirmed CI errors](02-confirmed-ci.md): exact scripts, steps, expected/actual, where to look next.
3. [03 - Requests, transfer and admin risks](03-manual-risk-tests.md): click-by-click reproduction for each code concern.
4. [04 - Full-system manual discovery matrix](04-full-system-matrix.md): broader coverage for every main module.
5. [05 - Debugging and evidence worksheet](05-debug-and-evidence.md): SELECT-only queries, suggested code changes after proof and a copyable issue template.

## CI evidence

- Latest audited workflow: https://github.com/curib123/pk_document_tracking_system/actions/runs/39369989105
- Earlier workflow with same Workflow Builder error: https://github.com/curib123/pk_document_tracking_system/actions/runs/39365105474
- CI database job passed tests/smoke.sh using the disposable fixture, then failed in tests/schema_install.py using the public SQL snapshot. These are distinct tests.

## Status rules

- CONFIRMED CI = GitHub log proves an assertion/command failed.
- CONFIRMED SOURCE = code clearly lacks a check/filter, but user-visible effect needs reproduction.
- HYPOTHESIS = plausible issue; no reproduced failure yet.
- FAIL REPRODUCED = manual exact steps and evidence demonstrate unexpected behavior twice on disposable data.
- PASS / BLOCKED / NOT TESTED = use honestly, never promote to confirmed by assumption.

**No production code changes in this audit.** Only after you collect evidence should you implement a fix, add regression tests, and evaluate for merge into development. Never run destructive tests against company data.
