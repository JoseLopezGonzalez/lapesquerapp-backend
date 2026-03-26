<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PunchEvent;
use App\Models\RawMaterialReception;
use App\Models\Salesperson;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Siembra "datos sucios controlados" para QA manual y reproducción de anomalías.
 * Todos los registros usan nombres claramente identificables.
 * Uso: dataset edge únicamente. No mezclar con seed base.
 */
class TenantDataAnomaliesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAnomalousCustomers();
        $this->seedAnomalousOrders();
        $this->seedAnomalousPallets();
        $this->seedAnomalousPunchEvents();
        $this->seedAnomalousReceptions();
    }

    private function seedAnomalousCustomers(): void
    {
        $salespersonId = Salesperson::query()->value('id');

        // Cliente sin alias (alias null)
        Customer::firstOrCreate(
            ['name' => 'Anomalía Cliente Sin Alias'],
            [
                'name'               => 'Anomalía Cliente Sin Alias',
                'alias'              => null,
                'vat_number'         => 'ESANOMALY01',
                'operational_status' => 'active',
                'salesperson_id'     => $salespersonId,
                'emails'             => 'anomalia.sinAlias@pesquerapp.test',
            ]
        );

        // Cliente sin transportista asignado
        Customer::firstOrCreate(
            ['name' => 'Anomalía Cliente Sin Transporte'],
            [
                'name'               => 'Anomalía Cliente Sin Transporte',
                'alias'              => 'ANOM-TRANSPORT',
                'vat_number'         => 'ESANOMALY02',
                'operational_status' => 'active',
                'salesperson_id'     => $salespersonId,
                'transport_id'       => null,
                'emails'             => 'anomalia.sinTransporte@pesquerapp.test',
            ]
        );

        // Cliente en estado pausado
        Customer::firstOrCreate(
            ['name' => 'Anomalía Cliente Pausado'],
            [
                'name'               => 'Anomalía Cliente Pausado',
                'alias'              => 'ANOM-PAUSED',
                'vat_number'         => 'ESANOMALY03',
                'operational_status' => 'paused',
                'salesperson_id'     => $salespersonId,
                'emails'             => 'anomalia.pausado@pesquerapp.test',
            ]
        );
    }

    private function seedAnomalousOrders(): void
    {
        $customer = Customer::query()->first();
        if (!$customer) {
            return;
        }

        // Pedido sin líneas de producto
        Order::firstOrCreate(
            ['reference' => 'ANOM-ORDER-NOLINES'],
            [
                'reference'   => 'ANOM-ORDER-NOLINES',
                'customer_id' => $customer->id,
                'status'      => 'pending',
                'notes'       => 'Anomalía: pedido sin líneas de producto para QA',
            ]
        );
    }

    private function seedAnomalousPallets(): void
    {
        // Palé sin posición (registrado pero sin StoredPallet)
        Pallet::firstOrCreate(
            ['observations' => 'Anomalía: Palé sin posición en almacén'],
            [
                'observations' => 'Anomalía: Palé sin posición en almacén',
                'status'       => Pallet::STATE_STORED,
                'order_id'     => null,
                'reception_id' => null,
            ]
        );

        // Palé en estado registrado sin asociación
        Pallet::firstOrCreate(
            ['observations' => 'Anomalía: Palé sin asociación a recepción ni pedido'],
            [
                'observations' => 'Anomalía: Palé sin asociación a recepción ni pedido',
                'status'       => Pallet::STATE_REGISTERED,
                'order_id'     => null,
                'reception_id' => null,
            ]
        );
    }

    private function seedAnomalousPunchEvents(): void
    {
        $employee = Employee::query()->first();
        if (!$employee) {
            return;
        }

        $base = Carbon::now()->subDays(2)->setTime(8, 0);

        // Doble IN consecutivo (sin OUT entre medias)
        PunchEvent::create([
            'employee_id' => $employee->id,
            'event_type'  => PunchEvent::TYPE_IN,
            'device_id'   => 'DEVICE-ANOMALY',
            'timestamp'   => $base,
        ]);

        PunchEvent::create([
            'employee_id' => $employee->id,
            'event_type'  => PunchEvent::TYPE_IN,
            'device_id'   => 'DEVICE-ANOMALY',
            'timestamp'   => $base->copy()->addMinutes(5),
        ]);

        // OUT sin IN previo (inicio de día ya en OUT)
        PunchEvent::create([
            'employee_id' => $employee->id,
            'event_type'  => PunchEvent::TYPE_OUT,
            'device_id'   => 'DEVICE-ANOMALY',
            'timestamp'   => Carbon::now()->subDays(1)->setTime(7, 55),
        ]);
    }

    private function seedAnomalousReceptions(): void
    {
        $supplier = \App\Models\Supplier::query()->first();
        if (!$supplier) {
            return;
        }

        // Recepción sin datos declarados (sin precio, sin observaciones)
        RawMaterialReception::firstOrCreate(
            ['reference' => 'ANOM-RECEP-NODECLARED'],
            [
                'reference'    => 'ANOM-RECEP-NODECLARED',
                'supplier_id'  => $supplier->id,
                'reception_at' => now()->subDays(5),
                'observations' => null,
                'status'       => 'pending',
            ]
        );
    }
}
