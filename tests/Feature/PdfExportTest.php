<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ekspor PDF memakai dompdf; upgrade mayor bisa mematahkannya tanpa gagal test lain.
 */
class PdfExportTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function laporan_bisa_diekspor_menjadi_pdf(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'pdf-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );

        $response = $this->actingAs($admin)
            ->post(route('laporan.cetak'), [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        $body = $response->baseResponse->getContent();

        $this->assertStringStartsWith('%PDF-', $body, 'Respons harus berupa PDF valid.');
        $this->assertGreaterThan(1000, strlen($body));
    }
}