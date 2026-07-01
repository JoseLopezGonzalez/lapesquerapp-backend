<?php

namespace Tests\Unit\Support;

use App\Exports\v2\A3ERPOrderSalesDeliveryNoteExport;
use App\Exports\v2\FacilcomOrderSalesDeliveryNoteExport;
use App\Models\AuxiliaryProduct;
use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use App\Models\Tax;
use App\Support\OrderErpExportLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderErpExportLinesTest extends TestCase
{
    use BuildsOperationsScenario;
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private Order $order;

    private AuxiliaryProduct $auxiliaryProduct;

    private Tax $tax;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        DB::connection('tenant')->table('order_auxiliary_lines')->delete();
        DB::connection('tenant')->table('auxiliary_products')->delete();

        $context = $this->createSalesContext('ErpExport');
        $customer = $this->createCustomerForTest($context, 'ErpExport');
        $customer->update(['a3erp_code' => 'CLI001', 'facilcom_code' => 'FC001']);
        $this->order = $this->createOrderForTest($customer, $context);

        $this->auxiliaryProduct = AuxiliaryProduct::factory()->create([
            'name' => 'Nieve granulada '.uniqid(),
            'reference' => 'NIE-01',
            'unit' => 'kg',
        ]);
        $this->tax = Tax::factory()->create(['name' => 'IVA 10% '.uniqid(), 'rate' => 10]);

        OrderAuxiliaryLine::factory()->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => $this->auxiliaryProduct->id,
            'description' => null,
            'quantity' => 500,
            'unit' => 'kg',
            'unit_price' => 0.08,
            'tax_id' => $this->tax->id,
        ]);
    }

    public function test_a3_erp_export_includes_auxiliary_line(): void
    {
        $rows = (new A3ERPOrderSalesDeliveryNoteExport($this->order))->collection();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('NIE-01', $row['LINCODART']);
        $this->assertSame($this->auxiliaryProduct->name, $row['LINDESCLIN']);
        $this->assertEquals(500, $row['LINUNIDADES']);
        $this->assertEquals(0.08, $row['LINPRCMONEDA']);
        $this->assertSame($this->tax->name, $row['LINTIPIVA']);
        $this->assertSame(0, $row['LINBULTOS']);
    }

    public function test_facilcom_export_includes_auxiliary_line_before_pedido_summary(): void
    {
        $rows = (new FacilcomOrderSalesDeliveryNoteExport($this->order))->array();

        $this->assertCount(2, $rows);
        $this->assertSame('NIE-01', $rows[0][4]);
        $this->assertSame($this->auxiliaryProduct->name, $rows[0][5]);
        $this->assertEquals(500, $rows[0][6]);
        $this->assertStringContainsString('PEDIDO #', $rows[1][5]);
    }

    public function test_ad_hoc_auxiliary_line_uses_placeholder_for_article_code(): void
    {
        OrderAuxiliaryLine::query()->delete();

        OrderAuxiliaryLine::factory()->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => null,
            'description' => 'Servicio puntual',
            'quantity' => 1,
            'unit' => 'servicio',
            'unit_price' => 120,
            'tax_id' => null,
        ]);

        $serie = 'P'.date('y', strtotime($this->order->load_date));
        $rows = OrderErpExportLines::a3ErpRowsForOrder($this->order, $serie, useFacilcomClientCode: true, useMissingPlaceholder: true);

        $this->assertCount(1, $rows);
        $this->assertSame('-', $rows[0]['LINCODART']);
        $this->assertSame('Servicio puntual', $rows[0]['LINDESCLIN']);
    }
}
