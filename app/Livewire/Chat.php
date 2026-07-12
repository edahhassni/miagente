<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Chat extends Component
{
    public $message    = '';
    public $messages   = [];
    public $conversation;
    public $conversations = [];
    public $models        = [];
    public $selectedModel = '';
    public $isSending     = false;

    protected array $fallbackModels = [
        'دردشة عامة' => [
            'zhipuai/glm-5.1',
            'moonshotai/kimi-k2.6',
            'qwen/qwen3.5-397b-a17b',
            'minimaxai/minimax-m3',
            'deepseek/deepseek-v4-flash',
        ],
        'برمجة' => [
            'qwen/qwen2.5-coder-32b-instruct',
        ],
        'رؤية / وسائط متعددة' => [],
    ];

    public function mount(): void
    {
        $this->conversations = Conversation::latest()->get();

        if ($this->conversations->count()) {
            $this->loadConversation($this->conversations->first()->id);
        } else {
            $this->newConversation();
        }

        $this->loadModels();
    }

    public function loadModels(): void
    {
        $this->models = cache()->remember('nvidia_models_grouped', 900, function () {
            try {
                $response = $this->buildHttpClient(15)
                    ->get('https://integrate.api.nvidia.com/v1/models');

                if (! $response->successful()) {
                    return $this->fallbackModels;
                }

                $ids = collect($response->json('data', []))->pluck('id');

                if ($ids->isEmpty()) {
                    return $this->fallbackModels;
                }

                return $this->categorizeModels($ids);

            } catch (ConnectionException | RequestException) {
                return $this->fallbackModels;
            }
        });

        $this->selectedModel = $this->models['دردشة عامة'][0] ?? 'qwen/qwen3.5-397b-a17b';
    }

    protected function categorizeModels($ids): array
    {
        $exclude = [
            'embed', 'safety', 'guard', 'translate', 'parse',
            'detector', 'reward', 'gliner', 'clip', 'pii',
            'moderation', 'topic-control', 'tts', 'whisper',
            'ocr', 'rerank', 'ner',
        ];

        $ids = $ids->reject(
            fn($id) => collect($exclude)->contains(fn($p) => str_contains($id, $p))
        );

        $codePatterns   = ['coder', 'codestral', 'codellama', 'starcoder', 'codegemma', 'granite-code'];
        $visionPatterns = ['vision', '-vl', 'multimodal', 'vila', 'neva', 'kosmos'];

        $code   = $ids->filter(fn($id) => collect($codePatterns)->contains(fn($p) => str_contains($id, $p)));
        $vision = $ids->filter(fn($id) => collect($visionPatterns)->contains(fn($p) => str_contains($id, $p)));
        $chat   = $ids->diff($code)->diff($vision);

        return [
            'دردشة عامة'          => $this->sortByPower($chat)->values()->all(),
            'برمجة'                => $this->sortByPower($code)->values()->all(),
            'رؤية / وسائط متعددة' => $this->sortByPower($vision)->values()->all(),
        ];
    }

    protected function sortByPower($collection)
    {
        return $collection->sortByDesc(fn($id) => $this->modelPowerScore($id));
    }

    protected function modelPowerScore(string $id): float
    {
        $flagships = ['qwen3.5-397b', 'deepseek-v4', 'kimi-k2', 'glm-5', 'minimax-m3'];

        foreach ($flagships as $i => $needle) {
            if (str_contains($id, $needle)) {
                return 100000 - $i;
            }
        }

        if (preg_match('/(\d+(?:\.\d+)?)b/i', $id, $m)) {
            return (float) $m[1];
        }

        return 0;
    }

    public function newConversation(): void
    {
        $this->conversation  = Conversation::create(['title' => 'محادثة جديدة']);
        $this->messages      = [];
        $this->conversations = Conversation::latest()->get();
    }

    public function loadConversation(int $id): void
    {
        $this->conversation = Conversation::findOrFail($id);
        $this->messages     = $this->conversation
            ->messages()
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function deleteConversation(int $id): void
    {
        Conversation::findOrFail($id)->delete();
        $this->conversations = Conversation::latest()->get();

        if ($this->conversation?->id === $id) {
            if ($this->conversations->count()) {
                $this->loadConversation($this->conversations->first()->id);
            } else {
                $this->newConversation();
            }
        }
    }

    public function sendMessage(): void
    {
        $text = trim($this->message);
        if (! $text || $this->isSending) return;

        $this->message   = '';
        $this->isSending = true;

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role'            => 'user',
            'content'         => $text,
        ]);

        if ($this->conversation->messages()->count() === 1) {
            $this->conversation->update([
                'title' => \Illuminate\Support\Str::limit($text, 40),
            ]);
        }

        $history = $this->conversation
            ->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $reply = $this->generateAssistantReply($history);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role'            => 'assistant',
            'content'         => $reply,
        ]);

        $this->loadConversation($this->conversation->id);
        $this->conversations = Conversation::latest()->get();
        $this->isSending     = false;
    }

    protected function generateAssistantReply(array $history): string
    {
        $apiKey = config('services.nvidia.api_key');

        if (! $apiKey || ! $this->selectedModel) {
            return 'عذرًا، لم يتم ضبط مفتاح API أو اختيار نموذج.';
        }

        try {
            $response = $this->buildHttpClient(90)
                ->post('https://integrate.api.nvidia.com/v1/chat/completions', [
                    'model'       => $this->selectedModel,
                    'messages'    => $history,
                    'temperature' => 0.6,
                    'max_tokens'  => 2048,
                ]);

            if (! $response->successful()) {
                \Log::error('NVIDIA API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return 'عذرًا، حدث خطأ في الاتصال بالنموذج.';
            }

            return data_get(
                $response->json(),
                'choices.0.message.content',
                'عذرًا، لم يأتِ رد من النموذج.'
            );

        } catch (ConnectionException $e) {
            return 'انتهت مهلة الانتظار. حاول مرة أخرى.';
        } catch (RequestException $e) {
            return 'عذرًا، حدث خطأ في الطلب.';
        }
    }

    protected function buildHttpClient(int $timeout = 15)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.nvidia.api_key'),
            'Content-Type'  => 'application/json',
        ])->timeout($timeout)->connectTimeout(10);
    }

    public function render()
    {
        return view('livewire.chat');
    }
}