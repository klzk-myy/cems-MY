<?php

namespace Tests\Unit\Http\Traits;

use App\Http\Traits\ValidatorMethods;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidatorMethodsTest extends TestCase
{
    private TestableValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TestableValidator;
    }

    #[Test]
    public function validate_currency_code_accepts_valid_codes(): void
    {
        $this->validator->publicValidateCurrencyCode('USD');
        $this->validator->publicValidateCurrencyCode('EUR');
        $this->validator->publicValidateCurrencyCode('GBP');
        $this->validator->publicValidateCurrencyCode('JPY');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_currency_code_rejects_invalid_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid currency code: usd');
        $this->validator->publicValidateCurrencyCode('usd');
    }

    #[Test]
    public function validate_currency_code_rejects_too_short_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->publicValidateCurrencyCode('US');
    }

    #[Test]
    public function validate_currency_code_rejects_too_long_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->publicValidateCurrencyCode('USDD');
    }

    #[Test]
    public function validate_currency_code_rejects_numeric_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->publicValidateCurrencyCode('123');
    }

    #[Test]
    public function validate_ip_address_accepts_valid_ipv4(): void
    {
        $this->validator->publicValidateIpAddress('192.168.1.1');
        $this->validator->publicValidateIpAddress('10.0.0.1');
        $this->validator->publicValidateIpAddress('255.255.255.255');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_ip_address_accepts_valid_ipv6(): void
    {
        $this->validator->publicValidateIpAddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->validator->publicValidateIpAddress('::1');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_ip_address_accepts_null(): void
    {
        $this->validator->publicValidateIpAddress(null);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_ip_address_rejects_invalid_ip(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IP address: not.an.ip');
        $this->validator->publicValidateIpAddress('not.an.ip');
    }

    #[Test]
    public function validate_ip_address_rejects_non_ip_strings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->publicValidateIpAddress('localhost');
    }

    #[Test]
    public function validate_xml_accepts_valid_xml(): void
    {
        $xml = '<?xml version="1.0"?><root><item>value</item></root>';
        $this->assertTrue($this->validator->publicValidateXml($xml));
    }

    #[Test]
    public function validate_xml_rejects_invalid_xml(): void
    {
        $xml = '<?xml version="1.0"?><root><item>value</root>';
        $this->assertFalse($this->validator->publicValidateXml($xml));
    }

    #[Test]
    public function validate_xml_rejects_malformed_tags(): void
    {
        $xml = '<root><item>value</item></root>';
        $this->assertTrue($this->validator->publicValidateXml($xml));

        $malformed = '<root><item>value</root>';
        $this->assertFalse($this->validator->publicValidateXml($malformed));
    }

    #[Test]
    public function validate_json_accepts_valid_json(): void
    {
        $json = '{"key": "value", "number": 123}';
        $this->assertTrue($this->validator->publicValidateJson($json));
    }

    #[Test]
    public function validate_json_accepts_empty_array(): void
    {
        $json = '[]';
        $this->assertTrue($this->validator->publicValidateJson($json));
    }

    #[Test]
    public function validate_json_rejects_invalid_json(): void
    {
        $json = '{"key": "value",}';
        $this->assertFalse($this->validator->publicValidateJson($json));
    }

    #[Test]
    public function validate_json_rejects_non_json_strings(): void
    {
        $json = 'not json at all';
        $this->assertFalse($this->validator->publicValidateJson($json));
    }

    #[Test]
    public function validate_csv_accepts_valid_csv_with_commas(): void
    {
        $csv = "header1,header2,header3\nvalue1,value2,value3";
        $this->assertTrue($this->validator->publicValidateCsv($csv));
    }

    #[Test]
    public function validate_csv_accepts_valid_csv_with_tabs(): void
    {
        $csv = "header1\theader2\theader3\nvalue1\tvalue2\tvalue3";
        $this->assertTrue($this->validator->publicValidateCsv($csv));
    }

    #[Test]
    public function validate_csv_rejects_single_line(): void
    {
        $csv = 'single line without newline';
        $this->assertFalse($this->validator->publicValidateCsv($csv));
    }

    #[Test]
    public function validate_csv_rejects_empty_content(): void
    {
        $csv = '';
        $this->assertFalse($this->validator->publicValidateCsv($csv));
    }

    #[Test]
    public function validate_csv_rejects_no_separator(): void
    {
        $csv = "header without separator\nvalue without separator";
        $this->assertFalse($this->validator->publicValidateCsv($csv));
    }
}

class TestableValidator
{
    use ValidatorMethods;

    public function publicValidateCurrencyCode(string $currencyCode): void
    {
        $this->validateCurrencyCode($currencyCode);
    }

    public function publicValidateIpAddress(?string $ipAddress): void
    {
        $this->validateIpAddress($ipAddress);
    }

    public function publicValidateXml(string $content): bool
    {
        return $this->validateXml($content);
    }

    public function publicValidateJson(string $content): bool
    {
        return $this->validateJson($content);
    }

    public function publicValidateCsv(string $content): bool
    {
        return $this->validateCsv($content);
    }
}
