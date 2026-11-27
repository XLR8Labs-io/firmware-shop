<?php

namespace App\Filament\Pages;

use App\Services\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageSiteSettings extends Page
{
    protected string $view = 'filament.pages.manage-site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(SiteSettings $settings): void
    {
        $this->form->fill([
            ...$settings->all(),
            // Until a name is saved the site shows the app name, so start from that.
            'site_name' => $settings->siteName(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Site')
                        ->schema([
                            TextInput::make('site_name')
                                ->label('Site name')
                                ->required()
                                ->maxLength(100),
                            Textarea::make('ticker_text')
                                ->label('Notice ticker')
                                ->helperText('Shown in the scrolling bar at the top of every page.')
                                ->maxLength(500)
                                ->rows(3),
                            Textarea::make('support_contact_text')
                                ->label('Support contact')
                                ->helperText('Shown in the footer. Tell customers how to reach support.')
                                ->maxLength(500)
                                ->rows(3),
                        ]),
                    Section::make('Community links')
                        ->description('Leave a field empty to hide that link.')
                        ->schema([
                            $this->linkInput('telegram_channel_url', 'Telegram channel'),
                            $this->linkInput('telegram_group_url', 'Telegram group'),
                            $this->linkInput('facebook_url', 'Facebook'),
                            $this->linkInput('youtube_url', 'YouTube'),
                            TextInput::make('whatsapp_number')
                                ->label('WhatsApp number')
                                ->helperText('With the country code, e.g. 8801712345678.')
                                ->tel()
                                ->regex('/^\+?[0-9]{8,15}$/')
                                ->maxLength(16),
                        ])
                        ->columns(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SiteSettings $settings): void
    {
        /** @var array<string, string|null> $values */
        $values = $this->form->getState();

        $settings->save($values);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    /**
     * Links are rendered as hrefs on the public site, so only web addresses are accepted.
     */
    private function linkInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->url()
            ->rule('url:http,https')
            ->maxLength(255);
    }
}
