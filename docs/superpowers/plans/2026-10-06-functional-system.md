# PK DTS Functional System Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline, test each domain before publishing changes.

**Goal:** Implement the repository requirements as an unstyled, modal-operated CodeIgniter 3 application.
**Architecture:** Thin CI controllers delegate to application services. MySQL transactions protect state, authorization, immutable snapshots and file ownership; one central browser table/dialog layer serves the modules.
**Tech Stack:** PHP 8.2+, CodeIgniter 3.1.13, PDO MySQL, semantic HTML, external JavaScript, FPDI/FPDF.
**Spec:** ../specs/2026-10-06-functional-system.md

## Global Constraints
- No public registration, CSS, visual framework, browser alert/confirm/prompt, unauthenticated file paths or hidden default passwords.
- Separate softcopy/hardcopy records, published workflow snapshots, recipient-gated transfers and complete audit trails.
- Preserve the original requirements file and work on a feature branch rather than replacing master.

## Review Focus
Authorization on direct HTTP calls; stale/double submissions; inactive assignees and empty approval paths; file ownership and path traversal; dialog failures, Escape/focus and double submits.

## Tasks
- [ ] 1. Add tests for validation, workflow graph safety and permission rules; run failing suite, implement core/config/schema, rerun and commit.
- [ ] 2. Add authentication/catalog tests; implement generated-password accounts, roles, permissions, organizational constraints and transactional services; verify and commit.
- [ ] 3. Add lifecycle integration scenarios; implement documents, revisions, files/artifacts, draft requests and immutable workflows; verify and commit.
- [ ] 4. Add transfer/access/disposal scenarios; implement recipient acceptance, grants, assignment, status history and notifications; verify and commit.
- [ ] 5. Add browser scenarios; implement shared tables/dialogs and complete module actions, including errors and validation; verify and commit.
- [ ] 6. Add installation/CI/readme/traceability, lint all files, run available suites, perform self-review and publish a review branch/PR with exact verification results.

## Interfaces
Core Database provides query/one/all/insert/update/transaction/lock; Context provides actor/can/require/audit/notify/sequence. Services receive Context. Application dispatches named read or mutation operations; UI consumes metadata, lookups and paginated records through one JSON envelope. Uploaded files are private records referenced by ID, never client-supplied paths.
