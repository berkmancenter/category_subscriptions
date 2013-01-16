Feature: Allow users to manage their category subscriptions
    In order to allow users to subscribe to specific categories in this wordpress blog, we need to give users 
    the ability to manage their subscriptions in a logical way.

Scenario: The options appear on the profile page and a user can see them.
  Given a "Activated" plugin in the row with the id "category-subscriptions"  
  And a logged in user of type "subscriber"
  When I visit "/wp-admin/profile.php"
  Then I should see "Email Updates"

Scenario: As a user I can check a category and have it saved.
  Given a "Activated" plugin in the row with the id "category-subscriptions"  
  And a logged in user of type "subscriber"
  When I visit "/wp-admin/profile.php"
  And I check "Test Category"
  And I press "Update Profile"
  Then I should see "User updated"
  And the checkbox "Test Category" should be checked

Scenario: As a user I can uncheck a category and have it removed.
  Given a "Activated" plugin in the row with the id "category-subscriptions"  
  And a logged in user of type "subscriber"
  When I visit "/wp-admin/profile.php"
  And I uncheck "Test Category"
  And I press "Update Profile"
  Then I should see "User updated"
  And the checkbox "Test Category" should not be checked
