<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BicycleServiceHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive a booking all the way through the workshop lifecycle to
     * Completed, with a real repair item and inspection along the way, so
     * it represents genuine historical service data (not a forced status).
     */
    private function completeBooking(User $customer, Bicycle $bicycle, string $fulfillment = 'pickup'): Booking
    {
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);

        $booking->inspection()->create([
            'findings' => 'Chain is worn and skipping under load.',
            'recommended_repairs' => 'Replace chain and cassette.',
        ]);
        $booking->transitionTo(BookingStatus::Inspection);

        $item = $booking->repairItems()->create(['description' => 'Replace chain']);

        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);
        $booking->transitionTo(BookingStatus::RepairInProgress);

        $item->completed_at = now();
        $item->save();

        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);
        $booking->transitionTo($fulfillment === 'pickup' ? BookingStatus::ReadyForPickup : BookingStatus::ReadyForDelivery);
        $booking->transitionTo(BookingStatus::Completed);

        return $booking->refresh();
    }

    public function test_bicycle_with_no_history_renders_empty_state(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee('No completed service history yet.');
    }

    public function test_completed_repair_appears_in_service_history(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = $this->completeBooking($customer, $bicycle);

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee($booking->reference_number)
            ->assertSee('Replace chain')
            ->assertDontSee('No completed service history yet.');
    }

    public function test_multiple_completed_repairs_are_ordered_newest_first(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $older = $this->completeBooking($customer, $bicycle);
        Booking::whereKey($older->id)->update(['updated_at' => now()->subDays(30)]);

        $newer = $this->completeBooking($customer, $bicycle);
        Booking::whereKey($newer->id)->update(['updated_at' => now()]);

        $response = $this->actingAs($customer)->get(route('customer.bikes.show', $bicycle));

        $response->assertOk()->assertSeeInOrder([
            $newer->reference_number,
            $older->reference_number,
        ]);
    }

    public function test_cancelled_booking_is_not_shown_as_completed_service(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $cancelled = Booking::factory()->for($customer)->for($bicycle)->create();
        $cancelled->transitionTo(BookingStatus::Cancelled);

        $response = $this->actingAs($customer)->get(route('customer.bikes.show', $bicycle));

        $response->assertOk()
            ->assertSee('No completed service history yet.')
            ->assertDontSee($cancelled->reference_number);
    }

    public function test_active_repair_is_shown_separately_from_completed_history(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $completed = $this->completeBooking($customer, $bicycle);

        $active = Booking::factory()->for($customer)->for($bicycle)->create();
        $active->transitionTo(BookingStatus::Accepted);

        $response = $this->actingAs($customer)->get(route('customer.bikes.show', $bicycle));

        $response->assertOk()
            ->assertSee('Current Repair')
            ->assertSee($active->reference_number)
            ->assertSee($completed->reference_number)
            ->assertSee('Previous Service History');
    }

    public function test_multiple_active_bookings_are_all_shown(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $first = Booking::factory()->for($customer)->for($bicycle)->create();
        $first->transitionTo(BookingStatus::Accepted);

        $second = Booking::factory()->for($customer)->for($bicycle)->create();
        $second->transitionTo(BookingStatus::Accepted);

        $response = $this->actingAs($customer)->get(route('customer.bikes.show', $bicycle));

        $response->assertOk()
            ->assertSee('Current Repairs')
            ->assertSee($first->reference_number)
            ->assertSee($second->reference_number);
    }

    public function test_history_entry_links_to_the_existing_repair_detail_page(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = $this->completeBooking($customer, $bicycle);

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee(route('customer.repairs.show', $booking), false);
    }

    public function test_completed_service_count_is_correct(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $this->completeBooking($customer, $bicycle);
        $this->completeBooking($customer, $bicycle);
        $this->completeBooking($customer, $bicycle);

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee('3 completed services');
    }

    public function test_last_service_date_is_correct(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $booking = $this->completeBooking($customer, $bicycle);
        Booking::whereKey($booking->id)->update(['updated_at' => now()->subDays(5)]);

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee('Last serviced '.$booking->fresh()->updated_at->format('M j, Y'));
    }

    public function test_fulfillment_information_renders_for_delivered_repairs(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $this->completeBooking($customer, $bicycle, fulfillment: 'delivery');

        $this->actingAs($customer)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertOk()
            ->assertSee('Delivered');
    }

    public function test_customer_cannot_view_another_customers_bicycle_service_history(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bicycle = Bicycle::factory()->for($owner)->create();
        $this->completeBooking($owner, $bicycle);

        $this->actingAs($other)
            ->get(route('customer.bikes.show', $bicycle))
            ->assertForbidden();
    }

    public function test_customer_cannot_reach_another_customers_historical_repair_through_the_history_link(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bicycle = Bicycle::factory()->for($owner)->create();
        $booking = $this->completeBooking($owner, $bicycle);

        $this->actingAs($other)
            ->get(route('customer.repairs.show', $booking))
            ->assertForbidden();
    }
}
