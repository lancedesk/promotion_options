<?php
/* Helper Function for Creating Custom Fields */
function ensure_custom_field_exists()
{
    if (!function_exists('register_field_group')) 
    {
        return;
    }

    register_field_group(array(
        'id' => 'acf_promotion-level',
        'title' => 'Promotion Level',
        'fields' => array(
            array(
                'key' => 'field_promotion_level',
                'label' => 'Promotion Level',
                'name' => 'promotion_level',
                'type' => 'text',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'dp_listing',
                ),
            ),
        ),
    ));
}
add_action('init', 'ensure_custom_field_exists');


add_action('init', 'register_custom_post_status');

function register_custom_post_status()
{
    register_post_status('expired', array(
        'label'                     => _x('Expired', 'post'),
        'public'                    => true,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>'),
    ));
}

/* Trigger handle_publish_action when a post is published to add marker to listing. */
add_action('transition_post_status', 'handle_post_publish', 10, 3);

function handle_post_publish($new_status, $old_status, $post)
{
    if ($new_status === 'publish' && $old_status !== 'publish')
    {
        $handler = new Promotion_Handler();
        $handler->handle_publish_action($post->ID);
    }
}