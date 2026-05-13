---
phase: 4
slug: moderation-production-launch
status: verified
threats_open: 0
threats_total: 29
threats_closed: 29
mitigated: 20
accepted: 9
asvs_level: 1
created: 2026-05-13
audited: 2026-05-13
---

# Phase 4 — Security Audit Report

**Phase:** 04 — Moderation & Production Launch
**Audit Date:** 2026-05-13
**ASVS Level:** 1
**Total Threats:** 29 (20 mitigate + 9 accept)
**Closed:** 29 / 29

## Summary

All 29 documented threats verified closed:
- 20 `mitigate` threats: code/doc evidence located in cited files at cited line ranges.
- 9 `accept` threats: dispositions documented in PLAN threat registers and reproduced in this report.
- Test-backed mitigations cross-checked: every named test in SUMMARY.md `## Threat Surface Coverage` exists in `tests/TestCase/Controller/`.

No OPEN threats. No ESCALATE conditions. No `unregistered_flag` entries (SUMMARY.md threat coverage maps cleanly to PLAN threat IDs).

---

## Threat Verification — Plan 04-01 (Block + Soft-delete + Dashboard)

| Threat ID | Disposition | Category | Evidence |
|-----------|-------------|----------|----------|
| T-04-01-01 | mitigate | Tampering/IDOR | `src/Controller/BlocksController.php:106-111` — re-fetch by id, then `(string)$block->blocker_user_id !== $myId` → `ForbiddenException`. Test: `BlocksControllerTest::testDeleteForbiddenForNonOwner` (line 138). |
| T-04-01-02 | mitigate | Tampering/IDOR | `src/Model/Table/MessagesTable.php:339-348` (`softDeleteByReceiver` — `contain('Inboxes')` then `(string)$inbox->user_id !== $ownerUserId` → `ForbiddenException`). Test: `MessagesControllerTest::testDeleteForbiddenForNonOwner` (line 498). |
| T-04-01-03 | accept | Repudiation | Documented in 04-01 PLAN threat register: `blocks.created_at` timestamp only; no append-only audit log for MVP. |
| T-04-01-04 | mitigate | Information Disclosure | `templates/Messages/send.php:32` — fixed banner text `<div class="error-banner">この受信箱には送信できません</div>`. No blocker handle leaked. Same fixed copy in `src/Controller/MessagesController.php:106` Flash error. |
| T-04-01-05 | accept | DoS | Documented via DatabaseException catch idempotent pattern: `src/Controller/BlocksController.php:63-65` swallows `uk_blocks_pair` UNIQUE collision silently. Test: `BlocksControllerTest::testCreateIdempotentOnDuplicate` (line 80). |
| T-04-01-06 | mitigate | Elevation of Privilege | App-layer self-block reject: `src/Controller/BlocksController.php:44-48` (`if ($myId === $senderUserId)` → Flash error + redirect). DB-level second layer: `config/Migrations/20260422120005_CreateBlocks.php:103-104` raw SQL `CONSTRAINT blocks_no_self CHECK (blocker_user_id <> blocked_user_id)`. Test: `BlocksControllerTest::testCreateRejectsSelfBlock` (line 98). |
| T-04-01-07 | mitigate | Tampering | Route regex enforced in `config/routes.php`: lines 102, 115, 122, 130, 137 all use `'[0-9a-f-]{36}'` for UUID-passed params. Identity is read exclusively from `$this->Authentication->getIdentity()->getIdentifier()` in all controllers (verified in BlocksController, MessagesController, ReportsController, AccountController). |
| T-04-01-08 | accept | Spoofing | CakePHP session/cookie security middleware applies globally; CSRF middleware automatic per `MessagesController.php` docblock and `BlocksController.php` docblock comments. No project-specific mitigation required for MVP. |
| T-04-01-09 | accept | Information Disclosure | Ownership re-check on every delete-by-id action (T-04-01-01/02 above). Message UUID v4 entropy ≈ 122 bits; brute-force enumeration infeasible. |

## Threat Verification — Plan 04-02 (Report + Account Deletion + REV-01)

| Threat ID | Disposition | Category | Evidence |
|-----------|-------------|----------|----------|
| T-04-02-01 | mitigate | Tampering/IDOR | `src/Controller/ReportsController.php:46-56` — `contain('Inboxes')` + `(string)$msg->inbox->user_id !== $myId` → `NotFoundException` (not 403, by design — does not leak existence). Tests: `ReportsControllerTest::testCreateGetForeignMessageReturns404` (line 100), `testCreatePostForeignMessageReturns404` (line 233). |
| T-04-02-02 | mitigate | Mass-assignment | `src/Controller/ReportsController.php:107` hardcodes `'status' => 'pending'` in newEntity payload; `accessibleFields` whitelist at lines 108-115 lists status as `true` but value is supplied by server, not from POST body. |
| T-04-02-03 | mitigate | Reason ENUM injection | `src/Controller/ReportsController.php:82-83` — `$allowedReasons = ['harassment','spam','illegal','other'];` then `in_array($reason, $allowedReasons, true)` strict check. Test: `ReportsControllerTest::testCreatePostInvalidReasonRejected` (line 169). |
| T-04-02-04 | mitigate | DoS | `config/Migrations/20260428120001_AddReporterMessageUniqueToReports.php` adds `uk_reports_reporter_message` UNIQUE. Controller catch at `src/Controller/ReportsController.php:117-126` handles `DatabaseException \| PDOException` union, matches SQLSTATE 23000 / index name / 'Duplicate entry' for silent dedupe. Test: `ReportsControllerTest::testCreatePostDuplicateRejectedByUniqueConstraint` (line 205). |
| T-04-02-05 | mitigate | Detail TEXT length | `src/Controller/ReportsController.php:94` — `if (mb_strlen($detail) > 1000)` → Flash error + redirect. Test: `ReportsControllerTest::testCreatePostDetailOver1000CharsRejected` (line 187). |
| T-04-02-06 | mitigate | IDOR | `src/Controller/AccountController.php:37-45` — `$userId` resolved only from `$identity->getIdentifier()`. Route `/account/delete` (routes.php:141-144) carries no user-id parameter. |
| T-04-02-07 | mitigate | confirm_delete bypass | `src/Controller/AccountController.php:52-58` — `allowMethod(['post'])` + server-side `$confirmed` null/empty/false/'0' check → `BadRequestException`. Template `templates/Account/delete.php` includes `required` HTML5 attribute as front-end gate. Test: `AccountControllerTest::testDeletePostWithoutCheckboxRejected` (line 111). |
| T-04-02-08 | mitigate | Retired-user slug enumeration | `src/Model/Table/InboxesTable.php:147-173` — both branches (`findBySlugOrPrevious` current-slug branch line 154, slug_previous fallback branch line 166) include `'Users.deleted_at IS' => null` on the contained `Users` JOIN. Non-existent slug and retired-user slug both raise the same `NotFoundException` (line 173). Test: `MessagesControllerTest::testSendReturns404WhenInboxOwnerRetired` (line 555). |
| T-04-02-09 | accept | Repudiation | Documented per D-25: post-退会 re-signup creates a new user row; the old user_id stays soft-deleted forever. Slug previously owned remains "dead" (cannot resolve due to T-04-02-08 filter). |
| T-04-02-10 | accept | Sender snapshot persistence | `src/Controller/AccountController.php:60-67` only UPDATEs `users.deleted_at`; `messages.sender_*_snapshot` columns untouched. Test: `AccountControllerTest::testDeletePostPreservesSenderSnapshots` (line 126). |

## Threat Verification — Plan 04-03 (Production Launch Runbook + Smoke Checklist)

| Threat ID | Disposition | Category | Evidence |
|-----------|-------------|----------|----------|
| T-04-03-01 | mitigate | Information Disclosure | `.gitignore:4` excludes `/config/.env`; lines 13-14 exclude `/config/keys/*.key` and `*.pem`. `LAUNCH-RUNBOOK.md` Step 2 (lines 80-130 region) explicitly documents SSH-only placement of secrets — never re-deployed via git per D-33. |
| T-04-03-02 | mitigate | Information Disclosure | Defense-in-depth (2 layers): (a) `composer.json:20` places `cakephp/debug_kit` in `require-dev` block — `composer install --no-dev` (LAUNCH-RUNBOOK.md hook line 180; D-32) excludes it from prod vendor/. (b) `src/Application.php:70-72` — `if (Configure::read('debug')) { $this->addPlugin('DebugKit'); }` short-circuits when DEBUG=false. Step 6 verification gate at `LAUNCH-RUNBOOK.md:234` ("DebugKit absent verification") explicitly checks `ls vendor/cakephp/debug_kit`. |
| T-04-03-03 | mitigate | Information Disclosure | `config/app.php:19` — `'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN)` — env-driven with default false. `config/.env.example` Phase 4 production-guidance block documents `DEBUG=false` requirement. `LAUNCH-RUNBOOK.md` Step 6 verification gate visits a 404 URL to confirm production error page (no stack trace). |
| T-04-03-04 | mitigate | Tampering | `LAUNCH-RUNBOOK.md:184-200` Step 4 explicitly states `bin/cake migrations migrate` is "intentionally NOT in the hook" and run manually by SSH (D-34). Hook body at lines 145-156 contains no migration command. |
| T-04-03-05 | accept | DoS | Documented in PLAN threat register: solo developer, single push event = single deploy. Lolipop per-account SSH rate limit is the safeguard. |
| T-04-03-06 | mitigate | Spoofing | `LAUNCH-RUNBOOK.md:145` hook script declares `PHP_BIN="/usr/local/php/8.1/bin/php"`. Lolipop quirks section (line 274) lists explicit PHP-binary-path advisory (Pitfall 6). Lines 190-191, 197, 253-254, 259-260 all use the explicit path. |
| T-04-03-07 | mitigate | Information Disclosure | `.htaccess` rewrite to `webroot/` exists at repo root (verified Phase 1 INFRA-05; covered by `LAUNCH-RUNBOOK.md` Lolipop quirks section line 275). `LAUNCH-RUNBOOK.md` verification gates instruct production URL inspection. |
| T-04-03-08 | accept | Tampering | Documented: SSH access is itself the threat surface; post-receive hook tampering is unreachable without prior compromise. Periodic SSH key rotation noted as post-MVP. |
| T-04-03-09 | accept | Repudiation | Documented: hook echoes timestamp on completion (`LAUNCH-RUNBOOK.md:155` `echo "[tamabox] post-receive deploy complete: $(date)"`); git history serves as deploy log. No formal audit log for MVP. |
| T-04-03-10 | mitigate | Information Disclosure | `src/Controller/OauthController.php:78-93` (`jwks` action) — uses `KeyManager::getPublicJwk()` (public-only — kty/crv/kid/use/alg/x/y); the `d` private scalar is never included. Phase 2 unit + integration tests prove no `d` field in output (per 04-VERIFICATION.md). `LAUNCH-RUNBOOK.md` Step 5 + verification gates include curl check. |

---

## Threat Flags from SUMMARY.md

All SUMMARY.md `## Threat Surface Coverage` entries map 1:1 to the threat IDs in their respective PLAN threat registers. No `unregistered_flag` entries.

Cross-check completed:
- 04-01-SUMMARY.md: 6 entries (T-04-01-01, T-04-01-02, T-04-01-04, T-04-01-05, T-04-01-06, T-04-01-07) — all mapped. 3 `accept` threats (T-04-01-03, T-04-01-08, T-04-01-09) are PLAN-only; this is expected per Plan 04-01's truthful coverage of mitigated controls.
- 04-02-SUMMARY.md: 10 entries — full 10/10 coverage.
- 04-03-SUMMARY.md: 10 entries — full 10/10 coverage (with 4 `accept` explicitly listed).

---

## Accepted Risks Log

| Threat ID | Description | Rationale for Acceptance |
|-----------|-------------|--------------------------|
| T-04-01-03 | Block-create not append-only-audited | MVP: timestamped row sufficient; admin-side review path is Out-of-Scope per PROJECT.md. |
| T-04-01-05 | Repeated `/block` POST flood | UNIQUE constraint + idempotent silent success; per-IP/per-user rate limit deferred post-MVP. |
| T-04-01-08 | Session hijack baseline | CakePHP session/CSRF middleware baseline acceptable for ASVS L1. |
| T-04-01-09 | Soft-deleted leak via PK guess | UUIDv4 122-bit entropy + ownership re-check make leakage infeasible. |
| T-04-02-09 | Retired-user re-signup creates new identity | Per D-25 — old slug stays "dead", no UX/security regression. |
| T-04-02-10 | Sender snapshot persists after sender 退会 | MOD-03 contract — receivers MUST see dead-link snapshot indefinitely. |
| T-04-03-05 | Mass push deploys | Solo dev; Lolipop SSH per-account limit is sufficient. |
| T-04-03-08 | post-receive hook tampered after SSH compromise | SSH compromise is dominant threat; tamper detection is post-MVP. |
| T-04-03-09 | Deploy actions not formally logged | git history + hook echo are MVP audit. |

---

## Audit Methodology

- **Verification:** For each `mitigate` threat, grep + read for the cited control pattern in named files. Test names from SUMMARY.md cross-checked against `tests/TestCase/Controller/*.php`.
- **Implementation files:** Read-only. No modifications made.
- **Scope:** Bounded by `<threat_model>` in each PLAN.md. No new-vulnerability scanning performed (per `/gsd-secure-phase` mandate).
- **Block-on policy:** `closing-only-if-evidence-cited` — every `mitigate` threat in this report has a file:line or test-name evidence pointer.

---

*Auditor: gsd-security-auditor (Phase 4 audit, 2026-05-13)*
