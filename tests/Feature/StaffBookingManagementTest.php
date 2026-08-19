<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\RepairInspection;
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

    public function test_staff_can_assign_a_technician_to_a_booking(): void
    {
        $staff = User::factory()->staff()->create();
        $technician = User::factory()->technician()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);

        $this->actingAs($staff)
            ->put(route('staff.bookings.technician.update', $booking), [
                'assigned_technician_id' => $technician->id,
            ])
            ->assertRedirect();

        $this->assertSame($technician->id, $booking->refresh()->assigned_technician_id);
    }

    public function test_staff_can_unassign_a_technician(): void
    {
        $staff = User::factory()->staff()->create();
        $technician = User::factory()->technician()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);
        $booking->forceFill(['assigned_technician_id' => $technician->id])->save();

        $this->actingAs($staff)
            ->put(route('staff.bookings.technician.update', $booking), [
                'assigned_technician_id' => '',
            ])
            ->assertRedirect();

        $this->assertNull($booking->refresh()->assigned_technician_id);
    }

    public function test_assigning_a_non_technician_user_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);

        $this->actingAs($staff)
            ->put(route('staff.bookings.technician.update', $booking), [
                'assigned_technician_id' => $customer->id,
            ])
            ->assertSessionHasErrors('assigned_technician_id');

        $this->assertNull($booking->refresh()->assigned_technician_id);
    }

    public function test_staff_can_save_inspection_findings_and_it_transitions_the_booking(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);

        $this->actingAs($staff)
            ->put(route('staff.bookings.inspection.update', $booking), [
                'findings' => 'Worn brake pads, loose headset.',
                'recommended_repairs' => 'Replace brake pads, adjust headset.',
            ])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Inspection, $booking->status);
        $this->assertSame('Worn brake pads, loose headset.', $booking->inspection->findings);
        $this->assertSame('Replace brake pads, adjust headset.', $booking->inspection->recommended_repairs);
        $this->assertSame($staff->id, $booking->inspection->inspected_by);
    }

    public function test_saving_inspection_again_updates_the_same_record_without_retransitioning(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::Inspection);
        $booking->inspection()->create(['findings' => 'Initial findings']);

        $this->actingAs($staff)->put(route('staff.bookings.inspection.update', $booking), [
            'findings' => 'Updated findings',
            'recommended_repairs' => null,
        ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Inspection, $booking->status);
        $this->assertSame(1, RepairInspection::where('booking_id', $booking->id)->count());
        $this->assertSame('Updated findings', $booking->inspection->findings);
    }

    public function test_staff_can_add_and_complete_repair_items(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::Inspection);

        $this->actingAs($staff)
            ->post(route('staff.bookings.repair-items.store', $booking), [
                'description' => 'Replace brake pads',
            ])
            ->assertRedirect();

        $item = $booking->repairItems()->sole();
        $this->assertSame('Replace brake pads', $item->description);
        $this->assertSame($staff->id, $item->added_by);
        $this->assertFalse($item->isCompleted());

        $this->actingAs($staff)
            ->post(route('staff.bookings.repair-items.complete', [$booking, $item]))
            ->assertRedirect();

        $this->assertTrue($item->refresh()->isCompleted());
    }

    public function test_completing_a_repair_item_from_a_different_booking_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $bookingA = $this->bookingFor(User::factory()->create(), BookingStatus::Inspection);
        $bookingB = $this->bookingFor(User::factory()->create(), BookingStatus::Inspection);
        $item = $bookingB->repairItems()->create(['description' => 'Adjust gears']);

        $this->actingAs($staff)
            ->post(route('staff.bookings.repair-items.complete', [$bookingA, $item]))
            ->assertNotFound();
    }

    public function test_staff_can_add_a_technician_note(): void
    {
        $technician = User::factory()->technician()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::RepairInProgress);

        $this->actingAs($technician)
            ->post(route('staff.bookings.notes.store', $booking), [
                'note' => 'Needs a part ordered from the supplier.',
            ])
            ->assertRedirect();

        $note = $booking->technicianNotes()->sole();
        $this->assertSame('Needs a part ordered from the supplier.', $note->note);
        $this->assertSame($technician->id, $note->user_id);
    }

    public function test_customer_cannot_manage_repair_job_details(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor(User::factory()->create(), BookingStatus::BikeReceived);

        $this->actingAs($customer)
            ->put(route('staff.bookings.inspection.update', $booking), ['findings' => 'x'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('staff.bookings.repair-items.store', $booking), ['description' => 'x'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('staff.bookings.notes.store', $booking), ['note' => 'x'])
            ->assertForbidden();
    }
}
