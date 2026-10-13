<?php

namespace Tests\Fakes;

use App\Services\FirestoreService;

/**
 * Fake em memória do Firestore para testes de funcionalidade.
 *
 * Não abre conexão com o Firebase: mantém os documentos em um array e
 * registra cada escrita para asserções.
 */
class FakeFirestoreService extends FirestoreService
{
    /** @var array<string, array<string, mixed>> Documentos no formato "colecao/id" => dados */
    public array $documents = [];

    /** @var array<int, array{collection: string, id: string, data: array<string, mixed>}> */
    public array $writes = [];

    /** Simula uma falha de conexão com o Firebase quando true. */
    public bool $shouldFail = false;

    public function __construct() {}

    public function getDocumentData(string $collection, string $documentId): ?array
    {
        $this->failIfNeeded();

        return $this->documents["{$collection}/{$documentId}"] ?? null;
    }

    public function setDocumentMerge(string $collection, string $documentId, array $data): void
    {
        $this->failIfNeeded();

        $key = "{$collection}/{$documentId}";
        $this->documents[$key] = array_merge($this->documents[$key] ?? [], $data);
        $this->writes[] = ['collection' => $collection, 'id' => $documentId, 'data' => $data];
    }

    public function seed(string $collection, string $documentId, array $data): void
    {
        $this->documents["{$collection}/{$documentId}"] = $data;
    }

    public function hasWrite(string $collection, string $documentId, array $expected): bool
    {
        foreach ($this->writes as $write) {
            if ($write['collection'] === $collection
                && $write['id'] === $documentId
                && $expected === array_intersect_key($write['data'], $expected)) {
                return true;
            }
        }

        return false;
    }

    private function failIfNeeded(): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Falha de conexão com o Firestore (simulada).');
        }
    }
}
