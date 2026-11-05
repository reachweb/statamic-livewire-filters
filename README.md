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

## Deferred filters (apply button)

On mobile, filters usually live in a drawer, and re-querying the collection on every tap is wasted work. Deferred mode queues filter changes until the visitor applies them. Set it on the collection tag:

```antlers
{{ livewire-collection:cars paginate="9" defer_filters="drawer" }}
```

- `defer_filters="drawer"` (recommended): filters apply live until the drawer opens. While it is open, changes are queued; closing it with Apply applies them, and closing it any other way (✕, the backdrop or Escape) discards them. On desktop, where the drawer is a sidebar, filters stay live.
- `defer_filters="true"`: changes are always queued, on every screen size, until an Apply button is pressed.

While changes are queued, the entries, tags, faceted counts and URL stay as they are. Apply replays the whole queue in one render. A later change of the same filter replaces its queued change, and a change back to the applied value drops it.

Wrap your filters in the `{{ livewire-filters:drawer }}` tag:

```antlers
{{ livewire-filters:drawer }}
    {{ livewire:lf-checkbox-filter blueprint="cars.car" field="car_brand" condition="taxonomy" }}
    {{ livewire:lf-radio-filter blueprint="cars.car" field="transmission" condition="is" }}
{{ /livewire-filters:drawer }}
```

Below its breakpoint (`breakpoint="1280"` by default, in pixels; `0` keeps the drawer at every width) it is an off-canvas panel with a "Filters (n)" trigger, where n is the number of applied filters, and an apply button in its footer. From the breakpoint up it renders its content in place as a sidebar; widening the window with the drawer open applies its queued changes. The button reads "Apply (n)" while n filter changes are queued, and "Show N results" otherwise, N being the current result count from `entries-updated`. Render the button anywhere else with `{{ livewire-filters:apply_button }}`. Both tags accept a `class` parameter for their trigger button. To restyle them fully, publish `livewire/ui/drawer.blade.php` and `livewire/ui/apply-button.blade.php`.

Removing a tag, Clear all and sort changes still apply right away. In custom views, dispatch the events yourself with `Livewire.dispatch()`: `start-deferring` and `stop-deferring` (with `{ apply: true }` to apply) in drawer mode, or `apply-filters` and `discard-filters` directly. Listen for `pending-filters-updated`, whose `count` is the number of queued changes. Custom filters that don't extend `LivewireFilter` don't restore their value on discard.

## License 

When you are ready to deploy to production you need to buy a license at the Statamic Marketplace.
Statamic Livewire Filters is **not** free software. 

## Issues and pull requests 

Feel free to open an issue right here on Github. Email us directly for a security issue: info@reach.gr

