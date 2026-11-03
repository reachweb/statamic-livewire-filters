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
- No JavaScript required, except for Flatpickr for the Date filter, simplifying integration.

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

## Match any / Match all toggle

A checkbox filter on a taxonomy or a multi-value field can let the visitor choose whether entries must match **any** of the ticked values or **all** of them. Add `logic_toggle="true"`:

```antlers
{{ livewire:lf-checkbox-filter blueprint="cars.car" field="car_category" condition="taxonomy" logic_toggle="true" }}
{{ livewire:lf-checkbox-filter blueprint="cars.car" field="special_categories" condition="overlaps" logic_toggle="true" }}
```

A small "Match any / Match all" switch renders above the options. It moves the filter between two param keys:

| Filter | Match any | Match all |
|---|---|---|
| `condition="taxonomy"` | `taxonomy:car_category:any` | `taxonomy:car_category:all` |
| `condition="overlaps"` | `special_categories:overlaps` | `special_categories:contains_all` |

`contains_all` is a condition the addon adds: an entry matches when its field holds every ticked value. It works with the same drivers as Statamic's `overlaps`. Start a filter in match-all mode with `modifier="all"` (taxonomy) or `condition="contains_all"`. The toggle is ignored on other conditions, such as `taxonomy` with `modifier="not"` or `is`.

The modifier and condition stay locked: the switch calls `setLogic('any'|'all')`, which only ever moves the filter to its own counterpart key. With `enable_filter_values_count` on, match-all counts show what ticking each option would leave, rather than counting as if the filter were unset.

In custom query string mode, an alias for the match-any key also covers its match-all key with an `-all` suffix, so `'brand' => 'taxonomy:car_brand:any'` writes and reads `/search/brand-all/audi,bmw`. An alias you configure for the match-all key yourself takes precedence. If you use `allowed_filters`, list both keys. If you published the `tags` view, update its condition label to the new expression so match-all tags read "Car brand (all): Audi".

## License 

When you are ready to deploy to production you need to buy a license at the Statamic Marketplace.
Statamic Livewire Filters is **not** free software. 

## Issues and pull requests 

Feel free to open an issue right here on Github. Email us directly for a security issue: info@reach.gr

