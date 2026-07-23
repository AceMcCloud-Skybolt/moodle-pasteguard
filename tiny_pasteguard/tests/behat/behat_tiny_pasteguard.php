<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL check: this is a behat step definition file.

require_once(__DIR__ . '/../../../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for tiny_pasteguard.
 *
 * Real OS clipboard access is unreliable under WebDriver, so paste and copy
 * are simulated with synthetic ClipboardEvents dispatched into the editor.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tiny_pasteguard extends behat_base {
    /**
     * JS expression resolving the TinyMCE editor instance for a field label.
     *
     * @param string $field The field label, e.g. "Message".
     * @return string JS expression (expects a `label` binding, returns `editor`).
     */
    private function editor_lookup_js(string $field): string {
        $label = json_encode($field);
        return <<<JS
            const label = Array.from(document.querySelectorAll('label'))
                .find((node) => node.textContent.trim().startsWith({$label}));
            if (!label) {
                throw new Error('No label found for field ' + {$label});
            }
            const editor = window.tinymce.get(label.getAttribute('for'));
            if (!editor) {
                throw new Error('No TinyMCE editor for field ' + {$label});
            }
JS;
    }

    /**
     * Enable PasteGuard for an activity identified by idnumber.
     *
     * @Given PasteGuard is enabled for the :idnumber activity
     * @param string $idnumber The activity idnumber.
     */
    public function pasteguard_is_enabled_for_activity(string $idnumber): void {
        global $DB;

        $cmid = $DB->get_field('course_modules', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        \local_pasteguard\api::set_for_cm((int) $cmid, true);
    }

    /**
     * Dispatch a synthetic external paste into an editor.
     *
     * @When I simulate pasting :text into the :field TinyMCE editor
     * @param string $text The pasted plain text.
     * @param string $field The field label.
     */
    public function i_simulate_pasting_into_editor(string $text, string $field): void {
        $textjs = json_encode($text);
        $this->execute_script($this->editor_lookup_js($field) . <<<JS
            const data = new DataTransfer();
            data.setData('text/plain', {$textjs});
            data.setData('text/html', '<p>' + {$textjs} + '</p>');
            const event = new ClipboardEvent('paste', {
                clipboardData: data,
                bubbles: true,
                cancelable: true,
            });
            editor.getBody().dispatchEvent(event);
            // Mirror the browser default for uncancelled synthetic pastes,
            // since WebDriver cannot perform the real insertion.
            if (!event.defaultPrevented) {
                editor.execCommand('mceInsertContent', false, {$textjs});
            }
JS);
    }

    /**
     * Select the given text in the editor and dispatch a synthetic copy.
     *
     * @When I simulate copying the selection :text in the :field TinyMCE editor
     * @param string $text The text to select and copy.
     * @param string $field The field label.
     */
    public function i_simulate_copying_selection(string $text, string $field): void {
        $textjs = json_encode($text);
        $this->execute_script($this->editor_lookup_js($field) . <<<JS
            const body = editor.getBody();
            const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
            let node;
            let start = -1;
            while ((node = walker.nextNode())) {
                start = node.textContent.indexOf({$textjs});
                if (start !== -1) {
                    break;
                }
            }
            if (start === -1) {
                throw new Error('Text not found in editor: ' + {$textjs});
            }
            const range = editor.dom.createRng();
            range.setStart(node, start);
            range.setEnd(node, start + {$textjs}.length);
            editor.selection.setRng(range);
            body.dispatchEvent(new ClipboardEvent('copy', {bubbles: true, cancelable: true}));
JS);
    }

    /**
     * Type text into the editor (programmatic insert at the caret).
     *
     * @When I type :text into the :field TinyMCE editor
     * @param string $text The text to type.
     * @param string $field The field label.
     */
    public function i_type_into_editor(string $text, string $field): void {
        $textjs = json_encode($text);
        $this->execute_script($this->editor_lookup_js($field) . <<<JS
            editor.focus();
            editor.execCommand('mceInsertContent', false, {$textjs});
JS);
    }

    /**
     * Assert the editor's plain text contains the given text.
     *
     * @Then the :field TinyMCE editor should contain :text
     * @param string $field The field label.
     * @param string $text The expected text.
     */
    public function editor_should_contain(string $field, string $text): void {
        $content = $this->get_editor_text($field);
        if (!str_contains($this->collapse($content), $this->collapse($text))) {
            throw new ExpectationException(
                "Editor '{$field}' does not contain '{$text}'. Content: {$content}",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the editor's plain text does not contain the given text.
     *
     * @Then the :field TinyMCE editor should not contain :text
     * @param string $field The field label.
     * @param string $text The forbidden text.
     */
    public function editor_should_not_contain(string $field, string $text): void {
        $content = $this->get_editor_text($field);
        if (str_contains($this->collapse($content), $this->collapse($text))) {
            throw new ExpectationException(
                "Editor '{$field}' unexpectedly contains '{$text}'.",
                $this->getSession()
            );
        }
    }

    /**
     * Get an editor's content as plain text.
     *
     * @param string $field The field label.
     * @return string
     */
    private function get_editor_text(string $field): string {
        return (string) $this->evaluate_script($this->editor_lookup_js($field) . <<<JS
            return editor.getContent({format: 'text'});
JS);
    }

    /**
     * Collapse whitespace for tolerant text comparison.
     *
     * @param string $text The text.
     * @return string
     */
    private function collapse(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
