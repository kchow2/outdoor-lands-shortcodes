<?php

/*
  Plugin Name: Outdoor Land Shortcodes
  Plugin URI:  http://www.google.ca
  Description: This plugin implements all the custom shortcode functionality for the 'Outdoor Lands' website. Usage: [popular-loc parent="continent" parentterm="north-america" target="country" max="50"]
  Version:     0.1
  Author:      Kevin Chow
  Author URI:  http://www.google.ca
 */
defined('ABSPATH') or die('No script kiddies please!');

//popular-loc shortcode.
//Usage: [popular-loc parent="continent" parentterm="north-america" target="country" max="50"]
function popular_loc_sc($atts) {
    $pluralsLookup = array(
        'destination' => 'Destinations',
        'subregion' => 'Sub-regions',
        'sub-region' => 'Sub-regions',
        'region' => 'Regions',
        'city' => 'Cities',
        'country' => 'Countries',
        'continent' => 'Continents',
        'state' => 'States',
    );
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'popular-loc: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['target']) || !array_key_exists($atts['target'], $taxonomyLookup))
        return 'popular-loc: target attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'popular-loc: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular " . $pluralsLookup[$atts['target']];

    $title = $atts['title'];
    $parentTaxSlug = $taxonomyLookup[$atts['parent']];
    $parentTermSlug = $atts['parentterm'];
    $targetPostType = $atts['target'];
    $targetTaxSlug = $taxonomyLookup[$atts['target']];
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'post_type' => $targetPostType,
        'posts_per_page' => -1, //can't limit the query here, since some locations returned will have 0 activities and won't be displayed.
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => $parentTaxSlug,
                'field' => 'slug',
                'terms' => array($parentTermSlug),
            ),
        )
    );

    $query = new WP_Query($queryArgs);
    $locationCount = $query->found_posts;
    $totalActivityCount = 0;
    $locationsDisplayed = 0;
    $locationsHidden = 0;
    $resultData = array();
    if ($query->have_posts()) {
        while ($query->have_posts() and $locationsDisplayed < $maxPostCount) {
            $query->the_post();
            $postTitle = the_title("", "", false);
            $postUrl = get_permalink();
            $postSlug = basename(get_permalink());

            //for each location do another query to find the number of 'activities' for that location
            $activityQueryArgs = array(
                'post_type' => 'activity',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => $targetTaxSlug,
                        'field' => 'slug',
                        'terms' => array($postSlug),
                    ),
                )
            );
            $activityQuery = new WP_Query($activityQueryArgs);
            $activityCount = $activityQuery->found_posts;   //only interested in the number of results, not the actual activities
            $totalActivityCount += $activityCount;

            if ($activityCount > 0) {
                $resultData[] = array('url' => $postUrl, 'title' => $postTitle, 'activity_count' => $activityCount);
                $locationsDisplayed++;
            } else {
                $locationsHidden++;
            }
        }
    }

    //Generate the output
    $res = popular_loc_format_result($title, $totalActivityCount, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Locations found: $locationCount<br>";
    //$res .= "Locations displayed: $locationsDisplayed<br>";
    //$res .= "Locations hidden: $locationsHidden<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-loc', 'popular_loc_sc');

//helper function to format the HTML output from the found results
function popular_loc_format_result($title, $totalActivityCount, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
    foreach ($data as $location) {
        $locUrl = $location['url'];
        $locTitle = $location['title'];
        $activityCount = $location['activity_count'];

        $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
        $res .= "<a href=\"$locUrl\">$locTitle</a>";
        $res .= ' ( ' . $activityCount . ' ) ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    return $res;
}

//popular-activities shortcode. Displays a list of activity categories for a location, with the article count for each category.
//Usage: [popular-activities parent="continent" parentterm="north-america" max="50"]
function popular_activities_sc($atts) {
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state'
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'popular-activities: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'popular-activities: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular Activities";

    $title = $atts['title'];
    $parentTaxSlug = $taxonomyLookup[$atts['parent']];
    $targetPostType = 'activity-category';
    $parentTermSlug = $atts['parentterm'];
    $targetTaxSlug = 'tr-activity-category';
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'post_type' => $targetPostType,
        'posts_per_page' => -1, //return all the categories here, and filter them later...
        'orderby' => 'title',
        'order' => 'ASC',
    );

    $query = new WP_Query($queryArgs);
    $categoryCount = $query->found_posts;
    $totalActivityCount = 0;
    $categoriesDisplayed = 0;
    $categoriesHidden = 0;
    $resultData = array();
    if ($query->have_posts()) {
        while ($query->have_posts() and $categoriesDisplayed < $maxPostCount) {
            $query->the_post();
            $postTitle = the_title("", "", false);
            $postUrl = get_permalink();
            $postSlug = basename(get_permalink());

            //for each category do another query to find the number of 'activities' for that category with the same location
            $activityQueryArgs = array(
                'post_type' => 'activity',
                'posts_per_page' => -1,
                'tax_query' => array(
                    'relationship' => 'AND',
                    array(
                        'taxonomy' => $targetTaxSlug,
                        'field' => 'slug',
                        'terms' => array($postSlug),
                    ),
                    array(
                        'taxonomy' => $parentTaxSlug,
                        'field' => 'slug',
                        'terms' => array($parentTermSlug),
                    ),
                )
            );
            $activityQuery = new WP_Query($activityQueryArgs);
            $activityCount = $activityQuery->found_posts;   //only interested in the number of results, not the actual activities
            $totalActivityCount += $activityCount;

            if ($activityCount > 0) {
                $resultData[] = array('url' => $postUrl, 'title' => $postTitle, 'activity_count' => $activityCount);
                $categoriesDisplayed++;
            } else {
                $categoriesHidden++;
            }
        }
    }

    //Generate the output
    $res = popular_activities_format_result($title, $totalActivityCount, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "targetTaxSlug=$targetTaxSlug<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Locations found: $categoryCount<br>";
    //$res .= "Locations displayed: $categoriesDisplayed<br>";
    //$res .= "Locations hidden: $categoriesHidden<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-activities', 'popular_activities_sc');

//helper function to format the HTML output from the found results
function popular_activities_format_result($title, $totalActivityCount, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations tr-pop-destinations" style="overflow:auto;">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul style="padding-left:0px; overflow:hidden;">';
    foreach ($data as $category) {
        $url = $category['url'];
        $title = $category['title'];
        $activityCount = $category['activity_count'];

        $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
        $res .= "<a href=\"$url\">$title</a>";
        $res .= ' ( ' . $activityCount . ' ) ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    $res .= '<div style="clear:both;"></div>';
    return $res;
}

//top-contributors shortcode. Displays a list of top contributors for a location or activity category.
//Usage: [top-contributors parent="continent" parentterm="north-america" max="50"]
//Usage: [top-contributors parent="activity-category" parentterm="cycling" max="50"]
function top_contributors_sc($atts) {
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
        'activity-category' => 'tr-activity-category'
    );

    if (!isset($atts['parent']) || !array_key_exists($atts['parent'], $taxonomyLookup))
        return 'top-contributors: parent attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['parentterm']))
        return 'top-contributors: parentterm attribute not set!';
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Top Contributors";

    $title = $atts['title'];
    $targetTaxonomy = $atts['parent'];
    $targetTerm = $atts['parentterm'];
    $targetPostTypes = array('activity', 'guide', 'gear-review');
    $maxPostCount = $atts['max'];

    $queryArgs = array(
        'posts_per_page' => -1, //return all the categories here, and filter them later...
        'orderby' => 'name',
        'order' => 'ASC',
    );

    $query = new WP_User_Query($queryArgs);
    $userCount = $query->get_total();
    $userQueryResults = $query->get_results();

    $usersDisplayed = 0;
    $usersHidden = 0;
    $resultData = array();
    foreach ($userQueryResults as $user) {
        $authorUrl = $user->user_url;
        $userName = $user->nickname;
        $avatar = get_user_meta($user->ID, 'wpcf-tr-user-profile-image', true);
        $postCount = 0;
        foreach ($targetPostTypes as $postType) {
            //get all posts of each type from this author for this location
            $queryArgs = array(
                'author' => $user->ID,
                'post_type' => $postType,
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomyLookup[$targetTaxonomy],
                        'field' => 'slug',
                        'terms' => $targetTerm,
                    ),
                ),
            );
            $query = new WP_Query($queryArgs);
            $postCount += $query->found_posts;
            //$posts=$query->get_posts();
            //foreach($posts as $p){
            //    echo $p->post_title." (".$user->nickname.')<br>';
            //}  
        }

        if ($postCount > 0) {
            $resultData[] = array('url' => $authorUrl, 'name' => $userName, 'post_count' => $postCount, 'avatar' => $avatar);
            $usersDisplayed++;
        } else {
            $usersHidden++;
        }

        if ($usersDisplayed == $maxPostCount)
            break;
    }
    //Generate the output
    $res = top_contributors_format_result($title, $resultData);
    //$res .= "parentTaxSlug=$parentTaxSlug<br>";
    //$res .= "parentTermSlug=$parentTermSlug<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Users found: $userCount<br>";
    //$res .= "Users displayed: $usersDisplayed<br>";
    //$res .= "Users hidden: $usersHidden<br>";
    wp_reset_postdata();
    return $res;
}
add_shortcode('top-contributors', 'top_contributors_sc');

//helper function to format the HTML output from the found results
function top_contributors_format_result($title, $data) {
    if (empty($data))
        return '';

    $itemsPerRow = 4;
    $numRows = ceil(count($data) / $itemsPerRow);

    $res .= '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    for ($row = 0; $row < $numRows; $row++) {
        $res .= '<div class="row">';
        for ($i = 0; $i < $itemsPerRow; $i++) {

            $dataIndex = $row * $itemsPerRow + $i;
            if ($dataIndex >= count($data))
                break;

            $url = $data[$dataIndex]['url'];
            $name = $data[$dataIndex]['name'];
            $postCount = $data[$dataIndex]['post_count'];
            $avatar = $data[$dataIndex]['avatar'];
            if(!$avatar) 
                $avatar = 'http://test4.thenextturn.com/wp-content/uploads/2015/05/almeria_92658m1.jpg';

            $res .= '<div class="col-sm-3" height="100px" style="padding-left:15px; padding-right:5px;">';
            //$res .= '<div style="margin-right:-10px; margin-left:-10px;">';
            $res .= '<a href="' . $url . '" class="tr-users-column">';
            $res .= "<image alt=\"$name\" title=\"$name\" src=\"$avatar\" max-height=\"85px\">" . '<br>';
            $res .= "<h3>$name ($postCount)</h3>";
            $res .= '</a>';
            //$res .= '</div>';
            $res .= '</div>';
        }
        $res .= '</div>';//<div class="row">
    }
    $res .= '</div>';//<div class="tr-pop-locations tr-pop-destinations">
    return $res;
}

//popular-cat-loc shortcode.
//Usage: [popular-loc category="cycling" target="country" max="50"]
function popular_cat_loc_sc($atts) {
    $pluralsLookup = array(
        'destination' => 'Destinations',
        'subregion' => 'Sub-regions',
        'sub-region' => 'Sub-regions',
        'region' => 'Regions',
        'city' => 'Cities',
        'country' => 'Countries',
        'continent' => 'Continents',
        'state' => 'States',
    );
    $taxonomyLookup = array(
        'destination' => 'tr-destination',
        'subregion' => 'tr-subregion',
        'sub-region' => 'tr-subregion',
        'region' => 'tr-region',
        'city' => 'tr-city',
        'country' => 'tr-country',
        'continent' => 'tr-continent',
        'state' => 'tr-state',
    );

    if (!isset($atts['target']) || !array_key_exists($atts['target'], $taxonomyLookup))
        return 'popular-cat-loc: target attribute not valid! Must be one of {' . implode(", ", array_keys($taxonomyLookup)) . "}.";
    if (!isset($atts['max']))
        $atts['max'] = 15;
    if (!isset($atts['title']))
        $atts['title'] = "Popular " . $pluralsLookup[$atts['target']];

    $title = $atts['title'];
    $categoryTermSlug = $atts['category'];
    //$parentTaxSlug = $taxonomyLookup[$atts['parent']];
    //$parentTermSlug = $atts['parentterm'];
    $targetPostType = $atts['target'];
    $targetTaxSlug = $taxonomyLookup[$atts['target']];
    $maxPostCount = $atts['max'];

    //get all the activities for this category
    $queryArgs = array(
        'post_type' => 'activity',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'tr-activity-category',
                'field' => 'slug',
                'terms' => array($categoryTermSlug),
            ),
        )
    );
    $query = new WP_Query($queryArgs);
    $totalActivityCount = $query->found_posts;
    $locationArticleCount = array();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $locationTerms = wp_get_post_terms(get_the_ID(), $targetTaxSlug);
            //echo $locationTerms;
            foreach($locationTerms as $locTerm){
                $locName = $locTerm->name;
                if(isset($locationArticleCount[$locName]))
                    $locationArticleCount[$locName]++;
                else
                    $locationArticleCount[$locName] = 1;
            }
            
            //$postTitle = the_title("", "", false);
            //$postUrl = get_permalink();
            //$postSlug = basename(get_permalink());
        }
    }

    //generate the data we will use to display the location list
    $resultData = array();
    $locationsDisplayed = 0;
    foreach($locationArticleCount as $location=>$activityCount){
        if($locationsDisplayed == $maxPostCount)
            break;
        $resultData[] = array('title'=>$location, 'activity_count'=>$activityCount);
        $locationsDisplayed++;
    }
    
    //sort the result list alphabetically
    usort($resultData, function ($a, $b){return strcasecmp($a['title'], $b['title']);});

    //Generate the output
    $res = popular_cat_loc_format_result($title, $resultData);
    //$res .= "title=$title<br>";
    //$res .= "targetPostType=$targetPostType<br>";
    //$res .= "maxPostCount=$maxPostCount<br>";
    //$res .= $query->request . "<br>";
    //$res .= "Total activities found: $totalActivityCount<br>";
    //$res .= "Locations found: $locationsFound<br>";
    //$res .= "Locations displayed: $locationsDisplayed<br>";
    wp_reset_postdata();
    return $res;
}

add_shortcode('popular-cat-loc', 'popular_cat_loc_sc');

//helper function to format the HTML output from the found results
function popular_cat_loc_format_result($title, $data) {
    if (empty($data))
        return '';

    $res = '<div class="tr-pop-locations tr-pop-destinations">';
    $res .= '<h3 style="text-align:left;">' . $title . '</h3>';
    $res .= '<ul style="width:100%; padding-left:0px; overflow:hidden;">';
    foreach ($data as $location) {
        $locUrl = $location['url'];
        $locTitle = $location['title'];
        $activityCount = $location['activity_count'];

        $res .= '<li style="text-align:left; width:50%; display:block; float:left;">';
        $res .= "<a href=\"$locUrl\">$locTitle</a>";
        $res .= ' ( ' . $activityCount . ' ) ';
        $res .= '</li>';
    }
    $res .= '</ul>';
    $res .= '</div>';
    return $res;
}
