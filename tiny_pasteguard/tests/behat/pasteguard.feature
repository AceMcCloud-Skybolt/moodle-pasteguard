@editor @editor_tiny @tiny @tiny_pasteguard @javascript
Feature: PasteGuard blocks external pasting in protected activities
  In order to deter copy-paste of external content
  As a teacher
  I need pasting from outside the editor to be blocked while students keep their own text

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name       | course | idnumber |
      | forum    | Test forum | C1     | forum1   |

  Scenario: Teacher enables PasteGuard on a forum
    Given I am on the "Test forum" "forum activity editing" page logged in as teacher1
    When I expand all fieldsets
    And I set the field "Block external pasting (PasteGuard)" to "1"
    And I press "Save and display"
    And I am on the "Test forum" "forum activity editing" page
    And I expand all fieldsets
    Then the field "Block external pasting (PasteGuard)" matches value "1"

  Scenario: External paste is blocked with a message
    Given PasteGuard is enabled for the "forum1" activity
    And I am on the "Test forum" "forum activity" page logged in as student1
    And I press "Add discussion topic"
    # Simulate an external paste: dispatch a synthetic ClipboardEvent carrying
    # text never copied inside the editor (see tests/behat/README for helpers).
    When I simulate pasting "External AI-generated text" into the "Message" TinyMCE editor
    Then I should see "Pasting from outside this editor is disabled"
    And the "Message" TinyMCE editor should not contain "External AI-generated text"

  Scenario: Internal copy and paste is allowed
    Given PasteGuard is enabled for the "forum1" activity
    And I am on the "Test forum" "forum activity" page logged in as student1
    And I press "Add discussion topic"
    And I type "My own words" into the "Message" TinyMCE editor
    When I simulate copying the selection "My own words" in the "Message" TinyMCE editor
    And I simulate pasting "My own words" into the "Message" TinyMCE editor
    Then the "Message" TinyMCE editor should contain "My own words My own words"

  Scenario: A user with the bypass capability pastes freely
    Given PasteGuard is enabled for the "forum1" activity
    And the following "roles" exist:
      | shortname        | name              | archetype |
      | pasteguardexempt | PasteGuard Exempt |           |
    And the following "permission overrides" exist:
      | capability            | permission | role             | contextlevel | reference |
      | tiny/pasteguard:bypass | Allow      | pasteguardexempt | Activity module | forum1 |
    And the following "role assigns" exist:
      | user     | role             | contextlevel    | reference |
      | student1 | pasteguardexempt | Activity module | forum1    |
    And I am on the "Test forum" "forum activity" page logged in as student1
    And I press "Add discussion topic"
    When I simulate pasting "External text" into the "Message" TinyMCE editor
    Then the "Message" TinyMCE editor should contain "External text"
