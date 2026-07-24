# PasteGuard manual test script

> Verified in a real browser (Chrome, Moodle 5.1, real student session via
> "log in as"): PasteGuard active on the forum editor and on both quiz essay
> editors (context id confirmed = the module context), and the undo behaviour
> in item 1 (a blocked paste adds no undo level; the first undo reverts the
> user's own typing). Assignment online-text and quiz/lesson essay editors
> render in the module context (verified live for quiz; by code for the rest),
> so they activate identically. Still to run in a supervised browser: styled
> Word paste, middle-click, drag-drop, and draft restore.

### Real-hardware paste tests (Chrome, Moodle 5.1, student session)

Four physical Ctrl+C/Ctrl+V tests with a logger on the editor(s). Verbatim log
lines:

- **A — external source, no marker → BLOCKED.** A real paste of web/Word text:
  `NATIVE paste: text/plain="Placeholder content for Assessment Prep." | htmlHasMarkerComment=false | hasMarkerMime=false`
  — `PastePreProcess` did not fire; content not inserted (`decision.allowed=false`).
  Confirms a real hardware paste reaches `handleNativePaste`.
- **B — real `x-tinymce/html` marker, empty page allowlist → BLOCKED.** Copied
  from a TinyMCE editor in another tab:
  `NATIVE paste: text/plain="MARKER PAYLOAD" | htmlHasMarkerComment=true | hasMarkerMime=true`
  — `PastePreProcess` did not fire; not inserted (`decision.allowed=false`).
  **The clipboard marker does not grant passage**; the native allowlist handler
  blocks on the plain-text mismatch and TinyMCE bails on `isDefaultPrevented`.
- **C — internal copy/paste, same editor → ALLOWED.**
  `NATIVE paste: text/plain="My own paragraph." | htmlHasMarkerComment=true | hasMarkerMime=true`
  then `PastePreProcess FIRED: internal=true` — inserted (`decision.allowed=true`).
  The native handler matched the allowlist (populated by the real copy), so
  TinyMCE proceeded and `handlePastePreprocess` honoured `currentDecision===true`.
- **D — cross-editor, same page (§3 edge case 1) → ALLOWED.** Two quiz essay
  editors; copy in editor 1, paste in editor 2:
  `captureInternalCopy via editor1` then
  `NATIVE paste into editor2: text/plain="My example" htmlHasMarker=true mime=true`
  then `PastePreProcess in editor2: internal=true` — inserted (`decision.allowed=true`).
  Carried by the **shared page-scoped allowlist**, not the marker.

Conclusion: `handleNativePaste` decides every real paste via the page-scoped
allowlist; the `x-tinymce/html` marker never decides one on the standard Ctrl+V
path.

Run in Chrome, Firefox and Edge unless a case says otherwise. Setup: site
setting *enabled* on, defaults elsewhere; a forum with PasteGuard ticked; a
quiz with two essay questions and PasteGuard ticked; one assignment with
online text + PasteGuard; a student account; a second student holding a
"PasteGuard Exempt" role with `tiny/pasteguard:bypass` on the forum.

## Automated coverage boundary — read before trusting "the tests pass"

Automated tests exercise only part of the client-side logic. Named against the
current source so a reviewer can verify:

- **Covered by JS unit tests** — `tests/js/comparison.test.mjs` (18 tests, `node
  --test`): the whole of `amd/src/comparison.js` — `normalise`, `matches`,
  `stripHtmlDom`, `stripHtmlRegex`, `defaultStripHtml`.
- **Covered by the Behat `@javascript` scenarios** — `tests/behat/pasteguard.feature`,
  via step definitions in `tests/behat/behat_tiny_pasteguard.php`: in
  `amd/src/pasteguard.js`, `handleNativePaste` (external-block, allowlist-allow,
  and bypass/inactive paths), plus `captureInternalCopy` and `isAllowed` (the
  internal copy→paste scenario), and the notification side of `reportBlock` →
  `notifyBlocked` (asserted by the "Pasting from outside this editor is disabled"
  message). **Caveat:** those steps dispatch synthetic `ClipboardEvent`s, which
  reach the native `paste` handler but do **not** drive TinyMCE's
  `PastePreProcess` pipeline, and never carry the `x-tinymce/html` marker. So the
  48 passing steps cover the native-handler paths only, not the pipeline.
- **No automated coverage — manual testing only** (all in `amd/src/pasteguard.js`):
  - `handlePastePreprocess`, including the `args.internal` internal-marker branch
    and the `currentDecision` honouring;
  - `handleDrop` — drag-and-drop interception;
  - `handleBeforeInput` — the `beforeinput` backstop for IME/other paste paths;
  - the throttled event-logging path of `reportBlock` (`shouldLogEvents` →
    `logBlock`). The server-side external function `tiny_pasteguard_log_block` is
    covered by PHPUnit (`tests/external/log_block_test.php`), but the JS that
    calls and throttles it is not.
- **Incidental only:** `recordDecision` and `currentDecision` are invoked inside
  `handleNativePaste`, but their purpose — a native verdict being read back by
  `handlePastePreprocess`, and `currentDecision`'s non-null (within-TTL) branch —
  is not exercised by any automated test.

Net: the internal-marker / cross-site behaviour, drag-and-drop, the beforeinput
backstop, and paste logging rest on the manual checks below, not on CI.

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
