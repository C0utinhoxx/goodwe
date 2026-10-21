<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    /**
     * Idiomas suportados pelo middleware SetLocale.
     *
     * @var list<string>
     */
    private const LOCALES = ['pt', 'en', 'es'];

    /**
     * Palavras sem acento que existem em português. No inglês, qualquer uma
     * delas denuncia tradução pendente.
     *
     * @var list<string>
     */
    private const PORTUGUESE_MARKERS = [
        'disponivel', 'disponiveis', 'proxima', 'concluida', 'inicio',
        'historico', 'beneficios', 'simulacao', 'gestao', 'creditos',
        'sessao', 'sessoes', 'ultimas', 'medio', 'usuario', 'usuarios',
        'distribuicao', 'endereco', 'localizacao', 'periodo', 'potencia',
        'descricao', 'verificacao', 'acoes', 'informacoes', 'portugues',
        'conexao', 'necessario', 'obrigatorio', 'horario', 'numero',
        'padrao',
    ];

    /**
     * Termos válidos em espanhol que coincidem com a forma portuguesa sem
     * acento. Não podem acusar tradução pendente em `es`.
     *
     * @var list<string>
     */
    private const SPANISH_SHARED_FORMS = [
        'inicio', 'usuario', 'usuarios', 'numero', 'periodo',
        'potencia', 'horario', 'beneficios', 'ultimos', 'dias',
    ];

    private function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_every_locale_defines_exactly_the_same_keys(): void
    {
        $reference = array_keys($this->translations('pt'));

        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                $reference,
                array_keys($this->translations($locale)),
                "As chaves de lang/{$locale}.json divergem do português."
            );
        }
    }

    public function test_no_locale_has_empty_translations(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->translations($locale) as $key => $value) {
                $this->assertNotSame(
                    '',
                    trim((string) $value),
                    "A chave [{$key}] está vazia em lang/{$locale}.json."
                );
            }
        }
    }

    /**
     * O inglês e o espanhol não podem conter palavras portuguesas sem
     * acentuação — sintoma de tradução pendente.
     */
    public function test_english_and_spanish_do_not_leak_unaccented_portuguese(): void
    {
        $offenders = [];

        foreach (['en', 'es'] as $locale) {
            $markers = $locale === 'es'
                ? array_values(array_diff(self::PORTUGUESE_MARKERS, self::SPANISH_SHARED_FORMS))
                : self::PORTUGUESE_MARKERS;

            foreach ($this->translations($locale) as $key => $value) {
                $words = preg_split('/[^\p{L}]+/u', mb_strtolower((string) $value), -1, PREG_SPLIT_NO_EMPTY);

                foreach (array_intersect($words, $markers) as $found) {
                    $offenders[] = "lang/{$locale}.json [{$key}] contém \"{$found}\"";
                }
            }
        }

        $this->assertSame([], $offenders, "Traduções pendentes encontradas:\n".implode("\n", $offenders));
    }

    /**
     * O espanhol precisa manter o acento nas formas que o possuem
     * (ex.: "Últimos días", "Período"), mesmo quando o português coincide.
     */
    public function test_spanish_keeps_accents_where_required(): void
    {
        App::setLocale('es');
        $offenders = [];

        foreach ($this->translations('es') as $key => $value) {
            $words = preg_split('/[^\p{L}]+/u', mb_strtolower((string) $value), -1, PREG_SPLIT_NO_EMPTY);

            foreach (['ultimos', 'periodo'] as $wrong) {
                if (in_array($wrong, $words, true)) {
                    $offenders[] = "lang/es.json [{$key}] usa \"{$wrong}\" sem acento: \"{$value}\"";
                }
            }
        }

        $this->assertSame([], $offenders, "Acentos ausentes no espanhol:\n".implode("\n", $offenders));
    }

    public function test_portuguese_and_spanish_keep_their_diacritics(): void
    {
        App::setLocale('pt');
        $this->assertSame('Início', __('nav.home'));
        $this->assertSame('Simulação de recarga', __('app.subtitle'));

        App::setLocale('es');
        $this->assertSame('Inicio', __('nav.home'));
        $this->assertSame('Simulación de recarga', __('app.subtitle'));
        $this->assertSame('Cerrar sesión', __('app.logout'));
    }

    /**
     * Frases que foram corrigidas e não podem voltar a perder o acento.
     */
    public function test_previously_fixed_phrases_stay_correct(): void
    {
        $expected = [
            'es' => [
                'manager.period_7' => 'Últimos 7 días',
                'home.see_benefits' => 'Ver benefícios',
                'payment.error_description' => 'No pudimos completar el pago. Intenta nuevamente o utiliza otro método de pago.',
                'auth.driver_only' => 'Esta función es exclusiva de las cuentas de usuario.',
                'totens.index_note' => 'Los comandos se envían al equipo a través de Firestore. Mantén esta pantalla abierta durante la recarga.',
                'support.overview_description' => 'Resumen de los tickets de usuarios y gestores.',
            ],
            'pt' => [
                'app.subtitle' => 'Simulação de recarga',
                'nav.home' => 'Início',
                'home.greeting' => 'Olá, :name!',
                'manager.period_7' => 'Últimos 7 dias',
                'payment.error_description' => 'Não foi possível concluir o pagamento. Tente novamente ou utilize outro método.',
            ],
        ];

        foreach ($expected as $locale => $phrases) {
            App::setLocale($locale);

            foreach ($phrases as $key => $value) {
                $this->assertSame($value, __($key), "lang/{$locale}.json [{$key}] regrediu.");
            }
        }
    }

    public function test_language_selector_labels_are_correctly_spelled_in_each_language(): void
    {
        foreach (self::LOCALES as $locale) {
            $translations = $this->translations($locale);

            $this->assertSame('Português', $translations['language.pt']);
            $this->assertSame('English', $translations['language.en']);
            $this->assertSame('Español', $translations['language.es']);
        }
    }

    /**
     * Os arquivos lang/{locale}/app.php precisam espelhar as chaves app.*
     * do JSON, senão os rodapés divergem entre as telas.
     *
     * Compara os arrays PHP direto, porque o Laravel resolve `app.*` pelo
     * JSON e mascararia a divergência.
     */
    public function test_app_php_mirrors_the_json_translations(): void
    {
        foreach (self::LOCALES as $locale) {
            $php = require lang_path("{$locale}/app.php");
            $json = $this->translations($locale);

            foreach (['name', 'subtitle', 'logout', 'footer_tagline', 'footer_copyright'] as $key) {
                $this->assertArrayHasKey($key, $php, "lang/{$locale}/app.php não define [{$key}].");
                $this->assertSame(
                    $json["app.{$key}"],
                    $php[$key],
                    "A chave [app.{$key}] de lang/{$locale}/app.php diverge do JSON."
                );
            }
        }
    }
}
