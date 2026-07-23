# PasteGuard manual test script

Run in Chrome, Firefox and Edge unless a case says otherwise. Setup: site
setting *enabled* on, defaults elsewhere; a forum with PasteGuard ticked; a
quiz with two essay questions and PasteGuard ticked; one assignment with
online text + PasteGuard; a student account; a second student holding a
"PasteGuard Exempt" role with `tiny/pasteguard:bypass` on the forum.

## Core blocking

1. **External paste blocked** — copy text from another website tab, paste
   (Ctrl+V) into the forum post editor. Expect: nothing inserted, warning
   notification with the block message, no placeholder left, Ctrl+Z does
   nothing (no undo step).
2. **Context-menu paste blocked** — same, via right-click → Paste.
3. **Word paste blocked** — copy styled text (bold, bullets, a link) from
   Microsoft Word; paste. Expect: blocked.
4. **Internal copy→paste allowed** — type a sentence, select it, Ctrl+C,
   click elsewhere in the editor, Ctrl+V. Expect: inserted.
5. **Internal cut→paste allowed** — as above with Ctrl+X.
6. **Formatted internal text** — make a selection bold and part of a list,
   copy, paste. Expect: allowed (normalisation strips markup on comparison).
7. **Internal word inside external paragraph** — copy one word internally,
   then copy an external paragraph containing that word, paste. Expect:
   blocked (exact match only).

## Cross-editor and page scope

8. **Two essay questions, one attempt** — copy text from essay 1's editor,
   paste into essay 2. Expect: allowed.
9. **Across pages** — copy in the forum editor, open the assignment in a new
   page, paste. Expect: blocked (allowlist is page-scoped; document if this
   surprises testers).

## Drag and drop

10. **External drop blocked** — drag selected text from another browser
    window into the editor. Expect: blocked.
11. **Internal drag allowed** — select text inside the editor, drag it to
    another position in the same editor. Expect: moves normally.

## Platform paths

12. **Linux middle-click paste** (Firefox/Chromium on Linux) — select
    external text elsewhere, middle-click in the editor. Expect: blocked.
13. **IME/composition** — with an IME active, confirm normal typing is never
    blocked.

## Non-interference

14. **Draft restore** — type into a forum post, wait for autosave, reload
    the page. Expect: draft restored, no block message.
15. **Undo/redo of own typing** unaffected.
16. **Unprotected activity** — an activity without the tick behaves
    completely normally (paste from anywhere works).
17. **Exempt student** — the bypass-role student pastes external text
    freely; the normal student on the same forum is blocked.

## Logging (site setting on)

18. Block several pastes quickly; check *Site administration → Reports →
    Logs* shows "External paste blocked" events with a character count, at
    most one per editor per 10 seconds, and never the pasted text.
