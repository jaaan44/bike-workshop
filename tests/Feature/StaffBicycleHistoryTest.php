<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBicycleHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive a booking all the way through the workshop lifecycle to
     * Completed, with a real repair item and inspection, so it represents
     * genuine historical service data.
     */
    private function completeBooking(User $customer, Bicycle $bicycle): Booking
    {
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);

        $booking->inspection()->create([
            'findings' => 'Rear derailleur misaligned.',
            'recommended_repairs' => 'Adjust derailleur and replace cable.',
        ]);
        $booking->transitionTo(BookingStatus::Inspection);

        $item = $booking->repairItems()->create(['description' => 'Adjust rear derailleur']);

        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);
        $booking->transitionTo(BookingStatus::RepairInProgress);

        $item->completed_at = now();
        $item->save();

        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);
        $booking->transitionTo(BookingStatus::ReadyForPickup);
        $booking->transitionTo(BookingStatus::Completed);

        return $booking->refresh();
    }

    public function test_staff_sees_no_previous_repairs_section_when_bicycle_has_no_history(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $this->actingAs($staff)
            ->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Previous Repairs for This Bicycle');
    }

    public function test_staff_can_see_previous_completed_repairs_for_the_bicycle(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $previous = $this->completeBooking($customer, $bicycle);

        $current = Booking::factory()->for($customer)->for($bicycle)->create();
        $current->transitionTo(BookingStatus::Accepted);

        $response = $this->actingAs($staff)->get(route('staff.bookings.show', $current));

        $response->assertOk()
            ->assertSee('Previous Repairs for This Bicycle')
            ->assertSee($previous->reference_number)
            ->assertSee('Rear derailleur misaligned.')
            ->assertSee('Adjust rear derailleur')
            ->assertSee('Picked up');
    }

    public function test_technician_can_see_previous_completed_repairs_for_the_bicycle(): void
    {
        $technician = User::factory()->technician()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $previous = $this->completeBooking($customer, $bicycle);
        $current = Booking::factory()->for($customer)->for($bicycle)->create();

        $this->actingAs($technician)
            ->get(route('staff.bookings.show', $current))
            ->assertOk()
            ->assertSee($previous->reference_number);
    }

    public function test_current_booking_is_not_listed_among_its_own_previous_repairs(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $booking = $this->completeBooking($customer, $bicycle);

        // Viewing the completed booking's own detail page shouldn't list
        // itself as a "previous" repair.
        $this->actingAs($staff)
            ->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Previous Repairs for This Bicycle');
    }

    public function test_cancelled_booking_does_not_appear_as_a_previous_repair(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $cancelled = Booking::factory()->for($customer)->for($bicycle)->create();
        $cancelled->transitionTo(BookingStatus::Cancelled);

        $current = Booking::factory()->for($customer)->for($bicycle)->create();

        $response = $this->actingAs($staff)->get(route('staff.bookings.show', $current));

        $response->assertOk()
            ->assertDontSee('Previous Repairs for This Bicycle')
            ->assertDontSee($cancelled->reference_number);
    }

    public function test_previous_repairs_are_ordered_newest_first(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();

        $older = $this->completeBooking($customer, $bicycle);
        Booking::whereKey($older->id)->update(['updated_at' => now()->subDays(30)]);

        $newer = $this->completeBooking($customer, $bicycle);
        Booking::whereKey($newer->id)->update(['updated_at' => now()]);

        $current = Booking::factory()->for($customer)->for($bicycle)->create();

        $response = $this->actingAs($staff)->get(route('staff.bookings.show', $current));

        $response->assertOk()->assertSeeInOrder([
            $newer->reference_number,
            $older->reference_number,
        ]);
    }
}
