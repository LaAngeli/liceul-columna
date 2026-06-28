import { Link, router } from '@inertiajs/react';
import { LogOut, Monitor, Moon, Sun, UserCircle } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const t = useTranslations();
    const { appearance, updateAppearance } = useAppearance();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const themes: { value: Appearance; icon: typeof Sun; label: string }[] = [
        { value: 'light', icon: Sun, label: t('theme.light', 'Luminos') },
        { value: 'dark', icon: Moon, label: t('theme.dark', 'Întunecat') },
        { value: 'system', icon: Monitor, label: t('theme.system', 'Sistem') },
    ];

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href="/cabinet/profil"
                        prefetch
                        onClick={cleanup}
                    >
                        <UserCircle className="mr-2" />
                        {t('profile.head', 'Profil')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            {/* Comutator temă luminos/întunecat/sistem — preferință de afișare, nu modificare de cont. */}
            <div className="px-1 py-1">
                <p className="px-1 pb-1 text-xs text-muted-foreground">{t('profile.theme', 'Temă')}</p>
                <div className="grid grid-cols-3 gap-1">
                    {themes.map(({ value, icon: Icon, label }) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => updateAppearance(value)}
                            aria-pressed={appearance === value}
                            className={cn(
                                'flex flex-col items-center gap-1 rounded-md px-1 py-1.5 text-xs transition-colors',
                                appearance === value
                                    ? 'bg-accent text-accent-foreground'
                                    : 'text-muted-foreground hover:bg-accent/50',
                            )}
                        >
                            <Icon className="size-4" />
                            <span>{label}</span>
                        </button>
                    ))}
                </div>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('auth.logout', 'Deconectare')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
