<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBiodataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The student's own "my info" page: academic/financial biodata plus the
 * account fields (name, email, password, delete account) that Breeze's
 * profile partials handle for every role — students get them here instead
 * of on a separate /profile page.
 */
class BiodataController extends Controller
{
    public function edit(): View
    {
        return view('student.biodata.edit', [
            'profile' => Auth::user()->studentProfile,
            'guardianPhones' => Auth::user()->guardianPhones->pluck('phone_number')->all(),
            'user' => Auth::user(),
        ]);
    }

    public function update(UpdateBiodataRequest $request): RedirectResponse
    {
        Auth::user()->studentProfile()->updateOrCreate(
            ['user_id' => Auth::id()],
            Arr::except($request->validated(), ['guardian_phones']),
        );
        Auth::user()->syncGuardianPhones($request->validated('guardian_phones') ?? []);

        return redirect()
            ->route('student.biodata.edit')
            ->with('status', __('Biodata saved.'));
    }
}
