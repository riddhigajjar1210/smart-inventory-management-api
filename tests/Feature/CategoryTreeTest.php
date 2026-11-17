<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_is_nested_and_promotes_children_of_inactive_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->getJson('/api/categories/tree');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Electronics')
            ->assertJsonPath('data.0.children.0.name', 'Laptops')
            ->assertJsonPath('data.0.children.0.children.0.name', 'Gaming Laptops');

        $tree = $response->json('data');
        $office = collect($tree)->firstWhere('slug', 'office-supplies');

        $this->assertNotNull($office);

        $childSlugs = array_column($office['children'], 'slug');

        $this->assertContains('notebooks', $childSlugs);
        $this->assertNotContains('discontinued-range', $childSlugs);
    }

    public function test_category_with_no_products_is_still_returned(): void
    {
        $this->seed(CategorySeeder::class);

        $this->getJson('/api/categories/tree')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'clearance']);
    }
}
