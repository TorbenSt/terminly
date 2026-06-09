<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StaffMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_cannot_view_staff_management_index(): void
    {
        $company = Company::factory()->create();
        $user = $this->createStaffUser($company);

        $this->actingAs($user)
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_staff_management_index(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('company_admin');

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk();
    }

    public function test_staff_can_view_and_update_own_working_hours(): void
    {
        $company = Company::factory()->create();
        $user = $this->createStaffUser($company);

        $this->actingAs($user)
            ->get(route('working-hours.index'))
            ->assertOk();

        $staffMember = StaffMember::where('user_id', $user->id)->firstOrFail();

        $response = $this->actingAs($user)->patch(route('working-hours.update'), [
            'availabilities' => collect(range(0, 6))->map(fn (int $day) => [
                'day_of_week' => $day,
                'is_working' => in_array($day, [1, 2, 3, 4, 5], true),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'has_break' => in_array($day, [1, 2, 3, 4, 5], true),
                'break_start_time' => '12:00',
                'break_end_time' => '13:00',
            ])->all(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_availabilities', [
            'staff_member_id' => $staffMember->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start_time' => '12:00:00',
            'break_end_time' => '13:00:00',
        ]);
    }

    private function createStaffUser(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('staff');

        $staffMember = StaffMember::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        foreach (range(1, 5) as $day) {
            $staffMember->availabilities()->create([
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
            ]);
        }

        return $user;
    }
}
