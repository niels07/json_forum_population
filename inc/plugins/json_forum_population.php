<?php

// Prevent direct access to the plugin file.
if (!defined("IN_MYBB")) {
    die("Direct access not allowed.");
}

// Plugin metadata
function json_forum_population_info()
{
    return [
        "name" => "JSON Forum Population",
        "description" => "Populate forums with threads and posts based on JSON input.",
        "website" => "",
        "author" => "Niels Vanden Eynde",
        "authorsite" => "",
        "version" => "1.1",
        "compatibility" => "18*"
    ];
}

// Install function
function json_forum_population_install()
{
    global $db;

    // Add plugin setting group (if needed)
    $setting_group = [
        "name" => "json_forum_population",
        "title" => "JSON Forum Population Settings",
        "description" => "Settings for the JSON Forum Population plugin.",
        "disporder" => 1,
        "isdefault" => 0
    ];
    $db->insert_query("settinggroups", $setting_group);

    rebuild_settings();
}

// Uninstall function
function json_forum_population_uninstall()
{
    global $db;

    // Remove setting group (if needed)
    $db->delete_query("settinggroups", "name = 'json_forum_population'");
    $db->delete_query("settings", "name LIKE 'json_forum_population_%'");
    rebuild_settings();
}

// Is the plugin installed?
function json_forum_population_is_installed()
{
    global $db;
    $query = $db->simple_select("settinggroups", "name", "name = 'json_forum_population'");
    return $db->num_rows($query) > 0;
}

// Activate the plugin
function json_forum_population_activate()
{
    // No need to insert anything into adminoptions, this was incorrect
}

// Deactivate the plugin
function json_forum_population_deactivate()
{
    // No need to remove anything from adminoptions
}

// Hook to add the menu to the admin control panel
$plugins->add_hook('admin_config_menu', 'json_forum_population_admin_menu');
$plugins->add_hook('admin_config_action_handler', 'json_forum_population_admin_action_handler');
$plugins->add_hook('admin_page_output_footer', 'json_forum_population_process_json');

// Admin Menu
function json_forum_population_admin_menu(&$sub_menu)
{
    $sub_menu[] = [
        "id" => "json_forum_population",
        "title" => "JSON Forum Population",
        "link" => "index.php?module=config-json_forum_population"
    ];
}

// Admin Action Handler
function json_forum_population_admin_action_handler(&$actions)
{
    $actions['json_forum_population'] = ['active' => 'json_forum_population', 'file' => 'json_forum_population'];
}

// Admin Page
function json_forum_population_admin_page()
{
    global $page, $db, $mybb;

    $page->output_header("JSON Forum Population");

    // Show JSON input form
    if (!$mybb->input['generate']) {
        $form = new Form("index.php?module=config-json_forum_population&action=generate", "post");
        $form_container = new FormContainer("JSON Forum Population JSON Input");
        $form_container->output_row("JSON Input", "Enter your JSON data here.", $form->generate_text_area('json_input', '', ['rows' => 20, 'style' => 'width: 100%']), 'json_input');
        $form_container->end();
        $buttons[] = $form->generate_submit_button("Generate Threads and Posts");
        $form->output_submit_wrapper($buttons);
        $form->end();
    } else {
        // JSON processing will be handled by the json_forum_population_process_json function
        echo "<p>Processing JSON data...</p>";
    }

    $page->output_footer();
}

// JSON Processing Function
function json_forum_population_process_json()
{
    global $mybb, $db, $lang, $plugins, $cache;

    if ($mybb->input['action'] == "generate" && !empty($mybb->input['json_input'])) {
        $json_data = json_decode($mybb->input['json_input'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $base_start_date = strtotime("2024-08-01");
            $today = time();

            foreach ($json_data['threads'] as $thread) {
                $forum_id = get_forum_id_by_title($thread['forum_id']);
                $first_post = $thread['posts'][0];

                $random_thread_timestamp = get_random_datetime($base_start_date, $today);
                $thread_id = create_thread($forum_id, $thread['title'], $first_post['user_id'], $first_post['content'], $random_thread_timestamp);

                $previous_post_timestamp = $random_thread_timestamp;

                for ($i = 1; $i < count($thread['posts']); $i++) {
                    $post = $thread['posts'][$i];
                    $previous_post_timestamp = get_random_datetime($previous_post_timestamp, $today);
                    create_post($thread_id, $post['user_id'], $post['content'], $previous_post_timestamp);
                }
            }
            echo "<p>Threads and posts generated successfully!</p>";
        } else {
            echo "<p>Error processing JSON input: " . json_last_error_msg() . "</p>";
        }
    }
}

// Helper: Get Forum ID by Title
function get_forum_id_by_title($forum_title)
{
    global $db;
    $query = $db->simple_select("forums", "fid", "name='" . $db->escape_string($forum_title) . "'");
    $forum = $db->fetch_array($query);
    return $forum['fid'];
}

// Helper: Create Thread
function create_thread($forum_id, $title, $user_id, $content, $timestamp)
{
    global $db;

    require_once MYBB_ROOT . "inc/datahandlers/post.php";
    $posthandler = new PostDataHandler("insert");
    $posthandler->action = "thread";

    // Fetch the username based on the user ID
    $user = fetch_user($user_id);

    // Ensure the username is valid
    if (empty($user['username'])) {
        error_log("Invalid user ID: {$user_id}");
        return false;
    }

    $random_views = rand(10, 150);

    // Prepare thread data
    $thread_data = [
        "fid" => $forum_id,
        "subject" => $title,
        "uid" => $user_id,
        "username" => $user['username'],
        "message" => $content,
        "dateline" => $timestamp,
        "icon" => 0,
        "visible" => 1,
        "savedraft" => 0,
        "options" => [
            "signature" => 1,
            "subscriptionmethod" => 0
        ]
    ];

    $posthandler->set_data($thread_data);

    if (!$posthandler->validate_thread()) {
        $errors = $posthandler->get_friendly_errors();
        error_log("Thread validation failed: " . implode(", ", $errors));
        return false;
    }

    $thread_info = $posthandler->insert_thread();
    
    // Check if the thread creation was successful and retrieve the thread ID
    if (!$thread_info || !is_array($thread_info)) {
        error_log("Thread creation failed.");
        return false;
    }

    $thread_id = $thread_info['tid']; // Correctly retrieve the thread ID from the insert_thread response
    
     // Update the thread views
     $db->update_query("threads", ["views" => $random_views], "tid='$thread_id'");

    return $thread_id;
}

// Create a Post under Thread ID with user ID and specified content and timestamp
function create_post($thread_id, $user_id, $content, $timestamp)
{
    global $db;

    require_once MYBB_ROOT . "inc/datahandlers/post.php";
    $posthandler = new PostDataHandler("insert");
    $posthandler->action = "post";

    // Fetch the username based on the user ID
    $user = fetch_user($user_id);

    // Ensure the username is valid
    if (empty($user['username'])) {
        error_log("Invalid user ID: {$user_id}");
        return false;
    }

     // Get the thread subject for the reply
    $thread_subject = get_thread_subject($thread_id);
    if (empty($thread_subject)) {
        error_log("Failed to retrieve thread subject for Thread ID: {$thread_id}");
        return false;
    }

    // Prepare post data
    $post_data = [
        "tid" => intval($thread_id),
        "fid" => get_forum_id_by_thread($thread_id), // Retrieve the correct forum ID
        "uid" => $user_id,
        "username" => $user['username'],
        "subject" => "RE: " . $thread_subject, // Use the correct thread subject
        "message" => $content,
        "dateline" => $timestamp,
        "visible" => 1,
        "posthash" => md5(uniqid(microtime(), true)),
        "options" => [
            "signature" => 1,
        ]
    ];


    $posthandler->set_data($post_data);

    // Validate the post
    if (!$posthandler->validate_post()) {
        $errors = $posthandler->get_friendly_errors();
        error_log("Post validation failed: " . implode(", ", $errors));
        return false;
    }

    // Insert the post
    $post_id = $posthandler->insert_post();
    if (!$post_id) {
        error_log("Post creation failed.");
        return false;
    }

    return $post_id;
}

// Get Forum ID by Thread ID
function get_forum_id_by_thread($thread_id)
{
    global $db;
    $query = $db->simple_select("threads", "fid", "tid='" . intval($thread_id) . "'");
    $thread = $db->fetch_array($query);
    return $thread['fid'];
}

// Get Thread Subject by Thread ID
function get_thread_subject($thread_id)
{
    global $db;
    $query = $db->simple_select("threads", "subject", "tid='" . intval($thread_id) . "'");
    $thread = $db->fetch_array($query);
    return $thread['subject'];
}

// Fetch User Data by User ID
function fetch_user($user_id)
{
    global $db;
    $query = $db->simple_select("users", "username", "uid='" . intval($user_id) . "'");
    $user = $db->fetch_array($query);
    return $user;
}

// Update thread lastpost info
function update_thread_lastpost($thread_id, $post_id)
{
    global $db;

    $lastpost_data = $db->fetch_array($db->simple_select("posts", "uid, dateline", "pid='{$post_id}'"));
    $update_data = [
        "lastpost" => $lastpost_data['dateline'],
        "lastposter" => $lastpost_data['uid'],
        "replies" => "replies+1"
    ];

    $db->update_query("threads", $update_data, "tid='{$thread_id}'");
}

// Generate Random Datetime
function get_random_datetime($start_timestamp, $end_timestamp)
{
    return rand($start_timestamp, $end_timestamp);
}
