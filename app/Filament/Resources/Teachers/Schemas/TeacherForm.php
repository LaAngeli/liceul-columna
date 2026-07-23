<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Enums\Sex;
use App\Filament\Schemas\FicheAccountSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fișa cadrului didactic: datele PERSOANEI + starea contului ei de acces.
 *
 * ⚠️ Contul NU se mai alege dintr-un select de conturi existente (cerința beneficiarului
 * 2026-07-24): pentru o fișă fără cont — cazul a 18 profesori importați — lista aceea era goală
 * sau, mai rău, nefiltrată (se putea lega un cont de părinte ori unul deja folosit de altă fișă).
 * Acum contul se CREEAZĂ din fișă, cu identitatea preluată din registru; legarea unui cont orfan
 * rămâne ca supapă, dar numai când există într-adevăr unul potrivit ({@see FicheAccountSection}).
 */
class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('panel.forms.fiche_account.section_person'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('last_name')
                            ->label(__('panel.fields.last_name'))
                            ->maxLength(50),
                        TextInput::make('first_name')
                            ->label(__('panel.fields.first_name'))
                            ->required()
                            ->maxLength(50),
                        Select::make('sex')
                            ->label(__('panel.fields.sex'))
                            ->options(Sex::class)
                            ->native(false),
                        TextInput::make('email')
                            ->label(__('panel.fields.email'))
                            ->email()
                            ->maxLength(255),
                    ]),

                FicheAccountSection::make(),
            ]);
    }
}
