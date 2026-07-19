{{--
    Poșta internă a personalului — client de e-mail (tipar Gmail): șină de foldere + listă de
    conversații + firul deschis în același ecran + compunere în card-overlay.

    ⚠️ UN SINGUR element rădăcină (morphing Livewire). Stilurile stau în theme.css (.cx-mail*,
    .cx-msg*) — un <style> frate al rădăcinii ar fi eliminat la morphing. `wire:key` pe rânduri,
    mesaje și pe compozitoarele cu FileUpload (recreate prin cheile de versiune după expediere).

    Poll DOAR pe vederea de listă, cu compunerea închisă — altfel ar rescrie textul în lucru.
--}}
<x-filament-panels::page>
    <div class="cx-mail">
        {{-- ȘINA: Scrie + foldere --}}
        <aside class="cx-mail__rail">
            <button type="button" class="cx-write" wire:click="openCompose">
                <x-filament::icon icon="heroicon-o-pencil-square" class="cx-write__icon" />
                {{ __('panel.mailbox.compose') }}
            </button>

            <nav class="cx-folders" aria-label="{{ __('panel.mailbox.folders_aria') }}">
                @foreach ($this->folders() as $key)
                    @php($count = $this->countsData[$key] ?? ['total' => 0, 'unread' => 0])
                    @php($badge = in_array($key, ['inbox', 'audience'], true) ? $count['unread'] : ($key === 'trash' || $key === 'archive' || $key === 'starred' ? $count['total'] : 0))
                    <button
                        type="button"
                        wire:key="folder-{{ $key }}"
                        wire:click="setFolder('{{ $key }}')"
                        @class(['cx-folder', 'cx-folder--active' => $folder === $key])
                    >
                        <x-filament::icon :icon="match ($key) {
                            'inbox' => 'heroicon-o-inbox',
                            'starred' => 'heroicon-o-star',
                            'sent' => 'heroicon-o-paper-airplane',
                            'archive' => 'heroicon-o-archive-box',
                            'trash' => 'heroicon-o-trash',
                            'audience' => 'heroicon-o-megaphone',
                            default => 'heroicon-o-folder',
                        }" class="cx-folder__icon" />
                        <span class="cx-folder__label">{{ __("panel.mailbox.folders.{$key}") }}</span>
                        @if ($badge > 0)
                            <span @class(['cx-folder__badge', 'cx-folder__badge--info' => in_array($key, ['inbox', 'audience'], true)])>{{ $badge }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- ZONA PRINCIPALĂ --}}
        <section
            class="cx-mail__main"
            @if ($thread === null && ! $composeOpen) wire:poll.30s @endif
        >
            @if ($thread === null)
                {{-- LISTA --}}
                <div class="cx-toolbar">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="cx-toolbar__icon" />
                    <input
                        type="search"
                        class="cx-toolbar__search"
                        placeholder="{{ __('panel.mailbox.search_placeholder') }}"
                        wire:model.live.debounce.400ms="search"
                    >
                </div>

                <ul @class(['cx-list', 'cx-list--trash' => $folder === 'trash']) role="list">
                    @forelse ($this->threads as $row)
                        <li wire:key="row-{{ $row['id'] }}" @class(['cx-row', 'cx-row--unread' => $row['unread']])>
                            <button
                                type="button"
                                class="cx-row__star"
                                wire:click="toggleStar({{ $row['id'] }})"
                                title="{{ $row['starred'] ? __('panel.mailbox.unstar') : __('panel.mailbox.star') }}"
                            >
                                <x-filament::icon :icon="$row['starred'] ? 'heroicon-s-star' : 'heroicon-o-star'" @class(['cx-star', 'cx-star--on' => $row['starred']]) />
                            </button>

                            <button type="button" class="cx-row__main" wire:click="openThread({{ $row['id'] }})">
                                <span class="cx-row__who">
                                    {{ $row['with'] }}
                                    @if ($row['isAudience'])
                                        <span class="cx-chip cx-chip--audience">{{ __('panel.mailbox.folders.audience') }}</span>
                                    @endif
                                </span>
                                <span class="cx-row__text">
                                    <span class="cx-row__subject">{{ $row['subject'] }}</span>
                                    <span class="cx-row__snippet">&nbsp;— {{ $row['snippet'] }}</span>
                                </span>
                                <span class="cx-row__meta">
                                    @if ($row['hasAttachments'])
                                        <x-filament::icon icon="heroicon-o-paper-clip" class="cx-row__clip" />
                                    @endif
                                    <time class="cx-row__time">{{ $row['time'] }}</time>
                                </span>
                            </button>

                            {{-- Acțiuni pe rând (vizibile la hover, ca la un client de e-mail) --}}
                            <span class="cx-row__actions">
                                @if ($folder === 'trash')
                                    <button type="button" class="cx-iconbtn" wire:click="restoreThread({{ $row['id'] }})" title="{{ __('panel.mailbox.restore') }}">
                                        <x-filament::icon icon="heroicon-o-arrow-uturn-up" />
                                    </button>
                                @else
                                    <button type="button" class="cx-iconbtn" wire:click="toggleArchive({{ $row['id'] }})" title="{{ $row['archived'] ? __('panel.mailbox.unarchive') : __('panel.mailbox.archive') }}">
                                        <x-filament::icon :icon="$row['archived'] ? 'heroicon-o-archive-box-x-mark' : 'heroicon-o-archive-box-arrow-down'" />
                                    </button>
                                    @if ($row['unread'])
                                        <button type="button" class="cx-iconbtn" wire:click="openThread({{ $row['id'] }})" title="{{ __('panel.mailbox.open') }}">
                                            <x-filament::icon icon="heroicon-o-envelope-open" />
                                        </button>
                                    @else
                                        <button type="button" class="cx-iconbtn" wire:click="markUnread({{ $row['id'] }})" title="{{ __('panel.mailbox.mark_unread') }}">
                                            <x-filament::icon icon="heroicon-o-envelope" />
                                        </button>
                                    @endif
                                    <button type="button" class="cx-iconbtn cx-iconbtn--danger" wire:click="moveToTrash({{ $row['id'] }})" title="{{ __('panel.mailbox.trash') }}">
                                        <x-filament::icon icon="heroicon-o-trash" />
                                    </button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="cx-empty">
                            <x-filament::icon icon="heroicon-o-inbox" class="cx-empty__icon" />
                            <p>{{ __("panel.mailbox.empty.{$folder}") }}</p>
                        </li>
                    @endforelse
                </ul>

                @if (count($this->threads) >= 50)
                    <p class="cx-cap">{{ __('panel.mailbox.cap_note') }}</p>
                @endif
            @else
                {{-- CONVERSAȚIA --}}
                @php($data = $this->openThreadData)
                <div class="cx-thread-top">
                    <button type="button" class="cx-iconbtn" wire:click="closeThread" title="{{ __('panel.mailbox.back') }}">
                        <x-filament::icon icon="heroicon-o-arrow-left" />
                    </button>
                    <h2 class="cx-thread-top__subject">{{ $data['subject'] }}</h2>
                    <span class="cx-thread-top__actions">
                        <button type="button" class="cx-iconbtn" wire:click="toggleStar({{ $data['id'] }})" title="{{ $data['starred'] ? __('panel.mailbox.unstar') : __('panel.mailbox.star') }}">
                            <x-filament::icon :icon="$data['starred'] ? 'heroicon-s-star' : 'heroicon-o-star'" @class(['cx-star--on' => $data['starred']]) />
                        </button>
                        @if ($data['trashed'])
                            <button type="button" class="cx-iconbtn" wire:click="restoreThread({{ $data['id'] }})" title="{{ __('panel.mailbox.restore') }}">
                                <x-filament::icon icon="heroicon-o-arrow-uturn-up" />
                            </button>
                        @else
                            <button type="button" class="cx-iconbtn" wire:click="toggleArchive({{ $data['id'] }})" title="{{ $data['archived'] ? __('panel.mailbox.unarchive') : __('panel.mailbox.archive') }}">
                                <x-filament::icon :icon="$data['archived'] ? 'heroicon-o-archive-box-x-mark' : 'heroicon-o-archive-box-arrow-down'" />
                            </button>
                            <button type="button" class="cx-iconbtn" wire:click="markUnread({{ $data['id'] }})" title="{{ __('panel.mailbox.mark_unread') }}">
                                <x-filament::icon icon="heroicon-o-envelope" />
                            </button>
                            <button type="button" class="cx-iconbtn cx-iconbtn--danger" wire:click="moveToTrash({{ $data['id'] }})" title="{{ __('panel.mailbox.trash') }}">
                                <x-filament::icon icon="heroicon-o-trash" />
                            </button>
                        @endif
                    </span>
                </div>

                <div class="cx-thread__meta">
                    <div>
                        <span class="cx-thread__with">{{ $data['with'] }}</span>
                        @if ($data['student'])
                            <span class="cx-thread__student">{{ __('panel.fields.student') }}: {{ $data['student'] }}</span>
                        @endif
                    </div>
                    <x-filament::badge :color="$data['type'] === 'audience' ? 'warning' : 'primary'">
                        {{ $data['type'] === 'audience' ? __('panel.mailbox.folders.audience') : __('panel.mailbox.body') }}
                    </x-filament::badge>
                </div>

                <div class="cx-thread__stream">
                    @foreach ($data['messages'] as $message)
                        <div wire:key="msg-{{ $message['id'] }}" @class(['cx-msg', 'cx-msg--mine' => $message['mine']])>
                            <div class="cx-msg__head">
                                <span class="cx-msg__sender">{{ $message['mine'] ? __('panel.mailbox.you') : $message['senderName'] }}</span>
                                <time class="cx-msg__time">{{ $message['at'] }}</time>
                            </div>
                            <div class="cx-msg__body">{!! nl2br(e($message['body'])) !!}</div>
                            @if ($message['attachments'] !== [])
                                <ul class="cx-msg__files">
                                    @foreach ($message['attachments'] as $attachment)
                                        <li>
                                            <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer" class="cx-file">
                                                <x-filament::icon :icon="$attachment['isImage'] ? 'heroicon-o-photo' : 'heroicon-o-paper-clip'" class="cx-file__icon" />
                                                <span class="cx-file__name">{{ $attachment['name'] }}</span>
                                                <span class="cx-file__size">{{ $attachment['size'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Răspuns INLINE — firul rămâne vizibil deasupra; ascuns când firul e la coș. --}}
                @if (! $data['trashed'])
                    <form wire:submit="sendReply" class="cx-reply">
                        <div class="cx-reply__head">
                            <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="cx-reply__icon" />
                            <span>{{ __('panel.mailbox.reply_to', ['name' => $data['with']]) }}</span>
                        </div>
                        <div wire:key="reply-composer-{{ $replyKey }}">
                            {{ $this->replyForm }}
                        </div>
                        <div class="cx-reply__actions">
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendReply,reply.files">
                                {{ __('panel.mailbox.send') }}
                            </x-filament::button>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        {{-- COMPUNERE — card-overlay (jos-dreapta pe desktop, tot ecranul pe mobil) --}}
        @if ($composeOpen)
            <div class="cx-composer" wire:key="composer-{{ $composeKey }}" role="dialog" aria-modal="true" aria-label="{{ __('panel.mailbox.compose') }}">
                <header class="cx-composer__bar">
                    <span>{{ __('panel.mailbox.compose') }}</span>
                    <button type="button" class="cx-composer__close" wire:click="closeCompose" title="{{ __('panel.mailbox.close') }}">
                        <x-filament::icon icon="heroicon-o-x-mark" />
                    </button>
                </header>
                <form wire:submit="sendCompose" class="cx-composer__body">
                    {{ $this->composeForm }}
                    <footer class="cx-composer__foot">
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendCompose,compose.files">
                            {{ __('panel.mailbox.send') }}
                        </x-filament::button>
                    </footer>
                </form>
            </div>
        @endif
    </div>
</x-filament-panels::page>
