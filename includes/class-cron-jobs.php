<?php
/* Class to handle cron jobs */
class Cron_Jobs
{
    public function __construct()
    {
        add_action('wp', array($this, 'register_cron_job'));
        add_action('check_expired_promotions_event', array($this, 'check_and_update_expired_promotions'));
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
    }
}