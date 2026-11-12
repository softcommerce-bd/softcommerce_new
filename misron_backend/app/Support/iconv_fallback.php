<?php
// Shared hosts sometimes ship PHP without the iconv extension, which the QR-code library (invoices) calls.
// Loaded only when iconv is missing; uses mbstring instead.
if (!function_exists('iconv') && function_exists('mb_convert_encoding')) {
    function iconv($from, $to, $string)
    {
        $strip = static fn ($e) => preg_replace('~//.*$~', '', $e);
        $r = @mb_convert_encoding($string, $strip($to), $strip($from));
        return $r === false ? false : $r;
    }
}
