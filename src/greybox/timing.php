<?php namespace timing {

    require_once __DIR__."/utils.php";
    require_once __DIR__."/debug.php";
    
    /** Map of names to running timers
     * [ string => [
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
     * Indicate that some work has started. Expect the logged times to differ from the measured duration
     * by a few microseconds, since `debug` calls `monotonic_time` a few lines later.
     * 
     * @param string $name must not contain spaces, should be unique. Non-unique names are matched via a
     * stack
     * @param string $category the logging category
     * @param string $desc description
     * @return - a function that stops the timer
     */
    function start($name, $category = null, $desc = null) {
        global $timers;
        if (key_exists($name, $timers)) throw new \Error("A timer called $name is already running");
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
        global $finished, $timers;
        if (!key_exists($name, $timers)) throw new \Error("No timer called $name is currently running");
        $record = $timers[$name];
        unset($timers[$name]);
        $duration = monotonic_time() - $record['start'];
        $record['name'] = $name;
        $record['duration'] = $duration;
        \debug\log("Ended timer $name after ${duration}ms", $record['category']);
        array_push($finished, $record);
        write_header();
    }

    function moment($name, $category = null, $desc = null) {
        global $finished;
        array_push($finished, [
            'name' => $name, 'category' => $category, 'description' => $desc,
            'start' => monotonic_time(), 'duration' => 0
        ]);
        \debug\log("Point reached $name: $desc");
        write_header();
    }

    /**
     * Write timings header, replacing any previous timings header
     * If you can, call this at the last point you have access to
     */
    function write_header() {
        global $finished, $start_ts;
        $write_time = monotonic_time();
        $enabled_records = array_filter($finished, function ($record) {
            return \debug\category_enabled($record['category']);
        });
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
        header("Server-Timing: $header_value");
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