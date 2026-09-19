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
        $openBrace = $openParen === null ? null : self::seekBeforeSemicolon($tokens, $openParen, '{');

        // Deklarasi abstrak, interface, dan `use function` berakhir di `;`.
        if ($openParen === null || $openBrace === null) {
            return null;
        }

        $closeBrace = self::matchingBrace($tokens, $openBrace);

        return [
            'metric' => new MethodMetric(
                name: self::nameAfter($tokens, $function),
                loc: max(1, $tokens[$closeBrace]->line - $tokens[$openBrace]->line),
                complexity: 1 + self::countKinds($tokens, $openBrace, $closeBrace, self::BRANCH_TOKENS),
                parameters: self::countParameters($tokens, $openParen),
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

    /** @param list<PhpToken> $tokens */
    private static function matchingBrace(array $tokens, int $openBrace): int
    {
        $depth = 0;
        $total = count($tokens);

        for ($i = $openBrace; $i < $total; $i++) {
            $depth += match (true) {
                $tokens[$i]->is(self::OPENING_BRACES) => 1,
                $tokens[$i]->is('}') => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $i;
            }
        }

        return $total - 1;
    }

    /**
     * Menghitung `T_VARIABLE` di kedalaman satu; promoted property ikut, karena ia memang parameter.
     *
     * @param  list<PhpToken>  $tokens
     */
    private static function countParameters(array $tokens, int $openParen): int
    {
        $depth = 0;
        $parameters = 0;
        $total = count($tokens);

        for ($i = $openParen; $i < $total; $i++) {
            $depth += match (true) {
                $tokens[$i]->is('(') => 1,
                $tokens[$i]->is(')') => -1,
                default => 0,
            };

            if ($depth === 0) {
                break;
            }

            if ($depth === 1 && $tokens[$i]->is(T_VARIABLE)) {
                $parameters++;
            }
        }

        return $parameters;
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
