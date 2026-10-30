<?php

namespace Tests\Feature\Security;

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Funnel\FunnelTestCase;

/**
 * File uploads that were open to abuse: the web uploader accepted files from anyone,
 * any signed-in user could upload SVG/XML (scripts on the shop's address), and the
 * base64 image endpoints wrote to whatever path the client named.
 */
class UploadSafetyTest extends FunnelTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>';

    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->before = glob(public_path('uploads/all/*')) ?: [];
    }

    protected function tearDown(): void
    {
        // Files are written to disk outside the database transaction; remove the ones made here.
        foreach (array_diff(glob(public_path('uploads/all/*')) ?: [], $this->before) as $file) {
            @unlink($file);
        }
        @unlink(public_path('escaped-e2e.png'));
        parent::tearDown();
    }

    public function test_the_web_uploader_refuses_visitors_who_are_not_signed_in(): void
    {
        $count = Upload::count();
        $this->post('/aiz-uploader/upload', ['aiz_file' => UploadedFile::fake()->createWithContent('page.xml', '<x/>')])
            ->assertRedirect();
        $this->post('/aiz-uploader', [])->assertRedirect();

        $this->assertSame($count, Upload::count());
        $this->assertSame([], array_values(array_diff(glob(public_path('uploads/all/*')) ?: [], $this->before)));
    }

    public function test_svg_and_xml_are_accepted_from_staff_only(): void
    {
        $seller = $this->makeUser('seller');
        $this->actingAs($seller)->post('/aiz-uploader/upload', ['aiz_file' => UploadedFile::fake()->createWithContent('logo.svg', self::SVG)])->assertOk();
        $this->actingAs($seller)->post('/aiz-uploader/upload', ['aiz_file' => UploadedFile::fake()->createWithContent('feed.xml', '<x/>')])->assertOk();
        $this->assertSame(0, Upload::where('user_id', $seller->id)->count());

        // Ordinary images still work for sellers.
        $this->actingAs($seller)->post('/aiz-uploader/upload', ['aiz_file' => UploadedFile::fake()->createWithContent('photo.png', base64_decode(self::PNG))])->assertOk();
        $this->assertSame(1, Upload::where('user_id', $seller->id)->where('extension', 'png')->count());

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/aiz-uploader/upload', ['aiz_file' => UploadedFile::fake()->createWithContent('logo.svg', self::SVG)])->assertOk();
        $upload = Upload::where('user_id', $admin->id)->where('extension', 'svg')->firstOrFail();
        // The existing sanitiser still cleans what staff upload.
        $this->assertStringNotContainsString('<script', (string) file_get_contents(public_path($upload->file_name)));
    }

    public function test_the_image_api_ignores_the_given_name_as_a_path_and_reads_the_format_from_the_image(): void
    {
        $customer = $this->makeUser('customer');
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v2/file/image-upload', ['image' => self::PNG, 'filename' => '../../escaped-e2e.png'])
            ->assertOk()->assertJsonPath('result', true);

        $this->assertFileDoesNotExist(public_path('escaped-e2e.png'));
        $upload = Upload::findOrFail($response->json('upload_id'));
        $this->assertSame('png', $upload->extension);
        $this->assertMatchesRegularExpression('#^uploads/all/[0-9a-f]{16}\d{14}\.png$#', $upload->file_name);
        $this->assertFileExists(public_path($upload->file_name));
    }

    public function test_the_image_api_refuses_svg_and_non_images_and_writes_nothing(): void
    {
        $customer = $this->makeUser('customer');
        Sanctum::actingAs($customer);

        $this->postJson('/api/v2/file/image-upload', ['image' => base64_encode(self::SVG), 'filename' => 'x.svg'])->assertJsonPath('result', false);
        $this->postJson('/api/v2/file/image-upload', ['image' => base64_encode('<?php echo 1;'), 'filename' => 'shell.php'])->assertJsonPath('result', false);
        $this->postJson('/api/v2/profile/image-upload', ['image' => base64_encode('<?php echo 1;'), 'filename' => 'a.png'])->assertJsonPath('result', false);

        $this->assertSame(0, Upload::where('user_id', $customer->id)->count());
        $this->assertSame([], array_values(array_diff(glob(public_path('uploads/all/*')) ?: [], $this->before)));
    }

    public function test_the_storefront_profile_picture_upload_still_works(): void
    {
        $customer = $this->makeUser('customer');
        Sanctum::actingAs($customer);

        // The storefront always names the file profile_<time>.jpg, whatever its real format.
        $this->postJson('/api/v2/profile/update-image', ['image' => self::PNG, 'filename' => 'profile_1730000000000.jpg'])
            ->assertOk()->assertJsonPath('result', true);

        $upload = Upload::findOrFail($customer->fresh()->avatar_original);
        $this->assertSame($customer->id, $upload->user_id);
        $this->assertSame('png', $upload->extension);
        $this->assertSame('profile_1730000000000', $upload->file_original_name);
    }
}
