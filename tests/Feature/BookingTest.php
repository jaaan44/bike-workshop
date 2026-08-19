<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\BicyclePart;
use App\Models\BicyclePartCategory;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_empty_state_with_no_repairs(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('customer.repairs.index'))
            ->assertSee('No repairs yet');
    }

    public function test_booking_page_prompts_to_add_a_bicycle_first_when_none_exist(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('customer.repairs.create'))
            ->assertSee('Add a bicycle first');
    }

    public function test_customer_can_book_a_repair(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $category = BicyclePartCategory::factory()->create();
        $part = BicyclePart::factory()->for($category)->create();

        $response = $this->actingAs($customer)->post(route('customer.repairs.store'), [
            'bicycle_id' => $bicycle->id,
            'bicycle_parts' => [$part->id],
            'remarks' => 'Chain makes noise.',
            'appointment_date' => now()->addDays(2)->toDateString(),
        ]);

        $booking = Booking::first();
        $response->assertRedirect(route('customer.repairs.show', $booking));

        $this->assertSame($customer->id, $booking->user_id);
        $this->assertSame($bicycle->id, $booking->bicycle_id);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertMatchesRegularExpression('/^BR-\d{4}-\d{5}$/', $booking->reference_number);
        $this->assertTrue($booking->bicycleParts->contains($part));
    }

    public function test_booking_requires_bicycle_parts_and_appointment_date(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->post(route('customer.repairs.store'), ['bicycle_id' => $bicycle->id])
            ->assertInvalid(['bicycle_parts', 'appointment_date']);
    }

    public function test_customer_cannot_book_a_repair_for_another_customers_bicycle(): void
    {
        $customer = User::factory()->create();
        $otherBicycle = Bicycle::factory()->create();
        $part = BicyclePart::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.repairs.store'), [
                'bicycle_id' => $otherBicycle->id,
                'bicycle_parts' => [$part->id],
                'appointment_date' => now()->addDay()->toDateString(),
            ])
            ->assertInvalid(['bicycle_id']);
    }

    public function test_appointment_date_cannot_be_in_the_past(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $part = BicyclePart::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.repairs.store'), [
                'bicycle_id' => $bicycle->id,
                'bicycle_parts' => [$part->id],
                'appointment_date' => now()->subDay()->toDateString(),
            ])
            ->assertInvalid(['appointment_date']);
    }

    public function test_customer_can_view_their_own_booking(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee($booking->reference_number);
    }

    public function test_customer_cannot_view_another_customers_booking(): void
    {
        $owner = User::factory()->create();
        $bicycle = Bicycle::factory()->for($owner)->create();
        $booking = Booking::factory()->for($owner)->for($bicycle)->create();

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('customer.repairs.show', $booking))
            ->assertForbidden();
    }

    public function test_reference_numbers_increment_within_the_same_year(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $first = Booking::factory()->for($customer)->for($bicycle)->create();
        $second = Booking::factory()->for($customer)->for($bicycle)->create();

        $year = now()->year;
        $this->assertSame("BR-{$year}-00001", $first->reference_number);
        $this->assertSame("BR-{$year}-00002", $second->reference_number);
    }

    private function bookingAwaitingApproval(User $customer): Booking
    {
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();
        $booking->forceFill(['status' => BookingStatus::AwaitingCustomerApproval])->save();
        $booking->repairItems()->create(['description' => 'Replace brake pads']);

        return $booking;
    }

    public function test_customer_can_approve_proposed_repairs(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingAwaitingApproval($customer);

        $this->actingAs($customer)
            ->post(route('customer.repairs.approve', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::RepairInProgress, $booking->refresh()->status);
    }

    public function test_customer_can_decline_proposed_repairs(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingAwaitingApproval($customer);

        $this->actingAs($customer)
            ->post(route('customer.repairs.decline', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Inspection, $booking->refresh()->status);
    }

    public function test_customer_cannot_approve_a_booking_not_awaiting_approval(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $this->actingAs($customer)->post(route('customer.repairs.approve', $booking));

        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);
    }

    public function test_customer_cannot_approve_another_customers_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->bookingAwaitingApproval($owner);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('customer.repairs.approve', $booking))
            ->assertForbidden();

        $this->assertSame(BookingStatus::AwaitingCustomerApproval, $booking->refresh()->status);
    }

    public function test_proposed_repairs_are_visible_once_awaiting_approval(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingAwaitingApproval($customer);

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertSee('Replace brake pads')
            ->assertSee('Approve');
    }
}
