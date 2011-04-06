Feature: Allow admins to activate the plugin, manage options and various other items.

@wip
Scenario: Activate the plugin without errors.
    Given a "Deactivated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/plugins.php"
    And I click "Activate" within the row with the id "category-subscriptions"
    Then I should see "Plugin activated"

Scenario: Deactive the plugin without errors.
    Given a "Activated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/plugins.php"
    And I click "Deactivate" within the row with the id "category-subscriptions"
    Then I should see "Plugin deactivated"

