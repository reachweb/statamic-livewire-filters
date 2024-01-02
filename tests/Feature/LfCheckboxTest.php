<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckbox;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfCheckboxTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('pages')->save();
        $this->blueprint = Facades\Blueprint::make()->setContents([
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

                            'handle' => 'checkbox',
                            'field' => [
                                'type' => 'checkbox',
                                'display' => 'Checkbox',
                                'listable' => 'hidden',
                                'options' => [
                                    'option1' => 'Option 1',
                                    'option2' => 'Option 2',
                                    'option3' => 'Option 3',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('pages')->setNamespace('collections.'.$this->collection->handle())->save();
    }

    /** @test */
    public function it_renders_the_component_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfCheckbox::class, ['field' => 'checkbox', 'collection' => 'pages', 'blueprint' => 'pages.pages'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    /** @test */
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfCheckbox::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages']);
    }

    /** @test */
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfCheckbox::class, ['field' => 'checkbox', 'blueprint' => 'pages.not-a-blueprint']);
    }

    /** @test */
    public function it_changes_the_value_of_selected_property_when_an_option_is_set_and_sends_an_event()
    {
        Livewire::test(LfCheckbox::class, ['field' => 'checkbox', 'collection' => 'pages', 'blueprint' => 'pages.pages'])
            ->assertSet('selected', [])
            ->set('selected', ['option1'])
            ->assertSet('selected', ['option1'])
            ->assertDispatched('filterUpdated',
                field: 'checkbox',
                payload: ['option1']
            )
            ->set('selected', ['option1', 'option2'])
            ->assertSet('selected', ['option1', 'option2'])
            ->assertDispatched('filterUpdated',
                field: 'checkbox',
                payload: ['option1', 'option2']
            );
    }
}
