<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\{ColorPicker, FileUpload, Section, TextInput};
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'General Settings';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.general-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
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
            'logo_height' => $s->logo_height, // accessor returns the default when null
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Branding')
                    ->description('Name, logo and colours used across the app and admin panel.')
                    ->schema([
                        TextInput::make('app_name')->label('Application name')->placeholder(config('app.name')),
                        TextInput::make('company_name')->label('Company name'),

                        FileUpload::make('logo_path')->label('Logo')
                            ->image()->disk('public')->directory('branding')->visibility('public')
                            ->helperText('Leave empty to show the default placeholder logo. Uploading replaces it everywhere.'),
                        TextInput::make('logo_height')->label('Logo height (px)')
                            ->numeric()->minValue(16)->maxValue(200)
                            ->placeholder((string) \App\Models\AppSetting::DEFAULT_LOGO_HEIGHT)
                            ->helperText('How tall the logo appears in the site header. Default '
                                . \App\Models\AppSetting::DEFAULT_LOGO_HEIGHT . 'px.'),
                        FileUpload::make('favicon_path')->label('Favicon')
                            ->image()->disk('public')->directory('branding')->visibility('public'),

                        ColorPicker::make('primary_color')->label('Primary color')->placeholder('#F58220'),
                        ColorPicker::make('accent_color')->label('Accent color')->placeholder('#F58220'),
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
