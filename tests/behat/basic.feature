@bbbext @bbbext_bnx
Feature: Basic tests for BigBlueButton BN Experience

  @javascript
  Scenario: Plugin bbbext_bnx appears in the list of installed additional plugins
    Given I log in as "admin"
    When I navigate to "Plugins > Plugins overview" in site administration
    And I follow "Additional plugins"
    Then I should see "BigBlueButton BN Experience"
    And I should see "bbbext_bnx"
