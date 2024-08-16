<?php
/*
Plugin Name: Promotion Options
Description: A plugin to add and manage promotion options for listings.
Version: 2.3.4
Author: Robert June
Text Domain: promotion-options
*/

/* Ensure that WordPress functions are available */
if (!defined('ABSPATH')) 
{
    exit; /* Exit if accessed directly */
}

/* Path to default listing featured image */
if ( ! defined('DEFAULT_LISTING_IMAGE') )
{
    define('DEFAULT_LISTING_IMAGE', 'https://icehockeymarket.com/wp-content/plugins/directorypress/assets/images/no-thumbnail.jpg');
}

/* Include the necessary files */
require_once plugin_dir_path(__FILE__) . 'includes/class-sales-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-promotion-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-cron-jobs.php';
require_once plugin_dir_path(__FILE__) . 'includes/custom-post-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/helper-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/locations-ajax.php';

/* Instantiate the necessary classes */
$promotion_handler = new Promotion_Handler();
new Cron_Jobs();
$sales_manager = new Sales_Manager();

function enqueue_promotion_assets()
{
    $plugin_url = plugin_dir_url(__FILE__);
    /* Enqueue the CSS file */
    wp_enqueue_style('promotion-options-css', $plugin_url . 'css/promotion-options.css');
    /* Enqueue the JavaScript file, ensuring it's loaded in the footer */
    wp_enqueue_script('promotion-options-js', $plugin_url . 'js/promotion-options.js', array('jquery'), null, true);
    /* Generate a nonce for security */
    $nonce = wp_create_nonce('submit_review_nonce');
    /* Localize the script with the AJAX URL and nonce */
    wp_localize_script('promotion-options-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => $nonce
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_promotion_assets');

function add_promotion_options_to_submission_form($current_promotion_level)
{
	global $promotion_handler;

    $args = array(
        'post_type' => 'promotion_option',
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) 
    {
        $promotion_mapping = array(
            'Highlighted Listing' => '_is_highlighted',
            'Home Page Spotlight' => '_is_home_spotlight',
            'Urgent Listing' => '_is_urgent_listing',
            'Featured Listing' => '_is_featured',
            'Free Listing' => '_is_free_listing'
        );

        /* Handle cases where $current_promotion_level is null or empty */
        $promotion_levels = !empty($current_promotion_level) ? explode(',', $current_promotion_level) : array();
		
		/* Get new listing price and charging allowed status */
        $new_listing_price = $promotion_handler->get_new_listing_price();
        $charging_allowed = $promotion_handler->charging_allowed();

		echo '<div class="promotion-options" data-promotion-levels="' . esc_attr(implode(',', $promotion_levels)) . '"';
        echo ' data-new-listing-price="' . esc_attr($new_listing_price) . '"';
        echo ' data-charging-allowed="' . esc_attr($charging_allowed ? 'true' : 'false') . '">';

        while ($query->have_posts()) 
        {
            $query->the_post();
            $price = get_field('price'); /* ACF function to get the field value */
            $features = get_field('features'); /* ACF function to get the repeater field value */
            $title = get_the_title();
            $level = isset($promotion_mapping[$title]) ? $promotion_mapping[$title] : '';
			
			/* Skip _is_free_listing from the loop */
            if ($level === '_is_free_listing')
			{
                continue;
            }

            /* Check if the current level is already promoted */
            $is_promoted = in_array($level, $promotion_levels) ? ' already-promoted' : '';

            echo '<div class="promotion-option' . esc_attr($is_promoted) . '">';
            echo '<input type="checkbox" name="promotion_option[]" value="' . get_the_ID() . '" data-price="' . esc_html($price) . '" data-level="' . esc_attr($level) . '"';
            if (in_array($level, $promotion_levels)) {
                echo ' checked disabled';
            }
            echo '>';
            echo '<h4>' . $title . '</h4>';
            echo '<p>' . get_the_content() . '</p>';
            echo '<p>Price: €' . esc_html($price) . '</p>';
            if ($features) 
            {
                echo '<ul>';
                foreach ($features as $feature) 
                {
                    echo '<li>' . esc_html($feature['feature']) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '</div>';
		echo '<input type="hidden" name="new_item_charge" value="' . esc_attr($new_listing_price) . '">';
		echo '<div class="new-item-charge" style="display: none;">New item charge: €<span id="new-item-charge"></span></div>';
        echo '<div class="promotion-total">Total: €<span id="promotion-total-amount">0.0</span></div>';
    }

    wp_reset_postdata();
}
add_action('directorypress_listing_submit_promotion_options', 'add_promotion_options_to_submission_form');

/* Integrate with WooCommerce - Add promotion options to cart item data */
function add_promotion_option_to_cart($cart_item_data, $product_id)
{
    if (!empty($_POST['promotion_option'])) 
    {
        $promotion_options = $_POST['promotion_option'];
        $promotion_data = array();
        $total_price = 0; /* Initialize total price */

        foreach ($promotion_options as $option_id) 
        {
            $post_title = get_the_title($option_id);
            $price = get_field('price', $option_id);

            /* Ensure price is numeric before adding to total */
            if (is_numeric($price)) 
            {
                $total_price += floatval($price); /* Add price to total */
            } else {
                /* Handle non-numeric price scenarios */
                error_log('Non-numeric price encountered for option ID: ' . $option_id);
                continue; /* Skip this option if price is not numeric */
            }

            $promotion_data[] = array(
                'title' => $post_title,
                'price' => $price,
            );
        }

        /* Add total price to cart item data */
        $cart_item_data['promotion_options'] = $promotion_data;
        $cart_item_data['promotion_options_total'] = $total_price;
    }
	
	/* Handle new item charge */
    if (isset($_POST['new_item_charge']) && is_numeric($_POST['new_item_charge'])) 
    {
        $cart_item_data['new_item_charge'] = floatval($_POST['new_item_charge']);
    }

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_promotion_option_to_cart', 10, 2);

function display_promotion_options_in_cart($item_data, $cart_item)
{
    if (isset($cart_item['promotion_options'])) {
        $html = '<table class="promotion-options-table">';

        foreach ($cart_item['promotion_options'] as $promotion) {
            $html .= '<tr class="promotion-option-row">';
            $html .= '<td class="promotion-option-title">' . esc_html($promotion['title']) . '</td>';
            $html .= '<td class="promotion-option-price">€' . esc_html($promotion['price']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        /* Add the table to item data */
        $item_data[] = array(
            'name' => 'Promotion Options',
            'value' => $html,
            'display' => ''
        );
    }
	
	/* Display new item charge */
    if (isset($cart_item['new_item_charge']) && is_numeric($cart_item['new_item_charge'])) {
        $item_data[] = array(
            'name' => 'New Item Charge',
            'value' => '€' . esc_html($cart_item['new_item_charge']),
            'display' => ''
        );
    }

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_promotion_options_in_cart', 10, 2);

/* Update cart totals with promotion options total */
function update_cart_totals_with_promotion_options($total, $cart)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return $total;
    }

    /* Initialize promotion options & new items charge totals */
    $promotion_options_total = 0;
	$new_item_charge = 0;

    /* Loop through cart items to calculate promotion options total */
    foreach ($cart->cart_contents as $cart_item) {
        if (isset($cart_item['promotion_options_total'])) {
            /* Ensure promotion_options_total is numeric before adding */
            $promotion_options_total += is_numeric($cart_item['promotion_options_total']) ? floatval($cart_item['promotion_options_total']) : 0;
        }
		
		if (isset($cart_item['new_item_charge'])) {
            /* Ensure new_item_charge is numeric before adding */
            $new_item_charge += is_numeric($cart_item['new_item_charge']) ? floatval($cart_item['new_item_charge']) : 0;
        }
    }

    /* Add promotion options total & new items charge to cart total */
	$total += $promotion_options_total + $new_item_charge;

    return $total;
}
add_filter('woocommerce_calculated_total', 'update_cart_totals_with_promotion_options', 10, 2);

/* Display Promotion Options on the Order Details Page (Frontend) */
function display_promotion_options_in_order($item_id, $item, $order, $plain_text = false)
{
    $promotion_options = $item->get_meta('promotion_options');
    $promotion_options_total = $item->get_meta('promotion_options_total');
	$new_item_charge = $item->get_meta('new_item_charge');

    if ($promotion_options) {
        if ($plain_text) {
            echo "\n" . __('Promotion Options:', 'woocommerce') . "\n";
            foreach ($promotion_options as $promotion) {
                echo esc_html($promotion['title']) . ': €' . esc_html($promotion['price']) . "\n";
            }
			
			if ($new_item_charge) {
                echo __('New Item Charge:', 'woocommerce') . ' €' . esc_html($new_item_charge) . "\n";
            }
        } else {
            echo '<h4>' . __('Promotion Options:', 'woocommerce') . '</h4>';
            echo '<table class="promotion-options-table">';
            
            foreach ($promotion_options as $promotion) {
                echo '<tr class="promotion-option-row">';
                echo '<td class="promotion-option-title">' . esc_html($promotion['title']) . '</td>';
                echo '<td class="promotion-option-price">€' . esc_html($promotion['price']) . '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '<p><strong>' . __('Promotion Options Total:', 'woocommerce') . '</strong> €' . esc_html($promotion_options_total) . '</p>';
			
			if ($new_item_charge) {
                echo '<p><strong>' . __('New Item Charge:', 'woocommerce') . '</strong> €' . esc_html($new_item_charge) . '</p>';
            }
        }
    }
}
add_action('woocommerce_order_item_meta_end', 'display_promotion_options_in_order', 10, 4);

/* Save Promotion Options to Order Items */
function save_promotion_options_to_order_items($item, $cart_item_key, $values, $order)
{
    if (isset($values['promotion_options'])) {
        $item->update_meta_data('promotion_options', $values['promotion_options']);
    }

    if (isset($values['promotion_options_total'])) {
        $item->update_meta_data('promotion_options_total', $values['promotion_options_total']);
    }
	
	if (isset($values['new_item_charge']) && is_numeric($values['new_item_charge'])) {
        $item->update_meta_data('new_item_charge', floatval($values['new_item_charge']));
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'save_promotion_options_to_order_items', 10, 4);

/* Display Promotion Options in the Admin Order Details Page with Listing ID for Debugging */
function display_promotion_options_in_admin_order($item_id, $item, $product)
{
    $promotion_options = $item->get_meta('promotion_options');
    $promotion_options_total = $item->get_meta('promotion_options_total');
	$new_item_charge = $item->get_meta('new_item_charge');

    if ($promotion_options) {
        echo '<div class="view">';
        echo '<h4>' . __('Promotion Options:', 'woocommerce') . '</h4>';
        echo '<table class="promotion-options-table">';

        foreach ($promotion_options as $promotion) {
            echo '<tr class="promotion-option-row">';
            echo '<td class="promotion-option-title">' . esc_html($promotion['title']) . '</td>';
            echo '<td class="promotion-option-price">€' . esc_html($promotion['price']) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
    }

    if ($promotion_options_total) {
        echo '<p><strong>' . __('Promotion Options Total:', 'woocommerce') . '</strong> €' . esc_html($promotion_options_total) . '</p>';
    }
	
	if ($new_item_charge) {
        echo '<p><strong>' . __('New Item Charge:', 'woocommerce') . '</strong> €' . esc_html($new_item_charge) . '</p>';
    }
}
add_action('woocommerce_before_order_itemmeta', 'display_promotion_options_in_admin_order', 10, 3);

/* Filter to remove the promotion_options_total & new item charge meta keys from order item meta display */
function remove_promotion_options_total_meta($formatted_meta, $order_item)
{
    foreach ($formatted_meta as $index => $meta) {
		if ($meta->key === 'promotion_options_total' || $meta->key === 'new_item_charge') {
            unset($formatted_meta[$index]);
        }
    }
    return $formatted_meta;
}
add_filter('woocommerce_order_item_get_formatted_meta_data', 'remove_promotion_options_total_meta', 10, 2);

/* Hook into WooCommerce order status change to completed */
add_action('woocommerce_order_status_completed', 'add_marker_to_listing_on_order_completion');

function add_marker_to_listing_on_order_completion($order_id)
{
    global $wpdb;

    /* Map promotion options to their field names */
    $promotion_mapping = array(
        'Highlighted Listing' => '_is_highlighted',
        'Home Page Spotlight' => '_is_home_spotlight',
        'Urgent Listing' => '_is_urgent_listing',
        'Featured Listing' => '_is_featured',
        'Free Listing' => '_is_free_listing'
    );

    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) 
    {
        /* Extract listing ID from order item meta data */
        $listing_id = $item->get_meta('_directorypress_listing_id');

        if ($listing_id) 
        {
            /* Access promotion options */
            $promotion_options = $item->get_meta('promotion_options');
            $promotion_levels = array(); /* Array to store promotion levels */
            $promotion_dates = array(); /* Array to store expiry dates for each promotion */
            $has_free_listing = false;

            if (is_array($promotion_options)) 
            {
                foreach ($promotion_options as $promotion) 
                {
                    $promotion_title = $promotion['title'];
                    
                    /* Check if the promotion title is for "Free Listing" */
                    if ($promotion_mapping[$promotion_title] === '_is_free_listing') 
                    {
                        $has_free_listing = true;
                    }

                    /* Query promotion options post type to find matching title */
                    $args = array(
                        'post_type' => 'promotion_option',
                        'post_status' => 'publish',
                        'title' => $promotion_title,
                        'posts_per_page' => 1
                    );
                    $query = new WP_Query($args);

                    if ($query->have_posts()) 
                    {
                        $promotion_post = $query->posts[0];
                        $promotion_id = $promotion_post->ID;

                        /* Retrieve validity period from ACF field */
                        $validity_period = get_field('validity_period', $promotion_id);
                        
                        /* Log the retrieved validity period */
                        $log_message = "Retrieved validity_period for Promotion ID: $promotion_id is: " . $validity_period . "\n";
                        file_put_contents($log_file_path, $log_message, FILE_APPEND | LOCK_EX);

                        /* If validity period is not set in ACF, use a default (e.g., 30 days) */
                        if (!$validity_period) 
                        {
                            $validity_period = 30; /* Default validity period in days */
                        }

                        /* Calculate the expiry date based on validity period */
                        $listing_creation_date = get_post_time('Y-m-d H:i:s', true, $listing_id);
                        $expiry_date_utc = strtotime("+{$validity_period} days", strtotime($listing_creation_date));
                        $expiry_date = gmdate('d/m/Y H:i:s', $expiry_date_utc); /* Date in d/m/Y H:i:s format */

                        /* Add the promotion level and expiry date to their respective arrays */
                        $promotion_levels[] = $promotion_mapping[$promotion_title];
                        $promotion_dates[] = array(
                            'promotion_level' => $promotion_mapping[$promotion_title],
                            'expiration_date' => $expiry_date
                        );
                    }

                    /* Add the promotion title as a tag to the listing */
                    wp_set_post_tags($listing_id, esc_html($promotion_title), true);
                }

                /* Ensure that "_is_free_listing" is included if not already present */
                if (!$has_free_listing) 
                {
                    $promotion_levels[] = $promotion_mapping['Free Listing'];
                    $validity_period = get_field('validity_period', $listing_id);

                    if (!$validity_period) 
                    {
                        $validity_period = 30; /* Default validity period in days */
                    }

                    $expiry_date_utc = strtotime("+{$validity_period} days");
                    $expiry_date = gmdate('d/m/Y H:i:s', $expiry_date_utc); /* Date in d/m/Y H:i:s format */
                    
                    $promotion_dates[] = array(
                        'promotion_level' => $promotion_mapping['Free Listing'],
                        'expiration_date' => $expiry_date
                    );
                }

                /* Store all promotion levels as a comma-separated string */
                if (!empty($promotion_levels)) 
                {
                    $promotion_levels_string = implode(',', $promotion_levels);
                    update_post_meta($listing_id, 'promotion_level', esc_html($promotion_levels_string));
                }
            }

            /* Update all promotion expiration dates using the repeater field */
            if ($promotion_dates) 
            {
                update_field('marker_expiration_dates', $promotion_dates, $listing_id);
            }
        }
    }
}

/* On successful payment, mark virtual WooCommerce product orders as complete and handle promotions */
add_action('woocommerce_thankyou', 'custom_woocommerce_auto_complete_order');

function custom_woocommerce_auto_complete_order($order_id)
{
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);

    if ($order->get_status() == 'processing') {
        $virtual_order = true;

        /* Check each item in the order. */
        foreach ($order->get_items() as $item_id => $item) {
            if (!$item->get_product()->is_virtual()) {
                $virtual_order = false;
                break;
            }
        }

        /* If the order contains only virtual products, change status to completed. */
        if ($virtual_order) {
            $order->update_status('completed');

            /* Handle promotions for virtual product orders */
            handle_promotions_for_virtual_order($order_id);
        }
    }
}

/**
 * Function to handle promotions for virtual product orders.
 *
 * @param int $order_id The order ID.
 */
 
function handle_promotions_for_virtual_order($order_id)
{

    /* Map promotion options to their field names */
    $promotion_mapping = array(
        'Highlighted Listing' => '_is_highlighted',
        'Home Page Spotlight' => '_is_home_spotlight',
        'Urgent Listing' => '_is_urgent_listing',
        'Featured Listing' => '_is_featured',
        'Free Listing' => '_is_free_listing'
    );

    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item)
    {
        /* Extract product details from order item */
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : 'Unknown Product';

        if ($product_name && array_key_exists($product_name, $promotion_mapping))
        {
            $promotion_level = $promotion_mapping[$product_name];

            /* Retrieve validity period from WooCommerce product */
            $validity_period = get_field('validity_period', $product_id);
            if ($validity_period === null)
            {
                $validity_period = 30; /* Default validity period in days */
            }

            /* Get the listing ID from order meta */
            $listing_id = $item->get_meta('listing_id');
            
            if ($listing_id)
            {
                /* Calculate the expiry date and time based on current date and validity period */
                $current_datetime_utc = gmdate('Y-m-d H:i:s');
                $expiry_datetime_utc = strtotime("+{$validity_period} days", strtotime($current_datetime_utc));
                $expiry_datetime = gmdate('d/m/Y H:i:s', $expiry_datetime_utc); /* Combined date and time */
                $expiry_date = gmdate('d/m/Y', $expiry_datetime_utc);
                $expiry_time = gmdate('H:i:s', $expiry_datetime_utc);
                $expiry_date_text = "Promotion valid until {$expiry_date} {$expiry_time}";

                /* Retrieve existing promotions from the repeater field */
                $existing_repeater = get_field('marker_expiration_dates', $listing_id);
                $existing_levels = array();
                if ($existing_repeater)
                {
                    foreach ($existing_repeater as $row)
                    {
                        $existing_levels[$row['promotion_level']] = $row['expiration_date'];
                    }
                }

                /* Add or update the new promotion */
                $existing_levels[$promotion_level] = $expiry_datetime;

                /* Prepare the updated repeater field */
                $updated_repeater = array();
                foreach ($existing_levels as $level => $expiry_date)
                {
                    if (count($updated_repeater) >= 5)
                    {
                        break; /* Limit to a maximum of 5 promotions */
                    }
                    $updated_repeater[] = array(
                        'promotion_level' => $level,
                        'expiration_date' => $expiry_date
                    );
                }

                /* Update all promotion expiration dates and times using the repeater field */
                update_field('marker_expiration_dates', $updated_repeater, $listing_id);
                
                /* Update listing promotion levels */
                $current_promotion_levels = get_post_meta($listing_id, 'promotion_level', true);
                $current_promotion_levels = !empty($current_promotion_levels) ? explode(',', $current_promotion_levels) : array();

                /* Ensure no duplicates */
                if (!in_array($promotion_level, $current_promotion_levels))
                {
                    $current_promotion_levels[] = $promotion_level;
                    $current_promotion_levels = array_unique($current_promotion_levels);
                    $promotion_levels_string = implode(',', $current_promotion_levels);

                    update_post_meta($listing_id, 'promotion_level', esc_html($promotion_levels_string));
                }
            }
        }
    }
}

/* Add promotion to cart from the user dashboard */
add_action('wp_ajax_directorypress_add_promotion_to_cart', 'directorypress_add_promotion_to_cart');
function directorypress_add_promotion_to_cart()
{
    $options = isset($_POST['selected_options']) ? $_POST['selected_options'] : array();
    $response = array('success' => false, 'message' => 'No options provided');

    if (!empty($options)) {
        $added_any = false;
        $errors = array();

        $current_listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $current_promotion_level = get_post_meta($current_listing_id, 'promotion_level', true);
        $current_promotion_levels = !empty($current_promotion_level) ? explode(',', $current_promotion_level) : array();

        $listing_name = get_the_title($current_listing_id);

        /* Get WooCommerce products by title */
        $product_titles = array(
            'Featured Listing',
            'Highlighted Listing',
            'Urgent Listing',
            'Home Page Spotlight'
        );

        $product_ids = array();
        foreach ($product_titles as $title) {
            $product = get_page_by_title($title, OBJECT, 'product');
            if ($product) {
                $product_ids[$title] = $product->ID;
            }
        }

        /* Check if products are already in the cart */
        $cart_product_ids = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            $cart_product_ids[] = $cart_item['product_id'];
        }

        /* Add selected promotion options to cart */
        foreach ($options as $option_id) {
            $option_title = get_the_title($option_id);

            /* Skip already promoted options */
            $level = isset($promotion_mapping[$option_title]) ? $promotion_mapping[$option_title] : '';
            if (in_array($level, $current_promotion_levels)) {
                continue;
            }

            /* Check if the option title matches any WooCommerce product title */
            if (in_array($option_title, array_keys($product_ids))) {
                $product_id = $product_ids[$option_title];

                /* Add product to cart if not already present */
                if (!in_array($product_id, $cart_product_ids)) {
                    WC()->cart->add_to_cart($product_id, 1, '', '', array('listing_id' => $current_listing_id, 'listing_name' => $listing_name));
                    $added_any = true;
                }
            } else {
                $errors[] = "No matching WooCommerce product found for option: " . esc_html($option_title);
            }
        }

        if ($added_any) {
            $response['success'] = true;
            $response['message'] = 'Promotion options added to cart successfully.';

            $cart_items = WC()->cart->get_cart();
            $total_price = 0.0;
            foreach ($cart_items as $cart_item) {
                $total_price += $cart_item['data']->get_price() * $cart_item['quantity'];
            }

            $response['message'] .= ' Total: ' . wc_price($total_price);
        } else {
            $response['message'] = 'No options added to cart. Errors: ' . implode(', ', $errors);
        }
    }

    wp_send_json($response);
    wp_die();
}

/* Save custom cart item data to order  */
add_action('woocommerce_add_order_item_meta', 'save_custom_cart_item_data_to_order', 10, 3);
function save_custom_cart_item_data_to_order($item_id, $values, $cart_item_key)
{
    if (isset($values['listing_id'])) {
        wc_add_order_item_meta($item_id, 'listing_id', $values['listing_id']);
    }
    if (isset($values['listing_name'])) {
        wc_add_order_item_meta($item_id, 'listing_name', $values['listing_name']);
    }
}

/* Display custom data in cart */
add_filter('woocommerce_get_item_data', 'display_custom_cart_item_data_in_cart', 10, 2);
function display_custom_cart_item_data_in_cart($item_data, $cart_item)
{
    if (isset($cart_item['listing_name'])) {
        $item_data[] = array(
            'name' => __('Listing name', 'textdomain'),
            'value' => wc_clean($cart_item['listing_name'])
        );
    }

    return $item_data;
}

/* Display custom data in admin order  */
add_action('woocommerce_admin_order_data_after_order_details', 'display_custom_data_in_admin_order');
function display_custom_data_in_admin_order($order)
{
    foreach ($order->get_items() as $item_id => $item) {
        if ($listing_name = wc_get_order_item_meta($item_id, 'listing_name', true)) {
            echo '<p><strong>' . __('Listing name') . ':</strong> ' . $listing_name . '</p>';
        }
        if ($listing_id = wc_get_order_item_meta($item_id, 'listing_id', true)) {
            echo '<p><strong>' . __('Listing ID') . ':</strong> ' . $listing_id . '</p>';
        }
    }
}