{{--
    Firul unei conversații din poșta personalului + compunerea răspunsului INLINE, dedesubt.
    Mesajul la care răspunzi rămâne vizibil cât timp scrii — logica unui client de e-mail obișnuit.

    ⚠️ UN SINGUR element rădăcină: Livewire face morphing pe re-render, iar un frate al rădăcinii
    (ex. un bloc <style>) e eliminat tăcut. Stilurile stau în resources/css/filament/admin/theme.css
    (clasele .cx-thread*). `wire:key` pe fiecare mesaj ca morphing-ul să nu amestece bulele.

    Atașamentele se servesc DOAR prin ruta autentificată (disc privat, autorizare pe participanți).
--}}
<x-filament-panels::page>
    <div class="cx-thread">
        {{-- Antetul conversației: cu cine, despre ce elev, ce tip --}}
        <div class="cx-thread__meta">
            <div>
                <span class="cx-thread__with">{{ $this->counterpart()?->name ?? __('panel.common.dash') }}</span>
                @if ($this->thread()->student)
                    <span class="cx-thread__student">
                        {{ __('panel.fields.student') }}: {{ $this->thread()->student->full_name }}
                    </span>
                @endif
            </div>
            <x-filament::badge :color="$this->thread()->type->value === 'audience' ? 'warning' : 'primary'">
                {{ $this->thread()->type->getLabel() }}
            </x-filament::badge>
        </div>

        {{-- Mesajele, cronologic --}}
        <div class="cx-thread__stream">
            @foreach ($this->threadMessages() as $message)
                @php($mine = (int) $message->sender_user_id === auth()->id())

                <div wire:key="msg-{{ $message->id }}" @class([
                    'cx-msg',
                    'cx-msg--mine' => $mine,
                ])>
                    <div class="cx-msg__head">
                        <span class="cx-msg__sender">{{ $mine ? __('panel.mailbox.you') : $message->sender->name }}</span>
                        <time class="cx-msg__time" datetime="{{ $message->created_at?->toIso8601String() }}">
                            {{ $message->created_at?->format('d.m.Y H:i') }}
                        </time>
                    </div>

                    <div class="cx-msg__body">{!! nl2br(e($message->body)) !!}</div>

                    @if ($message->attachments->isNotEmpty())
                        <ul class="cx-msg__files">
                            @foreach ($message->attachments as $attachment)
                                <li>
                                    <a href="{{ route('cabinet.messages.attachment', $attachment) }}"
                                       target="_blank" rel="noopener noreferrer" class="cx-file">
                                        <x-filament::icon
                                            :icon="$attachment->isImage() ? 'heroicon-o-photo' : 'heroicon-o-paper-clip'"
                                            class="cx-file__icon" />
                                        <span class="cx-file__name">{{ $attachment->original_name }}</span>
                                        <span class="cx-file__size">{{ $attachment->humanSize() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Compunerea răspunsului, pe ACEEAȘI pagină: firul de deasupra rămâne lizibil. --}}
        @if (! $this->isTrashed())
            <form wire:submit="sendReply" class="cx-reply">
                <div class="cx-reply__head">
                    <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="cx-reply__icon" />
                    <span>{{ __('panel.mailbox.reply_to', ['name' => $this->counterpart()?->name ?? '']) }}</span>
                </div>

                {{--
                    Cheia versionată: FileUpload poartă `wire:ignore`, deci Livewire nu-i curăță
                    niciodată DOM-ul. Schimbând cheia acestui părinte (care NU e ignorat), subarborele
                    e șters și reinserat după expediere, iar FilePond repornește gol.
                --}}
                <div wire:key="reply-composer-{{ $this->composerKey }}">
                    {{ $this->form }}
                </div>

                <div class="cx-reply__actions">
                    {{-- `wire:target` pe încărcare: nu se poate expedia cât un fișier urcă încă,
                         altfel mesajul ar pleca fără atașamentul pe care utilizatorul îl vede. --}}
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-paper-airplane"
                        wire:loading.attr="disabled"
                        wire:target="sendReply,data.files"
                    >
                        {{ __('panel.mailbox.send') }}
                    </x-filament::button>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>
