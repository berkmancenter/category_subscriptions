<?php function cat_subscribe_show_profile_fields( $user ) { ?>
        <h3><?php _e('Email Updates') ?></h3>
        <table class="form-table">
                <tr>
                        <th><label for="category_subscription_categories"><?php _e("Please select the types of updates you'd like to receive") ?></label></th>
                        <td>
                                <input type="text" name="category_subscription_categories" id="category_subscription_categories" value="<?php esc_attr( get_the_author_meta( 'category_subscription_categories', $user->ID ) ) ?>" class="regular-text" /><br />
                                <span class="description"><?php _e("Please select the categories you'd like to get updates from.") ?></span>
                        </td>
                </tr>
        </table>
<?php } ?> 

<?php

add_action( 'edit_user_profile', 'cat_subscribe_show_profile_fields' );
add_action( 'profile_personal_options', 'cat_subscribe_show_profile_fields' );

?>
