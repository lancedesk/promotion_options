<?php
/* Add Settings Page */
function add_settings_page() 
{
    add_submenu_page(
        'edit.php?post_type=promotion_option',
        'Settings',
        'Settings',
        'manage_options',
        'promotion-options-settings',
        'settings_page'
    );
}
add_action('admin_menu', 'add_settings_page');

/* Display Settings Page */
function settings_page() 
{
    ?>
    <div class="wrap">
        <h1>Promotion Options Settings</h1>
        
        <!-- Default Validity Period -->
        <h2>Default Validity Period</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            settings_fields('promotion_options_settings_group');
            do_settings_sections('promotion_options_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Default Validity Period (days)</th>
                    <td>
                        <input type="number" name="default_validity_period" value="<?php echo esc_attr(get_option('default_validity_period')); ?>" />
                        <p class="description">Default validity period for new promotions.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_default_validity_period" />
            <?php submit_button('Save Default Validity Period'); ?>
        </form>
        
        <!-- Manage Validity Periods for Existing Promotions -->
        <h2>Manage Validity Periods for Existing Promotions</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            $args = array(
                'post_type' => 'promotion_option',
                'posts_per_page' => -1,
            );
            $promotion_options = get_posts($args);

            if ($promotion_options) 
            {
                echo '<table class="form-table">';
                foreach ($promotion_options as $post) 
                {
                    $validity = get_field('validity_period', $post->ID);
                    ?>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html($post->post_title); ?></th>
                        <td>
                            <input type="hidden" name="promotion_option_ids[]" value="<?php echo esc_attr($post->ID); ?>" />
                            <input type="number" name="validity_period_<?php echo esc_attr($post->ID); ?>" value="<?php echo esc_attr($validity); ?>" />
                            <p class="description">Validity period in days.</p>
                        </td>
                    </tr>
                    <?php
                }
                echo '</table>';
            }
            ?>
            <input type="hidden" name="action" value="save_validity_periods" />
            <?php submit_button('Save Validity Periods'); ?>
        </form>

        <!-- Manage Validity Periods and Prices for WooCommerce Products -->
        <h2>Manage Validity Periods and Prices for WooCommerce Products</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            $product_args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'id',
                        'terms'    => 470, /* ID of the "Promotion Options" category */
                    ),
                ),
            );
            $products = get_posts($product_args);

            if ($products) 
            {
                echo '<table class="form-table">';
                foreach ($products as $product) 
                {
                    $validity = get_field('validity_period', $product->ID);
                    $price = get_post_meta($product->ID, '_regular_price', true);
                    ?>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html($product->post_title); ?></th>
                        <td>
                            <input type="hidden" name="product_ids[]" value="<?php echo esc_attr($product->ID); ?>" />
                            <input type="number" name="product_validity_period_<?php echo esc_attr($product->ID); ?>" value="<?php echo esc_attr($validity); ?>" />
                            <p class="description">Validity period in days.</p>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="product_price_<?php echo esc_attr($product->ID); ?>" value="<?php echo esc_attr($price); ?>" />
                            <p class="description">Regular price.</p>
                        </td>
                    </tr>
                    <?php
                }
                echo '</table>';
            }
            ?>
            <input type="hidden" name="action" value="save_product_details" />
            <?php submit_button('Save Product Details'); ?>
        </form>

        <!-- Charge for New Listings Settings -->
        <h2>Charge for New Listings</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Charge for New Listings</th>
                    <td>
                        <input type="checkbox" name="charge_for_new_listings" value="1" <?php checked(1, get_option('charge_for_new_listings'), true); ?> />
                        <label for="charge_for_new_listings">Enable charge for new listings</label>
                        <br />
                        <br />
                        <input type="number" name="new_listing_charge_amount" value="<?php echo esc_attr(get_option('new_listing_charge_amount')); ?>" step="0.01" />
                        <p class="description">Amount to charge for new listings.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_charge_settings" />
            <?php submit_button('Save Charge Settings'); ?>
        </form>
    </div>
    <?php
}

/* Register Settings */
function register_settings() 
{
    register_setting('promotion_options_settings_group', 'default_validity_period');
    register_setting('promotion_options_settings_group', 'charge_for_new_listings');
    register_setting('promotion_options_settings_group', 'new_listing_charge_amount');
}
add_action('admin_init', 'register_settings');

/* Save Validity Periods and Product Details */
function save_validity_period() 
{
    if (!current_user_can('manage_options')) 
    {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['promotion_option_ids'])) 
    {
        foreach ($_POST['promotion_option_ids'] as $id) 
        {
            if (isset($_POST["validity_period_$id"])) 
            {
                $validity = sanitize_text_field($_POST["validity_period_$id"]);
                update_field('validity_period', $validity, $id);
            }
        }
    }

    if (isset($_POST['product_ids'])) 
    {
        foreach ($_POST['product_ids'] as $id) 
        {
            if (isset($_POST["product_validity_period_$id"])) 
            {
                $validity = sanitize_text_field($_POST["product_validity_period_$id"]);
                update_field('validity_period', $validity, $id);
            }
            if (isset($_POST["product_price_$id"])) 
            {
                $price = sanitize_text_field($_POST["product_price_$id"]);
                update_post_meta($id, '_regular_price', $price);
                update_post_meta($id, '_price', $price);
            }
        }
    }

    update_option('charge_for_new_listings', isset($_POST['charge_for_new_listings']) ? 1 : 0);
    update_option('new_listing_charge_amount', sanitize_text_field($_POST['new_listing_charge_amount']));

    wp_redirect(admin_url('edit.php?post_type=promotion_option&page=promotion-options-settings&updated=true'));
    exit;
}
add_action('admin_post_save_validity_periods', 'save_validity_period');
add_action('admin_post_save_product_details', 'save_validity_period');

/* Save default Validity */
function save_default_validity_period() 
{
    if (!current_user_can('manage_options')) 
    {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $default_validity_period = sanitize_text_field($_POST['default_validity_period']);
    update_option('default_validity_period', $default_validity_period);

    wp_redirect(admin_url('edit.php?post_type=promotion_option&page=promotion-options-settings&updated=true'));
    exit;
}
add_action('admin_post_save_default_validity_period', 'save_default_validity_period');

/* Save Charge Settings */
function save_charge_settings() 
{
    if (!current_user_can('manage_options')) 
    {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    update_option('charge_for_new_listings', isset($_POST['charge_for_new_listings']) ? 1 : 0);
    update_option('new_listing_charge_amount', sanitize_text_field($_POST['new_listing_charge_amount']));

    wp_redirect(admin_url('edit.php?post_type=promotion_option&page=promotion-options-settings&updated=true'));
    exit;
}
add_action('admin_post_save_charge_settings', 'save_charge_settings');
?>