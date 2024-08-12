# Promotion Options

A WordPress plugin designed to add and manage promotion options for listings. This plugin integrates with the ClassiAds theme, DirectoryPress, and WooCommerce to handle various promotional features for listings, including pricing and display on the frontend and in the cart.

## Description

The Promotion Options plugin extends the functionality of the ClassiAds WordPress theme and DirectoryPress plugin by allowing users to add and manage promotion options for listings. It integrates with WooCommerce to handle promotional features in the cart and order details.

## Features

- **Add Promotion Options**: Adds promotion options to the listing submission form.
- **Handle Promotion Options in Cart**: Integrates with WooCommerce to include promotion options in the cart.
- **Display Promotion Options**: Shows promotion options on the frontend and in the order details.
- **AJAX Handling**: Uses AJAX for marking listings as sold without page reloads.
- **Custom Post Types**: Utilizes custom post types for promotion options.

## Installation

1. **Upload the Plugin**: Upload the `promotion-options` plugin folder to your `/wp-content/plugins/` directory.
2. **Activate the Plugin**: Go to the WordPress admin panel, navigate to `Plugins`, and activate the "Promotion Options" plugin.
3. **Configure Settings**: Configure the settings as needed through the WordPress admin panel.

## Usage

1. **Add Promotion Options**: Use the plugin to add different promotion options to your listings.
2. **Submit Listings**: When submitting a listing, select the desired promotion options.
3. **View in Cart**: Promotion options will be displayed in the WooCommerce cart and checkout pages.
4. **Order Details**: Promotion options and charges will be visible in the order details.

## AJAX Handling

The plugin uses AJAX to mark a listing as sold:

- **Action Hook**: `mark_as_sold`
- **Parameters**: `listing_id`, `buyer_id`, `seller_id`
- **Response**: Success or error message.

## Plugin Files

- `includes/class-sales-manager.php`: Handles sales-related functions.
- `includes/class-promotion-handler.php`: Manages promotion options and pricing.
- `includes/class-cron-jobs.php`: Contains scheduled tasks.
- `includes/custom-post-type.php`: Defines custom post types for promotions.
- `includes/settings-page.php`: Manages plugin settings.
- `includes/helper-functions.php`: Provides utility functions.
- `includes/locations-ajax.php`: Handles AJAX requests for location-based features.
- `css/promotion-options.css`: Plugin styles.
- `js/promotion-options.js`: JavaScript for handling AJAX and frontend interactions.

## Example Code

### PHP

```php
/**
 * Mark a listing as sold and record the sale in the database
 *
 * @param int $listing_id The ID of the listing
 * @param int $buyer_id The ID of the buyer
 * @param int $seller_id The ID of the seller
 */
public function mark_listing_as_sold($listing_id, $buyer_id, $seller_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'listing_sales';

    $data = array(
        'listing_id' => $listing_id,
        'buyer_id' => $buyer_id,
        'seller_id' => $seller_id,
        'sale_date' => current_time('mysql')
    );

    return $wpdb->insert($table_name, $data);
}
```

## License

This plugin is licensed under the GPLv2 or later license.

## Author

Robert June - Lead Developer @ Lance Desk

## Support

For support, please contact [dev@lancedesk.com](mailto:dev@lancedesk.com) or open an issue on GitHub.
