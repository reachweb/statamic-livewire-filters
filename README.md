# Statamic Livewire Filters

Statamic Livewire Filters is an add-on for Statamic that enables you to use Livewire to create "live" filters for your Statamic collections. It comprises a primary Livewire component and various standard filter components, which are designed to be easily editable to suit your project's needs. It allows you to filter your entries by almost any field you wish and display the results in a "live" manner, using the power of Livewire.

## Introduction

**No Livewire knowledge required**

Simply swap out your Statamic collection tag and add the filters using Antlers – you're all set. This addon is designed to be straightforward and accessible, making it a seamless experience whether you're already familiar with Livewire or not.

**Ready to use filters**

Starting from scratch isn’t fun. You can use the basic filters already included to hit the ground running. Want to change how the look? Piece of cake using TailwindCSS. Want to extend and add more filters? Also possible.

**Advanced features**

Ready-to-use pagination? Absolutely! Query scopes? You bet! Query string support? Yes sir!

## Documentation & Examples

You can find the documentation and **examples** here: https://livewirefilters.com/

## Feature list

Main features:

- Collections Livewire component that, with the help of a Statamic tag, can seamlessly your collection tags using the exact same syntax.
- Simple and customizable filters equipped using the most common controls: Text input, Checkboxes, Radio, Select, Range, and Date.
- Compatibility with most Statamic conditions supported by the collection tag.
- Capability to dynamically order your collection besides filtering.
- Livewire-enabled pagination, prebuilt and ready to use.
- Query scopes support, including a prebuilt query scope for fields saved as an array.
- Query string support for your filters, enhancing usability.
- Minimal styling using TailwindCSS.
- Multiple view options for each component, offering flexibility in presentation.
- No JavaScript required, except for Flatpickr for the Date filter and Alpine Calendar for the Date range filter (both bundled), simplifying integration.

## Infinite scroll

Paginated collections can switch from numbered page links to an incremental "load more" flow by adding `infinite_scroll="true"` to the tag:

```antlers
{{ livewire-collection:cars view="cars" paginate="12" infinite_scroll="true" }}
```

Then render the trigger in your view with the `{{ livewire-filters:load_more }}` tag — the counterpart to `{{ links }}`:

```antlers
{{ livewire-filters:load_more }}              {{# manual "Load more" button #}}
{{ livewire-filters:load_more auto="true" }}  {{# auto-loads on scroll (infinite scroll) #}}
```

`auto="true"` wires up Alpine's `x-intersect` (bundled with Livewire — no build step) so the next page loads as the trigger scrolls into view. Without it, the visitor clicks to load more. Either way it renders a real `<button>`, so it keeps working without JavaScript.

Each load grows the page size by the initial `paginate` value, and the size resets automatically whenever a filter or sort changes. The tag hides itself once there are no more pages. The bundled view already includes it; you only add it yourself in custom views.

Optional parameters: `text` (override the label) and `class` (button classes). Publish `vendor/statamic-livewire-filters/livewire/ui/load-more.antlers.html` to fully restyle it.

> Note: the loaded-more state lives in the Livewire component, not the URL, so a full page reload resets the list to the first page.

## Date range filter

`lf-date-range-filter` filters a date field between two days, picked with two [Alpine Calendar](https://github.com/reachweb/alpine-calendar) popups ("from" and "to"):

```antlers
{{ livewire:lf-date-range-filter
    blueprint="events.event"
    field="starts"
    presets="today|7days|30days|this_month|this_year"
    format="D MMM YYYY"
}}
```

- Both days are inclusive, in `config('app.timezone')`, for date-only and time-enabled fields. Either end can be left empty for an open range.
- `presets` (optional) adds buttons that set both ends: `today`, `7days` and `30days` (ending today), `this_month` and `this_year` (the whole month or year). Presets are clamped to `earliest_date`/`latest_date`, and a preset entirely outside them is hidden.
- `format` (default `DD/MM/YYYY`) uses Alpine Calendar tokens (`D`, `DD`, `M`, `MM`, `MMM`, `MMMM`, `YY`, `YYYY`) for the inputs and the `lf-tags` chips.
- The calendars are limited to the field's `earliest_date` and `latest_date` from the blueprint.
- `lf-tags` shows one chip per end ("Starts from 1 Jan 2026", "Starts to 31 Dec 2026"); removing a chip clears only that end.

The range is stored as an `is_after`/`is_before` pair of UTC timestamps: the second before the first day and the midnight after the last day (e.g. `starts:is_after=2025-12-31T23:59:59Z` and `starts:is_before=2027-01-01T00:00:00Z` for 2026 in UTC), which is also what appears in the query string. `gte`/`lte` can't be used for dates: Statamic compares those as strings, and on the front end it turns dates into strings using the site's `date_format`.

## License 

When you are ready to deploy to production you need to buy a license at the Statamic Marketplace.
Statamic Livewire Filters is **not** free software. 

## Issues and pull requests 

Feel free to open an issue right here on Github. Email us directly for a security issue: info@reach.gr

