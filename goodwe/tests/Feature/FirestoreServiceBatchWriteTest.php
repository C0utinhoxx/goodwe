<?php

namespace Tests\Feature;

use App\Services\FirestoreService;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class FirestoreServiceBatchWriteTest extends TestCase
{
    public function test_batch_write_throws_when_a_document_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('users/tag-1: permission denied (código 7)');

        $this->assertWritesSucceeded(
            [
                'writeResults' => [[], []],
                'status' => [
                    ['code' => 7, 'message' => 'permission denied'],
                    ['code' => 0, 'message' => ''],
                ],
            ],
            ['users/tag-1', 'totems/totem-1']
        );
    }

    public function test_batch_write_accepts_when_every_document_succeeds(): void
    {
        $this->assertWritesSucceeded(
            [
                'writeResults' => [[], []],
                'status' => [
                    ['code' => 0, 'message' => ''],
                    ['code' => 0, 'message' => ''],
                ],
            ],
            ['users/tag-1', 'totems/totem-1']
        );

        $this->addToAssertionCount(1);
    }

    public function test_batch_write_accepts_a_response_without_statuses(): void
    {
        $this->assertWritesSucceeded([], []);

        $this->addToAssertionCount(1);
    }

    /**
     * Invoca a verificação usada por setDocumentsMerge sem abrir conexão.
     *
     * @param  array{status?: array<int, array{code?: int, message?: string}>}  $response
     * @param  string[]  $paths
     */
    private function assertWritesSucceeded(array $response, array $paths): void
    {
        $service = (new ReflectionClass(FirestoreService::class))->newInstanceWithoutConstructor();

        (new ReflectionMethod(FirestoreService::class, 'assertWritesSucceeded'))
            ->invoke($service, $response, $paths);
    }
}
