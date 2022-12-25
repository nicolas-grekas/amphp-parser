<?php

namespace Amp\Parser\Test;

use Amp\Parser\InvalidDelimiterError;
use Amp\Parser\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testIntDelimiter(): void
    {
        $parser = new Parser((function () use (&$value) {
            $value = yield 6;
        })());

        $parser->push("foobarfoo\r\n");

        $this->assertSame("foobar", $value);
    }

    public function testStringDelimiter(): void
    {
        $parser = new Parser((function () use (&$value1, &$value2): \Generator {
            $value1 = yield "bar";
            $value2 = yield "\r\n";
        })());

        $parser->push("foobarbaz\r\n");

        $this->assertSame("foo", $value1);
        $this->assertSame("baz", $value2);
    }

    public function testUndelimited(): void
    {
        $parser = new Parser((function () use (&$value): \Generator {
            $value = yield;
        })());

        $parser->push("foobarbaz\r\n");

        $this->assertSame("foobarbaz\r\n", $value);
    }

    public function testEndedGeneratorThrows(): void
    {
        $parser = new Parser((function (): \Generator {
            if (false) {
                yield;
            }
        })());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The parser is no longer writable");

        $parser->push("test");
    }

    public function testThrowingGeneratorEndsWhenDirectlyThrowing(): void
    {
        $this->expectException(\RuntimeException::class);

        new Parser((function (): \Generator {
            if (false) {
                yield;
            }

            throw new \RuntimeException;
        })());
    }

    public function testThrowingGeneratorEndsWhenThrowingLater(): void
    {
        $parser = new Parser((function (): \Generator {
            yield 3;

            throw new \RuntimeException;
        })());

        $this->expectException(\RuntimeException::class);

        $parser->push("abc");
    }

    public function testLengthDelimiterPartialPush(): void
    {
        $ok = false;

        $parser = new Parser((function () use (&$ok): \Generator {
            yield 6;
            $ok = true;
        })());

        $parser->push("abc\r\n");
        $this->assertFalse($ok);

        $parser->push("x");
        $this->assertTrue($ok);
    }

    public function testThrowsOnInvalidYield(): void
    {
        $this->expectException(InvalidDelimiterError::class);

        new Parser((function (): \Generator {
            yield true;
        })());
    }

    public function testThrowsOnLaterInvalidYield()
    {
        $parser = new Parser((function (): \Generator {
            yield 3;
            yield true;
        })());

        $this->expectException(InvalidDelimiterError::class);

        $parser->push("abcd");
    }

    public function testCancelReturnsInternalBuffer(): void
    {
        $parser = new Parser((function (): \Generator {
            yield 3;
        })());

        $parser->push("abcd");

        $this->assertSame("d", $parser->cancel());
    }

    public function testIsValidOnNonFinishedParser(): void
    {
        $parser = new Parser((function (): \Generator {
            yield 3;
        })());

        $this->assertTrue($parser->isValid());
    }

    public function testIsValidOnFinishedParser(): void
    {
        $parser = new Parser((function (): \Generator {
            yield 3;
        })());

        $parser->push("12345");

        $this->assertFalse($parser->isValid());
    }
}
