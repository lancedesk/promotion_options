<?php
/* Fetch the cities based on the selected country */
function load_cities_based_on_country() {
    $country_id = intval($_POST['country_id']);

    if ($country_id) {
        $args = array(
            'taxonomy' => 'directorypress-location',
            'parent' => $country_id,
            'hide_empty' => false,
        );
        
        $terms = get_terms($args);
        
        if (!empty($terms) && !is_wp_error($terms)) {
            $cities = array();
            foreach ($terms as $term) {
                $cities[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                );
            }
            wp_send_json_success($cities);
        } else {
            wp_send_json_error('No cities found');
        }
    } else {
        wp_send_json_error('Invalid country ID');
    }
}

add_action('wp_ajax_load_cities', 'load_cities_based_on_country');
add_action('wp_ajax_nopriv_load_cities', 'load_cities_based_on_country');