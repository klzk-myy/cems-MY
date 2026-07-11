<?php

namespace Tests\Unit\Helpers;

use App\Helpers\LabelHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

enum LabelHelperStatusTestEnum
{
    case Active;
}

enum LabelHelperTypeTestEnum
{
    case Buy;
}

class LabelHelperTest extends TestCase
{
    #[Test]
    public function it_returns_default_for_null_value(): void
    {
        $this->assertSame('Unknown', LabelHelper::getStatusLabel(null));
        $this->assertSame('Unknown', LabelHelper::getTypeLabel(null));
    }

    #[Test]
    public function it_returns_custom_default_when_provided(): void
    {
        $this->assertSame('N/A', LabelHelper::getStatusLabel(null, 'N/A'));
        $this->assertSame('N/A', LabelHelper::getTypeLabel(null, 'N/A'));
    }

    #[Test]
    public function it_returns_string_value_as_label(): void
    {
        $this->assertSame('Pending', LabelHelper::getStatusLabel('Pending'));
        $this->assertSame('Transfer', LabelHelper::getTypeLabel('Transfer'));
    }

    #[Test]
    public function it_prefers_status_or_type_label_methods(): void
    {
        $status = new class
        {
            public function getStatusLabel(): string
            {
                return 'Status Label';
            }
        };

        $type = new class
        {
            public function getTypeLabel(): string
            {
                return 'Type Label';
            }
        };

        $this->assertSame('Status Label', LabelHelper::getStatusLabel($status));
        $this->assertSame('Type Label', LabelHelper::getTypeLabel($type));
    }

    #[Test]
    public function it_falls_back_to_label_method(): void
    {
        $object = new class
        {
            public function label(): string
            {
                return 'Fallback Label';
            }
        };

        $this->assertSame('Fallback Label', LabelHelper::getStatusLabel($object));
        $this->assertSame('Fallback Label', LabelHelper::getTypeLabel($object));
    }

    #[Test]
    public function it_returns_enum_name_as_label(): void
    {
        $this->assertSame('Active', LabelHelper::getStatusLabel(LabelHelperStatusTestEnum::Active));
        $this->assertSame('Buy', LabelHelper::getTypeLabel(LabelHelperTypeTestEnum::Buy));
    }

    #[Test]
    public function it_returns_default_for_non_string_scalar(): void
    {
        $this->assertSame('Unknown', LabelHelper::getStatusLabel(123));
        $this->assertSame('Unknown', LabelHelper::getTypeLabel(123));
    }

    #[Test]
    public function it_returns_default_for_object_without_to_string(): void
    {
        $object = new class {};

        $this->assertSame('Unknown', LabelHelper::getStatusLabel($object));
        $this->assertSame('Unknown', LabelHelper::getTypeLabel($object));
    }
}
