@bbbext @bbbext_bnx @mod @mod_bigbluebuttonbn @javascript
Feature: Recordings table on a BNX-enabled BigBlueButton activity
  As an admin
  I want the recordings table to list, rename and edit recordings on a BNX-enabled instance
  So that the BNX `recording` overrides (including OL-3.1.8 SQL parameter merge fix)
  do not regress the user-facing flow

  Background:
    Given a BigBlueButton mock server is configured
    And I enable "bigbluebuttonbn" "mod" plugin
    And the bbbext "bnx" plugin is enabled
    And the following "courses" exist:
      | fullname      | shortname | category |
      | Test Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity        | name           | intro                           | course | idnumber         | type | recordings_imported |
      | bigbluebuttonbn | BnxRoom        | BNX recordings smoke            | C1     | bigbluebuttonbn1 | 0    | 0                   |
    And the following "mod_bigbluebuttonbn > meeting" exists:
      | activity | BnxRoom |
    And the following "mod_bigbluebuttonbn > recordings" exist:
      | bigbluebuttonbn | name        | description   | status |
      | BnxRoom         | Recording 1 | Description 1 | 2      |
      | BnxRoom         | Recording 2 | Description 2 | 3      |

  Scenario: Recordings list renders on a BNX-enabled instance
    Given I am on the "BnxRoom" "bigbluebuttonbn activity" page logged in as admin
    Then "Recording 1" "table_row" should exist
    And "Recording 2" "table_row" should exist

  Scenario: A recording can be renamed on a BNX-enabled instance
    Given I am on the "BnxRoom" "bigbluebuttonbn activity" page logged in as admin
    When I set the field "Edit name" in the "Recording 1" "table_row" to "Renamed by BNX smoke"
    Then I should see "Renamed by BNX smoke"
    And I should see "Recording 2"
    And I reload the page
    And I should see "Renamed by BNX smoke"
    And I should see "Recording 2"

  Scenario: A recording description can be edited on a BNX-enabled instance
    Given I am on the "BnxRoom" "bigbluebuttonbn activity" page logged in as admin
    When I set the field "Edit description" in the "Recording 1" "table_row" to "BNX-edited description"
    Then I should see "BNX-edited description"
    And I reload the page
    And I should see "BNX-edited description" in the "Recording 1" "table_row"
    And I should see "Description 2" in the "Recording 2" "table_row"
