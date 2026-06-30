<?php

namespace App\Console\Commands;

use App\Enums\NotificationChannel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Curăță setările de notificări ale utilizatorilor de canalele DEZAFECTATE (ex. messenger/whatsapp,
 * scoase din {@see NotificationChannel}) rămase în JSON. Păstrează doar canalele valide curente.
 * Idempotent. Rulează: php artisan app:prune-notification-channels.
 */
class PruneNotificationChannels extends Command
{
    protected $signature = 'app:prune-notification-channels';

    protected $description = 'Elimină canalele dezafectate (ex. messenger/whatsapp) din setările de notificări';

    public function handle(): int
    {
        $valid = array_map(static fn (NotificationChannel $c): string => $c->value, NotificationChannel::cases());
        $cleaned = 0;

        User::query()
            ->where(fn ($query) => $query->whereNotNull('notification_contacts')->orWhereNotNull('notification_preferences'))
            ->chunkById(200, function (Collection $users) use ($valid, &$cleaned): void {
                foreach ($users as $user) {
                    if ($this->prune($user, $valid)) {
                        $cleaned++;
                    }
                }
            });

        $this->info("Conturi curățate: {$cleaned}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $valid
     */
    private function prune(User $user, array $valid): bool
    {
        $dirty = false;

        $contacts = $user->notification_contacts ?? [];
        foreach (array_keys($contacts) as $key) {
            if (! in_array($key, $valid, true)) {
                unset($contacts[$key]);
                $dirty = true;
            }
        }

        $preferences = $user->notification_preferences ?? [];
        foreach ($preferences as $type => $channels) {
            $filtered = array_values(array_filter($channels, static fn (string $channel): bool => in_array($channel, $valid, true)));

            if ($filtered !== $channels) {
                $preferences[$type] = $filtered;
                $dirty = true;
            }
        }

        if ($dirty) {
            $user->update([
                'notification_contacts' => $contacts === [] ? null : $contacts,
                'notification_preferences' => $preferences === [] ? null : $preferences,
            ]);
        }

        return $dirty;
    }
}
