<?php
/**
 * Promotion_Handler Class
 *
 * Handles promotion data for listings based on WooCommerce orders.
 */

class Promotion_Handler
{
    /**
     * Constructor
     */

    public function __construct()
    {
       /* Constructor logic later */
    }
	
	/**
	 * Get promotion details for a listing.
	 *
	 * @param int $listing_id The ID of the listing.
	 * @return array The promotion details with levels and expiration information.
	 */
	
	public function get_listing_promotion_details($listing_id)
	{
		/* Ensure the listing ID is an integer */
		$listing_id = absint($listing_id);

		/* Retrieve the listing post */
		$listing = get_post($listing_id);

		if (!$listing || $listing->post_type !== 'dp_listing')
		{
			/* Return empty array if listing not found or incorrect post type */
			return array();
		}

		/* Mapping of promotion levels to readable names */
		$promotion_mapping = array(
			'Highlighted Listing' => '_is_highlighted',
			'Home Page Spotlight' => '_is_home_spotlight',
			'Urgent Listing' => '_is_urgent_listing',
			'Featured Listing' => '_is_featured',
			'Free Listing' => '_is_free_listing'
		);

		/* Get the repeater field value */
		$promotions = get_field('marker_expiration_dates', $listing_id);

		/* Initialize an array to hold the promotion details */
		$promotion_details = array();

		if ($promotions)
		{
			foreach ($promotions as $promotion)
			{
				$promotion_level_key = $promotion['promotion_level'];
				$expiration_date_text = $promotion['expiration_date'];

				/* Find the readable name for the promotion level */
				$promotion_level = array_search($promotion_level_key, $promotion_mapping);

				/* Parse the expiration date and time */
				$expiration_datetime = DateTime::createFromFormat('d/m/Y H:i:s', $expiration_date_text);

				if ($expiration_datetime)
				{
					$current_datetime = new DateTime();
					$interval = $current_datetime->diff($expiration_datetime);
					$days_left = (int)$interval->format('%r%a');
					$has_expired = $days_left < 0;

					/* Add the promotion details to the array */
					$promotion_details[] = array(
						'promotion_level' => $promotion_level,
						'expiration_date' => $expiration_datetime->format('d/m/Y H:i:s'),
						'days_left' => $days_left,
						'has_expired' => $has_expired
					);
				}
			}
		}

		return $promotion_details;
	}

	public function is_fully_promoted($listing_id)
	{
		/* Retrieve promotion details for the given listing ID */
		$promotion_details = $this->get_listing_promotion_details($listing_id);

		/* Mapping of promotion levels to readable names */
		$promotion_mapping = array(
			'Highlighted Listing' => '_is_highlighted',
			'Home Page Spotlight' => '_is_home_spotlight',
			'Urgent Listing' => '_is_urgent_listing',
			'Featured Listing' => '_is_featured',
			'Free Listing' => '_is_free_listing'
		);

		/* Count the promotion levels excluding the 'Free Listing' */
		$active_promotions = 0;

		foreach ($promotion_details as $promotion)
		{
			if ($promotion['promotion_level'] !== 'Free Listing' && !$promotion['has_expired'])
			{
				$active_promotions++;
			}
		}

		/* Check if the listing is fully promoted */
		return ($active_promotions == count($promotion_mapping) - 1);
	}
	
	/**
     * Get the latest expiration date for a listing.
     *
     * @param int $listing_id The ID of the listing.
     * @return mixed The latest expiration date in 'd/m/Y H:i:s' format or false if expired.
     */

    public function get_latest_expiration_date($listing_id)
    {
        $marker_expiration_dates = get_field('marker_expiration_dates', $listing_id);

        if (!$marker_expiration_dates) {
            return false;
        }

        $latest_expiration_date = null;

        foreach ($marker_expiration_dates as $entry)
        {
            $expiration_date = DateTime::createFromFormat('d/m/Y H:i:s', $entry['expiration_date'], new DateTimeZone('UTC'));

            if ($expiration_date)
            {
                if ($latest_expiration_date === null || $expiration_date > $latest_expiration_date)
                {
                    $latest_expiration_date = $expiration_date;
                }
            }
        }

        return $latest_expiration_date ? $latest_expiration_date->format('d/m/Y H:i:s') : false;
    }
	
	
    public function handle_publish_action($post_id)
    {
        /* Check if the post is being published */
        if (get_post_status($post_id) !== 'publish')
        {
            return;
        }

        /* Add marker for free listing to the first text field */
        update_post_meta($post_id, '_is_free_listing', '1');

        /* Get the current date (publish date) */
        $publish_date = get_the_date('Y-m-d H:i:s', $post_id);

        /* Set the validity period (e.g., 30 days) */
        $validity_period = 30;

        /* Calculate the expiry date based on the publish date */
        $expiry_datetime_utc = strtotime("+{$validity_period} days", strtotime($publish_date));
        $expiry_datetime = gmdate('d/m/Y H:i:s', $expiry_datetime_utc); /* Combined date and time */

        /* Retrieve existing promotions from the repeater field */
        $existing_repeater = get_field('marker_expiration_dates', $post_id);
        $existing_levels = array();
        if ($existing_repeater)
        {
            foreach ($existing_repeater as $row)
            {
                $existing_levels[$row['promotion_level']] = $row['expiration_date'];
            }
        }

        /* Add the new promotion for free listing */
        $existing_levels['_is_free_listing'] = $expiry_datetime;

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
        update_field('marker_expiration_dates', $updated_repeater, $post_id);

        /* Update listing promotion levels */
        $current_promotion_levels = get_post_meta($post_id, 'promotion_level', true);
        $current_promotion_levels = !empty($current_promotion_levels) ? explode(',', $current_promotion_levels) : array();

        /* Ensure no duplicates */
        if (!in_array('_is_free_listing', $current_promotion_levels))
        {
            $current_promotion_levels[] = '_is_free_listing';
            $current_promotion_levels = array_unique($current_promotion_levels);
            $promotion_levels_string = implode(',', $current_promotion_levels);

            update_post_meta($post_id, 'promotion_level', esc_html($promotion_levels_string));
        }
    }
	
	/**
     * Retrieve custom settings for promotion options.
     *
     * @return array Settings for promotion options.
    */

    public function get_promotion_option_settings()
    {
        $settings = array(
            'charge_for_new_listings' => get_option('charge_for_new_listings', 0),
            'new_listing_charge_amount' => get_option('new_listing_charge_amount', 0)
        );

        return $settings;
    }
	
	/**
     * Check if charging for new listings is allowed.
     *
     * @return bool True if charging is allowed, false otherwise.
    */

    public function charging_allowed()
    {
        $settings = $this->get_promotion_option_settings();
        return (bool) $settings['charge_for_new_listings'];
    }

	/**
     * Get the new listing price.
     *
     * @return float The price for a new listing.
    */

    public function get_new_listing_price()
    {
        $settings = $this->get_promotion_option_settings();
        return $this->charging_allowed() ? floatval($settings['new_listing_charge_amount']) : 0;
    }

    /**
     * Check if required profile fields are filled.
     *
     * @param int $user_id The user ID.
     * @return array An array of missing fields or an empty array if all fields are filled.
    */

    public function check_required_fields($user_id)
    {
        $missing_fields = array();

        /* List of required fields */
        $required_fields = array(
            'first_name' => __('First Name', 'promotion-options'),
            'last_name'  => __('Last Name', 'promotion-options'),
            'nickname'   => __('Nickname', 'promotion-options'),
            'user_email' => __('Email', 'promotion-options'),
        );

        /* Check each required field */
        foreach ($required_fields as $field_key => $field_label) {
            $value = get_user_meta($user_id, $field_key, true);

            /* If field is empty, add to missing fields */
            if (empty($value)) {
                $missing_fields[] = $field_label;
            }
        }

        return $missing_fields;
    }

}
?>