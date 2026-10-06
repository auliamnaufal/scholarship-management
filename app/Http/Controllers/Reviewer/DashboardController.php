<?php

namespace App\Http\Controllers\Reviewer;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // A coordinator cannot review their own scholarships, so those do not
        // show up as work to pick up.
        $notMine = fn ($query) => $query->where('coordinator_id', '!=', Auth::id());

        $search = function ($query, string $term) {
            $term = '%'.trim($term).'%';

            $query->where(fn ($q) => $q
                ->whereHas('student', fn ($s) => $s->where('name', 'like', $term)->orWhere('email', 'like', $term))
                ->orWhereHas('program', fn ($p) => $p->where('name', 'like', $term)));
        };

        $claimable = Application::query()
            ->where('status', ApplicationStatus::Submitted)
            ->whereHas('program', $notMine)
            ->when($request->filled('claim_q'), fn ($query) => $search($query, $request->string('claim_q')))
            ->with(['student', 'program'])
            ->latest('submission_date')
            ->get();

        $awaitingMyReview = Application::query()
            ->where('status', ApplicationStatus::UnderReview)
            ->whereHas('program', $notMine)
            ->whereDoesntHave('reviews', function ($query) {
                $query->where('reviewer_id', Auth::id());
            })
            ->when($request->filled('review_q'), fn ($query) => $search($query, $request->string('review_q')))
            ->with(['student', 'program'])
            ->latest('submission_date')
            ->get();

        $allSubmissions = Application::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($query) => $search($query, $request->string('q')))
            ->with(['student', 'program'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $reviewedByMe = Review::where('reviewer_id', Auth::id())->count();

        return view('reviewer.dashboard', [
            'claimable' => $claimable,
            'awaitingMyReview' => $awaitingMyReview,
            'allSubmissions' => $allSubmissions,
            'reviewedByMe' => $reviewedByMe,
            'statuses' => ApplicationStatus::cases(),
            'selectedStatus' => $request->string('status')->toString(),
        ]);
    }
}
