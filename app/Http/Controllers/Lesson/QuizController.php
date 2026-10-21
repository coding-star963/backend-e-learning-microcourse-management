<?php

namespace App\Http\Controllers\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $admin = $course->isManageableBy($request->user());
        if (! $admin) {
            $this->authorizeLearner($request, $course, $lesson);
        }
        $quiz = $lesson->quiz;
        if (! $admin) {
            abort_unless($quiz?->is_published, 404, 'No quiz is available for this lesson.');
        }

        return response()->json([
            'quiz' => $quiz ? new QuizResource($quiz) : null,
            'latest_attempt' => ! $admin && $quiz
                ? $quiz->attempts()->where('user_id', $request->user()->id)->latest('id')->first()
                : null,
        ]);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($course->isManageableBy($request->user()), 403);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'passing_score' => 'required|integer|between:0,100',
            'is_published' => 'required|boolean',
            'revision' => 'required|integer|min:0',
            'questions' => 'required|array|list|min:1|max:50',
            'questions.*' => 'required|array:question,options,correct_answer,explanation',
            'questions.*.question' => 'required|string|max:2000',
            'questions.*.options' => 'required|array|list|min:2|max:6',
            'questions.*.options.*' => 'required|string|max:1000',
            'questions.*.correct_answer' => 'required|integer|min:0',
            'questions.*.explanation' => 'nullable|string|max:2000',
        ]);
        foreach ($data['questions'] as $index => $question) {
            if ($question['correct_answer'] >= count($question['options'])) {
                throw ValidationException::withMessages(["questions.$index.correct_answer" => 'Select one of the answer options.']);
            }
        }

        $quiz = DB::transaction(function () use ($lesson, $data) {
            // Serialize authoring and submissions; stale screens cannot overwrite or grade a newer revision.
            $lesson = Lesson::whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $quiz = $lesson->quiz()->lockForUpdate()->first();
            abort_unless(($quiz?->revision ?? 0) === (int) $data['revision'], 409, 'This quiz has changed. Reload it before saving.');
            $data['revision'] = ($quiz?->revision ?? 0) + 1;
            $quiz = $lesson->quiz()->updateOrCreate([], $data);
            $lesson->touch();
            return $quiz;
        });

        return response()->json(['quiz' => new QuizResource($quiz), 'message' => 'Quiz saved successfully.']);
    }

    public function submit(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeLearner($request, $course, $lesson);
        $data = $request->validate([
            'revision' => 'required|integer|min:1',
            'answers' => 'required|array|list|min:1|max:50',
            'answers.*' => 'required|integer|min:0|max:5',
        ]);

        $attempt = DB::transaction(function () use ($request, $lesson, $data) {
            Lesson::whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $quiz = $lesson->quiz()->lockForUpdate()->first();
            abort_unless($quiz?->is_published, 404, 'No quiz is available for this lesson.');
            abort_unless($quiz->revision === (int) $data['revision'], 409, 'This quiz has changed. Reload it and try again.');
            $questions = $quiz->questions;
            if (count($data['answers']) !== count($questions)) {
                throw ValidationException::withMessages(['answers' => 'Answer every question before submitting.']);
            }
            $results = [];
            $correct = 0;
            foreach ($questions as $index => $question) {
                $answer = (int) $data['answers'][$index];
                if (! array_key_exists($answer, $question['options'])) {
                    throw ValidationException::withMessages(["answers.$index" => 'Select one of the answer options.']);
                }
                $isCorrect = $answer === (int) $question['correct_answer'];
                $correct += (int) $isCorrect;
                $results[] = array_merge($question, ['selected_answer' => $answer, 'is_correct' => $isCorrect]);
            }
            $score = round($correct / count($questions) * 100, 2);

            return $quiz->attempts()->create([
                'user_id' => $request->user()->id,
                'revision' => $quiz->revision,
                'quiz_title' => $quiz->title,
                'correct_count' => $correct,
                'question_count' => count($questions),
                'score' => $score,
                'passing_score' => $quiz->passing_score,
                'passed' => $score >= $quiz->passing_score,
                'results' => $results,
            ]);
        });

        return response()->json(['attempt' => $attempt, 'message' => 'Quiz submitted successfully.'], 201);
    }

    public function attempts(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($course->isManageableBy($request->user()), 403);
        $quiz = $lesson->quiz;
        return response()->json(['attempts' => $quiz
            ? $quiz->attempts()->with('user:id,name,email')->latest('id')->paginate(20)
            : ['data' => []]]);
    }

    private function authorizeLearner(Request $request, Course $course, Lesson $lesson): void
    {
        abort_unless($request->user()->isStudent(), 403, 'Only learners can take quizzes.');
        abort_unless($course->isVisibleTo($request->user()) && $lesson->isAvailable(), 403, 'This lesson is not available yet.');
        abort_unless($course->enrollments()->where('user_id', $request->user()->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])->exists(),
            403, 'Enroll in this module to take its quizzes.');
    }
}
