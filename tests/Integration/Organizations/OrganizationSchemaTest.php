<?php

namespace Tests\Integration\Organizations;

use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_optional_fields_and_both_statuses(): void
    {
        $first = OrganizationRecord::factory()->create(['document' => '52998224725']);
        $second = OrganizationRecord::factory()->inactive()->create(['document' => '52998224725', 'name' => $first->name]);
        $this->assertNotSame($first->public_id, $second->public_id);
        $this->assertDatabaseCount('organizations', 2);
        $this->assertNull($first->legal_name);
        $this->assertSame('inactive', $second->status->value);
    }

    public function test_public_uuid_is_unique_in_postgresql(): void
    {
        $record = OrganizationRecord::factory()->create();
        $this->expectException(QueryException::class);
        OrganizationRecord::factory()->create(['public_id' => $record->public_id]);
    }

    public static function invalidValues(): array
    {
        return [['name', ''], ['name', ' padded '], ['name', "bad\nname"], ['name', str_repeat('x', 256)], ['legal_name', ''], ['status', 'deleted'], ['document', '529.982.247-25'], ['document', '12abc34501DE35'], ['version', 0], ['updated_at', '2000-01-01 00:00:00+00'], ['created_at', null]];
    }

    #[DataProvider('invalidValues')]
    public function test_postgresql_rejects_invalid_structural_data(string $field, mixed $value): void
    {
        $attributes = OrganizationRecord::factory()->raw();
        $attributes[$field] = $value;
        $this->expectException(QueryException::class);
        DB::table('organizations')->insert($attributes);
    }

    public function test_migration_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_11_04_000001_create_organizations_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('organizations'));
        $migration->up();
        OrganizationRecord::factory()->create();
        $this->assertDatabaseCount('organizations', 1);
    }
}
