<?php
/* Register custom post type */
function register_promotion_options_cpt() 
{
    $labels = array(
        'name' => 'Promotion Options',
        'singular_name' => 'Promotion Option',
        'menu_name' => 'Promotion Options',
        'name_admin_bar' => 'Promotion Option',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Promotion Option',
        'new_item' => 'New Promotion Option',
        'edit_item' => 'Edit Promotion Option',
        'view_item' => 'View Promotion Option',
        'all_items' => 'All Promotion Options',
        'search_items' => 'Search Promotion Options',
        'parent_item_colon' => 'Parent Promotion Options:',
        'not_found' => 'No promotion options found.',
        'not_found_in_trash' => 'No promotion options found in Trash.',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
    );

    register_post_type('promotion_option', $args);
}
add_action('init', 'register_promotion_options_cpt');