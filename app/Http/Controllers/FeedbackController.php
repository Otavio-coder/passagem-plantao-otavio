<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(): View
    {
        return view('dashboard.feedback');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sector' => ['nullable', 'string', 'max:255'],
            'shift' => ['required', 'in:manha,tarde,noite'],
            'rating' => ['required', 'in:excelente,bom,regular,ruim'],
            'difficulty' => ['required', 'string', 'max:5000'],
            'suggestion' => ['nullable', 'string', 'max:5000'],
            'is_anonymous' => ['boolean'],
        ]);

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        FeedbackSubmission::create([
            'sector' => $validated['sector'] ?? null,
            'shift' => $validated['shift'],
            'rating' => $validated['rating'],
            'difficulty' => $validated['difficulty'],
            'suggestion' => $validated['suggestion'] ?? null,
            'is_anonymous' => $isAnonymous,
            'user_id' => $isAnonymous ? null : Auth::id(),
            'user_name_cached' => $isAnonymous ? null : Auth::user()?->name,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true]);
    }
}
