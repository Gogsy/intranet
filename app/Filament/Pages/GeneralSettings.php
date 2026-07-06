<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Composer\InstalledVersions;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\{ColorPicker, FileUpload, TextInput};
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'General Settings';
    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    public function mount(): void
    {
        $s = AppSetting::current();
        $this->form->fill([
            'app_name' => $s->app_name,
            'company_name' => $s->company_name,
            'logo_path' => $s->logo_path,
            'favicon_path' => $s->favicon_path,
            'primary_color' => $s->primary_color ?: '#F58220',
            'accent_color' => $s->accent_color ?: '#F58220',
            // accessors return the defaults when null
            'logo_height' => $s->logo_height,
            'admin_logo_height' => $s->admin_logo_height,
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
                Section::make('Identity')
                    ->description('How the application and your company are named across the portal.')
                    ->aside()
                    ->schema([
                        TextInput::make('app_name')->label('Application name')
                            ->placeholder(config('app.name'))
                            ->helperText('Shown in the browser tab, e-mails and page headers.'),
                        TextInput::make('company_name')->label('Company name'),
                    ]),

                Section::make('Logo & favicon')
                    ->description('Artwork used in the site header, the admin panel and the browser tab. Leave the logo empty to keep the default placeholder.')
                    ->aside()
                    ->schema([
                        FileUpload::make('logo_path')->label('Logo')
                            ->image()->disk('public')->directory('branding')->visibility('public')
                            ->helperText('Uploading replaces the logo everywhere.'),
                        FileUpload::make('favicon_path')->label('Favicon')
                            ->image()->disk('public')->directory('branding')->visibility('public')
                            ->helperText('Small square icon shown in the browser tab.'),
                        TextInput::make('logo_height')->label('Site logo height (px)')
                            ->numeric()->minValue(16)->maxValue(200)
                            ->placeholder((string) AppSetting::DEFAULT_LOGO_HEIGHT)
                            ->helperText('Height in the public site header. Default '
                                . AppSetting::DEFAULT_LOGO_HEIGHT . 'px.'),
                        TextInput::make('admin_logo_height')->label('Admin panel logo height (px)')
                            ->numeric()->minValue(16)->maxValue(200)
                            ->placeholder((string) AppSetting::DEFAULT_ADMIN_LOGO_HEIGHT)
                            ->helperText('Height in the admin panel sidebar/topbar. Default '
                                . AppSetting::DEFAULT_ADMIN_LOGO_HEIGHT . 'px.'),
                    ])->columns(2),

                Section::make('Colours')
                    ->description('Brand colours applied to buttons, links and highlights in both the site and the admin panel.')
                    ->aside()
                    ->schema([
                        ColorPicker::make('primary_color')->label('Primary colour')->placeholder('#F58220'),
                        ColorPicker::make('accent_color')->label('Accent colour')->placeholder('#F58220'),
                    ])->columns(2),

                Section::make('System information')
                    ->description('Versions of the application and the platform it runs on. Read-only.')
                    ->aside()
                    ->schema([
                        TextEntry::make('app_version')->label('Application')
                            ->state('v' . config('app.version'))
                            ->badge()->color('primary'),
                        TextEntry::make('environment')->label('Environment')
                            ->state(app()->environment())
                            ->badge()
                            ->color(fn (string $state): string => $state === 'production' ? 'success' : 'warning'),
                        TextEntry::make('laravel_version')->label('Laravel')
                            ->state(app()->version()),
                        TextEntry::make('filament_version')->label('Filament')
                            ->state(InstalledVersions::getPrettyVersion('filament/filament')),
                        TextEntry::make('php_version')->label('PHP')
                            ->state(PHP_VERSION),
                        TextEntry::make('database')->label('Database')
                            ->state(fn (): string => \Illuminate\Support\Facades\DB::connection()->getDriverName()),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $s = AppSetting::current();
        $s->fill($data)->save();
        AppSetting::forgetCurrent();

        Notification::make()->title('Settings saved')->success()->send();
    }
}
