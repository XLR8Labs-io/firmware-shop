<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\Wallet\WalletService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('balance')
                            ->label('Wallet balance')
                            ->state(fn (User $record): string => '$'.app(WalletService::class)->balance($record)),
                        TextEntry::make('email_verified_at')
                            ->label('Email confirmed')
                            ->dateTime()
                            ->placeholder('Not confirmed'),
                        IconEntry::make('is_admin')
                            ->label('Admin')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }
}
