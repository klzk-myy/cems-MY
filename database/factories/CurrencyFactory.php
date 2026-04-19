<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    protected static $usedCodes = [];

    protected static $currencyDetails = [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'decimal_places' => 2],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'decimal_places' => 2],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'decimal_places' => 2],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'decimal_places' => 2],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimal_places' => 2],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'decimal_places' => 0],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimal_places' => 0],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'decimal_places' => 2],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2],
    ];

    public function definition(): array
    {
        // Get next available code
        $code = $this->getNextAvailableCode();

        // Get details for this code (or use defaults for random codes)
        $details = static::$currencyDetails[$code] ?? [
            'name' => fake()->word().' Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ];

        return [
            'code' => $code,
            'name' => $details['name'],
            'symbol' => $details['symbol'],
            'decimal_places' => $details['decimal_places'],
            'is_active' => true,
        ];
    }

    /**
     * Create a model and persist it to the database.
     *
     * @param  array  $attributes
     * @return Model
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        // If code is explicitly set, check if it already exists
        if (isset($attributes['code'])) {
            $existing = Currency::where('code', $attributes['code'])->first();
            if ($existing) {
                // Return existing currency
                return $existing;
            }
        }

        return parent::create($attributes, $parent);
    }

    protected function getNextAvailableCode(): string
    {
        // Sync usedCodes with what's actually in the database
        $existingCodes = Currency::pluck('code')->toArray();
        static::$usedCodes = array_unique(array_merge(static::$usedCodes, $existingCodes));

        $codes = array_keys(static::$currencyDetails);

        // Find first available currency
        foreach ($codes as $code) {
            if (! in_array($code, static::$usedCodes)) {
                static::$usedCodes[] = $code;

                return $code;
            }
        }

        // If all currencies are used, generate a unique random one
        $code = 'CUR'.fake()->unique()->numberBetween(100, 999);
        static::$usedCodes[] = $code;

        return $code;
    }

    public static function resetCounter(): void
    {
        static::$usedCodes = [];
    }
}
