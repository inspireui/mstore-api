<?php

function myListingExploreListings($request) 
{
    global $wpdb;

    if ( empty( $request['form_data'] ) || ! is_array( $request['form_data'] ) || empty( $request['listing_type'] ) ) {
        return [];
    }

    if ( ! ( $listing_type_obj = ( get_page_by_path( $request['listing_type'], OBJECT, 'case27_listing_type' ) ) ) ) {
        return [];
    }

    $type = new \MyListing\Src\Listing_Type( $listing_type_obj );
    $form_data = $request['form_data'];
    $starttime = microtime(true);

    $page = absint( isset($form_data['page']) ? $form_data['page'] : 0 );
    $per_page = isset($form_data['per_page']) ? absint(  $form_data['per_page'])  : -1;
    $orderby = sanitize_text_field( isset($form_data['orderby']) ? $form_data['orderby'] : 'date' );
    $args = [
        'order' => sanitize_text_field( isset($form_data['order']) ? $form_data['order'] : 'DESC' ),
        'offset' => $page * $per_page,
        'orderby' => $orderby,
        'posts_per_page' => $per_page,
        'tax_query' => [],
        'meta_query' => [],
        'fields' => 'ids',
        'recurring_dates' => [],
    ];

    \MyListing\Src\Queries\Explore_Listings::instance()->get_ordering_clauses( $args, $type, $form_data );

    // Make sure we're only querying listings of the requested listing type.
    if ( ! $type->is_global() ) {
        $args['meta_query']['listing_type_query'] = [
            'key'     => '_case27_listing_type',
            'value'   =>  $type->get_slug(),
            'compare' => '='
        ];
    }
	
    foreach ( (array) $type->get_advanced_filters() as $filter ) {
        $args = $filter->apply_to_query( $args, $form_data );
    }
    
    $result = [];

    /**
     * Hook after the search args have been set, but before the query is executed.
     *
     * @since 1.7.0
     */
    do_action_ref_array( 'mylisting/get-listings/before-query', [ &$args, $type, $result ] );
	
    $listings = \MyListing\Src\Queries\Explore_Listings::instance()->query( $args );

    if(count($listings->posts) > 0){
        $in = '(' . implode(',', $listings->posts) .')';
        $table_name = $wpdb->prefix . "posts";
        $sql = "SELECT * FROM {$table_name}";
        $sql .= " WHERE {$table_name}.ID in ".$in;
        $sql = $wpdb->prepare($sql);
        $results = $wpdb->get_results($sql);

        return $results;
    }else{
        return [];
    }
}
?>