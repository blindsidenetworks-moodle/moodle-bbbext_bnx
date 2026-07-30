@bbbext @bbbext_bnx @mod @mod_bigbluebuttonbn @javascript
Feature: Guest users can join meetings via BNX guest flow

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
      | username | firstname | lastname | email                 |
      | traverst | Terry     | Travers  | t.travers@example.com |
      | teacher  | Teacher   | Teacher  | t.eacher@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | traverst | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "activity" exists:
      | course       | C1                  |
      | activity     | bigbluebuttonbn     |
      | name         | Room recordings     |
      | idnumber     | Room recordings     |
      | moderators   | role:editingteacher |
      | wait         | 0                   |
      | guestallowed | 1                   |

  Scenario: Guest users should be able to join a meeting as guest when the meeting is running
    When I am on the "Room recordings" Activity page logged in as traverst
    And "Join session" "link" should exist
    And I click on "Join session" "link"
    And I switch to the main window
    And I log out
    And I close all opened windows
    And I open the BNX guest page for "Room recordings"
    Then I should see "Guest username"
    And I should see "Password"
    And I set the field "username" to "Test Guest User"
    And I click on "Join meeting" "button"
    And I switch to "bigbluebutton_conference" window
    And I wait until the page is ready
    And I should see "Test Guest User"
