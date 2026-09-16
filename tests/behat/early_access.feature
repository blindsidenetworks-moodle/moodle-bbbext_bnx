@mod @mod_bigbluebuttonbn @bbbext @bbbext_bnx
Feature: Early access to BigBlueButton sessions
  In order to prepare a session before the scheduled opening time
  As a teacher with early access capability
  I need to be able to join the session early when early access is enabled

  Background:
    Given a BigBlueButton mock server is configured
    And I enable "bigbluebuttonbn" "mod" plugin
    And the following "courses" exist:
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

  @javascript
  Scenario Outline: Early access visibility depends on user role
    Given the following config values are set as admin:
      | earlyaccess_default  | 1 | bbbext_bnx |
      | earlyaccess_editable | 1 | bbbext_bnx |
    And the following "activities" exist:
      | course | activity        | name       | openingtime         |
      | C1     | bigbluebuttonbn | Early Room | ##now +30 minutes## |
    When I am on the "Early Room" "bigbluebuttonbn activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Allow room access before opening time" to "1"
    And I press "Save and display"
    And I log out
    And I am on the "Early Room" "bigbluebuttonbn activity" page logged in as <user>
    Then "Join session" "link" <joinvisibility>
    And I <messagevisibility> "You have early access to prepare this room"

    Examples:
      | user     | joinvisibility   | messagevisibility |
      | teacher1 | should exist     | should see        |
      | student1 | should not exist | should not see    |

  @javascript
  Scenario: Early access checkbox is disabled when opening time is not set
    Given the following config values are set as admin:
      | earlyaccess_default  | 0 | bbbext_bnx |
      | earlyaccess_editable | 1 | bbbext_bnx |
    And the following "activities" exist:
      | course | activity        | name       |
      | C1     | bigbluebuttonbn | Early Room |
    When I am on the "Early Room" "bigbluebuttonbn activity editing" page logged in as teacher1
    And I expand all fieldsets
    Then the "earlyaccess" "checkbox" should be disabled
