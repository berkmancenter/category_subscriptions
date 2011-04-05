Given 'a logged in user of type "$user_type"' do |user_type|
  case user_type
  when 'subscriber'
    visit('/wp-login.php')
    fill_in('Username', :with => "testsubscriber")
    fill_in('Password', :with => "testsubscriber")
    click_button('Log In')
    raise Wordpress::LogInError unless page.has_content?('Personal Options')
  when 'administrator'
    visit('/wp-login.php')
    fill_in('Username', :with => "admin")
    fill_in('Password', :with => "admin")
    click_button('Log In')
    raise Wordpress::LogInError unless page.has_content?('Personal Options')
  end
end

When 'I visit "$a_page"' do |a_page|
  visit a_page
end

Then 'I should see "$text"' do |text|
    raise Wordpress::ContentError unless page.has_content?(text)
end

When 'I check "$field"' do |field|
  check(field)
end

When 'I uncheck "$field"' do |field|
  uncheck(field)
end

When 'I press "$button"' do |button|
  click_button(button)
end

Then 'the checkbox "$field" should be checked' do |field|
  raise Wordpress::CheckboxNotChecked unless find_field(field)['checked']
end
