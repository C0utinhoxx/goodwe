<?php

namespace App\Services;

use App\Support\LaravelCacheItemPool;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Rpc\Code;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

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

        $this->firestore = new FirestoreClient($this->clientOptions($credentialsPath));
    }

    /**
     * Opções do cliente Firestore.
     *
     * O token OAuth é guardado no cache da aplicação: sem isso, cada
     * requisição HTTP (inclusive cada polling da tela de recarga) faz um novo
     * round-trip ao Google antes de chegar ao Firestore.
     *
     * @return array<string, mixed>
     */
    private function clientOptions(string $credentialsPath): array
    {
        $options = [
            'projectId' => $this->projectId,
            'credentials' => $credentialsPath,
        ];

        if (! config('firebase.auth_cache.enabled', true)) {
            return $options;
        }

        try {
            $store = config('firebase.auth_cache.store');

            $options['credentialsConfig'] = [
                'authCache' => new LaravelCacheItemPool(
                    Cache::store(filled($store) ? (string) $store : null),
                    (string) config('firebase.auth_cache.prefix', 'firebase-auth-')
                ),
                'authCacheOptions' => [
                    'lifetime' => (int) config('firebase.auth_cache.lifetime', 1500),
                ],
            ];
        } catch (Throwable) {
            // Sem cache o Firestore continua funcionando (apenas mais lento).
        }

        return $options;
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

    /**
     * Lê vários documentos em uma única chamada (BatchGetDocuments).
     *
     * A tela de recarga precisa do documento da TAG e do totem ao mesmo tempo;
     * buscar os dois em um só round-trip corta pela metade o tempo de abertura.
     *
     * @param  string[]  $paths  Caminhos no formato "colecao/id".
     * @return array<string, array<string, mixed>|null> Dados indexados pelo caminho.
     */
    public function getDocumentsData(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $snapshots = $this->firestore->documents(array_values($paths));
        $results = [];

        foreach (array_values($paths) as $index => $path) {
            $snapshot = $snapshots[$index] ?? null;

            $results[$path] = $snapshot !== null && $snapshot->exists()
                ? ($snapshot->data() ?? [])
                : null;
        }

        return $results;
    }

    /**
     * Grava vários documentos com merge em um único commit.
     *
     * O início da recarga precisa atualizar o canal do ESP (users/{TAG}) e o
     * espelho do totem. Enviar os dois no mesmo batch economiza um round-trip
     * no caminho crítico entre clicar em "Iniciar" e o equipamento reagir.
     *
     * @param  array<string, array<string, mixed>>  $documents  Dados indexados por "colecao/id".
     */
    public function setDocumentsMerge(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        // Para um único documento o merge direto já é uma chamada só.
        if (count($documents) === 1) {
            $path = array_key_first($documents);
            [$collection, $documentId] = explode('/', $path, 2);
            $this->setDocumentMerge($collection, $documentId, $documents[$path]);

            return;
        }

        $writer = $this->firestore->bulkWriter([
            'maxBatchSize' => 20,
            'greedilySend' => true,
            'isThrottlingEnabled' => false,
        ]);

        foreach ($documents as $path => $data) {
            [$collection, $documentId] = explode('/', $path, 2);

            $writer->set(
                $this->firestore->collection($collection)->document($documentId),
                $data,
                ['merge' => true]
            );
        }

        // `close()` espera as tentativas repetíveis, mas uma falha definitiva
        // fica apenas registada no retorno. Sem esta verificação o totem
        // receberia "iniciado" mesmo sem o comando ter chegado ao Firestore.
        $this->assertWritesSucceeded($writer->close(), array_keys($documents));
    }

    /**
     * Lança exceção quando algum write do BulkWriter falhou.
     *
     * @param  array{status?: array<int, array{code?: int, message?: string}>}  $response
     * @param  string[]  $paths  Caminhos enviados, na mesma ordem dos writes.
     */
    private function assertWritesSucceeded(array $response, array $paths): void
    {
        $failures = [];

        foreach ($response['status'] ?? [] as $writeId => $status) {
            $code = (int) ($status['code'] ?? Code::OK);

            if ($code === Code::OK) {
                continue;
            }

            $path = $paths[$writeId] ?? "write #{$writeId}";
            $message = $status['message'] ?? 'erro desconhecido';
            $failures[] = "{$path}: {$message} (código {$code})";
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Falha ao gravar no Firestore: '.implode('; ', $failures)
            );
        }
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
