<?php

require $argv[1].'/vendor/autoload.php';
use Carbon\Carbon;

$inputs = ['2020-05-22', '2020-05-22 13:45', '2020-05-22 13:45:10', '2020-05-22T10:00:00+02:00', '1700000000', '20200522', 'invalid', '', null, 0, 1700000000, Carbon::parse('2020-05-22 13:45', 'Europe/Athens'), new DateTime('2020-05-22 13:45', new DateTimeZone('America/New_York'))];
foreach (['UTC', 'Europe/Athens'] as $tz) {
    date_default_timezone_set($tz);
    echo "=== date_default_timezone={$tz} ===\n";
    foreach ($inputs as $v) {
        $label = is_object($v) ? get_class($v).'('.$v->format('c').')' : var_export($v, true);
        try {
            $c = Carbon::parse($v)->timestamp;
        } catch (Throwable $e) {
            $c = 'THROW '.get_class($e);
        }
        $s = ($v instanceof DateTimeInterface) ? $v->getTimestamp() : (is_string($v) ? strtotime($v) : 'n/a');
        $safe = ($v instanceof DateTimeInterface) ? $v->getTimestamp() : (function () use ($v) {
            try {
                return Carbon::parse($v)->timestamp;
            } catch (Throwable $e) {
                return 'THROW';
            }
        })();
        printf("%-60s Carbon::parse=%-22s strtotime=%-12s DateTimeInterface-shortcut=%-12s %s\n", $label, var_export($c, true), var_export($s, true), var_export($safe, true), ($c === $s ? 'same' : 'DIFF'));
    }
}
