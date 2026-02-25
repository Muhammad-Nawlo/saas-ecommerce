<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Constants\TenantPermissions;
use App\Enums\TenantRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Tenant panel: manage roles. Only owner (or users with manage roles permission) can access.
 * Prevents privilege escalation: cannot assign role with level higher than own.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return 'Roles';
    }

    public static function canAccess(): bool
    {
        return tenant('id') !== null && auth()->user()?->can(TenantPermissions::MANAGE_ROLES) === true;
    }

    public static function form(Form $form): Form
    {
        $guard = 'web';

        return $form
            ->schema([
                Forms\Components\Section::make('Role')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('guard_name', $guard)),
                        Forms\Components\Hidden::make('guard_name')->default($guard),
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $q) => $q->where('guard_name', $guard)->orderBy('name')
                            )
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guard_name')->toggleable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $q) => $q->where('guard_name', 'web'));
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\RoleResource\Pages\ListRoles::route('/'),
            'create' => \App\Filament\Tenant\Resources\RoleResource\Pages\CreateRole::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\RoleResource\Pages\EditRole::route('/{record}/edit'),
        ];
    }

    /** Role names the current user is allowed to assign (prevents privilege escalation). */
    public static function assignableRoleNames(): array
    {
        $user = auth()->user();
        if ($user === null) {
            return [];
        }
        $maxLevel = 0;
        foreach ($user->getRoleNames() as $name) {
            $enum = TenantRole::fromName($name);
            if ($enum !== null && $enum->level() > $maxLevel) {
                $maxLevel = $enum->level();
            }
        }
        return collect(TenantRole::cases())
            ->filter(fn (TenantRole $r) => $r->level() <= $maxLevel)
            ->map(fn (TenantRole $r) => $r->value)
            ->values()
            ->all();
    }

    /** Permission IDs the current user can assign (optional: restrict to subset). Null = all. */
    private static function assignablePermissionIds(): ?array
    {
        return null;
    }

    public static function getModelLabel(): string
    {
        return 'Role';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Roles';
    }
}
