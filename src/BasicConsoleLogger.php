<?php declare(strict_types=1);

/*
 * This file is part of the php-extended/php-basic-console-logger library
 * forked from https://gitlab.com/php-extended/php-basic-console-logger
 *
 * (c) Anastaszor
 * This source file is subject to the MIT license that
 * is bundled with this source code in the file LICENSE.
 */

namespace zymeli\BasicConsoleLogger;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * BasicConsoleLogger class file.
 *
 * This class logs to the console, with a new line for each call, the string
 * that is passed to it, which is interpolated with the given context.
 *
 * @author Anastaszor
 * @fixed by zymeli
 */
class BasicConsoleLogger extends AbstractLogger implements Stringable
{

    /**
     * The map of log levels to verbosity levels.
     *
     * @var array<integer|string, integer>
     */
    protected array $_verbosityLevelMap = [
        LogLevel::EMERGENCY => -4,
        '-4' => -4,
        LogLevel::ALERT => -3,
        '-3' => -3,
        LogLevel::CRITICAL => -2,
        '-2' => -2,
        LogLevel::ERROR => -1,
        '-1' => -1,
        LogLevel::WARNING => 0,
        '0' => 0,
        LogLevel::NOTICE => 1,
        '1' => 1,
        LogLevel::INFO => 2,
        '2' => 2,
        LogLevel::DEBUG => 3,
        '3' => 3,
    ];

    /**
     * The current verbosity level.
     *
     * @var integer
     */
    protected int $_currentVerbosity = 0;

    /**
     * Builds a new BasicConsoleLogger with the given default verbosity level.
     *
     * @param integer $verbose
     */
    public function __construct(int $verbose = 0)
    {
        $this->setVerbosityLevel($verbose);
    }

    /**
     * {@inheritDoc}
     * @see \Stringable::__toString()
     */
    public function __toString(): string
    {
        return static::class . '@' . \spl_object_hash($this);
    }

    /**
     * Gets a string from the given value.
     *
     * @param null|boolean|integer|float|string|object|resource|array<integer|string, null|boolean|integer|float|string|object|resource|array<integer|string, null|boolean|integer|float|string|object>> $value
     * @param integer $indent
     * @return string
     */
    protected function getStrval(mixed $value, int $indent = 1): string
    {
        if (\is_resource($value))
            return 'resource(' . \get_resource_type($value) . ')';

        if (\is_object($value))
            return $this->getStrvalObject($value);

        if (null === $value)
            return 'null';

        if (\is_bool($value))
            return $value ? 'true' : 'false';

        if (\is_array($value))
            return $this->getStrvalArray($value, $indent);

        return (string)$value;
    }

    /**
     * Gets a string from the given object value.
     *
     * @param object $value
     * @return string
     */
    public function getStrvalObject(object $value): string
    {
        if ($value instanceof DateTimeInterface)
            return $value->format(DateTime::RFC3339_EXTENDED);
        if ($value instanceof DateInterval) {
            if (\PHP_VERSION_ID >= 70100)
                return $value->format('Interval: %R %Y years, %M months, %D days, %H:%I:%S.%F');

            return $value->format('Interval: %R %Y years, %M months, %D days, %H:%I:%S');
        }
        if (\method_exists($value, '__toString'))
            return (string)$value->__toString();

        return \get_class($value) . '(' . \serialize($value) . ')';
    }

    /**
     * Gets a string from the given array value.
     *
     * @param array<integer|string, null|boolean|integer|float|string|object|resource|array<integer|string, null|boolean|integer|float|string|object>> $value
     * @param integer $indent
     * @return string
     */
    public function getStrvalArray(array $value, int $indent): string
    {
        if (0 === \count($value)) {
            return '[]';
        }
        if (1 === \count($value) && isset($value[0])) {
            return '[' . $this->getStrval($value[0]) . ']';
        }
        $strval = '[' . "\n";

        foreach ($value as $key => $val) {
            $keyStr = \is_string($key) ? '"' . $key . '"' : (string)$key;
            $strval .= \str_repeat("\t", $indent) . $keyStr . ' => ' . $this->getStrval($val, $indent + 1) . "\n";
        }

        return $strval . \str_repeat("\t", \max(0, $indent - 1)) . ']';
    }

    /**
     * Sets the current verbosity level.
     *
     * @param integer $verbose
     * @return BasicConsoleLogger
     */
    public function setVerbosityLevel(int $verbose): BasicConsoleLogger
    {
        if (-3 > $verbose)
            $verbose = -3;
        if (3 < $verbose)
            $verbose = 3;
        $this->_currentVerbosity = $verbose;

        return $this;
    }

    /**
     * Gets the verbosity level of the given log level.
     *
     * @param string $logLevel
     * @return integer
     */
    public function getVerbosityLevel(string $logLevel): int
    {
        if (isset($this->_verbosityLevelMap[$logLevel]))
            return $this->_verbosityLevelMap[$logLevel];

        return -4;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, $message, array $context = []): void
    {
        if (!\is_scalar($level))
            return;
        // do not process the logs which levels are higher that the
        // current verbosity level
        $vlvl = $this->getVerbosityLevel((string)$level);
        if ($vlvl > $this->_currentVerbosity)
            return;

        $time = new DateTimeImmutable();
        $replacements = [];

        foreach ($context as $key => $value) {
            /** @psalm-suppress MixedArgument */
            $strval = $this->getStrval($value);
            $nkey = (string)$key;
            // add braces to key to conform to the psr-3 params specification
            if (false === \mb_strpos($nkey, '{') && false === \mb_strpos($nkey, '}'))
                $nkey = '{' . $nkey . '}';
            $replacements[$nkey] = $strval;
        }

        $messageFormatted = \strtr((string)$message, $replacements);
        $dtFormatted = $time->format('Y-m-d H:i:s');
        $levelFormatted = \str_pad((string)\mb_strtoupper((string)$level), 9, ' ', \STR_PAD_BOTH);
        \fwrite((0 > $vlvl ? \STDERR : \STDOUT), $dtFormatted . ' [' . $levelFormatted . '] ' . $messageFormatted . "\n");
    }

}
