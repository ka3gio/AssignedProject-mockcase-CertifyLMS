<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ShowAction;
use App\Http\Requests\QaThread\StoreRequest;
use App\UseCases\QaThread\StoreAction;
use Illuminate\View\View;
use App\Models\Certification;
use App\Models\QaThread;
use App\Enums\CertificationStatus;
use Illuminate\Http\RedirectResponse;


class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $result = $action(
            viewer: $request->user(),
            keyword: $validated['keyword'] ?? null,
            certification_id: $validated['certification_id'] ?? null,
            status: isset($validated['status']) ? QaThreadStatus::from($validated['status']) : null,
        );

        // Q&Aスレッドの一覧を取得してビューに渡す処理
        return view('qa-thread.index', [
            'threads' => $result['threads'],
            'filters' => [
                'keyword' => $validated['keyword'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
            'certifications' => $result['certifications'],
            'publishedStatus' => QaThreadStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()->where('status', CertificationStatus::Published->value)->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を作成しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
