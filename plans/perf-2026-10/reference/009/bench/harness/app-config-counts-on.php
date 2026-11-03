<?php

return [
    // Enable the query string feature of Livewire, saving the filters in the URL
    'enable_query_string' => false,

    // Validate that the values of radio and checkbox filters are in the available options array
    'validate_filter_values' => true,

    // Use origin id for entries field
    'use_origin_id_for_entries_field' => true,

    // If enabled the addon will preset the term parameters in any taxonomy term routes
    'enable_term_routes' => false,

    // The addon will calculate the number of entries for each filter value (can be slow for a large number of entries)
    'enable_filter_values_count' => true,

    // Dynamically update the min/max bounds of range filters (lf-range, lf-dual-range) to match
    // the entries remaining after the other active filters (runs the same pooled query as the
    // filter values count feature — can be slow for a large number of entries)
    'enable_dynamic_range_bounds' => true,

    // Enable custom query string
    'custom_query_string' => 'search',

    // Set the aliases for each custom query string parameter
    'custom_query_string_aliases' => [
        'title' => 'title:contains',
        'seats' => 'seats:gte',
        'brand' => 'taxonomy:car_brand:any',
        'transmission' => 'transmission:is',
        'categories' => 'query_scope:multiselect:special_categories',
        'fuel' => 'fuel_type:is',
        'ac' => 'air_condition:is',
        'model-year-after' => 'date_of_registration:is_after',
        'model-year-before' => 'date_of_registration:is_before',
    ],
];
