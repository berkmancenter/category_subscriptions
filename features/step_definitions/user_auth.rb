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
    raise Wordpress::LogInError unless page.has_content?('Howdy, admin')
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

Then 'the checkbox "$field" should not be checked' do |field|
  raise Wordpress::CheckboxChecked if find_field(field)['checked']
end

When 'I click "$link" within the row with the id "$id"' do |link,id|
  find('#' + id).click_link(link)
end

Given 'a "$plugin_state" plugin in the row with the id "$id"' do |plugin_state,id|
  # This goes too fast for some browsers.
  set_speed(:medium)
  visit('/wp-login.php')
  fill_in('Username', :with => "admin")
  fill_in('Password', :with => "admin")
  click_button('Log In')
  visit('/wp-admin/plugins.php')
  selector = '#' + id + ' span.' + ((plugin_state == 'Activated') ? 'deactivate' : 'activate') 
  if has_no_selector?(selector)
    if plugin_state == 'Activated'
      find('#' + id).click_link('Activate')
    else
      find('#' + id).click_link('Deactivate')
    end
  end
  click_link('Log Out')
end
