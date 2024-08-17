<?php
/* Class to handle cron jobs */
class Cron_Jobs
{
    public function __construct()
    {
        add_action('wp', array($this, 'register_cron_job'));
        add_action('check_expired_promotions_event', array($this, 'check_and_update_expired_promotions'));
        add_action('send_expiry_reminders_event', array($this, 'send_expiry_reminders'));
        register_activation_hook(__FILE__, array($this, 'activate_cron_job'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_cron_job'));
    }

    /* Schedule Cron Job */
    public function register_cron_job()
    {
        if (!wp_next_scheduled('check_expired_promotions_event'))
        {
            wp_schedule_event(time(), 'hourly', 'check_expired_promotions_event');
        }

        if (!wp_next_scheduled('send_expiry_reminders_event'))
        {
            wp_schedule_event(time(), 'daily', 'send_expiry_reminders_event');
        }
    }

    /* Check and Update Expired Promotions */
    public function check_and_update_expired_promotions()
    {
        global $wpdb;

        /* Fetch all listings with promotion expiration dates */
        $listings = $wpdb->get_results("
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = 'dp_listing'
            AND post_status = 'publish'
        ");

        foreach ($listings as $listing)
        {
            $listing_id = $listing->ID;

            /* Get current promotion levels */
            $promotion_levels = get_post_meta($listing_id, 'promotion_level', true);
            $promotion_levels_array = !empty($promotion_levels) ? explode(',', $promotion_levels) : array();

            /* Get marker expiration dates */
            $marker_expiration_dates = get_field('marker_expiration_dates', $listing_id);
            if ($marker_expiration_dates)
            {
                $current_time = new DateTime(current_time('Y-m-d H:i:s', 1), new DateTimeZone('UTC'));

                foreach ($marker_expiration_dates as $index => $entry)
                {
                    $promotion_level = $entry['promotion_level'];
                    $expiration_date = DateTime::createFromFormat('d/m/Y H:i:s', $entry['expiration_date'], new DateTimeZone('UTC'));

                    if ($expiration_date && $expiration_date <= $current_time)
                    {
                        /* Remove expired promotion level from the array */
                        if (($key = array_search($promotion_level, $promotion_levels_array)) !== false)
                        {
                            unset($promotion_levels_array[$key]);
                        }

                        /* Remove expired promotion level from the repeater field */
                        unset($marker_expiration_dates[$index]);
                    }
                }

                /* Update promotion levels */
                update_post_meta($listing_id, 'promotion_level', implode(',', $promotion_levels_array));

                /* Update marker expiration dates */
                update_field('marker_expiration_dates', $marker_expiration_dates, $listing_id);

                /* If no promotion levels are left, set post status to draft */
                if (empty($promotion_levels_array))
                {
                    $wpdb->update(
                        $wpdb->posts,
                        array('post_status' => 'draft'),
                        array('ID' => $listing_id)
                    );
                }
            }
        }
    }
   
    /*
    * Sends expiry reminder emails for listings nearing expiration.
    */
    
    public function send_expiry_reminders()
    {
        global $wpdb;
    
        /* Fetch all listings with promotion expiration dates */
        $listings = $wpdb->get_results("
            SELECT ID, post_author
            FROM {$wpdb->posts}
            WHERE post_type = 'dp_listing'
            AND post_status = 'publish'
        ");
    
        /* Date object for comparison (current time plus 6 days) */
        $six_days_from_now = new DateTime(current_time('Y-m-d H:i:s', 1), new DateTimeZone('UTC'));
        $six_days_from_now->modify('+6 days');
    
        $user_listings = [];
    
        foreach ($listings as $listing)
        {
            $listing_id = $listing->ID;
    
            /* Get marker expiration dates */
            $marker_expiration_dates = get_field('marker_expiration_dates', $listing_id);
            if ($marker_expiration_dates)
            {
                foreach ($marker_expiration_dates as $entry)
                {
                    $expiration_date = DateTime::createFromFormat('d/m/Y H:i:s', $entry['expiration_date'], new DateTimeZone('UTC'));
    
                    if ($expiration_date && $expiration_date->format('Y-m-d') == $six_days_from_now->format('Y-m-d'))
                    {
                        $user_id = $listing->post_author;
                        $promotion_level = $entry['promotion_level'];
    
                        /* Collect listings for each user */
                        if (!isset($user_listings[$user_id]))
                        {
                            $user_listings[$user_id] = [];
                        }
    
                        if (!isset($user_listings[$user_id][$listing_id]))
                        {
                            $user_listings[$user_id][$listing_id] = [
                                'title' => get_the_title($listing_id),
                                'url' => get_permalink($listing_id),
                                'views' => get_post_meta($listing_id, '_total_clicks', true) ?: 0,
                                'featured_image' => get_the_post_thumbnail_url($listing_id),
                                'expiries' => []
                            ];
                        }
    
                        $user_listings[$user_id][$listing_id]['expiries'][$promotion_level] = $expiration_date->format('d/m/Y H:i:s');
                    }
                }
            }
        }
    
        /* Send email for each user */
        foreach ($user_listings as $user_id => $listings)
        {
            $user_info = get_userdata($user_id);
            $user_email = $user_info->user_email;
    
            $this->send_combined_reminder_email($user_email, $listings, $user_info);
        }
    }
    
    /* 
    * Sends a combined reminder email to the user.
    *
    * @param string $user_email The email address of the user.
    * @param array $listings Array of listings with expiration dates.
    * @param WP_User $user_info The user information object.
    */
    
    private function send_combined_reminder_email($user_email, $listings, $user_info)
    {
         /* Set the sender name and email */
        $from_name = 'Ice Hockey Market';
        $from_email = 'noreply@icehockeymarket.com';
        
        /* HTML email headers */
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
    
        /* Determine if there's one or more listings */
        $subject = (count($listings) > 1) ? "Reminder: Your listings are about to expire!" : "Reminder: Your listing is about to expire!";
        
        /* Get user's first name, username, or fallback to "User" */
        $user_name = !empty($user_info->first_name) ? $user_info->first_name : 
                    (!empty($user_info->user_login) ? $user_info->user_login : "User");
    
        /* Start the message with the personalized greeting */
        $message = "<p>Dear {$user_name},</p>";
        $message .= "<p>The following listing" . (count($listings) > 1 ? "s are" : " is") . " set to expire soon:</p>";
        $message .= "<ul>";
    
        foreach ($listings as $listing_id => $listing_info) 
        {
            $listing_title = $listing_info['title'];
            $listing_url = $listing_info['url'];
            $listing_views = $listing_info['views'];
            $listing_image = !empty($listing_info['featured_image']) ? $listing_info['featured_image'] : DEFAULT_LISTING_IMAGE;
            
            $message .= '<li>';
            $message .= '<strong>' . $listing_title . '</strong>';
    
            /* Handle multiple expiries on the same day */
            $message .= '<ul>';
            foreach ($listing_info['expiries'] as $promotion_level => $expiry_date)
            {
                $message .= '<li>' . $promotion_level . ' - Expires on: ' . $expiry_date . '</li>';
            }
            $message .= '</ul>';
    
            $message .= "<p><img src='{$listing_image}' alt='{$listing_title}' width='150px' height='150px'></p>";
            $message .= "<p>Number of views: {$listing_views}</p>";
            $message .= "<p><a href='{$listing_url}'>View your listing</a></p>";
            $message .= "<p><a href='https://icehockeymarket.com/my-dashboard/?directorypress_action=promote_listing&listing_id={$listing_id}'>Click here to renew your listing</a></p>";
            $message .= '</li>';
        }
    
        $message .= "</ul><p>Thank you!</p>";
    
        wp_mail($user_email, $subject, $message, $headers);
    }    

    /* Activate Cron Job */
    public function activate_cron_job()
    {
        $this->register_cron_job();
    }

    /* Deactivate Cron Job */
    public function deactivate_cron_job()
    {
        $timestamp = wp_next_scheduled('check_expired_promotions_event');
        wp_unschedule_event($timestamp, 'check_expired_promotions_event');

        $timestamp = wp_next_scheduled('send_expiry_reminders_event');
        wp_unschedule_event($timestamp, 'send_expiry_reminders_event');
    }
}