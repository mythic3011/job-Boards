<?php

namespace Tests\Unit\Services;

use App\Services\CvFileService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CvFileServiceFilenameTest extends TestCase
{
    public function test_filenames_with_multiple_benign_dots_are_accepted(): void
    {
        $file = UploadedFile::fake()->create('john.doe.resume.pdf', 10, 'application/pdf');

        $result = app(CvFileService::class)->validateFile($file);

        $this->assertTrue($result['valid'], $result['error'] ?? '');
    }

    public function test_dangerous_penultimate_extension_is_rejected_even_when_final_extension_is_allowed(): void
    {
        $file = UploadedFile::fake()->create('cv.php.pdf', 10, 'application/pdf');

        $result = app(CvFileService::class)->validateFile($file);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid filename. Double extensions are not allowed.', $result['error']);
    }
}
