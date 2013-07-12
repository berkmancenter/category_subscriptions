Feature: Allow admins to activate the plugin, manage options and various other items.

Scenario: Activate the plugin without errors.
    Given a "Deactivated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/plugins.php"
    And I click "Activate" within the row with the id "category-subscriptions"
    Then I should see "Plugin activated"

Scenario: Deactivate the plugin without errors.
    Given a "Activated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/plugins.php"
    And I click "Deactivate" within the row with the id "category-subscriptions"
    Then I should see "Plugin deactivated"

Scenario: Manage options for the plugin
    Given a "Activated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/options-general.php?page=category-subscriptions-config"
    And I click "#individual_toggler"
    And I click "#daily_toggler"
    And I fill in "Subject line template for individual emails" with "foo test individual subject line"
    And I fill in "HTML email template for individual emails" with "html email template individual"
    And I fill in "Plain text email template for individual emails" with "individual plain text"
    And I fill in "Subject line template for daily emails" with "foo test daily subject line"
    And I press "Update Options"
    Then I should see "Configure Category Subscriptions"
    And I should see "Maximum outgoing email batch size"
    And I should see "Individual Emails"
    And the "Subject line template for individual emails" field should contain "foo test individual subject line"
    And the "Subject line template for daily emails" field should contain "foo test daily subject line"
    And the "HTML email template for individual emails" field should contain "html email template individual"
    And the "Plain text email template for individual emails" field should contain "individual plain text"

@wip
Scenario: Create a post and see that it's scheduled in the backend.
    Given a "Activated" plugin in the row with the id "category-subscriptions"
    And a logged in user of type "administrator"
    When I visit "/wp-admin/post-new.php"
    And I click "#edButtonHTML"
    And I fill in "title" with "A new post"
    And I fill in "content" with "Some new content"
    And I wait "3" seconds
    And I ensure the "Categories" post edit page panel is visible
    And I check "in-category-3"
    And I check "in-category-4"
    And I press "Publish"
    Then I should see "Post published"
    

