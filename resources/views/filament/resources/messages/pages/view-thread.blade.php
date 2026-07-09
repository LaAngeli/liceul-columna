{{--
    Firul unei conversații din poșta personalului.

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
    </div>
</x-filament-panels::page>
