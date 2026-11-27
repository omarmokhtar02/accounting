<?php

namespace Modules\Shared\Domain;

use Modules\Shared\Domain\Exceptions\CurrencyMismatch;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;

/**
 * An amount in integer minor units of one currency. Immutable. No floating point anywhere.
 */
final readonly class Money
{
    public function __construct(public int $minor, public Currency $currency) {}

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Parses "1,234.56" / "10" / "-0.5" strictly: more decimals than the currency allows is an error,
     * never silently rounded.
     */
    public static function fromDecimal(string $amount, Currency $currency): self
    {
        $clean = trim(str_replace(',', '', $amount));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $clean, $m)) {
            throw InvalidMoneyAmount::notANumber($amount);
        }

        $fraction = $m[3] ?? '';

        if (strlen($fraction) > $currency->decimals) {
            throw InvalidMoneyAmount::tooManyDecimals($amount, $currency->code, $currency->decimals);
        }

        $minor = (int) ($m[2].str_pad($fraction, $currency->decimals, '0'));

        return new self($m[1] === '-' ? -$minor : $minor, $currency);
    }

    /** Plain decimal string with exactly the currency's decimals, e.g. "1234.560". */
    public function toDecimal(): string
    {
        $abs = abs($this->minor);
        $digits = str_pad((string) $abs, $this->currency->decimals + 1, '0', STR_PAD_LEFT);
        $whole = $this->currency->decimals === 0 ? $digits : substr($digits, 0, -$this->currency->decimals);
        $fraction = $this->currency->decimals === 0 ? '' : '.'.substr($digits, -$this->currency->decimals);

        return ($this->minor < 0 ? '-' : '').$whole.$fraction;
    }

    /** Display string with thousands separators, e.g. "1,234.560". */
    public function format(): string
    {
        $plain = $this->toDecimal();
        $negative = str_starts_with($plain, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($plain, '-')), 2, null);

        return ($negative ? '-' : '').number_format((int) $whole).($fraction === null ? '' : '.'.$fraction);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency) && $this->minor === $other->minor;
    }

    /** Returns -1, 0 or 1. */
    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    /** Converts to $rate->to using half-away-from-zero rounding to the target's minor unit. */
    public function convert(ExchangeRate $rate): self
    {
        if (! $this->currency->equals($rate->from)) {
            throw CurrencyMismatch::between($this->currency->code, $rate->from->code);
        }

        // minor_from / 10^dec_from * rate * 10^dec_to
        $value = bcmul(bcmul((string) $this->minor, $rate->rate, Rounding::SCALE), $rate->to->factor(), Rounding::SCALE);
        $value = bcdiv($value, $rate->from->factor(), Rounding::SCALE);

        return new self(Rounding::toInteger($value), $rate->to);
    }

    /**
     * Splits the amount proportionally so the parts always add up to the original
     * (largest remainder method, ties go to the earlier part).
     *
     * @param  list<int>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);

        if ($ratios === [] || $total <= 0) {
            throw InvalidMoneyAmount::emptyAllocation();
        }

        $sign = $this->minor < 0 ? -1 : 1;
        $abs = abs($this->minor);
        $parts = [];
        $remainders = [];

        foreach ($ratios as $i => $ratio) {
            $share = intdiv($abs * $ratio, $total);
            $parts[$i] = $share;
            $remainders[$i] = ($abs * $ratio) % $total;
        }

        $left = $abs - array_sum($parts);
        arsort($remainders);

        foreach (array_keys($remainders) as $i) {
            if ($left <= 0) {
                break;
            }
            $parts[$i]++;
            $left--;
        }

        ksort($parts);

        return array_map(fn (int $minor) => new self($sign * $minor, $this->currency), array_values($parts));
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency->code, $other->currency->code);
        }
    }
}
