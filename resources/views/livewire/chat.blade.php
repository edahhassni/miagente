<div dir="rtl" x-data="{ sidebarOpen: false }"
     class="flex h-screen bg-[#F7F4EE] text-[#2B2A28] overflow-hidden"
     style="font-family: 'IBM Plex Sans Arabic', sans-serif;">

    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/30 z-20 md:hidden"
         style="display: none;"></div>

    <aside
        :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'"
        class="fixed md:static z-30 md:z-auto top-0 right-0 h-full w-72
               flex flex-col bg-[#EFEBE3] border-l border-[#E3DDD0]
               transition-transform duration-200 md:translate-x-0">

        <div class="p-5 flex items-center justify-between gap-2.5 border-b border-[#E3DDD0]/60">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-full bg-[#C1652F] flex items-center justify-center text-white text-sm font-bold shrink-0">✦</span>
                <span class="font-semibold text-[15px]">مساعدك الذكي</span>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden text-[#9C9686] text-xl leading-none px-1">×</button>
        </div>

        <div class="px-4 pt-4 pb-3">
            <button wire:click="newConversation" @click="sidebarOpen = false"
                class="w-full py-2.5 rounded-xl bg-[#C1652F] text-white text-sm font-medium
                       hover:bg-[#A8541F] transition-colors shadow-sm">
                + محادثة جديدة
            </button>
        </div>

        <div class="px-4 pb-3">
            <label class="block text-[11px] text-[#9C9686] mb-1.5 px-0.5">النموذج</label>
            <select wire:model.live="selectedModel"
                class="w-full text-xs p-2.5 rounded-lg bg-white border border-[#E3DDD0]
                       focus:outline-none focus:ring-1 focus:ring-[#C1652F]">
                @forelse ($models as $groupLabel => $groupModels)
                    @if (! empty($groupModels))
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($groupModels as $model)
                                <option value="{{ $model }}">{{ $model }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @empty
                    <option value="">لا توجد نماذج متاحة</option>
                @endforelse
            </select>
        </div>

        <div class="px-2.5 pt-2 pb-4 flex-1 overflow-y-auto space-y-1">
            @forelse ($conversations as $conv)
                <div class="group flex items-center gap-1">
                    <button wire:click="loadConversation({{ $conv->id }})" @click="sidebarOpen = false"
                        class="flex-1 text-right px-3 py-2.5 rounded-lg text-sm truncate transition-colors
                               {{ $conversation && $conversation->id === $conv->id
                                    ? 'bg-white shadow-sm font-medium'
                                    : 'hover:bg-white/60 text-[#6B6559]' }}">
                        {{ $conv->title }}
                    </button>
                    <button wire:click="deleteConversation({{ $conv->id }})"
                        class="hidden group-hover:flex w-7 h-7 items-center justify-center
                               rounded-lg text-[#9C9686] hover:text-red-500 hover:bg-red-50 transition-colors shrink-0">
                        ×
                    </button>
                </div>
            @empty
                <p class="text-xs text-[#9C9686] px-3 py-2">لا توجد محادثات بعد</p>
            @endforelse
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">

        <div class="md:hidden flex items-center gap-3 px-4 py-3 border-b border-[#E3DDD0] bg-[#F7F4EE]">
            <button @click="sidebarOpen = true" class="text-xl leading-none">☰</button>
            <span class="text-sm font-medium">مساعدك الذكي</span>
        </div>

        <div class="flex-1 overflow-y-auto" id="messagesPane">
            <div class="max-w-3xl mx-auto px-4 md:px-6 py-6 md:py-8 space-y-6">

                @if (empty($messages))
                    <div class="text-center text-[#9C9686] pt-16 md:pt-24">
                        <p class="text-2xl mb-2">✦</p>
                        <p class="text-lg font-medium">كيف يمكنني مساعدتك؟</p>
                        <p class="text-sm mt-1">اكتب رسالتك بالأسفل وابدأ المحادثة</p>
                    </div>
                @endif

                @foreach ($messages as $msg)
                    @if ($msg['role'] === 'user')
                        <div class="flex justify-start">
                            <div class="bg-white shadow-sm rounded-2xl rounded-tr-md px-4 py-2.5
                                        max-w-[85%] md:max-w-lg text-sm leading-relaxed">
                                {{ $msg['content'] }}
                            </div>
                        </div>
                    @else
                        <div class="flex gap-3 items-start">
                            <span class="w-7 h-7 shrink-0 rounded-full bg-[#C1652F] flex items-center justify-center text-white text-xs mt-0.5">✦</span>
                            <div class="markdown-body text-[14.5px] leading-relaxed min-w-0 flex-1"
                                 data-raw="{{ $msg['content'] }}">{{ $msg['content'] }}</div>
                        </div>
                    @endif
                @endforeach

                @if ($isSending)
                    <div class="flex gap-3 items-center">
                        <span class="w-7 h-7 shrink-0 rounded-full bg-[#C1652F] flex items-center justify-center text-white text-xs">✦</span>
                        <div class="flex gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#C1652F] animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-[#C1652F] animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-[#C1652F] animate-bounce"></span>
                        </div>
                    </div>
                @endif

            </div>
        </div>

        <div class="px-4 md:px-6 pb-4 md:pb-6 pt-2 border-t border-[#E3DDD0]">
            <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md border border-[#E3DDD0] flex items-end gap-2 p-2">
                <textarea
                    wire:model="message"
                    wire:keydown.enter.prevent.exact="sendMessage"
                    rows="1"
                    placeholder="اكتب رسالتك هنا... (Shift+Enter لسطر جديد)"
                    class="flex-1 resize-none border-none focus:ring-0 text-sm p-2.5 max-h-40 bg-transparent outline-none"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,160)+'px'"
                ></textarea>
                <button wire:click="sendMessage"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    class="w-10 h-10 shrink-0 rounded-xl bg-[#C1652F] hover:bg-[#A8541F]
                           disabled:opacity-40 text-white flex items-center justify-center transition-colors">
                    <span wire:loading.remove wire:target="sendMessage" class="text-lg">↑</span>
                    <span wire:loading wire:target="sendMessage" class="text-xs">…</span>
                </button>
            </div>
        </div>
    </main>
</div>

@once
<style>
    .markdown-body p { margin: 0 0 0.6em; }
    .markdown-body p:last-child { margin-bottom: 0; }
    .markdown-body ul, .markdown-body ol { margin: 0.4em 0 0.8em 1.4em; }
    .markdown-body li { margin-bottom: 0.2em; }
    .markdown-body h1,.markdown-body h2,.markdown-body h3 { font-weight: 700; margin: 0.8em 0 0.4em; }
    .markdown-body h1 { font-size: 1.3em; }
    .markdown-body h2 { font-size: 1.15em; }
    .markdown-body h3 { font-size: 1em; }
    .markdown-body strong { font-weight: 700; }
    .markdown-body em { font-style: italic; }
    .markdown-body a { color: #C1652F; text-decoration: underline; }
    .markdown-body blockquote {
        border-right: 3px solid #C1652F;
        padding: 6px 12px; margin: 8px 0;
        background: #f9f6f0; border-radius: 0 8px 8px 0;
        color: #6B6559;
    }
    .markdown-body table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 13px; }
    .markdown-body th,.markdown-body td { border: 1px solid #E3DDD0; padding: 7px 12px; }
    .markdown-body th { background: #EFEBE3; font-weight: 600; }
    .markdown-body code:not(pre code) {
        background: #EFEBE3; padding: 1px 5px; border-radius: 4px;
        font-size: 0.85em; direction: ltr; display: inline-block;
    }
    .markdown-body pre {
        position: relative; direction: ltr; text-align: left;
        border-radius: 10px; margin: 0.8em 0; overflow: hidden;
        background: #1e1e2e;
    }
    .markdown-body pre code {
        display: block; padding: 2.2em 1em 1em;
        overflow-x: auto; font-size: 0.82em; color: #e2e8f0;
    }
    .markdown-body .copy-btn {
        position: absolute; top: 8px; left: 10px;
        font-size: 11px; padding: 3px 9px; border-radius: 6px;
        background: rgba(255,255,255,0.1); color: #cfcfcf;
        border: 1px solid rgba(255,255,255,0.15); cursor: pointer;
        font-family: sans-serif; transition: background .15s;
    }
    .markdown-body .copy-btn:hover { background: rgba(255,255,255,0.2); }
</style>

<script>
function renderMarkdownBodies() {
    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') return;

    document.querySelectorAll('.markdown-body[data-raw]').forEach((el) => {
        try {
            const raw   = el.getAttribute('data-raw');
            const dirty = marked.parse(raw, { breaks: true });
            el.innerHTML = DOMPurify.sanitize(dirty);
            el.removeAttribute('data-raw');

            el.querySelectorAll('pre code').forEach((block) => {
                if (typeof hljs !== 'undefined') hljs.highlightElement(block);
                const pre = block.parentElement;
                if (pre.querySelector('.copy-btn')) return;
                const btn = document.createElement('button');
                btn.className   = 'copy-btn';
                btn.type        = 'button';
                btn.textContent = 'نسخ';
                btn.addEventListener('click', () => {
                    navigator.clipboard.writeText(block.textContent).then(() => {
                        btn.textContent = 'تم ✓';
                        setTimeout(() => btn.textContent = 'نسخ', 1500);
                    });
                });
                pre.appendChild(btn);
            });
        } catch (e) {
            console.error('Markdown render error:', e);
        }
    });

    const pane = document.getElementById('messagesPane');
    if (pane) pane.scrollTop = pane.scrollHeight;
}

document.addEventListener('DOMContentLoaded',      renderMarkdownBodies);
document.addEventListener('livewire:navigated',    renderMarkdownBodies);
document.addEventListener('livewire:morph',        renderMarkdownBodies);
document.addEventListener('livewire:morphed',      renderMarkdownBodies);
document.addEventListener('livewire:update',       renderMarkdownBodies);
document.addEventListener('livewire:updated',      renderMarkdownBodies);
</script>
@endonce