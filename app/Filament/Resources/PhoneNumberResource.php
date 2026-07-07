<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use App\Filament\Resources\PhoneNumberResource\Pages\ListPhoneNumbers;
use App\Filament\Resources\PhoneNumberResource\Pages\CreatePhoneNumber;
use App\Filament\Resources\PhoneNumberResource\Pages\EditPhoneNumber;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\PhoneNumberResource\Pages;
use App\Models\PhoneNumber;
use App\Models\{Operator, NumberType, Department, Center, Employee};
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhoneNumberResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = PhoneNumber::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Phone Numbers';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('number')->label('Phone number')->required()->maxLength(255),
                TextInput::make('sim_card')->label('SIM card')->maxLength(255),

                Select::make('operator_id')->label('Operator')
                    ->relationship('operator', 'name')->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->required()]),
                Select::make('number_type_id')->label('Type')
                    ->relationship('numberType', 'name')->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->required()]),

                Select::make('employee_id')->label('Assigned to (person)')
                    ->relationship('employee', 'full_name')->searchable()->preload()
                    ->placeholder('— Free (unassigned) —')
                    ->helperText('Type a name to assign. Pick an existing person, or "Create" a new one inline (name + department + center). Leave empty = free number.')
                    ->createOptionForm([
                        TextInput::make('full_name')->label('Full name')->required()->maxLength(255),
                        Select::make('department_id')->label('Department')
                            ->relationship('department', 'name')->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->required()]),
                        Select::make('center_id')->label('Center')
                            ->relationship('center', 'name')->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->required()]),
                    ]),

                Toggle::make('is_public')->label('Visible to everyone')->default(true)
                    ->helperText('Off = hidden number: not shown to the public, only to logged-in Managers/Finance.'),

                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee.department']))
            ->defaultSort('number')
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('number')->sortable()
                    // Numbers are stored grouped ("+385 95 741 2358"); match the raw
                    // literal AND a space/“+”-stripped form so a digits-only query
                    // ("957412358") still finds the formatted value.
                    ->searchable(query: function ($query, string $search) {
                        $digits = preg_replace('/\D+/', '', $search);
                        $query->where('number', 'like', "%{$search}%");
                        if ($digits !== '') {
                            $query->orWhereRaw("REPLACE(REPLACE(`number`, ' ', ''), '+', '') LIKE ?", ["%{$digits}%"]);
                        }
                    }),
                TextColumn::make('operator.name')->label('Operator')->sortable(),
                TextColumn::make('numberType.name')->label('Type')->badge(),
                TextColumn::make('employee.full_name')->label('Assigned to')
                    ->placeholder('— Free —')->searchable(),
                IconColumn::make('is_public')->label('Public')->boolean()
                    ->tooltip('This number\'s own flag (also hidden if its Type or Department is hidden)'),
                IconColumn::make('effective_public')->label('Shown in imenik')->boolean()
                    ->tooltip('Combined result: number is public AND its Type is public AND its Department is public')
                    ->getStateUsing(fn ($record) => $record->is_public
                        && ($record->numberType?->is_public ?? true)
                        && ($record->employee?->department?->is_public ?? true)),
                TextColumn::make('sim_card')->label('SIM')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('operator_id')->label('Operator')->relationship('operator', 'name'),
                SelectFilter::make('number_type_id')->label('Type')->relationship('numberType', 'name'),
                TernaryFilter::make('assigned')->label('Assignment')
                    ->placeholder('All')->trueLabel('Assigned')->falseLabel('Free (unassigned)')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('employee_id'),
                        false: fn ($q) => $q->whereNull('employee_id'),
                        blank: fn ($q) => $q,
                    ),
                TernaryFilter::make('is_public')->label('Visibility')
                    ->placeholder('All')->trueLabel('Public')->falseLabel('Hidden'),
            ])
            ->headerActions([self::exportAction(), self::importAction()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    protected static function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->action(function () {
                $rows = PhoneNumber::with(['operator', 'numberType', 'employee.department', 'employee.center'])
                    ->orderBy('number')->get();

                return response()->streamDownload(function () use ($rows) {
                    $out = fopen('php://output', 'w');
                    // UTF-8 BOM so Excel renders Croatian characters (č ć ž š đ) correctly.
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['number', 'type', 'operator', 'sim', 'employee', 'department', 'center', 'public', 'notes']);
                    foreach ($rows as $n) {
                        fputcsv($out, [
                            $n->number, $n->numberType?->name, $n->operator?->name, $n->sim_card,
                            $n->employee?->full_name, $n->employee?->department?->name, $n->employee?->center?->name,
                            $n->is_public ? 'yes' : 'no', $n->notes,
                        ]);
                    }
                    fclose($out);
                }, 'phone-numbers-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
            });
    }

    protected static function importAction(): Action
    {
        return Action::make('import')
            ->label('Import CSV')->icon('heroicon-o-arrow-up-tray')->color('gray')
            ->modalDescription('First row = header. Columns: number, type, operator, sim, employee, department, center, public, notes. Missing operators/types/employees/departments/centers are created automatically.')
            ->schema([
                FileUpload::make('file')->label('CSV file')->required()
                    ->disk('local')->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel']),
            ])
            ->action(function (array $data) {
                $count = self::importCsv(Storage::disk('local')->path($data['file']));
                Storage::disk('local')->delete($data['file']);
                Notification::make()->title("Imported {$count} phone number(s)")->success()->send();
            });
    }

    protected static function importCsv(string $path): int
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            return 0;
        }
        $header = fgetcsv($fh);
        if (! $header) {
            fclose($fh);
            return 0;
        }
        $idx = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $header));
        $get = fn ($row, $key) => isset($idx[$key]) ? trim((string) ($row[$idx[$key]] ?? '')) : '';

        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $number = $get($row, 'number');
            if ($number === '') {
                continue;
            }
            $operatorId = ($v = $get($row, 'operator')) !== '' ? Operator::firstOrCreate(['name' => $v])->id : null;
            $typeId = ($v = $get($row, 'type')) !== '' ? NumberType::firstOrCreate(['name' => $v])->id : null;

            $employeeId = null;
            if (($emp = $get($row, 'employee')) !== '') {
                $deptId = ($v = $get($row, 'department')) !== '' ? Department::firstOrCreate(['name' => $v])->id : null;
                $cenId = ($v = $get($row, 'center')) !== '' ? Center::firstOrCreate(['name' => $v])->id : null;
                $employee = Employee::firstOrCreate(['full_name' => $emp], ['department_id' => $deptId, 'center_id' => $cenId]);
                if ($deptId && ! $employee->department_id) {
                    $employee->department_id = $deptId;
                }
                if ($cenId && ! $employee->center_id) {
                    $employee->center_id = $cenId;
                }
                $employee->save();
                $employeeId = $employee->id;
            }

            $public = strtolower($get($row, 'public'));
            PhoneNumber::create([
                'number' => $number,
                'sim_card' => $get($row, 'sim') ?: null,
                'notes' => $get($row, 'notes') ?: null,
                'operator_id' => $operatorId,
                'number_type_id' => $typeId,
                'employee_id' => $employeeId,
                'is_public' => ! in_array($public, ['no', '0', 'false', 'ne'], true),
            ]);
            $count++;
        }
        fclose($fh);

        return $count;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPhoneNumbers::route('/'),
            'create' => CreatePhoneNumber::route('/create'),
            'edit' => EditPhoneNumber::route('/{record}/edit'),
        ];
    }
}
