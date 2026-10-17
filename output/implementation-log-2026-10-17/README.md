# Implementation Log evidence — October 17, 2026

Only Control #1 (MFA and Account Lockout) was updated in the original Google Sheet. The 17 updated answer cells are yellow. Control #2 and the diagram field were restored to their original values and formatting.

- Application tests: 38 passed, 186 assertions.
- Extra evidence tests: 4 passed, 61 assertions.
- Password and MFA lockouts: the next request after five failures was blocked. A retry after 61 seconds succeeded.
- MFA limitation: the confirmed-account challenge still returns the existing enrollment secret before MFA verification. One evidence test reproduces this issue; its passing result does not mean the MFA design is secure.
- Physical-phone QR scanning was not independently tested.

The three MFA page screenshots were captured with an isolated test account earlier on October 17. The lockout screenshots from October 16 remain historical evidence and do not represent the current post-lockout password-only login behavior.

sheet-updates.json contains the final Control #1 text. control-1-updated.jpg shows the saved Google Sheet. No application code was changed for this spreadsheet task.