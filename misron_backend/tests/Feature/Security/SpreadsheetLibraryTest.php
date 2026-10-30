<?php

namespace Tests\Feature\Security;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Smoke check after the PhpSpreadsheet security update (1.30.7) that the admin and
 * seller bulk uploads rely on: an uploaded .xlsx is read with its heading row, and
 * the library still writes spreadsheets.
 */
class SpreadsheetLibraryTest extends TestCase
{
    /**
     * In its own process: the xlsx writer reads in 16 MB chunks, which does not fit in what
     * is left of the memory limit after the rest of the suite, though it does in a request.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_an_uploaded_xlsx_is_read_and_a_spreadsheet_can_be_written(): void
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([['name', 'unit_price', 'current_stock'], ['Bulk Tee', 450, 12]]);
        $path = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        $rows = Excel::toArray(new class {
        }, new UploadedFile($path, 'products.xlsx', null, null, true));

        $this->assertSame([['name', 'unit_price', 'current_stock'], ['Bulk Tee', 450, 12]], $rows[0]);
        $this->assertGreaterThan(1000, filesize($path));
        @unlink($path);
    }
}
