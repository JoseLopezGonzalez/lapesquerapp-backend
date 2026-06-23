<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name'               => 'Cebo Galicia S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => '12',
                'facilcom_cebo_code' => '34',
                'a3erp_cebo_code'    => null,
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Suministros Pesqueros del Sur S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => '7',
                'facilcom_cebo_code' => '21',
                'a3erp_cebo_code'    => null,
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Materias Primas Costa Norte S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => '45',
                'facilcom_cebo_code' => '88',
                'a3erp_cebo_code'    => null,
                'type'               => '',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Cebo y Congelados Atlantico S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => null,
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => null,
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Proveedora Mar de Cadiz S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => '63',
                'facilcom_cebo_code' => '15',
                'a3erp_cebo_code'    => null,
                'type'               => '',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Suministros Facilcom Costa S.L.',
                'cebo_export_type'   => 'a3erp',
                'facil_com_code'     => '9',
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => '100234',
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Cebo A3ERP Mediterraneo S.L.',
                'cebo_export_type'   => 'a3erp',
                'facil_com_code'     => '28',
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => '200567',
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Materias Primas Cantabrico S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => '52',
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => null,
                'type'               => '',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Cebo y Descargas Huelva S.L.',
                'cebo_export_type'   => 'facilcom',
                'facil_com_code'     => null,
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => null,
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
            [
                'name'               => 'Suministros Pesqueros Levante S.L.',
                'cebo_export_type'   => 'a3erp',
                'facil_com_code'     => '74',
                'facilcom_cebo_code' => null,
                'a3erp_cebo_code'    => '300891',
                'type'               => 'raw_material',
                'contact_person'     => null,
                'phone'              => null,
                'emails'             => null,
                'address'            => null,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(
                ['name' => $data['name']],
                collect($data)->except('name')->toArray()
            );
        }
    }
}
