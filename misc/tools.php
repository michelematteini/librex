<?php
    function get_base_url($url)
    {
        $split_url = explode("/", $url);
        $base_url = $split_url[0] . "//" . $split_url[2] . "/";
        return $base_url;
    }

    function get_root_domain($url) {
        return parse_url($url, PHP_URL_HOST);
    }

    function try_replace_with_frontend($url, $frontend, $original)
    {
        global $config;
        $frontends = $config->frontends;

        if (isset($_COOKIE[$frontend]) || !empty($frontends[$frontend]["instance_url"]))
        {
            
            if (isset($_COOKIE[$frontend]))
                $frontend = $_COOKIE[$frontend];
            else if (!empty($frontends[$frontend]["instance_url"]))
                $frontend = $frontends[$frontend]["instance_url"];

            if (empty(trim($frontend)))
                return $url;

            if (strpos($url, "wikipedia.org") !== false)
            {
                $wiki_split = explode(".", $url);
                if (count($wiki_split) > 1)
                {
                    $lang = explode("://", $wiki_split[0])[1];
                    $url =  $frontend . explode($original, $url)[1] . (strpos($url, "?") !== false ? "&" : "?")  . "lang=" . $lang;
                }
            }
            else if (strpos($url, "fandom.com") !== false)
            {
                $fandom_split = explode(".", $url);
                if (count($fandom_split) > 1)
                {
                    $wiki_name = explode("://", $fandom_split[0])[1];
                    $url =  $frontend . "/" . $wiki_name . explode($original, $url)[1];
                }
            }
            else if (strpos($url, "gist.github.com") !== false)
            {
                $gist_path = explode("gist.github.com", $url)[1];
                $url = $frontend . "/gist" . $gist_path;
            }
            else if (strpos($url, "stackexchange.com") !== false)
            {
                $se_domain = explode(".", explode("://", $url)[1])[0];
                $se_path = explode("stackexchange.com", $url)[1];
                $url = $frontend . "/exchange" . "/" . $se_domain . $se_path;
            }
            else
            {
                $url =  $frontend . explode($original, $url)[1];
            }


            return $url;
        }

        return $url;
    }

    function check_for_privacy_frontend($url)
    {

        global $config;

        if (isset($_COOKIE["disable_frontends"]))
            return $url;

        foreach($config->frontends as $frontend => $data)
        {
            $original = $data["original_url"];

            if (strpos($url, $original))
            {
                $url = try_replace_with_frontend($url, $frontend, $original);
                break;
            }
            else if (strpos($url, "stackexchange.com"))
            {
                $url = try_replace_with_frontend($url, "anonymousoverflow", "stackexchange.com");
                break;
            }
        }

        return $url;
    }

    function check_ddg_bang($query)
    {

        $bangs_json = file_get_contents("static/misc/ddg_bang.json");
        $bangs = json_decode($bangs_json, true);

        if (substr($query, 0, 1) == "!")
            $search_word = substr(explode(" ", $query)[0], 1);
        else
            $search_word = substr(end(explode(" ", $query)), 1);
        
        $bang_url = null;

        foreach($bangs as $bang)
        {
            if ($bang["t"] == $search_word)
            {
                $bang_url = $bang["u"];
                break;
            }
        }

        if ($bang_url)
        {
            $bang_query_array = explode("!" . $search_word, $query);
            $bang_query = trim(implode("", $bang_query_array));

            $request_url = str_replace("{{{s}}}", str_replace('%26quot%3B','%22', urlencode($bang_query)), $bang_url);
            $request_url = check_for_privacy_frontend($request_url);

            header("Location: " . $request_url);
            die();
        }
    }

    function check_for_special_search($query)
    {
        if (isset($_COOKIE["disable_special"]))
            return 0;

         $query_lower = strtolower($query);
         $split_query = explode(" ", $query);

         if (strpos($query_lower, "to") && count($split_query) >= 4) // currency
         {
            $amount_to_convert = floatval($split_query[0]);
            if ($amount_to_convert != 0)
                return 1;
         }
         else if (strpos($query_lower, "mean") && count($split_query) >= 2) // definition
         {
             return 2;
         }
         else if (strpos($query_lower, "my") !== false)
         {
            if (strpos($query_lower, "ip"))
            {
                return 3;
            }
            else if (strpos($query_lower, "user agent") || strpos($query_lower, "ua"))
            {
                return 4;
            }
         }
         else if (strpos($query_lower, "weather") !== false)
         {
                return 5;
         }
         else if ($query_lower == "tor")
         {
                return 6;
         }
         else if (3 > count(explode(" ", $query))) // wikipedia
         {
             return 7;
         }

        return 0;
    }

    function get_xpath($response)
    {
        $htmlDom = new DOMDocument;
        @$htmlDom->loadHTML($response);
        $xpath = new DOMXPath($htmlDom);

        return $xpath;
    }

    function request($url)
    {
        global $config;

        $ch = curl_init($url);
        curl_setopt_array($ch, $config->curl_settings);
        $response = curl_exec($ch);

        return $response;
    }

    function human_filesize($bytes, $dec = 2)
    {
        $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    function remove_special($string)
    {
        $string = preg_replace("/[\r\n]+/", "\n", $string);
        return trim(preg_replace("/\s+/", ' ', $string));
     }

    function print_elapsed_time($start_time)
        {
            $end_time = number_format(microtime(true) - $start_time, 2, '.', '');
            echo "<p id=\"time\">Fetched the results in $end_time seconds</p>";
        }

    function print_next_page_button($text, $page, $query, $type)
    {
        echo "<form class=\"page\" action=\"search.php\" target=\"_top\" method=\"get\" autocomplete=\"off\">";
        echo "<input type=\"hidden\" name=\"p\" value=\"" . $page . "\" />";
        echo "<input type=\"hidden\" name=\"q\" value=\"$query\" />";
        echo "<input type=\"hidden\" name=\"t\" value=\"$type\" />";
        echo "<button type=\"submit\">$text</button>";
        echo "</form>";
    }

    // wraps a curl_multi_exec() in a more rubust loop to both avoid wasting cycles and offering a timeout
    // see comments under https://www.php.net/manual/en/function.curl-multi-select.php
    function curl_multi_exec_to_completion($multihandle, $timeout_seconds)
    {
        $start_time = microtime(true);
        $max_end_time = $start_time + $timeout_seconds;
        $active = null;

        // execute first curl_multi_exec loop
        do 
        {
            $mrc = curl_multi_exec($multihandle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    
        // loop until all requrests are completed, or a timeout is reached
        while ($active && $mrc == CURLM_OK && microtime(true) < $max_end_time) 
        {
            // wait for activity on any curl-connection with a small timeout
            if (curl_multi_select($multihandle, 0.05) == -1) {
                usleep(50000);
            }
    
            // process handles
            do 
            {
                $mrc = curl_multi_exec($multihandle, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

?>
