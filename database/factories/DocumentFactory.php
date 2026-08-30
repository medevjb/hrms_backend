<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(3),
            'category' => DocumentCategory::Contract,
            'file_path' => 'employee-documents/test.pdf',
            'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12345,
        ];
    }
}
