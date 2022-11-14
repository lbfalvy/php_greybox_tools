<?php

/**
 * Inserts underscores into bignum to make it more readable.
 * @param float $num
 */
function format_bignum($num, $precision = -1)
{
    $out = "";
    $abs = abs($num);
    $whole = strval(floor($abs));
    $frac = $abs - floor($abs);
    // Write positive places
    if (strlen($whole) <= 4) { $out = $whole; }
    else {
        for ($pos = strlen($whole) - 3; -2 <= $pos; $pos -= 3) {
            $start = max($pos, 0);
            $end = $pos + 3;
            $out = substr($whole, $start, $end - $start) . $out;
            if (0 < $pos) $out = "_" . $out;
        }
    }
    // Write negative places
    if ($frac != 0 && $precision != 0) {
        $out .= ".";
        $fracstr = substr(strval($frac), 2);
        if ($precision == -1) { $visible_digits = strlen($fracstr); }
        else { $visible_digits = min($precision, strlen($fracstr)); }
        for ($i = 0; $i < $visible_digits; $i += 3) {
            $out .= substr($fracstr, $i, 3);
            if ($visible_digits > $i + 3) $out .= "_";
        }
    }
    if ($num < 0) return "-" . $out;
    return $out;
}

/**
 * Returns the current time in milliseconds
 * 
 * Use this instead of hrtime or microtime
 * @return float
 */
function monotonic_time() {
    if (function_exists('hrtime')) {
        return hrtime(true) / 1000 / 1000;
    } else {
        return microtime(true) * 1000;
    }
}

/**
 * @var int monotonic_time at the start of script execution
 */
$start_ts = monotonic_time();
