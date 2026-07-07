<?php

namespace App\Support\Cms;

use InvalidArgumentException;

class SafeBladeRenderer
{
    private const RESPONSIVE_DIRECTIVES = ['desktop', 'tablet', 'mobile'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data): string
    {
        $this->assertSafeSyntax($template);

        $tokens = $this->tokens($template);
        $position = 0;
        $nodes = $this->parseNodes($tokens, $position);

        if ($position < count($tokens)) {
            throw new InvalidArgumentException('SafeBlade template contains an unexpected closing directive.');
        }

        return $this->renderNodes($nodes, $data);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $template): array
    {
        $tokens = preg_split(
            '/(@if\s*\([^)]*\)|@elseif\s*\([^)]*\)|@else(?!if)|@endif|@foreach\s*\([^)]*\)|@endforeach|@cmsSlot\s*\([^)]+\)|@desktop|@enddesktop|@tablet|@endtablet|@mobile|@endmobile|{{\s*[^}]+\s*}})/',
            $template,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        return is_array($tokens) ? $tokens : [$template];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $stopDirectives
     * @return array<int, array<string, mixed>>
     */
    private function parseNodes(array $tokens, int &$position, array $stopDirectives = []): array
    {
        $nodes = [];

        while ($position < count($tokens)) {
            $token = $tokens[$position];
            $directive = $this->directiveName($token);

            if ($directive !== null && in_array($directive, $stopDirectives, true)) {
                break;
            }

            if (str_starts_with($token, '{{')) {
                $nodes[] = [
                    'type' => 'echo',
                    'path' => $this->echoPath($token),
                    'original' => $token,
                ];
                $position++;

                continue;
            }

            if ($directive === 'if') {
                $nodes[] = $this->parseIf($tokens, $position);

                continue;
            }

            if ($directive === 'foreach') {
                $nodes[] = $this->parseForeach($tokens, $position);

                continue;
            }

            if ($directive === 'cmsSlot') {
                $nodes[] = $this->parseCmsSlot($tokens, $position);

                continue;
            }

            if (in_array($directive, self::RESPONSIVE_DIRECTIVES, true)) {
                $nodes[] = $this->parseResponsive($tokens, $position, $directive);

                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'endforeach', 'enddesktop', 'endtablet', 'endmobile'], true)) {
                throw new InvalidArgumentException('SafeBlade template contains an unexpected closing directive.');
            }

            $nodes[] = ['type' => 'text', 'value' => $token];
            $position++;
        }

        return $nodes;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{type: string, key: string}
     */
    private function parseCmsSlot(array $tokens, int &$position): array
    {
        $key = $this->directiveExpression($tokens[$position], 'cmsSlot');
        $this->assertSafeSlotKey($key);
        $position++;

        return [
            'type' => 'cmsSlot',
            'key' => $key,
        ];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{type: string, branches: array<int, array{condition: string|null, nodes: array<int, array<string, mixed>>}>}
     */
    private function parseIf(array $tokens, int &$position): array
    {
        $branches = [];
        $condition = $this->directiveExpression($tokens[$position], 'if');
        $this->assertSafeCondition($condition);
        $position++;

        $branches[] = [
            'condition' => $condition,
            'nodes' => $this->parseNodes($tokens, $position, ['elseif', 'else', 'endif']),
        ];

        while ($position < count($tokens) && $this->directiveName($tokens[$position]) === 'elseif') {
            $condition = $this->directiveExpression($tokens[$position], 'elseif');
            $this->assertSafeCondition($condition);
            $position++;

            $branches[] = [
                'condition' => $condition,
                'nodes' => $this->parseNodes($tokens, $position, ['elseif', 'else', 'endif']),
            ];
        }

        if ($position < count($tokens) && $this->directiveName($tokens[$position]) === 'else') {
            $position++;

            $branches[] = [
                'condition' => null,
                'nodes' => $this->parseNodes($tokens, $position, ['endif']),
            ];
        }

        if ($position >= count($tokens) || $this->directiveName($tokens[$position]) !== 'endif') {
            throw new InvalidArgumentException('SafeBlade @if directive is missing @endif.');
        }

        $position++;

        return ['type' => 'if', 'branches' => $branches];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{type: string, path: string, alias: string, nodes: array<int, array<string, mixed>>}
     */
    private function parseForeach(array $tokens, int &$position): array
    {
        $expression = $this->directiveExpression($tokens[$position], 'foreach');

        if (! preg_match('/^([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $matches)) {
            throw new InvalidArgumentException('SafeBlade @foreach must use "path as alias" syntax.');
        }

        $this->assertSafePath($matches[1]);
        $position++;

        $nodes = $this->parseNodes($tokens, $position, ['endforeach']);

        if ($position >= count($tokens) || $this->directiveName($tokens[$position]) !== 'endforeach') {
            throw new InvalidArgumentException('SafeBlade @foreach directive is missing @endforeach.');
        }

        $position++;

        return [
            'type' => 'foreach',
            'path' => $matches[1],
            'alias' => $matches[2],
            'nodes' => $nodes,
        ];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{type: string, device: string, nodes: array<int, array<string, mixed>>}
     */
    private function parseResponsive(array $tokens, int &$position, string $device): array
    {
        $position++;
        $closingDirective = 'end'.$device;
        $nodes = $this->parseNodes($tokens, $position, [$closingDirective]);

        if ($position >= count($tokens) || $this->directiveName($tokens[$position]) !== $closingDirective) {
            throw new InvalidArgumentException("SafeBlade @{$device} directive is missing @{$closingDirective}.");
        }

        $position++;

        return [
            'type' => 'responsive',
            'device' => $device,
            'nodes' => $nodes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $data
     */
    private function renderNodes(array $nodes, array $data): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $output .= match ($node['type']) {
                'text' => (string) $node['value'],
                'echo' => $this->renderEcho($node, $data),
                'if' => $this->renderIf($node, $data),
                'foreach' => $this->renderForeach($node, $data),
                'cmsSlot' => $this->renderCmsSlot($node, $data),
                'responsive' => $this->renderResponsive($node, $data),
                default => '',
            };
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function renderCmsSlot(array $node, array $data): string
    {
        $key = (string) ($node['key'] ?? '');
        $html = $data['slots'][$key]['html'] ?? null;

        return is_string($html) ? $html : '';
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function renderEcho(array $node, array $data): string
    {
        $value = $this->valueForPath($data, (string) $node['path']);

        if ($value === null) {
            return (string) $node['original'];
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(fn (mixed $item): string => $this->stringValue($item), $value));
        }

        return e($this->stringValue($value));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function renderIf(array $node, array $data): string
    {
        foreach ($node['branches'] as $branch) {
            if ($branch['condition'] === null || $this->conditionPasses((string) $branch['condition'], $data)) {
                return $this->renderNodes($branch['nodes'], $data);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function renderForeach(array $node, array $data): string
    {
        $items = $this->valueForPath($data, (string) $node['path']);

        if (! is_iterable($items)) {
            return '';
        }

        $output = '';

        foreach ($items as $item) {
            $output .= $this->renderNodes($node['nodes'], array_merge($data, [(string) $node['alias'] => $item]));
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function renderResponsive(array $node, array $data): string
    {
        $device = (string) $node['device'];

        return sprintf(
            '<div class="cms-responsive-only cms-responsive-only--%s">%s</div>',
            e($device),
            $this->renderNodes($node['nodes'], $data),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function conditionPasses(string $condition, array $data): bool
    {
        if (preg_match('/^!\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)$/', $condition, $matches)) {
            return ! $this->truthy($this->valueForPath($data, $matches[1]));
        }

        if (preg_match('/^([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)$/', $condition, $matches)) {
            return $this->truthy($this->valueForPath($data, $matches[1]));
        }

        if (preg_match('/^([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\s*(===|!==|==|!=)\s*(true|false|null|\d+(?:\.\d+)?|\'[^\']*\'|"[^"]*")$/', $condition, $matches)) {
            $left = $this->valueForPath($data, $matches[1]);
            $right = $this->literalValue($matches[3]);

            return match ($matches[2]) {
                '===' => $left === $right,
                '!==' => $left !== $right,
                '==' => $left == $right,
                '!=' => $left != $right,
            };
        }

        throw new InvalidArgumentException('SafeBlade condition contains unsupported syntax.');
    }

    private function truthy(mixed $value): bool
    {
        return (bool) $value;
    }

    private function literalValue(string $value): mixed
    {
        return match (true) {
            $value === 'true' => true,
            $value === 'false' => false,
            $value === 'null' => null,
            preg_match('/^\d+\.\d+$/', $value) === 1 => (float) $value,
            preg_match('/^\d+$/', $value) === 1 => (int) $value,
            str_starts_with($value, "'") || str_starts_with($value, '"') => substr($value, 1, -1),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function valueForPath(array $data, string $path): mixed
    {
        $current = $data;

        foreach (explode('.', $path) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];

                continue;
            }

            if (is_object($current)) {
                $properties = get_object_vars($current);

                if (array_key_exists($segment, $properties)) {
                    $current = $properties[$segment];

                    continue;
                }
            }

            return null;
        }

        return $current;
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private function directiveName(string $token): ?string
    {
        return match (true) {
            preg_match('/^@if\s*\(/', $token) === 1 => 'if',
            preg_match('/^@elseif\s*\(/', $token) === 1 => 'elseif',
            preg_match('/^@else(?!if)/', $token) === 1 => 'else',
            preg_match('/^@endif/', $token) === 1 => 'endif',
            preg_match('/^@foreach\s*\(/', $token) === 1 => 'foreach',
            preg_match('/^@endforeach/', $token) === 1 => 'endforeach',
            preg_match('/^@cmsSlot\s*\(/', $token) === 1 => 'cmsSlot',
            preg_match('/^@desktop$/', $token) === 1 => 'desktop',
            preg_match('/^@enddesktop$/', $token) === 1 => 'enddesktop',
            preg_match('/^@tablet$/', $token) === 1 => 'tablet',
            preg_match('/^@endtablet$/', $token) === 1 => 'endtablet',
            preg_match('/^@mobile$/', $token) === 1 => 'mobile',
            preg_match('/^@endmobile$/', $token) === 1 => 'endmobile',
            default => null,
        };
    }

    private function directiveExpression(string $token, string $directive): string
    {
        if (! preg_match('/^@'.preg_quote($directive, '/').'\s*\((.*)\)$/s', $token, $matches)) {
            throw new InvalidArgumentException("SafeBlade @{$directive} directive is malformed.");
        }

        return trim($matches[1]);
    }

    private function echoPath(string $token): string
    {
        $path = trim(substr($token, 2, -2));
        $this->assertSafePath($path);

        return $path;
    }

    private function assertSafeSyntax(string $template): void
    {
        if (str_contains($template, '{!!') || str_contains($template, '!!}')) {
            throw new InvalidArgumentException('SafeBlade raw output is not allowed.');
        }

        if (preg_match('/@(php|endphp|include|extends|section|yield|component|slot|each|while|for|switch|isset|empty|unless|auth|guest)\b/i', $template) === 1) {
            throw new InvalidArgumentException('SafeBlade template contains a forbidden Blade directive.');
        }

        preg_match_all('/@cmsSlot\s*\((.*?)\)/s', $template, $slotMatches);

        foreach ($slotMatches[1] ?? [] as $slotKey) {
            $this->assertSafeSlotKey(trim((string) $slotKey));
        }

        preg_match_all('/{{\s*(.*?)\s*}}/s', $template, $matches);

        foreach ($matches[1] ?? [] as $path) {
            $this->assertSafePath((string) $path);
        }

        preg_match_all('/@(if|elseif)\s*\((.*?)\)/s', $template, $conditionMatches);

        foreach ($conditionMatches[2] ?? [] as $condition) {
            $this->assertSafeCondition((string) $condition);
        }
    }

    private function assertSafeCondition(string $condition): void
    {
        if (str_contains($condition, '$') || str_contains($condition, '->') || str_contains($condition, '::') || str_contains($condition, '(') || str_contains($condition, ')')) {
            throw new InvalidArgumentException('SafeBlade condition contains forbidden syntax.');
        }
    }

    private function assertSafePath(string $path): void
    {
        $pattern = (string) config('cms_blocks.contract.safe_blade.path_pattern', '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/');

        if (preg_match($pattern, $path) !== 1) {
            throw new InvalidArgumentException("SafeBlade path [{$path}] must use dot notation.");
        }
    }

    private function assertSafeSlotKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key) !== 1) {
            throw new InvalidArgumentException("SafeBlade slot [{$key}] must use a valid slot key.");
        }
    }
}
