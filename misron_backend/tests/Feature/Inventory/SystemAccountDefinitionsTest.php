<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/** chart_of_accounts.type / sub_type are ENUMs and the app runs MySQL non-strict: an invalid value is silently stored as ''. */
class SystemAccountDefinitionsTest extends InventoryV2TestCase
{
    private function enumValues(string $column): array
    {
        $type = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', 'chart_of_accounts')->where('COLUMN_NAME', $column)->value('COLUMN_TYPE');
        preg_match_all("/'([^']*)'/", (string) $type, $m);

        return $m[1];
    }

    public function test_every_system_account_definition_uses_values_the_enums_accept_and_is_stored_unchanged()
    {
        $types = $this->enumValues('type');
        $subs = $this->enumValues('sub_type');
        foreach (SystemAccounts::definitions() as $key => [$name, $type, $sub, $code]) {
            $this->assertContains($type, $types, "{$key}: type '{$type}' is not an allowed type");
            $this->assertContains($sub, $subs, "{$key}: sub_type '{$sub}' is not allowed and would be silently blanked");
            $row = DB::table('chart_of_accounts')->where('id', SystemAccounts::id($key))->first();
            $this->assertSame([$type, $sub], [$row->type, $row->sub_type], "{$key}: the stored account differs from its definition");
        }
    }
}
