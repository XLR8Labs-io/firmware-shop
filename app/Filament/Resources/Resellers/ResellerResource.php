<?php

namespace App\Filament\Resources\Resellers;

use App\Filament\Resources\Resellers\Pages\ManageResellers;
use App\Models\Reseller;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The World agents page. An active agent linked to an account can send wallet balance.
 */
class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'World agents';

    protected static ?string $modelLabel = 'agent';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('country')
                    ->required()
                    ->maxLength(100),
                TextInput::make('whatsapp')
                    ->label('WhatsApp number')
                    ->helperText('With the country code, such as +8801711000000.')
                    ->maxLength(30),
                TextInput::make('telegram')
                    ->label('Telegram username')
                    ->maxLength(100),
                Textarea::make('payment_methods')
                    ->label('Payment methods')
                    ->helperText('What customers can pay this agent with, such as bKash, Nagad, bank transfer.')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                Select::make('user_id')
                    ->label('Account that sends balance')
                    ->helperText('Optional. This account can send its own wallet balance to customers while the agent is active.')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive agents are hidden from the World agents page and cannot send balance.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('country')->searchable(),
                TextColumn::make('whatsapp')->label('WhatsApp'),
                TextColumn::make('user.email')->label('Sends balance from')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResellers::route('/'),
        ];
    }
}
