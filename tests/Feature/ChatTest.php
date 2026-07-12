<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('stores a fallback assistant reply when the AI request times out', function () {
    Http::fake(function ($request) {
        if ($request->url() === 'https://integrate.api.nvidia.com/v1/models') {
            return Http::response([
                'data' => [['id' => 'test-model']],
            ], 200);
        }

        if ($request->url() === 'https://integrate.api.nvidia.com/v1/chat/completions') {
            throw new ConnectionException('Timed out');
        }

        return Http::response([], 200);
    });

    Livewire::test('chat')
        ->set('message', 'مرحبا')
        ->call('sendMessage');

    expect(Conversation::count())->toBe(1)
        ->and(Message::count())->toBe(2);

    $assistantMessage = Message::where('role', 'assistant')->latest()->first();

    expect($assistantMessage->content)->toBe('عذرًا، لم أتمكن من معالجة طلبك حاليًا. يرجى المحاولة مرة أخرى.');
});
