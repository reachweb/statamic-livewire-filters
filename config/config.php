<?php

return [
    // Enable the query string feature of Livewire, saving the filters in the URL
    'enable_query_string' => false,

    // Validate that the values of radio and checkbox filters are in the available options array
    'validate_filter_values' => true,

    // Query-control parameters that are never accepted from client-supplied filter state,
    // because they change entry visibility, collection/site scope or query cost and are not
    // legitimate filter values. Keys are matched against the segment before the first colon, so
    // `status` blocks both a bare `status` param and condition-style params like `status:is`.
    // Params you set directly on the tag are trusted and always kept; this list only strips
    // keys that arrive through client-tamperable filter state on the entries query. The
    // faceted-count query intentionally does NOT strip them (see HandleEntriesCount): that path
    // returns only aggregate counts of a field's own option values, an accepted trade-off that
    // lets tag-authored control params scope the counts the same way they scope the entries.
    // Add the exact param key to `allowed_filters` on the tag to re-permit one on the entries query.
    //
    // Note: `query_scope` is intentionally absent. Statamic treats `query_scope` and `filter`
    // as equivalent scope-invocation keys, but the addon builds `query_scope` itself for its
    // scope filters, so it cannot be denylisted without breaking that feature. Blocking `filter`
    // stops the native alias; to lock scope invocation down entirely, set `allowed_filters` with
    // explicit `query_scope:<scope>` entries (the only fully client-proof option today).
    'blocked_query_params' => [
        // Visibility
        'status',
        'published',
        'show_future',
        'show_past',
        'since',
        'until',
        'redirect',
        'redirects',
        'links',
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
