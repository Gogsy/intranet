<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $navigationLabel = 'Users';

    /** Roles that only a super_admin may grant or revoke. */
    public const PROTECTED_ROLES = ['super_admin', 'admin'];

    /** May the current user assign roles at all? */
    public static function canManageRoles(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    /** May the current user grant/revoke the protected (super_admin/admin) roles? */
    public static function canManageProtectedRoles(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * Server-side guard: given the submitted roles array (ids) and the record
     * being edited, strip out any role the current user is not allowed to set,
     * and preserve protected roles the record already has so a non-super-admin
     * cannot accidentally revoke them. Returns the sanitised list of role ids.
     */
    public static function sanitizeRoles(array $submittedRoleIds, ?User $record = null): array
    {
        // Super admins may set anything.
        if (static::canManageProtectedRoles()) {
            return array_values(array_unique(array_map('intval', $submittedRoleIds)));
        }

        $protectedIds = \Spatie\Permission\Models\Role::whereIn('name', self::PROTECTED_ROLES)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Drop any protected role the submitter tried to add (prevents self-elevation
        // and granting admin/super_admin to anyone via a forged POST).
        $clean = array_values(array_filter(
            array_map('intval', $submittedRoleIds),
            fn ($id) => ! in_array($id, $protectedIds, true)
        ));

        // Preserve protected roles the record ALREADY had — a non-super-admin
        // must not be able to strip another user's admin/super_admin either.
        if ($record) {
            $existingProtected = $record->roles()
                ->whereIn('name', self::PROTECTED_ROLES)->pluck('roles.id')
                ->map(fn ($id) => (int) $id)->all();
            $clean = array_values(array_unique(array_merge($clean, $existingProtected)));
        }

        return $clean;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->maxLength(255)
                // On create the user sets their own password via the emailed invite,
                // so this field only appears when editing (to override a password).
                ->hidden(fn (string $context): bool => $context === 'create')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Leave blank to keep the current password'),

            // Super Admins and Admins may assign roles. Admins, however, can only
            // assign NON-privileged roles: the super_admin/admin options are
            // filtered out for them (UI), and the submission is re-checked
            // server-side in Create/Edit pages so a forged POST cannot elevate.
            Forms\Components\CheckboxList::make('roles')
                ->label('Roles — what this user can do')
                ->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->label ?: $record->name)
                ->options(function () {
                    $query = \Spatie\Permission\Models\Role::query();
                    // Non-super-admins cannot see or assign the protected roles.
                    if (! static::canManageProtectedRoles()) {
                        $query->whereNotIn('name', self::PROTECTED_ROLES);
                    }
                    return $query->get()
                        ->mapWithKeys(fn ($r) => [$r->id => ($r->label ?: $r->name)])
                        ->toArray();
                })
                ->descriptions(fn () => \Spatie\Permission\Models\Role::pluck('description', 'id')->filter()->toArray())
                ->bulkToggleable()
                ->columns(1)
                ->visible(fn () => static::canManageRoles())
                ->dehydrated(fn () => static::canManageRoles())
                ->helperText(fn () => static::canManageProtectedRoles()
                    ? 'You can assign any role.'
                    : 'You can assign roles except Super Admin and Admin.'),

            Forms\Components\Placeholder::make('roles_readonly')
                ->label('Roles')
                ->content(fn ($record) => $record
                    ? ($record->roles->map(fn ($r) => $r->label ?: $r->name)->implode(', ') ?: '—')
                    : '—')
                ->visible(fn () => ! static::canManageRoles()),

            Forms\Components\Toggle::make('is_admin')
                ->label('Legacy administrator flag')
                ->helperText('Deprecated — use roles instead. Kept only for migration.')
                ->default(false)
                ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                ->dehydrated(fn () => auth()->user()?->hasRole('super_admin')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('invite')
                    ->label('Send invite')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Emails this user a fresh "set your password" link.')
                    ->action(function (User $record) {
                        try {
                            $token = \Illuminate\Support\Facades\Password::broker()->createToken($record);
                            $record->notify(new \App\Notifications\UserInvitation($token));
                            \Filament\Notifications\Notification::make()
                                ->title('Invitation sent to ' . $record->email)->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Failed to send invite')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

}
