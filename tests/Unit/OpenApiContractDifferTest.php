<?php

namespace Tests\Unit;

use App\Services\OpenApi\OpenApiContractDiffer;
use PHPUnit\Framework\TestCase;

class OpenApiContractDifferTest extends TestCase
{
    private function spec(array $responseProperties, array $requestProperties = [], array $requestRequired = []): array
    {
        $operation = [
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $responseProperties,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($requestProperties) {
            $operation['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $requestProperties,
                            'required' => $requestRequired,
                        ],
                    ],
                ],
            ];
        }

        return ['paths' => ['/v2/widgets/{id}' => ['get' => $operation]]];
    }

    public function test_identical_specs_produce_no_changes(): void
    {
        $spec = $this->spec(['id' => ['type' => 'integer'], 'name' => ['type' => 'string']]);

        $changes = (new OpenApiContractDiffer)->diff($spec, $spec);

        $this->assertSame([], $changes);
    }

    public function test_removed_response_field_is_breaking(): void
    {
        $old = $this->spec(['id' => ['type' => 'integer'], 'name' => ['type' => 'string']]);
        $new = $this->spec(['id' => ['type' => 'integer']]);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::BREAKING, $changes[0]['severity']);
        $this->assertSame('response_field_removed', $changes[0]['type']);
    }

    public function test_response_field_type_change_is_breaking(): void
    {
        $old = $this->spec(['total' => ['type' => 'string']]);
        $new = $this->spec(['total' => ['type' => 'number']]);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::BREAKING, $changes[0]['severity']);
        $this->assertSame('response_field_type_changed', $changes[0]['type']);
    }

    public function test_added_response_field_is_compatible(): void
    {
        $old = $this->spec(['id' => ['type' => 'integer']]);
        $new = $this->spec(['id' => ['type' => 'integer'], 'notes' => ['type' => 'string']]);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::COMPATIBLE, $changes[0]['severity']);
        $this->assertSame('response_field_added', $changes[0]['type']);
    }

    public function test_new_required_request_field_is_breaking(): void
    {
        $old = $this->spec(['id' => ['type' => 'integer']], ['name' => ['type' => 'string']], []);
        $new = $this->spec(['id' => ['type' => 'integer']], ['name' => ['type' => 'string'], 'category' => ['type' => 'string']], ['category']);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::BREAKING, $changes[0]['severity']);
        $this->assertSame('request_field_added_required', $changes[0]['type']);
    }

    public function test_request_field_becoming_optional_is_compatible(): void
    {
        $old = $this->spec(['id' => ['type' => 'integer']], ['name' => ['type' => 'string']], ['name']);
        $new = $this->spec(['id' => ['type' => 'integer']], ['name' => ['type' => 'string']], []);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::COMPATIBLE, $changes[0]['severity']);
        $this->assertSame('request_field_now_optional', $changes[0]['type']);
    }

    public function test_removed_endpoint_is_breaking(): void
    {
        $old = $this->spec(['id' => ['type' => 'integer']]);
        $new = ['paths' => []];

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::BREAKING, $changes[0]['severity']);
        $this->assertSame('endpoint_removed', $changes[0]['type']);
    }

    public function test_added_endpoint_is_info(): void
    {
        $old = ['paths' => []];
        $new = $this->spec(['id' => ['type' => 'integer']]);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame(OpenApiContractDiffer::INFO, $changes[0]['severity']);
        $this->assertSame('endpoint_added', $changes[0]['type']);
    }

    public function test_paginated_collection_compares_item_shape(): void
    {
        $old = [
            'paths' => [
                '/v2/widgets' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'properties' => [
                                                'data' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $new = json_decode(json_encode($old), true);
        unset($new['paths']['/v2/widgets']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['properties']['name']);

        $changes = (new OpenApiContractDiffer)->diff($old, $new);

        $this->assertCount(1, $changes);
        $this->assertSame('response_field_removed', $changes[0]['type']);
    }
}
