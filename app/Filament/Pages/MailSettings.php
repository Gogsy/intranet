<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;
use App\Models\MailSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\{Select, TextInput};
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Mail / SMTP';
    protected static ?int $navigationSort = 30;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public function mount(): void
    {
        $s = MailSetting::current();
        $this->form->fill([
            'host' => $s->host,
            'port' => $s->port ?: 587,
            'encryption' => $s->encryption,
            'username' => $s->username,
            'from_address' => $s->from_address,
            'from_name' => $s->from_name,
            // password intentionally not pre-filled
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('SMTP server')
                    ->description('Works with Gmail/Google Workspace, Microsoft 365/Outlook, or any SMTP server.')
                    ->schema([
                        Select::make('provider_preset')
                            ->label('Provider preset')
                            ->options([
                                'gmail' => 'Gmail / Google Workspace',
                                'microsoft' => 'Microsoft 365 / Outlook',
                                'custom' => 'Custom',
                            ])
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state === 'gmail') {
                                    $set('host', 'smtp.gmail.com');
                                    $set('port', 587);
                                    $set('encryption', 'tls');
                                } elseif ($state === 'microsoft') {
                                    $set('host', 'smtp.office365.com');
                                    $set('port', 587);
                                    $set('encryption', 'tls');
                                }
                            })
                            ->helperText('Gmail/Workspace needs an App Password (2FA on). Microsoft 365 = smtp.office365.com:587 TLS.')
                            ->columnSpanFull(),

                        TextInput::make('host')->label('SMTP host')->required()->placeholder('smtp.gmail.com'),
                        TextInput::make('port')->label('Port')->numeric()->required()->default(587),
                        Select::make('encryption')->label('Encryption')
                            ->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'])
                            ->default('tls'),
                        TextInput::make('username')->label('Username')->placeholder('you@company.com'),
                        TextInput::make('password')->label('Password / App password')
                            ->password()->revealable()
                            ->placeholder('•••••••• (leave blank to keep current)'),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('From')->schema([
                    TextInput::make('from_address')->label('From address')->email()->placeholder('noreply@company.com'),
                    TextInput::make('from_name')->label('From name')->placeholder('Intranet Portal'),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if ($data['encryption'] === 'none') {
            $data['encryption'] = null;
        }
        if (blank($data['password'] ?? null)) {
            unset($data['password']); // keep existing
        }
        unset($data['provider_preset']);

        $s = MailSetting::current();
        $s->mailer = 'smtp';
        $s->fill($data)->save();
        $s->apply();

        Notification::make()->title('Mail settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon('heroicon-o-paper-airplane')
                ->schema([
                    TextInput::make('to')->label('Send test to')->email()->required()
                        ->default(fn () => auth()->user()->email),
                ])
                ->action(function (array $data) {
                    MailSetting::current()->apply();
                    try {
                        Mail::raw(
                            'Test email from ' . config('app.name') . '. If you can read this, your SMTP settings work. ✅',
                            fn ($m) => $m->to($data['to'])->subject('SMTP test — ' . config('app.name'))
                        );
                        Notification::make()->title('Test email sent to ' . $data['to'])->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Test email failed')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
