@mod @mod_bigbluebuttonbn @bbbext @bbbext_bnx @javascript
Feature: Manage BigBlueButton presentations
  In order to preload more than one document for a session
  As an activity editor
  I need to manage presentation files in the BNX activity form

  Background:
    Given I enable "bigbluebuttonbn" "mod" plugin
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following config values are set as admin:
      | bigbluebuttonbn_preuploadpresentation_editable | 1  |            |
      | maxfiles                                       | 10 | bbbext_bnx |
    And the following "activities" exist:
      | course | activity        | name       |
      | C1     | bigbluebuttonbn | BNX Room   |

  @_file_upload
  Scenario: Editor uploads a presentation that remains available on edit
    Given I change window size to "large"
    And I am on the "BNX Room" "bigbluebuttonbn activity editing" page logged in as teacher1
    And I expand all fieldsets
    When I upload "lib/tests/fixtures/upload_users.csv" file to "Select presentation files" filemanager
    And I press "Save and display"
    And I am on the "BNX Room" "bigbluebuttonbn activity editing" page
    And I expand all fieldsets
    Then I should see "upload_users.csv"
