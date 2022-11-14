<?php namespace timing {

    require_once __DIR__."/utils.php";
    require_once __DIR__."/debug.php";
    require_once __DIR__."/polyfills.php";

    /**
     * Indicates whether we're sharing the Server-Timing header with some other
     * author
     */
    $extend_header = false;
    /** Prevents changing to cooperative mode after the header's been overwritten */
    $header_written = false;
    
    /** Map of names to running timers
     * @var [ string => [
     *      'start' => int,
     *      'category' => ?string,
     *      'description' => ?string
     * ] ]
     */
    $timers = array();

    /** Log of finished timers
     * @var [
     *      'name' => string,
     *      'start' => int,
     *      'category' => string,
     *      'description' => string, 
     *      'duration' => int
     * ][]
     */
    $finished = array();

    /**
     * Indicate that some work has started. Expect the logged times to differ from the measured
     * duration by a few microseconds, since `debug` calls `monotonic_time` a few lines later.
     * 
     * @param string $name must not contain spaces, should be unique.
     * Non-unique names are matched via a stack
     * @param string $category the logging category
     * @param string $desc description
     * @return - a function that stops the timer
     */
    function start($name, $category = null, $desc = null) {
        global $timers;
        if (key_exists($name, $timers)) {
            \debug\log("ERROR: A timer called $name is already running");
            return function() {};
        }
        $timers[$name] = [
            'start' => monotonic_time(),
            'category' => $category,
            'description' => $desc
        ];
        \debug\log("Started timer $name", $category);
        return function() use($name) { end($name); };
    }

    /**
     * Indicate that some work has ended, and add it to the timing header
     * @param string $name must match a start_milestone call
     */
    function end($name) {
        global $finished, $timers, $extend_header;
        if (!key_exists($name, $timers)) {
            \debug\log("ERROR: No timer called $name is currently running");
            return;
        }
        $record = $timers[$name];
        unset($timers[$name]);
        $duration = monotonic_time() - $record['start'];
        $record['name'] = $name;
        $record['duration'] = $duration;
        $duration_str = format_bignum($duration, 0);
        \debug\log("Ended timer $name after ${duration_str}ms", $record['category']);
        array_push($finished, $record);
        if (!$extend_header) write_header();
    }

    /** Records a zero-length duration, a moment in time.
     * @param string $name must not contain spaces, should be unique.
     * Non-unique names are matched via a stack
     * @param string $category the logging category
     * @param string $desc description
     */
    function milestone($name, $category = null, $desc = null) {
        global $finished, $extend_header;
        array_push($finished, [
            'name' => $name, 'category' => $category, 'description' => $desc,
            'start' => monotonic_time(), 'duration' => 0
        ]);
        if ($desc != null) $desc_str = ": $desc";
        \debug\log("Milestone $name reached". $desc_str);
        if (!$extend_header) write_header();
    }

    /** End the (only) previous timer and start a new one. Fails if there is more than one timer
     * @param string $name must not contain spaces, should be unique.
     * Non-unique names are matched via a stack
     * @param string $category the logging category
     * @param string $desc description
     * @return - a function that stops the timer
     */
    function start_single($name, $category = null, $desc = null) {
        global $timers;
        $names = array_keys($timers);
        if (count($names) > 1) {
            \debug\log("ERROR: More than one timer when starting single $name");
            return;
        }
        if (count($names) == 1) end($names[0]);
        return start($name, $category, $desc);
    }

    /**
     * Write timings header, replacing any previous timings header
     * If you can, call this at the last point you have access to
     */
    function write_header() {
        global $finished, $start_ts, $extend_header, $header_written;
        $header_written = true;
        $write_time = monotonic_time();
        $enabled_records = array_filter($finished, function ($record) {
            return \debug\category_enabled($record['category']);
        });
        if (empty($enabled_records)) return;
        $record_strings = array_map(function ($record) use ($write_time) {
            global $cumulative;
            [
                'name' => $name, 'description' => $description,
                'duration' => $duration, 'start' => $start
            ] = $record;
            if ($cumulative) $displayed_duration = $write_time - $start - $duration;
            else $displayed_duration = $duration;
            if ($description == null) $desc_str = "";
            else $desc_str = "desc=\"$description\";";
            return "$name;" . $desc_str . "dur=$displayed_duration";
        }, $enabled_records);
        $total_time = $write_time - $start_ts;
        array_unshift($record_strings, "total;dur=$total_time");
        $header_value = join(", ", $record_strings);
        if ($extend_header) {
            $header = array_find(headers_list(), function($h) {
                return str_starts_with($h, "Server-Timing");
            });
            if ($header != null) {
                header("$header, $header_value");
                return;
            }
        }
        header("Server-Timing: $header_value");
    }

    /**
     * Call this function upon initialization if you have other systems writing to Server-Timing.
     * You must call `write_header` once manually at the end, it will close all timers and
     * append the content of the header to any existing value.
     */
    function extend_header() {
        global $extend_header, $header_written;
        if ($header_written) {
            \debug\log("ERROR: \\timing\\extend_header called after Server-Timing had been written");
            return;
        }
        $extend_header = true;
    }
    
    if (key_exists('timing_cumulative', $_COOKIE)) {
        switch ($_COOKIE['timing_cumulative']) {
            case 'true': $cumulative = true; break;
            case 'false': $cumulative = false; break;
            default: throw new \Error("Invalid timing_cumulative cookie, valid values are true and false");
        }
    } else {
        $cumulative = false;
    }

    $recvd_at = new \DateTimeImmutable();
    $recvd_h = $recvd_at->format('Y-m-d H:i:s +Z (T)');
    \debug\log("First timestamp: $start_ts; ISO time: $recvd_h");
}