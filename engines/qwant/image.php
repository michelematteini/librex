<?php
    function get_image_results($query, $page) 
    {
        global $config;

        $offset = $page * 5; // load 50 images per page
        
        $url = "https://api.qwant.com/v3/search/images?q=$query&t=images&count=50&locale=en_us&offset=$offset&device=desktop&tgp=3&safesearch=0";
        $response = request($url);
        $json = json_decode($response, true);
        $results = array();

        if ($json["status"] == "success")
        {
            $imgs = $json["data"]["result"]["items"];
            $imgCount = $json["data"]["result"]["total"];

            for ($i = 0; $i < $imgCount; $i++)
            {
                array_push($results, 
                    array (
                        "thumbnail" => htmlspecialchars($imgs[$i]["thumbnail"]),
                        "alt" => htmlspecialchars($imgs[$i]["title"]),
                        "url" => htmlspecialchars($imgs[$i]["media"]) // use "url" for the source site url
                    )
                );
            }
        }
        else if ($json["status"] == "error")
        {
            if ($json["data"]["error_code"] == 27)
                echo "<p>qwant blocked the request</p>";
        }

        return $results;
    }

    function print_image_results($results)
    {
        echo "<div class=\"image-result-container\">";

            foreach($results as $result)
            {
                $thumbnail = urlencode($result["thumbnail"]);
                $alt = $result["alt"];
                $url = $result["url"];

                echo "<a title=\"$alt\" href=\"$url\" target=\"_blank\">";
                echo "<img src=\"image_proxy.php?url=$thumbnail\">";
                echo "</a>";
            }

        echo "</div>";
    }
?>
