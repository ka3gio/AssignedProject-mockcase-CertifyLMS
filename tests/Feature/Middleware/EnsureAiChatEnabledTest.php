<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureAiChatEnabled;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureAiChatEnabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Route::middleware(EnsureAiChatEnabled::class)
            ->get('/__test/ai-chat-enabled', fn () => 'ok');

        // 404 ページ描画時に共有サイドバーが参照するルート名を、この単体テスト内でも解決可能にする。
        Route::get('/__test/ai-chat-index', fn () => 'index')->name('ai-chat.index');
    }

    public function test_request_passes_when_feature_is_enabled(): void
    {
        config(['ai-chat.enabled' => true]);

        $this->get('/__test/ai-chat-enabled')->assertOk()->assertSee('ok');
    }

    public function test_request_is_not_found_when_feature_is_disabled(): void
    {
        config(['ai-chat.enabled' => false]);

        $this->get('/__test/ai-chat-enabled')->assertNotFound();
    }
}
