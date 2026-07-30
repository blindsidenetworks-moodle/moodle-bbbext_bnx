@bbbext @bbbext_bnx @mod @mod_bigbluebuttonbn @javascript
Feature: Guest password validation on the BNX guest login page
  As the BNX maintainer
  I want the guest-login form to reject wrong passwords and accept correct ones
  So that OL-3.2.6 (timing/type-safe password compare) stays locked in

  Background:
    Given a BigBlueButton mock server is configured
    And I enable "bigbluebuttonbn" "mod" plugin
    And the bbbext "bnx" plugin is enabled
    And the following config values are set as admin:
      | bigbluebuttonbn_guestaccess_enabled | 1 |
    And the following course exists:
      | name      | Test course |
      | shortname | C1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher  | Teacher   | Teacher  | t.eacher@example.com |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "activity" exists:
      | course       | C1                  |
      | activity     | bigbluebuttonbn     |
      | name         | BNX guest room      |
      | idnumber     | BNX guest room      |
      | moderators   | role:editingteacher |
      | wait         | 0                   |
      | guestallowed | 1                   |

  Scenario: An obviously-wrong guest password is rejected with the standard error
    When I open the BNX guest page without a password for "BNX guest room"
    Then I should see "Guest username"
    And I should see "Password"
    And I set the field "username" to "Wrong Password Tester"
    And I set the field "password" to "definitely-not-the-password"
    And I click on "Join meeting" "button"
    Then I should see "Incorrect password"

  Scenario: The numeric string "0" is rejected against a real guest password
    # OL-3.2.6 canonical type-juggling vector: the form must NOT accept "0" as a
    # match against a non-empty expected password.
    When I open the BNX guest page without a password for "BNX guest room"
    And I set the field "username" to "Zero String Tester"
    And I set the field "password" to "0"
    And I click on "Join meeting" "button"
    Then I should see "Incorrect password"

  Scenario: An empty guest password is rejected by client-side validation
    When I open the BNX guest page without a password for "BNX guest room"
    And I set the field "username" to "Empty Password Tester"
    And I click on "Join meeting" "button"
    Then I should see "Required"
