<?php

namespace Phpactor\Docblock;

use Phpactor\Docblock\Ast\Token;
use Phpactor\Docblock\Ast\Tokens;

final class Lexer
{
    /**
     * @var string[]
     */
    private const PATTERNS = [
        '/\*+', // start tag
        '\*/', // close tag
        ' {1}\* {1}',
        '\[\]', // list
        '\?', //tag
        '@\w+', //tag
        '\R', // newline
        ' *', // space
        ',', // comma
        '\|', // bar (union)
        '=', // equals
        '(', ')', '\{', '\}', '\[', '\]', '<', '>', // brackets
        '\$[a-zA-Z0-9_\x80-\xff]+', // variable
        '[a-zA-Z0-9_\\\]+', // label
    ];

    private const TOKEN_VALUE_MAP = [
        ']' => Token::T_BRACKET_SQUARE_CLOSE,
        '[' => Token::T_BRACKET_SQUARE_OPEN,
        '>' => Token::T_BRACKET_ANGLE_CLOSE,
        '<' => Token::T_BRACKET_ANGLE_OPEN,
        '{' => Token::T_BRACKET_CURLY_OPEN,
        '}' => Token::T_BRACKET_CURLY_CLOSE,
        '(' => Token::T_PAREN_OPEN,
        ')' => Token::T_PAREN_CLOSE,
        ',' => Token::T_COMMA,
        '[]' => Token::T_LIST,
        '?' => Token::T_NULLABLE,
        '|' => Token::T_BAR,
        '=' => Token::T_EQUALS,
    ];

    /**
     * @var string[]
     */
    private const IGNORE_PATTERNS = [
        '\s+',
    ];

    /**
     * @var string
     */
    private $pattern;

    public function __construct()
    {
        $this->pattern = sprintf(
            '{(%s)|%s}',
            implode(')|(', self::PATTERNS),
            implode('|', self::IGNORE_PATTERNS)
        );
    }

    public function lex(string $docblock): Tokens
    {
        $chunks = (array)preg_split(
            $this->pattern,
            $docblock,
            null,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE
        );

        $tokens = [];
        $prevChunk = null;
        foreach ($chunks as $chunk) {
            [ $value, $offset ] = $chunk;
            $tokens[] = new Token(
                $offset,
                $this->resolveType($value, $prevChunk),
                $value
            );
            $prevChunk = $chunk;
        }

        return new Tokens($tokens);
    }

    private function resolveType(string $value, ?array $prevChunk = null): string
    {
        if (false !== strpos($value, '/*')) {
            return Token::T_PHPDOC_OPEN;
        }

        if (false !== strpos($value, '*/')) {
            return Token::T_PHPDOC_CLOSE;
        }

        if (trim($value) === '*') {
            return Token::T_PHPDOC_LEADING;
        }

        if (array_key_exists($value, self::TOKEN_VALUE_MAP)) {
            return self::TOKEN_VALUE_MAP[$value];
        }

        if ($value[0] === '$') {
            return Token::T_VARIABLE;
        }

        if ($value[0] === '@') {
            return Token::T_TAG;
        }

        if (trim($value) === '') {
            return Token::T_WHITESPACE;
        }

        if (ctype_alpha($value[0]) || $value[0] === '\\') {
            return Token::T_LABEL;
        }

        return Token::T_UNKNOWN;
    }
}
