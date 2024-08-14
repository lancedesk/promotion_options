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
		
		/* Adding review functionality */
		add_action('wp_ajax_submit_review', array($this, 'handle_submit_review'));
		add_action('wp_ajax_nopriv_submit_review', array($this, 'handle_submit_review'));
        add_action('wp_ajax_get_reviews', array($this, 'handle_get_reviews'));
        add_action('wp_ajax_nopriv_get_reviews', array($this, 'handle_get_reviews'));
		add_filter('comment_form_default_fields', array($this, 'add_rating_field_to_comment_form'));
        add_action('comment_post', array($this, 'save_comment_rating'));
		add_action('pre_comment_on_post', array($this, 'validate_comment_rating'), 10, 2);
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
        $messages_table = $wpdb->prefix . 'messages'; /* To replace with messages table name */
        $query = $wpdb->prepare(
            "SELECT DISTINCT user_id FROM $messages_table WHERE listing_id = %d",
            $listing_id
        );
        $user_ids = $wpdb->get_col($query);

        /* Send notifications or update message threads */
        foreach ($user_ids as $user_id) {
            /* WordPress notification functions or custom email functions */
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
            'post_type' => 'listing_review', /* listing_review custom post type for reviews */
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
	
	/**
     * Adds a rating field to the comment form.
     *
     * @param array $fields The default comment form fields.
     * @return array Modified comment form fields with rating.
    */

    public function add_rating_field_to_comment_form($fields) {
        $fields['rating'] = '<p class="comment-form-rating">
            <label for="rating">' . esc_html__('Rating', 'promotion-options') . '</label>
            <input id="rating" name="rating" type="number" min="1" max="5" step="1" />
        </p>';
        return $fields;
    }
	
	/**
     * Saves the rating as comment meta data when a comment is posted.
     *
     * @param int $comment_id The ID of the comment being posted.
    */

    public function save_comment_rating($comment_id) {
        if (isset($_POST['rating'])) {
            $rating = intval($_POST['rating']);
			if ($rating >= 1 && $rating <= 5) {
				add_comment_meta($comment_id, 'rating', $rating);
			}
        }
    }

	/**
     * Validates that a rating has been provided before a comment is saved.
     *
     * @param int $comment_id The ID of the comment being posted.
     * @param string $comment_approved The approval status of the comment.
    */

    public function validate_comment_rating($comment_id, $comment_approved) {
        if (empty($_POST['rating'])) {
            wp_die('Error: Please provide a rating.');
        }
    }
	
	/**
	 * Handles the AJAX request for submitting a review.
	 *
	 * This method processes the review submission, associates the review with
	 * a specific listing, and saves the review and rating in the database.
	 * It sanitizes input data, checks for security nonce, and attaches rating
	 * meta information to the comment.
	 *
	 * @return void
	*/

	public function handle_submit_review()
	{
		/* Check for nonce security */
		if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'submit_review_nonce') ) {
			wp_send_json_error(array('message' => 'Nonce verification failed.'));
			return;
		}
		
		/* Log POST data for debugging */
		error_log('POST Data: ' . print_r($_POST, true));
		
		/* Check for required fields */
		if ( empty($_POST['rating']) || empty($_POST['comment']) || empty($_POST['listing_id']) ) {
			wp_send_json_error(array('message' => 'All fields are required.'));
			return;
		}

		/* Sanitize input data */
		$name = sanitize_text_field($_POST['review-name']);
		$email = sanitize_email($_POST['review-email']);
		$rating = intval($_POST['rating']);
		$comment = sanitize_textarea_field($_POST['comment']);
		$listing_id = intval($_POST['listing_id']);

		/* Validate rating */
		if ($rating < 1 || $rating > 5) {
			wp_send_json_error(array('message' => 'Rating must be between 1 and 5.'));
			return;
		}
		
		/* Get the current user ID */
		$current_user_id = get_current_user_id();
		
		/* Get the user's IP address */
		$user_ip = $_SERVER['REMOTE_ADDR'];

		/* Insert comment */
		$comment_data = array(
			'comment_post_ID' => $listing_id, /* Associate comment with the listing */
			'comment_author' => $name,
			'comment_author_email' => $email,
			'comment_content' => $comment,
			'comment_type' => 'review', /* Optional: set a custom comment type */
			'comment_approved' => 1, /* Automatically approve the comment */
			'user_id' => $current_user_id, /* Automatically set the current user ID */
			'comment_author_IP' => $user_ip /* Set the user's IP address */
		);

		/* Insert comment into the database */
		$comment_id = wp_insert_comment($comment_data);

		if ($comment_id && $rating) {
			/* Save rating as comment meta */
			add_comment_meta($comment_id, 'rating', $rating);
		}

		/* Return a success response */
		wp_send_json_success(array('message' => 'Review submitted successfully.'));
	}

    /**
     * Handles AJAX request to fetch reviews for a specific listing.
     *
     * @return void
    */

    public function handle_get_reviews() {
        /* Check for nonce security */
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'submit_review_nonce') ) {
            wp_send_json_error(array('message' => 'Nonce verification failed.'));
            return;
        }

        $listing_id = intval($_POST['listing_id']);

        if (!$listing_id) {
            wp_send_json_error(array('message' => 'Invalid listing ID.'));
            return;
        }

        $reviews_html = $this->display_alternating_reviews($listing_id);

        wp_send_json_success(array('reviews_html' => $reviews_html));
    }

	/**
     * Checks if the current logged-in user has sent a review for a specific listing.
     *
     * @param int $listing_id The ID of the listing to check reviews for.
     * @return bool True if the user has sent a review, false otherwise.
    */
	
	public function has_sent_review($listing_id)
	{
		/* Ensure user is logged in */
		if ( ! is_user_logged_in() ) 
		{
			return;
		}

		/* Get the ID of the current user */
		$current_user_id = get_current_user_id();

		/* Fetch all approved reviews for the given listing */
		$reviews = get_comments(array(
			'post_id' => $listing_id,
			'user_id' => $current_user_id,
			'type'    => 'review', /* Custom comment type */
			'status'  => 'approve',
		));
		

		/* Check if any reviews exist */
		if ( ! empty($reviews) ) 
		{
			return true;
		}

		return false; /* Return false if no review by the current user is found */
	}
	
	/**
     * Retrieves reviews for a listing that were not left by the logged-in user.
     *
     * @param int $listing_id The ID of the listing to retrieve reviews for.
     * @return array An array of comments that are not left by the logged-in user.
    */

    public function get_reviews_not_by_current_user($listing_id)
	{
		$current_user_id = get_current_user_id();
		$reviews = get_comments(array(
			'post_id' => $listing_id,
			'status' => 'approve',
			'parent' => 0,
		));

		return array_filter($reviews, function ($comment) use ($current_user_id) {
			return $comment->user_id !== $current_user_id;
		});
	}
	
	/**
     * Retrieves and displays reviews for a listing, including those by the logged-in user and the other participant.
     *
     * @param int $listing_id The ID of the listing to retrieve reviews for.
     * @return string HTML output for displaying the reviews in an alternating layout.
    */
	
	public function display_alternating_reviews($listing_id)
	{
		$current_user_id = get_current_user_id();

		/* Fetch all reviews not by the current user */
		$reviews = $this->get_reviews_not_by_current_user($listing_id);

		/* Fetch reviews by the current user */
		$current_user_reviews = get_comments(array(
			'post_id' => $listing_id,
			'user_id' => $current_user_id,
			'status'  => 'approve',
			'parent'  => 0,
		));

		/* Create an array of review IDs to ensure uniqueness */
		$current_user_review_ids = wp_list_pluck($current_user_reviews, 'comment_ID');

		/* Filter out current user reviews from the general reviews to avoid duplicates */
		$filtered_reviews = array_filter($reviews, function($review) use ($current_user_review_ids) {
			return !in_array($review->comment_ID, $current_user_review_ids);
		});

		/* Merge current user reviews with other reviews */
		$all_reviews = array_merge($current_user_reviews, $filtered_reviews);

		/* Sort reviews by date to ensure consistent order */
		usort($all_reviews, function($a, $b) {
			return strcmp($a->comment_date, $b->comment_date);
		});

		$output = '<div class="review-messages">';
		foreach ($all_reviews as $review) {
			$author_id = (int) $review->user_id;  /* Ensure comparison is type-consistent */
			$author_class = ($author_id === (int) $current_user_id) ? 'current-user' : 'other-user';

			/* Get the author's display name */
			$author_name = get_comment_author($review->comment_ID);

			$output .= '<div class="review-message ' . $author_class . '">';
			$output .= '<div class="review-author">';
			$output .= get_avatar($author_id, 60);
			$output .= '<span class="review-author-name">' . esc_html($author_name) . '</span>';
			$output .= '</div>';
			$output .= '<div class="review-content">';
			$output .= '<p>' . esc_html($review->comment_content) . '</p>';
			$rating = get_comment_meta($review->comment_ID, 'rating', true);
			if ($rating) {
				$output .= '<div class="review-rating">';
				for ($i = 0; $i < $rating; $i++) {
					$output .= '<i class="fas fa-star"></i>';
				}
				$output .= '</div>';
			}
			$output .= '</div>';
			$output .= '</div>';
		}
		$output .= '</div>';

		return $output;
	}

    /**
     * Get the buyer ID for a specific listing.
     *
     * @param int $listing_id The ID of the listing.
     * @return int|null The ID of the buyer or null if not found.
    */

    public function get_buyer_id_by_listing_id( $listing_id )
    {
        global $wpdb;

        /* Sanitize the listing_id to ensure it's an integer */
        $listing_id = intval( $listing_id );

        /* Prepare the SQL query */
        $query = $wpdb->prepare(
            "SELECT buyer_id FROM wpax_listing_sales WHERE listing_id = %d LIMIT 1",
            $listing_id
        );

        /* Execute the query and fetch the result */
        $buyer_id = $wpdb->get_var( $query );

        return $buyer_id ? intval( $buyer_id ) : null;
    }

}
?>