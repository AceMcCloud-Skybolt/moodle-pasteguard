# PasteGuard for Moodle

Blocks pasting of **external** content into TinyMCE editor areas in selected
activities, while permitting cut/copy/paste of the student's own text within
the editor session. A deterrent against AI/web copy-paste in **short writing
tasks** — forum posts, online-text assignment responses, quiz essay questions.

Two plugins, versioned in lockstep in this monorepo:

| Directory | Plugin | Deploys to |
|---|---|---|
| `tiny_pasteguard/` | TinyMCE editor plugin (interception, settings, logging) | `public/lib/editor/tiny/plugins/pasteguard` |
| `local_pasteguard/` | Companion (per-activity setting, storage, backup) | `public/local/pasteguard` |

Target: Moodle 5.1.x (branch 501), PHP 8.2+. License: GPL v3 or later.

## What it does / doesn't do

**Does:** blocks pasting text from outside Moodle (web pages, AI tools, Word)
into the online editor for activities where a teacher turns it on; allows
students to freely move their own text within the editor and between editors
on the same page; supports individual exemptions via a role for accessibility
needs; optionally records *when* a paste was blocked (never *what* was
pasted).

**Doesn't:** stop a student reading AI output on another screen and retyping;
stop AI use on file-upload submissions; protect quiz short-answer fields
(phase 2); replace supervised conditions for high-stakes assessment; suit
long-form writing tasks — intended for short posts and responses only.

## Honest limitations — do not oversell

This is a **deterrent, not a wall**. It is trivially bypassed by retyping
from a second screen, browser devtools, extensions, or disabling JavaScript
(the unprotected plain-textarea fallback then applies). It blocks *transfer*
of content, not *access* to AI — that remains Safe Exam Browser / supervised
assessment territory. Institutional guidance: short tasks only, not
essays/reports.

## How it works

1. A site administrator chooses which activity modules offer the toggle
   (*Site administration → Plugins → Text editors → TinyMCE → PasteGuard*).
   Default: assign, forum, quiz, lesson.
2. A teacher ticks **Block external pasting (PasteGuard)** in an activity's
   settings (Academic integrity section). Enablement is **per activity
   instance only** — there is no course- or site-wide blanket switch.
3. In protected editors, the plugin keeps a page-scoped record of the most
   recent cut/copy made *inside* any protected editor. A paste is allowed
   only when its normalised text exactly matches that record; anything else
   (including drag-and-drop from outside the page) is cancelled with a
   student-facing message. Copying between two protected editors on the same
   page (e.g. two essay questions in one quiz attempt) is allowed by design.
4. For `assign` the toggle only affects the *online text* submission type.

## Exemptions (accessibility / equity)

Create a role (e.g. "PasteGuard Exempt") with the `tiny/pasteguard:bypass`
capability, assignable at course or activity context, and assign it per
student as needed. The capability is deliberately granted to no archetype by
default — exemption is a deliberate act. Exemptions are invisible to peers
and auditable via standard role-assignment logs.

## Privacy

No keystroke capture, no biometrics, no external services, no ML. The
optional *Log blocked pastes* setting (default **off**) writes a standard
Moodle event recording user, time, activity context and the **character count
only** — the blocked content itself is never stored or transmitted.

## Installation

Copy `tiny_pasteguard` to `public/lib/editor/tiny/plugins/pasteguard` and
`local_pasteguard` to `public/local/pasteguard`, then run the upgrade:

```
php admin/cli/upgrade.php
```

On the local dev box, `deploy.ps1` does the copy and purges caches.

Note: `amd/build` currently contains hand-transpiled AMD modules; when a
grunt toolchain is available, rebuild with `grunt amd --root=lib/editor/tiny/plugins/pasteguard`.

## Testing

See `TESTING.md` for the manual browser checklist. PHPUnit and Behat suites
live under each plugin's `tests/` directory and run in CI (GitHub Actions,
moodle-plugin-ci, `--max-warnings 0`).

## Phase 2 (not built)

`quizaccess_pasteguard`: a quiz access rule extending the same allowlist
model to plain `<input>`/`<textarea>` question fields (short answer,
gapfill), with the same bypass semantics.
