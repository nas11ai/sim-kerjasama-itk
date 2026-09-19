<?php

namespace App\Support\Quality;

use PhpToken;

/**
 * Mengukur berkas PHP lewat tokenizer, bukan regex: regex tidak bisa membedakan
 * `}` penutup method dari `}` di dalam string, dan plafon yang dihitung keliru
 * menghabiskan kepercayaan pada pagarnya.
 */
final class CodeMetrics
{
    /** Token yang menambah satu jalur keputusan; `?` mewakili ternary. */
    private const BRANCH_TOKENS = [
        T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH,
        T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE, '?',
    ];

    /** Pembuka yang ditutup `}`, termasuk interpolasi `{$x}` dan `${x}` di dalam string. */
    private const OPENING_BRACES = ['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];

    /** Modifier yang menjadikan parameter constructor sebuah promoted property. */
    private const PROMOTION_MODIFIERS = [
        T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
    ];

    /** @return array{loc: int, methods: list<MethodMetric>} */
    public static function forFile(string $path): array
    {
        $source = SourceFile::read($path);

        return [
            'loc' => SourceFile::lineCount($source),
            'methods' => self::methods(array_values(PhpToken::tokenize($source))),
        ];
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return list<MethodMetric>
     */
    private static function methods(array $tokens): array
    {
        $methods = [];
        $total = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            $measured = $tokens[$i]->is(T_FUNCTION) ? self::measure($tokens, $i) : null;

            if ($measured === null) {
                continue;
            }

            $methods[] = $measured['metric'];
            $i = $measured['end'];
        }

        return $methods;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return array{metric: MethodMetric, end: int}|null null untuk deklarasi tanpa badan
     */
    private static function measure(array $tokens, int $function): ?array
    {
        $openParen = self::seekBeforeSemicolon($tokens, $function, '(');
        $closeParen = $openParen === null ? null : self::closingIndex($tokens, $openParen, ['('], ')');
        $openBrace = $closeParen === null ? null : self::seekBeforeSemicolon($tokens, $closeParen, '{');

        // Deklarasi abstrak, interface, dan `use function` berakhir di `;`.
        if ($openParen === null || $openBrace === null) {
            return null;
        }

        $closeBrace = self::closingIndex($tokens, $openBrace, self::OPENING_BRACES, '}');
        $parameters = self::parameters(array_slice($tokens, $openParen + 1, $closeParen - $openParen - 1));

        return [
            'metric' => new MethodMetric(
                name: self::nameAfter($tokens, $function),
                loc: max(1, $tokens[$closeBrace]->line - $tokens[$openBrace]->line),
                complexity: 1 + self::countKinds($tokens, $openBrace, $closeBrace, self::BRANCH_TOKENS),
                parameters: $parameters['count'],
                promotedParameters: $parameters['promoted'],
                returns: self::countKinds($tokens, $openBrace, $closeBrace, [T_RETURN]),
            ),
            'end' => $closeBrace,
        ];
    }

    /** @param list<PhpToken> $tokens */
    private static function seekBeforeSemicolon(array $tokens, int $from, string $needle): ?int
    {
        $total = count($tokens);

        for ($i = $from; $i < $total && !$tokens[$i]->is(';'); $i++) {
            if ($tokens[$i]->is($needle)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @param  list<int|string>  $openers
     * @return int indeks penutup yang berpasangan dengan pembuka di $open
     */
    private static function closingIndex(array $tokens, int $open, array $openers, string $closer): int
    {
        $depth = 0;
        $total = count($tokens);

        for ($i = $open; $i < $total; $i++) {
            $depth += match (true) {
                $tokens[$i]->is($openers) => 1,
                $tokens[$i]->is($closer) => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $i;
            }
        }

        return $total - 1;
    }

    /**
     * Satu `T_VARIABLE` = satu parameter; ia promoted kalau didahului modifier visibilitas.
     * Nilai default tidak bisa memuat variabel, jadi kedalaman kurung tidak perlu dilacak.
     *
     * @param  list<PhpToken>  $signature
     * @return array{count: int, promoted: int}
     */
    private static function parameters(array $signature): array
    {
        $count = 0;
        $promoted = 0;
        $hasModifier = false;

        foreach ($signature as $token) {
            $hasModifier = !$token->is(',') && ($hasModifier || $token->is(self::PROMOTION_MODIFIERS));

            if ($token->is(T_VARIABLE)) {
                $count++;
                $promoted += $hasModifier ? 1 : 0;
            }
        }

        return ['count' => $count, 'promoted' => $promoted];
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @param  list<int|string>  $kinds
     */
    private static function countKinds(array $tokens, int $from, int $to, array $kinds): int
    {
        return count(array_filter(
            array_slice($tokens, $from, $to - $from + 1),
            static fn (PhpToken $token): bool => $token->is($kinds),
        ));
    }

    /** @param list<PhpToken> $tokens */
    private static function nameAfter(array $tokens, int $function): string
    {
        $total = count($tokens);

        for ($i = $function + 1; $i < $total; $i++) {
            if ($tokens[$i]->isIgnorable() || $tokens[$i]->text === '&') {
                continue;
            }

            return $tokens[$i]->is('(') ? '{closure}' : $tokens[$i]->text;
        }

        return '{unknown}';
    }
}
