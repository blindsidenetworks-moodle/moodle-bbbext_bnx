# SP-1: bnx finish-up

Scope: split the completed bbbext_bnx work into the SP-1 implementation commit without changing Moodle core.

Repo boundary:
- Functional, migration, test, language, and release-support changes live in `public/mod/bigbluebuttonbn/extension/bnx`.
- Parent `mod_bigbluebuttonbn` core files are not part of this commit.

Included work:
- Retire BN Reminders in favour of BNX-owned migration and administration paths.
- Add the manual BN Reminders migration CLI entry point.
- Add BN Reminders conflict and pending-migration checks to BNX administration.
- Keep BNX self-contained when the legacy sidecar remains enabled, including the administrator notification on attempted BNX enablement.
- Preserve optional legacy guest-table handling during pending-migration detection.
- Add Spanish, French, and Catalan BNX language packs.
- Update BNX documentation and focused PHPUnit coverage for migration and conflict behaviour.

Verification:
- The BNX migration test suite passes: 7 tests, 35 assertions.
- The focused observer test suite covers the conflict notification path.
- Parent Moodle core files remain unchanged.

Follow-up outside SP-1:
- Bump stable release metadata and finalize release notes separately.
