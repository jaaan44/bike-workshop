<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function bookingWithStatus(BookingStatus $status): Booking
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();
        $booking->forceFill(['status' => $status])->save();

        return $booking;
    }

    public function test_dashboard_counts_reflect_actual_booking_records(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::Pending);
        $this->bookingWithStatus(BookingStatus::Pending);
        $this->bookingWithStatus(BookingStatus::Accepted);
        $this->bookingWithStatus(BookingStatus::BikeReceived);
        $this->bookingWithStatus(BookingStatus::AwaitingCustomerApproval);
        $this->bookingWithStatus(BookingStatus::AwaitingCustomerApproval);
        $this->bookingWithStatus(BookingStatus::RepairInProgress);
        $this->bookingWithStatus(BookingStatus::QualityCheck);
        $this->bookingWithStatus(BookingStatus::ReadyForPickup);
        $this->bookingWithStatus(BookingStatus::ReadyForDelivery);
        $this->bookingWithStatus(BookingStatus::Completed);
        $this->bookingWithStatus(BookingStatus::Cancelled);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertViewHas('newBookingsCount', 2);
        $response->assertViewHas('acceptedCount', 1);
        $response->assertViewHas('bikeReceivedCount', 1);
        $response->assertViewHas('awaitingApprovalCount', 2);
        $response->assertViewHas('inRepairCount', 1);
        $response->assertViewHas('qualityCheckCount', 1);
        $response->assertViewHas('readyCount', 2);
    }

    public function test_awaiting_approval_count_is_correct(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::AwaitingCustomerApproval);
        $this->bookingWithStatus(BookingStatus::Inspection);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertViewHas('awaitingApprovalCount', 1);
    }

    public function test_repair_in_progress_count_is_correct(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::RepairInProgress);
        $this->bookingWithStatus(BookingStatus::RepairInProgress);
        $this->bookingWithStatus(BookingStatus::RepairCompleted);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertViewHas('inRepairCount', 2);
    }

    public function test_quality_check_count_is_correct(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::QualityCheck);
        $this->bookingWithStatus(BookingStatus::RepairCompleted);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertViewHas('qualityCheckCount', 1);
    }

    public function test_ready_count_includes_both_pickup_and_delivery_statuses(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::ReadyForPickup);
        $this->bookingWithStatus(BookingStatus::ReadyForPickup);
        $this->bookingWithStatus(BookingStatus::ReadyForDelivery);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertViewHas('readyCount', 3);
    }

    public function test_unrelated_statuses_are_not_counted_in_any_tile(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::Completed);
        $this->bookingWithStatus(BookingStatus::Cancelled);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertViewHas('newBookingsCount', 0);
        $response->assertViewHas('acceptedCount', 0);
        $response->assertViewHas('bikeReceivedCount', 0);
        $response->assertViewHas('awaitingApprovalCount', 0);
        $response->assertViewHas('inRepairCount', 0);
        $response->assertViewHas('qualityCheckCount', 0);
        $response->assertViewHas('readyCount', 0);
    }

    public function test_dashboard_tiles_display_the_real_counts_in_the_rendered_page(): void
    {
        $staff = User::factory()->staff()->create();

        $this->bookingWithStatus(BookingStatus::AwaitingCustomerApproval);
        $this->bookingWithStatus(BookingStatus::AwaitingCustomerApproval);
        $this->bookingWithStatus(BookingStatus::QualityCheck);
        $this->bookingWithStatus(BookingStatus::RepairInProgress);
        $this->bookingWithStatus(BookingStatus::ReadyForPickup);

        $html = $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<p class="text-2xl font-bold text-gray-900">2<\/p>\s*<p class="text-xs text-gray-500 mt-1">Awaiting Approval<\/p>/', $html);
    }
}
