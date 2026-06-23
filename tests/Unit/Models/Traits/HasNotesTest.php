<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasNotes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes_owners', function (Blueprint $table) {
            $table->id();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    #[Test]
    public function it_adds_notes_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use HasNotes;

            protected $table = 'notes_owners';
        };

        $this->assertContains('notes', $model->getFillable());
    }
}
