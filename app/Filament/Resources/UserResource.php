<?php

namespace App\Filament\Resources;

use Spatie\Permission\Models\Role;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Password;
use App\Notifications\UserInvitation;
use Filament\Notifications\Notification;
use Throwable;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $navigationLabel = 'Users';

    /** Roles that only a super_admin may grant or revoke. */
    public const PROTECTED_ROLES = ['super_admin', 'security_overview'];

    /** May the current user assign roles at all? (Protected roles stay super_admin-only.) */
    public static function canManageRoles(): bool
    {
        return auth()->user()?->can('assign_roles') ?? false;
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

        $protectedIds = Role::whereIn('name', self::PROTECTED_ROLES)
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),

            TextInput::make('password')
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

            // Only a Super Admin may assign roles (incl. granting super_admin to
            // another user). The submission is re-checked server-side in the
            // Create/Edit pages (sanitizeRoles) so a forged POST cannot elevate.
            CheckboxList::make('roles')
                ->label('Roles — what this user can do')
                ->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->label ?: $record->name)
                ->options(function () {
                    $query = Role::query();
                    // Non-super-admins cannot see or assign the protected roles.
                    if (! static::canManageProtectedRoles()) {
                        $query->whereNotIn('name', self::PROTECTED_ROLES);
                    }
                    return $query->get()
                        ->mapWithKeys(fn ($r) => [$r->id => ($r->label ?: $r->name)])
                        ->toArray();
                })
                ->descriptions(fn () => Role::pluck('description', 'id')->filter()->toArray())
                ->bulkToggleable()
                ->columns(1)
                ->visible(fn () => static::canManageRoles())
                ->dehydrated(fn () => static::canManageRoles())
                ->helperText(fn () => static::canManageProtectedRoles()
                    ? 'You can assign any role.'
                    : 'You can assign any role except Super Admin.'),

            Placeholder::make('roles_readonly')
                ->label('Roles')
                ->content(fn ($record) => $record
                    ? ($record->roles->map(fn ($r) => $r->label ?: $r->name)->implode(', ') ?: '—')
                    : '—')
                ->visible(fn () => ! static::canManageRoles()),

            // Only a super admin may require MFA for a user. On their next
            // request a flagged user with no MFA method is bounced to the
            // setup page (EnsureMfaForFlaggedUsers). Gated server-side too:
            // dehydrated only for super admins so a forged POST can't set it.
            Toggle::make('mfa_required')
                ->label('Zahtijevaj MFA (dvofaktorska prijava)')
                ->helperText('Korisnik će pri sljedećoj prijavi morati postaviti autentifikator aplikaciju ili e-mail kôd prije pristupa panelu.')
                ->inline(false)
                ->visible(fn () => static::canManageProtectedRoles())
                ->dehydrated(fn () => static::canManageProtectedRoles()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => once(fn () => Role::pluck('label', 'name'))[$state] ?? \Illuminate\Support\Str::headline($state))
                    ->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->label ?: $record->name)
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('invite')
                    ->label('Send invite')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Emails this user a fresh "set your password" link.')
                    ->action(function (User $record) {
                        try {
                            $token = Password::broker()->createToken($record);
                            $record->notify(new UserInvitation($token));
                            Notification::make()
                                ->title('Invitation sent to ' . $record->email)->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to send invite')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

}
