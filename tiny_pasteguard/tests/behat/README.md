# Behat notes for tiny_pasteguard

Real OS clipboard access is unreliable under Selenium/WebDriver, so these
scenarios must not depend on the actual clipboard. The custom steps used by
`pasteguard.feature` are implemented in `behat_tiny_pasteguard.php`, driving
the editor with `execute_script` and synthetic events:

- **"I simulate pasting :text into the :field TinyMCE editor"** — build a
  `DataTransfer`, call `setData('text/plain', text)` (and a matching
  `text/html` payload for the HTML-mismatch cases), then dispatch a
  `ClipboardEvent('paste', {clipboardData, bubbles: true, cancelable: true})`
  on the editor body inside the iframe.
- **"I simulate copying the selection :text in the :field TinyMCE editor"** —
  select the text via `editor.selection` and dispatch a synthetic
  `ClipboardEvent('copy')` so the plugin records its internal allowlist entry.
- **"the :field TinyMCE editor should (not) contain :text"** — compare against
  `editor.getContent({format: 'text'})`.
- **"PasteGuard is enabled for the :idnumber activity"** — insert the
  `local_pasteguard` row for the cm via the data generator.

Additional cases to add over time (see TESTING.md for the manual
equivalents): cross-editor paste between two essay questions in one
quiz attempt, styled Word HTML paste, drag-and-drop from outside the page, and
draft restore not being blocked.

Local Behat runs are currently blocked pending `behat_*` config on the dev box
(same status as local_unittours); the suite is written to run in CI.
