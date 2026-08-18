<?php

namespace Tests\Feature;

use App\Models\Bicycle;
use App\Models\BicycleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BicycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_empty_state_with_no_bicycles(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('customer.bikes.index'))
            ->assertSee("You haven't added a bicycle yet.");
    }

    public function test_customer_can_register_a_bicycle(): void
    {
        $customer = User::factory()->create();
        $type = BicycleType::create(['name' => 'Road Bike', 'sort_order' => 0]);

        $response = $this->actingAs($customer)->post(route('customer.bikes.store'), [
            'nickname' => 'My Road Bike',
            'bicycle_type_id' => $type->id,
        ]);

        $bicycle = Bicycle::first();
        $response->assertRedirect(route('customer.bikes.show', $bicycle));
        $this->assertSame($customer->id, $bicycle->user_id);
        $this->assertSame('My Road Bike', $bicycle->nickname);
    }

    public function test_bicycle_registration_requires_nickname_and_type(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.bikes.store'), [])
            ->assertInvalid(['nickname', 'bicycle_type_id']);
    }

    public function test_customer_can_view_their_own_bicycle(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create(['nickname' => 'Trek Marlin']);

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee('Trek Marlin');
    }

    public function test_customer_cannot_view_another_customers_bicycle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bicycle = Bicycle::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertForbidden();
    }

    public function test_customer_can_update_their_own_bicycle(): void
    {
        $customer = User::factory()->create();
        $type = BicycleType::create(['name' => 'Mountain Bike', 'sort_order' => 0]);
        $bicycle = Bicycle::factory()->for($customer)->for($type)->create(['nickname' => 'Old Name']);

        $this->actingAs($customer)->put(route('customer.bikes.update', $bicycle), [
            'nickname' => 'New Name',
            'bicycle_type_id' => $type->id,
        ])->assertRedirect(route('customer.bikes.show', $bicycle));

        $this->assertSame('New Name', $bicycle->refresh()->nickname);
    }

    public function test_customer_cannot_update_another_customers_bicycle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bicycle = Bicycle::factory()->for($owner)->create();

        $this->actingAs($other)->put(route('customer.bikes.update', $bicycle), [
            'nickname' => 'Hijacked',
            'bicycle_type_id' => $bicycle->bicycle_type_id,
        ])->assertForbidden();
    }
}
