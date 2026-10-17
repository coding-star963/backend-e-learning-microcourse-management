<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    // --- Self-registration (mobile) ----------------------------------------

    public function test_student_can_register_with_a_gmail_address(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'Juan.DelaCruz@Gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.email', 'juan.delacruz@gmail.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'juan.delacruz@gmail.com',
            'role' => 'student',
            'is_active' => true,
        ]);
    }

    public function test_registration_rejects_non_gmail_addresses(): void
    {
        foreach (['juan@yahoo.com', 'juan@gmail.com.ph', 'juan@notgmail.com', 'juan@mail.gmail.com'] as $email) {
            $this->postJson('/api/register', [
                'name' => 'Juan',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertStatus(422)->assertJsonValidationErrors(['email']);
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_always_creates_a_student(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'administrator',
        ])->assertStatus(201)->assertJsonPath('user.role', 'student');
    }

    public function test_registration_rejects_a_taken_email(): void
    {
        $this->makeUser('taken@gmail.com', 'student');

        $this->postJson('/api/register', [
            'name' => 'Copy',
            'email' => 'taken@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // --- Web forms that create or edit student accounts --------------------

    public function test_admin_must_use_gmail_for_new_students_but_not_staff(): void
    {
        $this->actingAs($this->makeUser('admin@school.com', 'administrator'));

        $this->postJson('/api/users', [
            'name' => 'Student',
            'email' => 'student@school.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/api/users', [
            'name' => 'Teacher',
            'email' => 'teacher@school.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'teacher',
        ])->assertStatus(201);
    }

    public function test_admin_cannot_change_a_students_email_to_non_gmail(): void
    {
        $this->actingAs($this->makeUser('admin@school.com', 'administrator'));
        $student = $this->makeUser('student@gmail.com', 'student');

        $this->putJson("/api/users/{$student->id}", ['email' => 'student@school.com'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->putJson("/api/users/{$student->id}", ['email' => 'renamed@gmail.com'])
            ->assertStatus(200);
    }

    public function test_teacher_must_use_gmail_when_registering_a_student_for_enrollment(): void
    {
        $teacher = $this->makeUser('teacher@school.com', 'teacher');
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Course',
            'status' => 'published',
            'is_published' => true,
            'difficulty_level' => 'beginner',
        ]);
        $this->actingAs($teacher);

        $this->postJson('/api/enrollments', [
            'course_id' => $course->id,
            'name' => 'New',
            'email' => 'new@school.com',
            'password' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/api/enrollments', [
            'course_id' => $course->id,
            'name' => 'New',
            'email' => 'new@gmail.com',
            'password' => 'password123',
        ])->assertStatus(201);
    }

    // --- Profile ------------------------------------------------------------

    public function test_student_profile_email_changes_must_be_gmail(): void
    {
        $this->actingAs($this->makeUser('legacy@school.com', 'student'));

        // Saving the unchanged (older, non-Gmail) email still works.
        $this->putJson('/api/profile', ['name' => 'Renamed', 'email' => 'legacy@school.com'])
            ->assertStatus(200);

        $this->putJson('/api/profile', ['email' => 'other@school.com'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->putJson('/api/profile', ['email' => 'mine@gmail.com'])
            ->assertStatus(200);
    }

    public function test_teacher_profile_email_is_not_restricted_to_gmail(): void
    {
        $this->actingAs($this->makeUser('teacher@school.com', 'teacher'));

        $this->putJson('/api/profile', ['email' => 'teacher@deped.gov.ph'])->assertStatus(200);
    }
}
