<?php namespace debug {

    require_once __DIR__ . "/utils.php";
    require_once __DIR__ . "/Set.php";
    
    // Either a whitelist or blacklist of log categories depending on the cookie
    $selected_categories = new \Set();
    
    if (key_exists('debug_categories', $_COOKIE)) {
        // Parse header into logging configuration and throw if it is incorrect
        $debug_cookie = $_COOKIE["debug_categories"];
        try {
            [$debug_mode, $debug_groups] = explode(':', $debug_cookie, 2);
            foreach (explode(',', $debug_groups) as $group) {
                $selected_categories->add($group);
            }
            switch ($debug_mode) {
                case 'include':
                    $categories_included = true;
                    break;
                case 'skip':
                    $categories_included = false;
                    break;
                    default:
                    throw new \Error("Valid modes are 'include' and 'skip'");
            }
        } catch (\Throwable $ex) {
            $msg = <<<MSG
Invalid debug_headers cookie; the correct syntax is 'mode:cat1,cat2,cat3'
To show all debug headers, set the cookie to 'skip:'
Value of the cookie:
$debug_cookie
MSG;
            throw new \Error($msg, -1, $ex);
        }
    } else {
        // Default state should lead to nothing being logged
        $categories_included = true;
    }
            
    /**
     * Returns whether a given logging category is enabled
     */
    function category_enabled($category)
    {
        global $selected_categories, $categories_included;
        return $selected_categories->includes($category) == $categories_included;
    }
    
    /**
     * Enables or disables a given logging category
     */
    function set_category($category, $enabled)
    {
        global $selected_categories, $categories_included;
        if (category_enabled($category) == $enabled) return;
        if ($categories_included == $enabled) $selected_categories->add($category);
        else $selected_categories->remove($category);
    }
    
    /**
     * Logs a message in headers
     * @param string $message
     * @param ?string $category Can be enabled or disabled in the debug headers
     * @param ?string $list Will be shown separately in the browser console
     */
    function log($message, $category = null, $list = null)
    {
        global $start_ts;
        $msg_string = "";
        $header_name = "X-Debug";
        if ($category != null) {
            if (!category_enabled($category)) return;
            $msg_string .= "[$category] ";
        }
        if ($list != null) $header_name .= "-$list";
        $delta = monotonic_time() - $start_ts;
        $msg_string .= "T+" . format_bignum($delta, 0) . "ms ";
        $msg_string .= $message;
        header("$header_name: $msg_string", false);
    }
}