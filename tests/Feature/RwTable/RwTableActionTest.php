<?php

namespace Tests\Feature\RwTable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rwsoft\RwTableLaravel\Actions\RwTableAction;
use Tests\Support\Models\RwTableDummyItem;
use Tests\TestCase;

class RwTableActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('rwtable_dummy_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('description');
        });

        Schema::create('rwtable_dummy_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->integer('index')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->date('created_on')->nullable();
            $table->boolean('flagged')->default(false);
        });

        $groupAlpha = DB::table('rwtable_dummy_groups')->insertGetId([
            'description' => 'Alpha Group',
        ]);

        $groupBeta = DB::table('rwtable_dummy_groups')->insertGetId([
            'description' => 'Beta Group',
        ]);

        RwTableDummyItem::query()->insert([
            [
                'id' => 1,
                'name' => 'Anna',
                'active' => true,
                'index' => 1000,
                'group_id' => $groupAlpha,
                'created_on' => '2026-01-01',
                'flagged' => false,
            ],
            [
                'id' => 2,
                'name' => 'Bert',
                'active' => true,
                'index' => 2000,
                'group_id' => $groupBeta,
                'created_on' => '2026-02-10',
                'flagged' => true,
            ],
            [
                'id' => 3,
                'name' => 'Carl',
                'active' => false,
                'index' => 3000,
                'group_id' => $groupBeta,
                'created_on' => '2026-02-12',
                'flagged' => false,
            ],
        ]);

        config()->set('rwtable.field_aliases', [
            'group_description' => 'rwtable_dummy_groups.description',
        ]);
    }

    public function test_handle_applies_alias_global_search_filters_and_selection_exclude(): void
    {
        $request = Request::create('/fake', 'GET', [
            'columns' => [
                ['key' => 'name', 'selected' => true],
                ['key' => 'group_description', 'selected' => true],
            ],
            'global' => 'Beta',
            'filters' => [
                'active' => 1,
            ],
            'filterModes' => [
                'active' => '=',
            ],
            'filterTypes' => [
                'active' => 'number',
            ],
            'selectionFilter' => 'exclude',
            'selectedRowIds' => [2],
            'rowsPerPage' => 10,
            'sortField' => 'name',
            'sortOrder' => 'asc',
        ]);
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = RwTableAction::handle(
            $request,
            RwTableDummyItem::class,
            'Admin/FakeTable',
            ['id', 'name'],
            25,
            [],
            function ($query): void {
                $query
                    ->leftJoin('rwtable_dummy_groups', 'rwtable_dummy_groups.id', '=', 'rwtable_dummy_items.group_id')
                    ->select('rwtable_dummy_items.*');
            }
        );

        $httpResponse = $response->toResponse($request);
        $this->assertSame(200, $httpResponse->getStatusCode());

        $payload = json_decode((string) $httpResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Admin/FakeTable', $payload['component']);
        $this->assertCount(0, $payload['props']['rw_table_dummy_items']['data']);
    }

    public function test_handle_sanitizes_malicious_sort_field(): void
    {
        $request = Request::create('/fake', 'GET', [
            'rowsPerPage' => 10,
            'sortField' => 'name;drop table users',
            'sortOrder' => 'desc',
        ]);
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = RwTableAction::handle($request, RwTableDummyItem::class, 'Admin/FakeTable');
        $httpResponse = $response->toResponse($request);
        $this->assertSame(200, $httpResponse->getStatusCode());

        $payload = json_decode((string) $httpResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $payload['props']['rw_table_dummy_items']['data']);
        $this->assertSame([3, 2, 1], $ids);
    }

    public function test_update_supports_client_boolean_validation_and_persists_value(): void
    {
        $request = Request::create('/fake', 'PATCH', [
            'field' => 'flagged',
            'value' => 'true',
            'validationType' => 'client',
            'validationRules' => 'required|boolean',
        ]);

        $response = RwTableAction::update($request, RwTableDummyItem::class, 1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('rwtable_dummy_items', [
            'id' => 1,
            'flagged' => 1,
        ]);
    }

    public function test_create_with_manual_ordering_insert_above_places_index_between_records(): void
    {
        $request = Request::create('/fake', 'POST', [
            'name' => 'Dina',
            'active' => true,
            'group_id' => 1,
            'created_on' => '2026-03-01',
            'flagged' => false,
            'validationType' => 'model',
            'manualOrdering' => true,
            'manualOrderField' => 'index',
            'insertAboveId' => 2,
        ]);

        $response = RwTableAction::create($request, RwTableDummyItem::class);
        $this->assertSame(200, $response->getStatusCode());

        $newId = (int) $response->getData()->id;
        $newRow = RwTableDummyItem::query()->findOrFail($newId);

        $this->assertGreaterThan(1000, (int) $newRow->index);
        $this->assertLessThan(2000, (int) $newRow->index);
    }

    public function test_destroy_and_reindex_ordering_work_as_expected(): void
    {
        $deleteRequest = Request::create('/fake', 'DELETE');
        $deleteResponse = RwTableAction::destroy($deleteRequest, RwTableDummyItem::class, 2);
        $this->assertSame(200, $deleteResponse->getStatusCode());

        $this->assertDatabaseMissing('rwtable_dummy_items', ['id' => 2]);

        $rowThree = RwTableDummyItem::query()->findOrFail(3);
        $rowThree->index = 1001;
        $rowThree->save();

        $reindexResponse = RwTableAction::reindexOrdering(RwTableDummyItem::class, 'index');
        $this->assertSame(200, $reindexResponse->getStatusCode());

        $ordered = RwTableDummyItem::query()->orderBy('index')->pluck('index')->all();
        $this->assertSame([1000, 2000], array_values($ordered));
    }
}
