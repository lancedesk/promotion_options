<?php
class Sales_Manager
{
    /**
     * Constructor to register actions
     */
    public function __construct()
    {
		/* For logged-in users */
        add_action('wp_ajax_mark_as_sold', array($this, 'handle_mark_as_sold'));
		/* For non-logged-in users (if necessary) */
        add_action('wp_ajax_nopriv_mark_as_sold', array($this, 'handle_mark_as_sold'));
    }

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
		
		/* Check if the listing has already been marked as sold */
		$existing_sale = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE listing_id = %d AND buyer_id = %d AND seller_id = %d",
				$listing_id, $buyer_id, $seller_id
			)
		);

		if ($existing_sale) {
			/* If a sale already exists, don't insert a new record */
			return false;
		}

        $data = array(
            'listing_id' => $listing_id,
            'buyer_id' => $buyer_id,
            'seller_id' => $seller_id,
            'sale_date' => current_time( 'mysql' )
        );

        return $wpdb->insert( $table_name, $data );
	}
	
	/**
	 * Handle AJAX request to mark a listing as sold
	*/

	public function handle_mark_as_sold()
	{
        if ( ! isset( $_POST['listing_id'] ) || ! isset( $_POST['buyer_id'] ) || ! isset( $_POST['seller_id'] ) ) {
            wp_send_json_error( 'Missing required parameters.' );
        }

        $listing_id = intval( $_POST['listing_id'] );
        $buyer_id = intval( $_POST['buyer_id'] );
        $seller_id = intval( $_POST['seller_id'] );

        /* Ensure user has permission to perform this action */
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }
		
		/* Create a unique key for this transaction */
		$transient_key = 'mark_as_sold_' . $listing_id . '_' . $buyer_id . '_' . $seller_id;

		if (false !== get_transient($transient_key)) {
			wp_send_json_error('Transaction already processed.');
		}

		/* Set a transient to prevent duplicate processing */
		set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);

        /* Perform the database update */
        $result = $this->mark_listing_as_sold( $listing_id, $buyer_id, $seller_id );

        if ( $result ) {
            wp_send_json_success( 'Listing marked as sold.' );
        } else {
            wp_send_json_error( 'Failed to update listing.' );
        }
    }
	
	/**
	 * Check if a listing was sold to a specific buyer
	 *
	 * @param int $listing_id The ID of the listing
	 * @param int $buyer_id The ID of the buyer
	 * @return bool True if the listing was sold to the specified buyer, false otherwise
	*/

	public function is_listing_sold_to_buyer($listing_id, $buyer_id)
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'listing_sales';
		$result = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE listing_id = %d AND buyer_id = %d",
			$listing_id,
			$buyer_id
		));

		return $result > 0;
	}
	
	/**
	 * Check if a listing has been sold
	 *
	 * @param int $listing_id The ID of the listing
	 * @return bool True if the listing has been sold, false otherwise
	*/

	public function is_listing_sold($listing_id)
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'listing_sales';
		$result = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE listing_id = %d",
			$listing_id
		));

		return $result > 0;
	}

    /**
     * Notify users who have messaged about a sold listing
     *
     * @param int $listing_id The ID of the sold listing
    */

    public function notify_users_about_sold_listing($listing_id)
    {
        global $wpdb;

        /* Fetch all users who have messaged about the listing */
        $messages_table = $wpdb->prefix . 'messages'; /* Replace with actual messages table name */
        $query = $wpdb->prepare(
            "SELECT DISTINCT user_id FROM $messages_table WHERE listing_id = %d",
            $listing_id
        );
        $user_ids = $wpdb->get_col($query);

        /* Send notifications or update message threads */
        foreach ($user_ids as $user_id) {
            /* You can use WordPress notification functions or custom email functions */
            wp_mail(
                get_the_author_meta('user_email', $user_id),
                'Listing Sold Notification',
                'The listing you were interested in has been sold.'
            );
        }
    }

    /**
     * Add a review for a listing
     *
     * @param int $listing_id The ID of the listing
     * @param int $reviewer_id The ID of the reviewer
     * @param string $review_text The text of the review
     */
    public function add_review($listing_id, $reviewer_id, $review_text)
    {
        $review_post = array(
            'post_title' => 'Review for Listing ' . $listing_id,
            'post_content' => $review_text,
            'post_status' => 'publish',
            'post_type' => 'listing_review', /* Your custom post type for reviews */
            'post_author' => $reviewer_id,
        );
        wp_insert_post($review_post);
    }
	
	/**
     * Retrieves the user's first name or fallback to the WordPress username.
     *
     * @param int $user_id The ID of the user.
     * @return string The user's first name or username.
    */
    public function get_user_first_name_or_username($user_id)
    {
        global $wpdb;

        /* Ensure user ID is valid */
        if ( ! is_numeric($user_id) || $user_id <= 0 ) {
            return 'Invalid user ID';
        }

        /* Fetch user first name from user meta */
        $first_name = get_user_meta($user_id, 'first_name', true);

        if ( ! empty($first_name) ) {
            return $first_name;
        }

        /* Fetch the username if first name is not set */
        $user_info = get_userdata($user_id);
        if ( $user_info ) {
            return $user_info->user_login;
        }

        return 'User not found';
    }
	
	/**
     * Retrieves the creator ID of a listing based on its ID.
     *
     * @param int $listing_id The ID of the listing.
     * @return int|false The creator ID if found, otherwise false.
    */
    public function get_listing_creator_id($listing_id)
    {
        global $wpdb;

        /* Validate listing_id to ensure it is an integer */
        if (!is_numeric($listing_id) || $listing_id <= 0) {
            return false;
        }

        /* Fetch the creator ID from the post_author field in the posts table */
        $creator_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_author FROM {$wpdb->prefix}posts WHERE ID = %d",
            $listing_id
        ));

        /* Return the creator ID or false if not found */
        return $creator_id ? intval($creator_id) : false;
    }
}
?>