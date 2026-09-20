<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRepairTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $customer): Booking
    {
        $bicycle = Bicycle::factory()->for($customer)->create();

        return Booking::factory()->for($customer)->for($bicycle)->create();
    }

    public function test_current_booking_status_is_displayed_correctly(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->transitionTo(BookingStatus::Accepted);

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('Accepted');
    }

    public function test_timeline_renders_existing_history_in_chronological_order(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection);

        $response = $this->actingAs($customer)->get(route('customer.repairs.show', $booking));

        $response->assertOk()->assertSeeInOrder([
            'Booking Submitted',
            'Accepted',
            'Bike Received',
            'Inspection',
        ]);
    }

    public function test_booking_with_minimal_history_does_not_fail(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('Booking Submitted')
            ->assertSee('(Current)');
    }

    public function test_quality_check_rework_loop_is_preserved_in_the_timeline(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection);
        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);
        $booking->transitionTo(BookingStatus::RepairInProgress);
        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);
        $booking->transitionTo(BookingStatus::RepairInProgress);
        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);

        $response = $this->actingAs($customer)->get(route('customer.repairs.show', $booking));

        $response->assertOk();

        // "Repair In Progress" only appears in the timeline (the current
        // status badge shows "Quality Check"), so this count is exactly the
        // number of times the booking passed through that stage.
        $repairInProgressCount = substr_count($response->getContent(), 'Repair In Progress');

        $this->assertSame(2, $repairInProgressCount);
    }

    public function test_inspection_information_renders_when_present(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->inspection()->create([
            'findings' => 'Brake pads worn down.',
            'recommended_repairs' => 'Replace both brake pads.',
        ]);
        $booking->repairItems()->create(['description' => 'Replace brake pads']);
        $booking->forceFill(['status' => BookingStatus::AwaitingCustomerApproval])->save();

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('Brake pads worn down.')
            ->assertSee('Replace both brake pads.')
            ->assertSee('Replace brake pads');
    }

    public function test_missing_optional_information_does_not_break_the_page(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->forceFill(['status' => BookingStatus::AwaitingCustomerApproval])->save();

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk();
    }

    public function test_ready_for_pickup_state_renders_correctly(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->forceFill(['status' => BookingStatus::ReadyForPickup])->save();

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('ready for pickup at the workshop');
    }

    public function test_ready_for_delivery_state_renders_correctly(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->forceFill(['status' => BookingStatus::ReadyForDelivery])->save();

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('ready to be delivered to you');
    }

    public function test_completed_repair_renders_correctly(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection);
        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);
        $booking->transitionTo(BookingStatus::RepairInProgress);
        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);
        $booking->transitionTo(BookingStatus::ReadyForPickup);
        $booking->transitionTo(BookingStatus::Completed);

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('This repair is complete')
            ->assertSee('picked up');
    }
}
