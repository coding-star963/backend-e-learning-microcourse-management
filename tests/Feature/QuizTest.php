<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $learner;
    private Course $course;
    private Lesson $lesson;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $this->learner = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->course = Course::create(['teacher_id' => $this->admin->id, 'title' => 'Community Learning',
            'status' => 'published', 'is_published' => true, 'difficulty_level' => 'beginner']);
        $this->lesson = $this->course->lessons()->create(['title' => 'Video Lesson', 'status' => 'published', 'availability' => 'free', 'order' => 1]);
        $this->enrollment = Enrollment::create(['user_id' => $this->learner->id, 'course_id' => $this->course->id,
            'status' => 'active', 'progress' => 0, 'enrolled_at' => now()]);
    }

    private function path(): string
    {
        return "/api/courses/{$this->course->slug}/lessons/{$this->lesson->id}/quiz";
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['title' => 'Check your understanding', 'description' => 'Choose one answer.',
            'passing_score' => 70, 'is_published' => true, 'revision' => 0, 'questions' => [
                ['question' => 'Which element embeds video?', 'options' => ['video', 'table'], 'correct_answer' => 0, 'explanation' => 'The video element embeds a player.'],
                ['question' => 'Which attribute shows playback controls?', 'options' => ['hidden', 'controls'], 'correct_answer' => 1, 'explanation' => 'Use the controls attribute.'],
            ]], $changes);
    }

    private function createQuiz(array $changes = []): void
    {
        $this->actingAs($this->admin)->putJson($this->path(), $this->payload($changes))->assertOk();
    }

    public function test_admin_can_author_and_unpublish_a_quiz_and_learners_never_receive_the_answer_key(): void
    {
        $this->actingAs($this->admin)->getJson($this->path())->assertOk()->assertJsonPath('quiz', null);
        $this->createQuiz();
        $this->getJson($this->path())->assertJsonPath('quiz.questions.0.correct_answer', 0);
        $response = $this->actingAs($this->learner)->getJson($this->path())->assertOk()
            ->assertJsonCount(2, 'quiz.questions')->assertJsonPath('latest_attempt', null);
        $this->assertArrayNotHasKey('correct_answer', $response->json('quiz.questions.0'));
        $this->assertArrayNotHasKey('explanation', $response->json('quiz.questions.0'));
        $this->getJson("/api/courses/{$this->course->id}/lessons/{$this->lesson->id}")
            ->assertOk()->assertJsonPath('data.quiz.question_count', 2)->assertJsonMissingPath('data.quiz.questions');
        $this->actingAs($this->admin)->putJson($this->path(), $this->payload(['revision' => 1, 'is_published' => false]))->assertOk();
        $this->actingAs($this->learner)->getJson($this->path())->assertNotFound();
        $this->getJson("/api/courses/{$this->course->id}/lessons/{$this->lesson->id}")->assertJsonPath('data.quiz', null);
    }

    public function test_server_scores_answers_and_saves_each_attempt_without_changing_lesson_progress(): void
    {
        $this->createQuiz();
        $this->actingAs($this->learner)->postJson($this->path().'/attempts', [
            'revision' => 1, 'answers' => [0, 0], 'score' => 100, 'passed' => true,
        ])->assertCreated()->assertJsonPath('attempt.score', 50)->assertJsonPath('attempt.passed', false)
            ->assertJsonPath('attempt.results.1.correct_answer', 1)->assertJsonPath('attempt.results.1.selected_answer', 0);
        $this->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])
            ->assertCreated()->assertJsonPath('attempt.score', 100)->assertJsonPath('attempt.passed', true);
        $this->getJson($this->path())->assertJsonPath('latest_attempt.score', 100);
        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->assertEquals(0, $this->enrollment->fresh()->progress);
    }

    public function test_quiz_requires_enrollment_and_an_available_lesson(): void
    {
        $this->createQuiz();
        $this->actingAs($this->learner);
        foreach (['cancelled', 'suspended'] as $status) {
            $this->enrollment->update(['status' => $status]);
            $this->getJson($this->path())->assertForbidden();
            $this->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertForbidden();
        }
        $this->enrollment->update(['status' => 'completed']);
        $this->getJson($this->path())->assertOk();
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['status' => 'published', 'availability' => 'locked'],
            ['availability' => 'scheduled', 'available_at' => now()->addDay()]] as $changes) {
            $this->lesson->update($changes);
            $this->getJson($this->path())->assertForbidden();
            $this->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertForbidden();
        }
        $this->lesson->update(['availability' => 'free', 'status' => 'published', 'is_free_preview' => true]);
        $this->enrollment->delete();
        $this->getJson($this->path())->assertForbidden();
    }

    public function test_student_cannot_author_read_other_results_or_submit_as_admin(): void
    {
        $this->createQuiz();
        $this->actingAs($this->learner)->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertCreated();
        $this->putJson($this->path(), $this->payload(['revision' => 1]))->assertForbidden();
        $this->getJson($this->path().'/attempts')->assertForbidden();
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Enrollment::create(['user_id' => $other->id, 'course_id' => $this->course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($other)->getJson($this->path())->assertOk()->assertJsonPath('latest_attempt', null);
        $this->actingAs($this->admin)->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertForbidden();
        $this->getJson($this->path().'/attempts')->assertOk()->assertJsonPath('attempts.data.0.user.id', $this->learner->id)
            ->assertJsonPath('attempts.data.0.score', 100);
    }

    public function test_stale_revision_cannot_overwrite_or_submit_and_old_results_are_preserved(): void
    {
        $this->createQuiz();
        $this->actingAs($this->learner)->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertCreated();
        $new = $this->payload(['revision' => 1, 'title' => 'Updated quiz', 'passing_score' => 100]);
        $new['questions'][0]['correct_answer'] = 1;
        $this->actingAs($this->admin)->putJson($this->path(), $new)->assertOk()->assertJsonPath('quiz.revision', 2);
        $this->putJson($this->path(), $new)->assertConflict();
        $this->actingAs($this->learner)->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertConflict();
        $this->getJson($this->path())->assertJsonPath('quiz.revision', 2)
            ->assertJsonPath('latest_attempt.revision', 1)->assertJsonPath('latest_attempt.quiz_title', 'Check your understanding')
            ->assertJsonPath('latest_attempt.results.0.correct_answer', 0);
        $this->assertDatabaseCount('quizzes', 1);
        $this->assertDatabaseCount('quiz_attempts', 1);
    }

    public function test_invalid_questions_and_missing_or_invalid_answers_are_rejected(): void
    {
        $invalid = $this->payload();
        $invalid['questions'][0]['correct_answer'] = 5;
        $this->actingAs($this->admin)->putJson($this->path(), $invalid)->assertUnprocessable()->assertJsonValidationErrors('questions.0.correct_answer');
        $invalid = $this->payload();
        $invalid['questions'][0]['options'] = [''];
        $this->putJson($this->path(), $invalid)->assertUnprocessable()->assertJsonValidationErrors('questions.0.options');
        $this->putJson($this->path(), $this->payload(['questions' => []]))->assertUnprocessable();
        $this->createQuiz();
        $this->actingAs($this->learner);
        foreach ([[], [0], [0, 1, 0], [null, 1], [0, 5], ['invalid', 1]] as $answers) {
            $this->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => $answers])->assertUnprocessable();
        }
        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_course_lesson_binding_and_authentication_are_enforced(): void
    {
        $this->getJson($this->path())->assertUnauthorized();
        $this->createQuiz();
        $otherCourse = Course::create(['teacher_id' => $this->admin->id, 'title' => 'Other module', 'difficulty_level' => 'beginner']);
        $this->getJson("/api/courses/{$otherCourse->id}/lessons/{$this->lesson->id}/quiz")->assertNotFound();
        $this->putJson("/api/courses/{$otherCourse->id}/lessons/{$this->lesson->id}/quiz", $this->payload())->assertNotFound();
        $this->learner->update(['is_active' => false]);
        $this->actingAs($this->learner)->getJson($this->path())->assertUnauthorized();
    }

    public function test_lesson_deletion_cascades_quiz_and_attempts(): void
    {
        $this->createQuiz();
        $this->actingAs($this->learner)->postJson($this->path().'/attempts', ['revision' => 1, 'answers' => [0, 1]])->assertCreated();
        $this->lesson->delete();
        $this->assertDatabaseCount('quizzes', 0);
        $this->assertDatabaseCount('quiz_attempts', 0);
    }
}
