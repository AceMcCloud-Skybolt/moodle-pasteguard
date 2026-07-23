@local @local_pasteguard
Feature: PasteGuard activity setting is offered only for supported modules
  In order to control where paste blocking applies
  As a teacher
  I need the PasteGuard checkbox on supported activity types only

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name       | course | idnumber |
      | forum    | Test forum | C1     | forum1   |
      | wiki     | Test wiki  | C1     | wiki1    |

  Scenario: The checkbox appears on a supported module
    Given I am on the "Test forum" "forum activity editing" page logged in as teacher1
    When I expand all fieldsets
    Then I should see "Block external pasting (PasteGuard)"

  Scenario: The checkbox does not appear on an unsupported module
    Given I am on the "Test wiki" "wiki activity editing" page logged in as teacher1
    When I expand all fieldsets
    Then I should not see "Block external pasting (PasteGuard)"

  Scenario: The checkbox is hidden when the site disables PasteGuard
    Given the following config values are set as admin:
      | enabled | 0 | tiny_pasteguard |
    And I am on the "Test forum" "forum activity editing" page logged in as teacher1
    When I expand all fieldsets
    Then I should not see "Block external pasting (PasteGuard)"
