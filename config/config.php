<?php

return [
    // Enable the query string feature of Livewire, saving the filters in the URL
    'enable_query_string' => false,

    // Validate that the values of radio and checkbox filters are in the available options array
    'validate_filter_values' => true,

    // If enabled the addon will preset the term parameters in any taxonomy term routes
    'enable_term_routes' => false,

    // The addon will calculate the number of entries for each filter value (can be slow for a large number of entries)
    'enable_filter_values_count' => false,

    // Enable custom query string
    'custom_query_string' => false,

    // Set the aliases for each custom query string parameter
    'custom_query_string_aliases' => [
        //
    ],
];
