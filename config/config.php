<?php

return [
    // Enable the query string feature of Livewire, saving the filters in the URL
    'enable_query_string' => false,

    // Validate that the values of radio and checkbox filters are in the available options array
    'validate_filter_values' => true,

    // Query-control parameters that are never accepted from client-supplied filter state,
    // because they change entry visibility, collection/site scope or query cost and are not
    // legitimate filter values. Keys are matched against the segment before the first colon, so
    // `status` blocks both a bare `status` param and condition-style params like `status:is`. Add
    // the exact param key to `allowed_filters` on the tag to explicitly re-permit one.
    'blocked_query_params' => [
        // Visibility
        'status',
        'published',
        'show_future',
        'show_past',
        // Collection / site scope
        'from',
        'in',
        'folder',
        'use',
        'collection',
        'not_from',
        'not_in',
        'not_folder',
        'dont_use',
        'not_collection',
        'site',
        'locale',
        // Query cost / shape
        'limit',
        'offset',
        'paginate',
        'chunk',
        // Arbitrary query scope (alias for query_scope)
        'filter',
    ],

    // Use origin id for entries field
    'use_origin_id_for_entries_field' => true,

    // If enabled the addon will preset the term parameters in any taxonomy term routes
    'enable_term_routes' => false,

    // The addon will calculate the number of entries for each filter value (can be slow for a large number of entries)
    'enable_filter_values_count' => false,

    // Enable custom query string (ignored when enable_query_string is true)
    'custom_query_string' => false,

    // Set the aliases for each custom query string parameter
    'custom_query_string_aliases' => [
        //
    ],
];
