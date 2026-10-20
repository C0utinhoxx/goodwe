<?php

namespace App\Services;

use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FirestoreClient;
use RuntimeException;

class FirestoreService
{
    private FirestoreClient $firestore;

    private string $projectId;

    public function __construct()
    {
        $this->projectId = config('firebase.projects.app.project_id')
            ?? 'powertag--contas';

        $credentialsPath = config('firebase.projects.app.credentials')
            ?? storage_path('app/firebase/credentials.json');

        if (! str_starts_with($credentialsPath, '/')) {
            $credentialsPath = base_path($credentialsPath);
        }

        $credentialsPath = $this->ensureCredentialsFile($credentialsPath);

        $this->firestore = new FirestoreClient([
            'projectId' => $this->projectId,
            'credentials' => $credentialsPath,
        ]);
    }

    /**
     * Garante que o arquivo de credenciais exista.
     *
     * Em produção (Render/Koyeb) o arquivo não é commitado: ele é
     * reconstruído a partir da env FIREBASE_CREDENTIALS_JSON.
     */
    private function ensureCredentialsFile(string $credentialsPath): string
    {
        if (file_exists($credentialsPath)) {
            return $credentialsPath;
        }

        $json = config('firebase.projects.app.credentials_json');

        if (is_string($json) && $json !== '') {
            $decoded = base64_decode($json, true);

            if ($decoded !== false && $this->isJson($decoded)) {
                $json = $decoded;
            }

            if (! $this->isJson($json)) {
                throw new RuntimeException(
                    'A variável FIREBASE_CREDENTIALS_JSON não contém um JSON válido.'
                );
            }

            $directory = dirname($credentialsPath);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException(
                    "Não foi possível criar o diretório de credenciais em: {$directory}"
                );
            }

            if (file_put_contents($credentialsPath, $json) === false) {
                throw new RuntimeException(
                    "Não foi possível gravar as credenciais do Firebase em: {$credentialsPath}"
                );
            }

            chmod($credentialsPath, 0600);

            return $credentialsPath;
        }

        throw new RuntimeException(
            "Arquivo de credenciais do Firebase não encontrado em: {$credentialsPath}"
        );
    }

    private function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public function getFirestore(): FirestoreClient
    {
        return $this->firestore;
    }

    public function getDocument(string $collection, string $documentId): ?DocumentReference
    {
        return $this->firestore->collection($collection)->document($documentId);
    }

    /**
     * Retorna os dados de um documento como array, ou null quando não existe.
     *
     * Método de conveniência para leituras pontuais (ex.: saldo do usuário,
     * status do totem) sem precisar trabalhar com DocumentSnapshot.
     */
    public function getDocumentData(string $collection, string $documentId): ?array
    {
        $snapshot = $this->firestore->collection($collection)->document($documentId)->snapshot();

        if (! $snapshot->exists()) {
            return null;
        }

        return $snapshot->data() ?? [];
    }

    public function saveDocument(string $collection, string $documentId, array $data): void
    {
        $this->firestore->collection($collection)->document($documentId)->set($data);
    }

    public function updateDocument(string $collection, string $documentId, array $data): void
    {
        $this->firestore->collection($collection)->document($documentId)->update(array_map(fn ($value) => ['fieldValue' => $value], $data));
    }

    public function setDocumentMerge(string $collection, string $documentId, array $data): void
    {
        $this->firestore->collection($collection)->document($documentId)->set($data, ['merge' => true]);
    }

    /**
     * Lista os documentos de uma coleção.
     *
     * @param  int|null  $limit  Quantidade máxima de documentos lidos; sem
     *                           limite a coleção inteira é percorrida via REST.
     * @return array<int, array<string, mixed>>
     */
    public function listCollection(string $collection, ?int $limit = null): array
    {
        $query = $this->firestore->collection($collection);

        if ($limit !== null && $limit > 0) {
            $query = $query->limit($limit);
        }

        $documents = $query->documents();
        $results = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $results[] = array_merge(
                    ['id' => $document->id()],
                    $document->data()
                );
            }
        }

        return $results;
    }

    public function queryCollection(string $collection, string $field, string $operator, $value): array
    {
        $documents = $this->firestore->collection($collection)
            ->where($field, $operator, $value)
            ->documents();

        $results = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $results[] = array_merge(
                    ['id' => $document->id()],
                    $document->data()
                );
            }
        }

        return $results;
    }
}
