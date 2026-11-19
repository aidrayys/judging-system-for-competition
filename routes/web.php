<?php

use App\Http\Controllers\Admin\CriterionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EvaluationController as AdminEvaluationController;
use App\Http\Controllers\Admin\FinalistController;
use App\Http\Controllers\Admin\JudgeController;
use App\Http\Controllers\Admin\PresentationController;
use App\Http\Controllers\Admin\ResultsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\VotingAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Judge\DashboardController as JudgeDashboardController;
use App\Http\Controllers\Judge\EvaluationController as JudgeEvaluationController;
use App\Http\Controllers\PresentationAccessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\VotingController;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('judge.dashboard');
    }
    return view('landing');
})->name('home');

Route::get('/voting', [VotingController::class, 'index'])->name('voting.public.index');
Route::post('/voting/verify', [VotingController::class, 'verify'])->name('voting.public.verify');
Route::get('/voting/ballot', [VotingController::class, 'ballot'])->name('voting.public.ballot');
Route::post('/voting/ballot', [VotingController::class, 'submit'])->name('voting.public.submit');
Route::get('/voting/thanks', [VotingController::class, 'thanks'])->name('voting.public.thanks');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Finalists
    Route::get('/finalists', [FinalistController::class, 'index'])->name('finalists.index');
    Route::get('/finalists/create', [FinalistController::class, 'create'])->name('finalists.create');
    Route::post('/finalists', [FinalistController::class, 'store'])->name('finalists.store');
    Route::get('/finalists/{finalist}/edit', [FinalistController::class, 'edit'])->name('finalists.edit');
    Route::put('/finalists/{finalist}', [FinalistController::class, 'update'])->name('finalists.update');
    Route::post('/finalists/{finalist}/toggle', [FinalistController::class, 'toggle'])->name('finalists.toggle');
    Route::delete('/finalists/{finalist}', [FinalistController::class, 'destroy'])->name('finalists.destroy');

    // Presentations
    Route::get('/presentations', [PresentationController::class, 'index'])->name('presentations.index');
    Route::post('/presentations/{finalist}', [PresentationController::class, 'store'])->name('presentations.store');
    Route::delete('/presentations/{finalist}', [PresentationController::class, 'destroy'])->name('presentations.destroy');

    // Rubric
    Route::get('/criteria', [CriterionController::class, 'index'])->name('criteria.index');
    Route::put('/criteria/{category}', [CriterionController::class, 'update'])->name('criteria.update');
    Route::put('/criteria/{category}/category', [CriterionController::class, 'updateCategory'])->name('categories.update');
    Route::post('/criteria/{category}/reset', [CriterionController::class, 'reset'])->name('criteria.reset');

    // Judges
    Route::get('/judges', [JudgeController::class, 'index'])->name('judges.index');
    Route::post('/judges', [JudgeController::class, 'store'])->name('judges.store');
    Route::put('/judges/{user}', [JudgeController::class, 'update'])->name('judges.update');
    Route::post('/judges/{user}/reset-password', [JudgeController::class, 'resetPassword'])->name('judges.reset-password');
    Route::post('/judges/{user}/toggle', [JudgeController::class, 'toggle'])->name('judges.toggle');

    // Evaluations
    Route::get('/evaluations', [AdminEvaluationController::class, 'index'])->name('evaluations.index');
    Route::get('/evaluations/{evaluation}', [AdminEvaluationController::class, 'show'])->name('evaluations.show');
    Route::put('/evaluations/{evaluation}', [AdminEvaluationController::class, 'correct'])->name('evaluations.correct');
    Route::post('/evaluations/{evaluation}/reset-scores', [AdminEvaluationController::class, 'resetScores'])->name('evaluations.reset-scores');
    Route::post('/evaluations/{evaluation}/reset-submission', [AdminEvaluationController::class, 'resetSubmission'])->name('evaluations.reset-submission');
    Route::post('/evaluations/{evaluation}/reopen', [AdminEvaluationController::class, 'reopen'])->name('evaluations.reopen');
    Route::post('/evaluations/{evaluation}/lock', [AdminEvaluationController::class, 'lock'])->name('evaluations.lock');

    // Results
    Route::get('/results', [ResultsController::class, 'index'])->name('results.index');
    Route::get('/results/{category}', [ResultsController::class, 'show'])->name('results.show');
    Route::post('/results/{category}/release', [ResultsController::class, 'release'])->name('results.release');
    Route::post('/results/{category}/republish', [ResultsController::class, 'republish'])->name('results.republish');

    // Settings (judging period)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/open', [SettingsController::class, 'open'])->name('settings.open');
    Route::post('/settings/close', [SettingsController::class, 'close'])->name('settings.close');

    // Favorite Idea Voting admin
    Route::get('/voting', [VotingAdminController::class, 'index'])->name('voting.index');
    Route::post('/voting/period', [VotingAdminController::class, 'storePeriod'])->name('voting.period.store');
    Route::post('/voting/toggle', [VotingAdminController::class, 'toggleOpen'])->name('voting.toggle');
    Route::post('/voting/import-voters', [VotingAdminController::class, 'importVoters'])->name('voting.import-voters');
    Route::post('/voting/finalists/{finalist}/winner', [VotingAdminController::class, 'setWinner'])->name('voting.set-winner');
    Route::post('/voting/finalists/{finalist}/winner/clear', [VotingAdminController::class, 'clearWinner'])->name('voting.clear-winner');
    Route::post('/voting/finalize', [VotingAdminController::class, 'finalize'])->name('voting.finalize');
    Route::post('/voting/unfinalize', [VotingAdminController::class, 'unfinalize'])->name('voting.unfinalize');
    Route::get('/voting/export', [VotingAdminController::class, 'exportCsv'])->name('voting.export');
    Route::get('/voting/voters', [VotingAdminController::class, 'voters'])->name('voting.voters');
    Route::post('/voting/voters/{voter}/reset', [VotingAdminController::class, 'resetVoter'])->name('voting.voters.reset');
    Route::post('/voting/reset-all', [VotingAdminController::class, 'resetAll'])->name('voting.reset-all');
});

/*
|--------------------------------------------------------------------------
| Judge
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:judge'])->prefix('judge')->name('judge.')->group(function () {
    Route::get('/', [JudgeDashboardController::class, 'index'])->name('dashboard');

    Route::get('/category/{category}/finalist/{finalist}', [JudgeEvaluationController::class, 'show'])->name('evaluate');
    Route::post('/category/{category}/finalist/{finalist}/autosave', [JudgeEvaluationController::class, 'autosave'])
        ->middleware('judging.open')
        ->name('autosave');
    Route::post('/category/{category}/finalist/{finalist}/submit', [JudgeEvaluationController::class, 'submit'])
        ->middleware('judging.open')
        ->name('submit');
    Route::post('/category/{category}/finalist/{finalist}/correct', [JudgeEvaluationController::class, 'correct'])
        ->middleware('judging.open')
        ->name('correct');
    Route::post('/category/{category}/finalist/{finalist}/reset', [JudgeEvaluationController::class, 'reset'])
        ->middleware('judging.open')
        ->name('reset');
    Route::get('/category/{category}/finalist/{finalist}/submitted', [JudgeEvaluationController::class, 'submitted'])->name('submitted');
});

/*
|--------------------------------------------------------------------------
| Private presentation files (authenticated only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,judge'])->group(function () {
    Route::get('/presentations/{finalist}/open', [PresentationAccessController::class, 'show'])->name('presentations.open');
    Route::get('/presentations/{finalist}/download', [PresentationAccessController::class, 'download'])->name('presentations.download');
});
