<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Site;

class MultiSiteTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup multi-language support with three sites
        Site::setSites([
            'en' => [
                'name' => 'English',
                'url' => '/',
                'locale' => 'en_US',
            ],
            'es' => [
                'name' => 'Spanish',
                'url' => '/es',
                'locale' => 'es_ES',
            ],
            'de' => [
                'name' => 'German',
                'url' => '/de',
                'locale' => 'de_DE',
            ],
        ]);

        // Create taxonomy with all three languages
        Facades\Taxonomy::make('colors')->sites(['en', 'es', 'de'])->save();

        // Create terms in English (default)
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('yellow')->data(['title' => 'Yellow'])->save();

        // Add Spanish translations
        $red = Facades\Term::find('colors::red');
        $red->in('es')->slug('rojo')->data(['title' => 'Rojo'])->save();

        $black = Facades\Term::find('colors::black');
        $black->in('es')->slug('negro')->data(['title' => 'Negro'])->save();

        $yellow = Facades\Term::find('colors::yellow');
        $yellow->in('es')->slug('amarillo')->data(['title' => 'Amarillo'])->save();

        // Add German translations
        $red->in('de')->slug('rot')->data(['title' => 'Rot'])->save();
        $black->in('de')->slug('schwarz')->data(['title' => 'Schwarz'])->save();
        $yellow->in('de')->slug('gelb')->data(['title' => 'Gelb'])->save();

        // Create collection with multisite support
        Facades\Collection::make('clothes')->sites(['en', 'es', 'de'])->taxonomies(['colors'])->save();

        $clothesBlueprint = $this->blueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [
                            'handle' => 'colors',
                            'field' => [
                                'type' => 'terms',
                                'taxonomies' => [
                                    'colors',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $clothesBlueprint->setHandle('clothes')->setNamespace('collections.clothes')->save();
    }

    /** @test */
    public function it_displays_taxonomy_terms_in_the_current_language()
    {
        // Test with English site
        Site::setCurrent('en');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Yellow')
            ->assertDontSee('Rojo')
            ->assertDontSee('Negro')
            ->assertDontSee('Amarillo')
            ->assertDontSee('Rot')
            ->assertDontSee('Schwarz')
            ->assertDontSee('Gelb');

        // Test with Spanish site
        Site::setCurrent('es');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Rojo')
            ->assertSee('Negro')
            ->assertSee('Amarillo')
            ->assertDontSee('Red')
            ->assertDontSee('Black')
            ->assertDontSee('Yellow')
            ->assertDontSee('Rot')
            ->assertDontSee('Schwarz')
            ->assertDontSee('Gelb');

        // Test with German site
        Site::setCurrent('de');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Rot')
            ->assertSee('Schwarz')
            ->assertSee('Gelb')
            ->assertDontSee('Red')
            ->assertDontSee('Black')
            ->assertDontSee('Yellow')
            ->assertDontSee('Rojo')
            ->assertDontSee('Negro')
            ->assertDontSee('Amarillo');
    }

    /** @test */
    public function it_displays_entries_in_the_current_language()
    {
        // Create English entries
        Site::setCurrent('en');
        $redShirt = EntryFactory::id('red-shirt')
            ->collection('clothes')
            ->slug('red-shirt')
            ->locale('en')
            ->data(['title' => 'Red Shirt'])
            ->create();

        $blueShirt = EntryFactory::id('blue-shirt')
            ->collection('clothes')
            ->slug('blue-shirt')
            ->locale('en')
            ->data(['title' => 'Blue Shirt'])
            ->create();

        // Create Spanish translations
        EntryFactory::id('red-shirt-es')
            ->collection('clothes')
            ->slug('camisa-roja')
            ->locale('es')
            ->origin($redShirt->id())
            ->data(['title' => 'Camisa Roja'])
            ->create();

        EntryFactory::id('blue-shirt-es')
            ->collection('clothes')
            ->slug('camisa-azul')
            ->locale('es')
            ->origin($blueShirt->id())
            ->data(['title' => 'Camisa Azul'])
            ->create();

        // Create German translations
        EntryFactory::id('red-shirt-de')
            ->collection('clothes')
            ->slug('rotes-hemd')
            ->locale('de')
            ->origin($redShirt->id())
            ->data(['title' => 'Rotes Hemd'])
            ->create();

        EntryFactory::id('blue-shirt-de')
            ->collection('clothes')
            ->slug('blaues-hemd')
            ->locale('de')
            ->origin($blueShirt->id())
            ->data(['title' => 'Blaues Hemd'])
            ->create();

        // Test English site
        Site::setCurrent('en');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Red Shirt')
            ->assertSee('Blue Shirt')
            ->assertDontSee('Camisa Roja')
            ->assertDontSee('Rotes Hemd');

        // Test Spanish site
        Site::setCurrent('es');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Camisa Roja')
            ->assertSee('Camisa Azul')
            ->assertDontSee('Red Shirt')
            ->assertDontSee('Rotes Hemd');

        // Test German site
        Site::setCurrent('de');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Rotes Hemd')
            ->assertSee('Blaues Hemd')
            ->assertDontSee('Red Shirt')
            ->assertDontSee('Camisa Roja');
    }

    /** @test */
    public function it_filters_entries_correctly_in_different_languages()
    {
        // Create English entries with colors
        Site::setCurrent('en');
        $redShirt = EntryFactory::id('red-shirt')
            ->collection('clothes')
            ->slug('red-shirt')
            ->locale('en')
            ->data(['title' => 'Red Shirt', 'colors' => ['red']])
            ->create();

        $blackShirt = EntryFactory::id('black-shirt')
            ->collection('clothes')
            ->slug('black-shirt')
            ->locale('en')
            ->data(['title' => 'Black Shirt', 'colors' => ['black']])
            ->create();

        $yellowShirt = EntryFactory::id('yellow-shirt')
            ->collection('clothes')
            ->slug('yellow-shirt')
            ->locale('en')
            ->data(['title' => 'Yellow Shirt', 'colors' => ['yellow']])
            ->create();

        // Create Spanish translations
        EntryFactory::id('red-shirt-es')
            ->collection('clothes')
            ->slug('camisa-roja')
            ->locale('es')
            ->origin($redShirt->id())
            ->data(['title' => 'Camisa Roja', 'colors' => ['red']])
            ->create();

        EntryFactory::id('black-shirt-es')
            ->collection('clothes')
            ->slug('camisa-negra')
            ->locale('es')
            ->origin($blackShirt->id())
            ->data(['title' => 'Camisa Negra', 'colors' => ['black']])
            ->create();

        EntryFactory::id('yellow-shirt-es')
            ->collection('clothes')
            ->slug('camisa-amarilla')
            ->locale('es')
            ->origin($yellowShirt->id())
            ->data(['title' => 'Camisa Amarilla', 'colors' => ['yellow']])
            ->create();

        // Create German translations
        EntryFactory::id('red-shirt-de')
            ->collection('clothes')
            ->slug('rotes-hemd')
            ->locale('de')
            ->origin($redShirt->id())
            ->data(['title' => 'Rotes Hemd', 'colors' => ['red']])
            ->create();

        EntryFactory::id('black-shirt-de')
            ->collection('clothes')
            ->slug('schwarzes-hemd')
            ->locale('de')
            ->origin($blackShirt->id())
            ->data(['title' => 'Schwarzes Hemd', 'colors' => ['black']])
            ->create();

        EntryFactory::id('yellow-shirt-de')
            ->collection('clothes')
            ->slug('gelbes-hemd')
            ->locale('de')
            ->origin($yellowShirt->id())
            ->data(['title' => 'Gelbes Hemd', 'colors' => ['yellow']])
            ->create();

        // Test filtering in English
        Site::setCurrent('en');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Red Shirt')
            ->assertSee('Black Shirt')
            ->assertSee('Yellow Shirt')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['red'],
                modifier: 'any'
            )
            ->assertSee('Red Shirt')
            ->assertDontSee('Black Shirt')
            ->assertDontSee('Yellow Shirt');

        // Test filtering in Spanish
        Site::setCurrent('es');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Camisa Roja')
            ->assertSee('Camisa Negra')
            ->assertSee('Camisa Amarilla')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['black'],
                modifier: 'any'
            )
            ->assertSee('Camisa Negra')
            ->assertDontSee('Camisa Roja')
            ->assertDontSee('Camisa Amarilla');

        // Test filtering in German
        Site::setCurrent('de');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSee('Rotes Hemd')
            ->assertSee('Schwarzes Hemd')
            ->assertSee('Gelbes Hemd')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['yellow'],
                modifier: 'any'
            )
            ->assertSee('Gelbes Hemd')
            ->assertDontSee('Rotes Hemd')
            ->assertDontSee('Schwarzes Hemd');
    }

    /** @test */
    public function it_displays_select_filter_options_in_the_current_language()
    {
        // Test with English site
        Site::setCurrent('en');
        Livewire::test(LfSelectFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Yellow')
            ->assertDontSee('Rojo')
            ->assertDontSee('Rot');

        // Test with Spanish site
        Site::setCurrent('es');
        Livewire::test(LfSelectFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Rojo')
            ->assertSee('Negro')
            ->assertSee('Amarillo')
            ->assertDontSee('Red')
            ->assertDontSee('Rot');

        // Test with German site
        Site::setCurrent('de');
        Livewire::test(LfSelectFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Rot')
            ->assertSee('Schwarz')
            ->assertSee('Gelb')
            ->assertDontSee('Red')
            ->assertDontSee('Rojo');
    }

    /** @test */
    public function it_filters_work_with_text_fields_in_different_languages()
    {
        // Create a new collection with a text field
        Facades\Collection::make('products')->sites(['en', 'es', 'de'])->save();

        $productsBlueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [
                            'handle' => 'description',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Description',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $productsBlueprint->setHandle('products')->setNamespace('collections.products')->save();

        // Create English entries
        Site::setCurrent('en');
        $laptop = EntryFactory::id('laptop')
            ->collection('products')
            ->slug('laptop')
            ->locale('en')
            ->data(['title' => 'Laptop', 'description' => 'Powerful computer'])
            ->create();

        $phone = EntryFactory::id('phone')
            ->collection('products')
            ->slug('phone')
            ->locale('en')
            ->data(['title' => 'Phone', 'description' => 'Smart mobile device'])
            ->create();

        // Create Spanish translations
        EntryFactory::id('laptop-es')
            ->collection('products')
            ->slug('portatil')
            ->locale('es')
            ->origin($laptop->id())
            ->data(['title' => 'Portátil', 'description' => 'Computadora potente'])
            ->create();

        EntryFactory::id('phone-es')
            ->collection('products')
            ->slug('telefono')
            ->locale('es')
            ->origin($phone->id())
            ->data(['title' => 'Teléfono', 'description' => 'Dispositivo móvil inteligente'])
            ->create();

        // Create German translations
        EntryFactory::id('laptop-de')
            ->collection('products')
            ->slug('laptop-de')
            ->locale('de')
            ->origin($laptop->id())
            ->data(['title' => 'Laptop', 'description' => 'Leistungsstarker Computer'])
            ->create();

        EntryFactory::id('phone-de')
            ->collection('products')
            ->slug('telefon')
            ->locale('de')
            ->origin($phone->id())
            ->data(['title' => 'Telefon', 'description' => 'Intelligentes Mobilgerät'])
            ->create();

        // Test filtering by title in English
        Site::setCurrent('en');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'products']])
            ->assertSee('Laptop')
            ->assertSee('Phone')
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'contains',
                payload: 'Laptop',
                modifier: 'any'
            )
            ->assertSee('Laptop')
            ->assertDontSee('Phone');

        // Test filtering by title in Spanish
        Site::setCurrent('es');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'products']])
            ->assertSee('Portátil')
            ->assertSee('Teléfono')
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'contains',
                payload: 'Portátil',
                modifier: 'any'
            )
            ->assertSee('Portátil')
            ->assertDontSee('Teléfono');

        // Test filtering by title in German
        Site::setCurrent('de');
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'products']])
            ->assertSee('Laptop')
            ->assertSee('Telefon')
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'contains',
                payload: 'Telefon',
                modifier: 'any'
            )
            ->assertSee('Telefon')
            ->assertDontSee('Laptop');
    }

    /** @test */
    public function it_displays_entries_field_options_in_the_current_language()
    {
        // Create a collection for reference entries (brands)
        Facades\Collection::make('brands')->sites(['en', 'es', 'de'])->save();

        $brandsBlueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $brandsBlueprint->setHandle('brands')->setNamespace('collections.brands')->save();

        // Create brand entries in English
        Site::setCurrent('en');
        $nike = EntryFactory::id('nike')
            ->collection('brands')
            ->slug('nike')
            ->locale('en')
            ->data(['title' => 'Nike'])
            ->create();

        $adidas = EntryFactory::id('adidas')
            ->collection('brands')
            ->slug('adidas')
            ->locale('en')
            ->data(['title' => 'Adidas'])
            ->create();

        // Create Spanish translations
        EntryFactory::id('nike-es')
            ->collection('brands')
            ->slug('nike-es')
            ->locale('es')
            ->origin($nike->id())
            ->data(['title' => 'Nike España'])
            ->create();

        EntryFactory::id('adidas-es')
            ->collection('brands')
            ->slug('adidas-es')
            ->locale('es')
            ->origin($adidas->id())
            ->data(['title' => 'Adidas España'])
            ->create();

        // Create German translations
        EntryFactory::id('nike-de')
            ->collection('brands')
            ->slug('nike-de')
            ->locale('de')
            ->origin($nike->id())
            ->data(['title' => 'Nike Deutschland'])
            ->create();

        EntryFactory::id('adidas-de')
            ->collection('brands')
            ->slug('adidas-de')
            ->locale('de')
            ->origin($adidas->id())
            ->data(['title' => 'Adidas Deutschland'])
            ->create();

        // Create a posts collection with entries field
        Facades\Collection::make('posts')->sites(['en', 'es', 'de'])->save();

        $postsBlueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [
                            'handle' => 'related_brands',
                            'field' => [
                                'type' => 'entries',
                                'display' => 'Related Brands',
                                'collections' => ['brands'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $postsBlueprint->setHandle('posts')->setNamespace('collections.posts')->save();

        // Test that entries field shows localized titles in English
        Site::setCurrent('en');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_brands', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSee('Nike')
            ->assertSee('Adidas')
            ->assertDontSee('Nike España')
            ->assertDontSee('Nike Deutschland');

        // Test that entries field shows localized titles in Spanish
        Site::setCurrent('es');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_brands', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSee('Nike España')
            ->assertSee('Adidas España')
            ->assertDontSee('Nike Deutschland');

        // Test that entries field shows localized titles in German
        Site::setCurrent('de');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_brands', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSee('Nike Deutschland')
            ->assertSee('Adidas Deutschland')
            ->assertDontSee('Nike España');
    }

    /** @test */
    public function it_maintains_filter_state_when_switching_sites()
    {
        // Test that filter values use slugs (which should work across locales)
        Site::setCurrent('en');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['red'])
            ->assertDispatched('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['red']
            );

        // Switch to Spanish site - the slug 'red' should still work
        Site::setCurrent('es');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->dispatch('preset-params', ['taxonomy:colors:any' => 'red'])
            ->assertSet('selected', ['red'])
            ->assertSee('Rojo'); // Display should be in Spanish

        // Switch to German site - the slug 'red' should still work
        Site::setCurrent('de');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->dispatch('preset-params', ['taxonomy:colors:any' => 'red'])
            ->assertSet('selected', ['red'])
            ->assertSee('Rot'); // Display should be in German
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
