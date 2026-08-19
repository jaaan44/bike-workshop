<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $customer, BookingStatus $status = BookingStatus::Pending): Booking
    {
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        if ($status !== BookingStatus::Pending) {
            $booking->forceFill(['status' => $status])->save();
        }

        return $booking;
    }

    public function test_staff_sees_empty_state_with_no_bookings(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('staff.bookings.index'))
            ->assertSee('No bookings yet');
    }

    public function test_staff_can_list_and_view_bookings(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $this->actingAs($staff)
            ->get(route('staff.bookings.index'))
            ->assertSee($booking->reference_number);

        $this->actingAs($staff)
            ->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertSee($booking->reference_number)
            ->assertSee($customer->name);
    }

    public function test_staff_can_accept_a_pending_booking(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create());

        $this->actingAs($staff)
            ->post(route('staff.bookings.accept', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Accepted, $booking->status);

        $history = $booking->statusHistories()->first();
        $this->assertSame(BookingStatus::Pending, $history->old_status);
        $this->assertSame(BookingStatus::Accepted, $history->new_status);
        $this->assertSame($staff->id, $history->changed_by);
    }

    public function test_accepting_an_already_accepted_booking_is_a_no_op(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::Accepted);

        $this->actingAs($staff)
            ->post(route('staff.bookings.accept', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Accepted, $booking->refresh()->status);
        $this->assertCount(0, $booking->statusHistories);
    }

    public function test_staff_can_receive_the_bicycle_for_an_accepted_booking(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::Accepted);

        $this->actingAs($staff)
            ->post(route('staff.bookings.receive', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::BikeReceived, $booking->refresh()->status);
    }

    public function test_staff_cannot_receive_a_bicycle_that_has_not_been_accepted(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create());

        $this->actingAs($staff)->post(route('staff.bookings.receive', $booking));

        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);
    }

    public function test_staff_can_cancel_a_pending_or_accepted_booking(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create());

        $this->actingAs($staff)
            ->post(route('staff.bookings.cancel', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_staff_cannot_cancel_a_booking_whose_bike_was_already_received(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);

        $this->actingAs($staff)->post(route('staff.bookings.cancel', $booking));

        $this->assertSame(BookingStatus::BikeReceived, $booking->refresh()->status);
    }

    public function test_technician_can_also_manage_bookings(): void
    {
        $technician = User::factory()->technician()->create();
        $booking = $this->bookingFor(User::factory()->create());

        $this->actingAs($technician)
            ->post(route('staff.bookings.accept', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Accepted, $booking->refresh()->status);
    }

    public function test_customer_cannot_manage_bookings(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor(User::factory()->create());

        $this->actingAs($customer)
            ->get(route('staff.bookings.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('staff.bookings.accept', $booking))
            ->assertForbidden();
    }
}
