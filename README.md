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

Paginated collections can switch from numbered pages to a "load more" flow by adding `infinite_scroll="true"` to the tag:

```antlers
{{ livewire-collection:cars view="cars" paginate="12" infinite_scroll="true" }}
```

Each `loadMore` call grows the page size by the initial `paginate` value, and the page size resets automatically whenever a filter or sort changes. The bundled view handles this for you. In a custom view, use the `has_more_pages` variable and the `loadMore` action instead of the `{{ links }}` tag:

```antlers
{{ entries }}
    <div wire:key="{{ id }}">{{ title }}</div>
{{ /entries }}

{{ if has_more_pages }}
    <button wire:click="loadMore" wire:target="loadMore" wire:loading.attr="disabled">
        Load more
    </button>
{{ /if }}
```

> Note: the loaded-more state lives in the Livewire component, not the URL, so a full page reload resets the list to the first page.

## License 

When you are ready to deploy to production you need to buy a license at the Statamic Marketplace.
Statamic Livewire Filters is **not** free software. 

## Issues and pull requests 

Feel free to open an issue right here on Github. Email us directly for a security issue: info@reach.gr

