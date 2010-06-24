<?php
/**
 * UserAccessManager.class.php
 * 
 * The UserAccessManager class file.
 * 
 * PHP versions 5
 * 
 * @category  UserAccessManager
 * @package   UserAccessManager
 * @author    Alexander Schneider <alexanderschneider85@googlemail.com>
 * @copyright 2008-2010 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */

/**
 * The user user access manager class.
 * 
 * @category UserAccessManager
 * @package  UserAccessManager
 * @author   Alexander Schneider <alexanderschneider85@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @link     http://wordpress.org/extend/plugins/user-access-manager/
 */

class UserAccessManager
{
    var $adminOptionsName = "uamAdminOptions";
    var $atAdminPanel = false;
    var $uamDbVersion = "1.1";
    var $adminOptions;
    
    /**
     * Consturctor
     * 
     * @return null
     */
    function UserAccessManager()
    {

    }
    
    /**
     * Loads the language files
     * 
     * @return null
     */
    function init()
    {
        
    }
    
    /**
     * Creates the needed tables at the database
     * 
     * @return null;
     */
    function install()
    {
        $this->createHtaccess();
        $this->createHtpasswd();
        global $wpdb;
        $uamDbVersion = $this->uam_db_version;
        include_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = '';
        
        if (version_compare(mysql_get_server_info(), '4.1.0', '>=')) {
            if (!empty($wpdb->charset)) {
                $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
            }
            
            if (!empty($wpdb->collate)) {
                $charset_collate.= " COLLATE $wpdb->collate";
            }
        }
        
        if ($wpdb->get_var("show tables like '" . DB_ACCESSGROUP . "'") != DB_ACCESSGROUP) {
            $sql = "CREATE TABLE " . DB_ACCESSGROUP . " (
					  ID int(11) NOT NULL auto_increment,
					  groupname tinytext NOT NULL,
					  groupdesc text NOT NULL,
					  read_access tinytext NOT NULL,
					  write_access tinytext NOT NULL,
					  ip_range mediumtext NULL,
					  PRIMARY KEY  (ID)
					) $charset_collate;";
            dbDelta($sql);
        }
        
        if ($wpdb->get_var("show tables like '" . DB_ACCESSGROUP_TO_POST . "'") != DB_ACCESSGROUP_TO_POST) {
            $sql = "CREATE TABLE " . DB_ACCESSGROUP_TO_POST . " (
					  post_id int(11) NOT NULL,
					  group_id int(11) NOT NULL,
					  PRIMARY KEY  (post_id,group_id)
					) $charset_collate;";
            dbDelta($sql);
        }
        
        if ($wpdb->get_var("show tables like '" . DB_ACCESSGROUP_TO_USER . "'") != DB_ACCESSGROUP_TO_USER) {
            $sql = "CREATE TABLE " . DB_ACCESSGROUP_TO_USER . " (
					  user_id int(11) NOT NULL,
					  group_id int(11) NOT NULL,
					  PRIMARY KEY  (user_id,group_id)
					) $charset_collate;";
            dbDelta($sql);
        }
        
        if ($wpdb->get_var("show tables like '" . DB_ACCESSGROUP_TO_CATEGORY . "'") != DB_ACCESSGROUP_TO_CATEGORY) {
            $sql = "CREATE TABLE " . DB_ACCESSGROUP_TO_CATEGORY . " (
					  category_id int(11) NOT NULL,
					  group_id int(11) NOT NULL,
					  PRIMARY KEY  (category_id,group_id)
					) $charset_collate;";
            dbDelta($sql);
        }
        
        if ($wpdb->get_var("show tables like '" . DB_ACCESSGROUP_TO_ROLE . "'") != DB_ACCESSGROUP_TO_ROLE) {
            $sql = "CREATE TABLE " . DB_ACCESSGROUP_TO_ROLE . " (
					  role_name varchar(255) NOT NULL,
					  group_id int(11) NOT NULL,
					  PRIMARY KEY  (role_name,group_id)
					) $charset_collate;";
            dbDelta($sql);
        }
        
        add_option("uam_db_version", $uamDbVersion);
    }
    
    /**
     * Updates the database if an old version was installed.
     * 
     * @return null;
     */
    function update()
    {
        global $wpdb;
        $uamDbVersion = $this->uam_db_version;
        $installed_ver = get_option("uam_db_version");
        
        if (empty($installed_ver)) {
            $this->install();
        }
        
        if ($installed_ver != $uamDbVersion) {
            if ($installed_ver == '1.0') {
                if ($wpdb->get_var("SHOW TABLES LIKE '" . DB_ACCESSGROUP . "'") == DB_ACCESSGROUP) {
                    $wpdb->query(
                    	"ALTER TABLE " . DB_ACCESSGROUP . " 
                    	ADD read_access TINYTEXT NOT NULL DEFAULT '', 
                    	ADD write_access TINYTEXT NOT NULL DEFAULT '', 
                    	ADD ip_range MEDIUMTEXT NULL DEFAULT ''"
                    );
                    
                    $wpdb->query(
                    	"UPDATE " . DB_ACCESSGROUP . " 
                    	SET read_access = 'group', 
                    		write_access = 'group'"
                    );
                    
                    update_option("uam_db_version", $uamDbVersion);
                }
            }
        }
        
        if ($wpdb->get_var("SHOW tables LIKE '" . DB_ACCESSGROUP . "'") == DB_ACCESSGROUP) {
            if ($wpdb->get_var("SHOW columns FROM " . DB_ACCESSGROUP . " LIKE 'ip_range'") != 'ip_range') {
                $wpdb->query(
                	"ALTER TABLE " . DB_ACCESSGROUP . " 
                	ADD ip_range MEDIUMTEXT NULL DEFAULT ''"
                );
            }
        }
    }
    
    /**
     * Clean up wordpress if the plugin will be uninstalled.
     * 
     * @return null
     */
    function uninstall()
    {
        global $wpdb;
        $wpdb->query(
        	"DROP TABLE " . DB_ACCESSGROUP . ", 
        		" . DB_ACCESSGROUP_TO_POST . ", 
        		" . DB_ACCESSGROUP_TO_USER . ", 
        		" . DB_ACCESSGROUP_TO_CATEGORY . ", 
        		" . DB_ACCESSGROUP_TO_ROLE
        );
        
        delete_option($this->adminOptionsName);
        delete_option("uam_db_version");
        $this->deleteHtaccessFiles();
    }
    
    /**
     * Remove the htaccess file if the plugin is deactivated.
     * 
     * @return null
     */
    function deactivate()
    {
        $this->deleteHtaccessFiles();
    }
    
    /**
     * Creates a htaccess file.
     * 
     * @return null.
     */
    function createHtaccess()
    {
        // Make .htaccess file to protect data
        // get url

        $wud = wp_upload_dir();
        if (empty($wud['error'])) {
            $url = $wud['basedir'] . "/";
            $areaname = "WP-Files";
            $uamOptions = $this->getAdminOptions();
            
            if ($uamOptions['lock_file_types'] == 'selected') {
                $fileTypes = $uamOptions['locked_file_types'];
            } elseif ($uamOptions['lock_file_types'] == 'not_selected') {
                $fileTypes = $uamOptions['not_locked_file_types'];
            }
            
            if (isset($fileTypes)) {
                $fileTypes = str_replace(",", "|", $fileTypes);
            }

            // make .htaccess and .htpasswd
            $htaccess_txt = "";
            
            if ($uamOptions['lock_file_types'] == 'selected') {
                $htaccess_txt.= "<FilesMatch '\.(" . $fileTypes . ")'>\n";
            }
            
            if ($uamOptions['lock_file_types'] == 'not_selected') {
                $htaccess_txt.= "<FilesMatch '^\.(" . $fileTypes . ")'>\n";
            }
            
            $htaccess_txt.= "AuthType Basic" . "\n";
            $htaccess_txt.= "AuthName \"" . $areaname . "\"" . "\n";
            $htaccess_txt.= "AuthUserFile " . $url . ".htpasswd" . "\n";
            $htaccess_txt.= "require valid-user" . "\n";
            
            if ($uamOptions['lock_file_types'] == 'selected' 
                || $uamOptions['lock_file_types'] == 'not_selected'
            ) {
                $htaccess_txt.= "</FilesMatch>\n";
            }

            // save files
            $htaccess = fopen($url . ".htaccess", "w");
            fwrite($htaccess, $htaccess_txt);
            fclose($htaccess);
        }
    }
    
	/**
     * Creates a htpasswd file.
     * 
     * @param boolean $createNew Force to create new file.
     * 
     * @return null
     */
    function createHtpasswd($createNew = false)
    {
        global $current_user;
        $uamOptions = $this->getAdminOptions();

        // get url
        $wud = wp_upload_dir();
        if (empty($wud['error'])) {
            $url = $wud['basedir'] . "/";
            $curUserdata = get_userdata($current_user->ID);
            $user = $curUserdata->user_login;
            
            if (!file_exists($url . ".htpasswd") || $createNew) {
                if ($uamOptions['file_pass_type'] == 'random') {
                    // create password
                    $array = array();
                    $length = 10;
                    $capitals = true;
                    $specialSigns = false;
                    if ($length < 8) {
                        $length = mt_rand(8, 20);
                    }

                    // numbers
                    for ($i = 48; $i < 58; $i++) {
                        $array[] = chr($i);
                    }

                    // small
                    for ($i = 97; $i < 122; $i++) {
                        $array[] = chr($i);
                    }

                    // capitals
                    if ($capitals) {
                        for ($i = 65; $i < 90; $i++) {
                            $array[] = chr($i);
                        }
                    } 

                    // specialchar:
                    if ($specialSigns) {
                        for ($i = 33; $i < 47; $i++) {
                            $array[] = chr($i);
                        }
                        
                        for ($i = 59; $i < 64; $i++) {
                            $array[] = chr($i);
                        }
                        
                        for ($i = 91; $i < 96; $i++) {
                            $array[] = chr($i);
                        }
                        
                        for ($i = 123; $i < 126; $i++) {
                            $array[] = chr($i);
                        }
                    }
                    
                    mt_srand((double)microtime() * 1000000);
                    $password = '';
                    
                    for ($i = 1; $i <= $length; $i++) {
                        $rnd = mt_rand(0, count($array) - 1);
                        $password.= $array[$rnd];
                        $password = md5($password);
                    }
                } elseif ($uamOptions['file_pass_type'] == 'admin') {
                    $password = $curUserdata->user_pass;
                }

                // make .htpasswd
                $htpasswd_txt = "$user:" . $password . "\n";

                // save file
                $htpasswd = fopen($url . ".htpasswd", "w");
                fwrite($htpasswd, $htpasswd_txt);
                fclose($htpasswd);
            }
        }
    }
    
    /**
     * Deletes the htaccess files.
     * 
     * @return null
     */
    function deleteHtaccessFiles()
    {
        $wud = wp_upload_dir();
        if (empty($wud['error'])) {
            $url = $wud['basedir'] . "/";
            unlink($url . ".htaccess");
            unlink($url . ".htpasswd");
        }
    }
    
    /**
     * Returns the current settings
     * 
     * @return array
     */
    function getAdminOptions()
    {
        if ($this->atAdminPanel || empty($this->adminOptions)) {
            $uamAdminOptions = array(
            	'hide_post_title' => 'false', 
            	'post_title' => __('No rights!', 'user-access-manager'), 
            	'hide_post_comment' => 'false', 
            	'post_comment_content' => __(
            		'Sorry no rights to view comments!', 
            		'user-access-manager'
                ), 
            	'allow_comments_locked' => 'false', 
            	'post_content' => 'Sorry no rights!', 
            	'hide_post' => 'false', 
            	'hide_page_title' => 'false', 
            	'page_title' => 'No rights!', 
            	'page_content' => __(
            		'Sorry you have no rights to view this page!', 
            		'user-access-manager'
                ), 
            	'hide_page' => 'false', 
            	'redirect' => 'false', 
            	'redirect_custom_page' => '', 
            	'redirect_custom_url' => '', 
            	'lock_recursive' => 'true', 
            	'lock_file' => 'true', 
            	'file_pass_type' => 'random', 
            	'lock_file_types' => 'all', 
            	'download_type' => 'fopen', 
            	'locked_file_types' => 'zip,rar,tar,gz,bz2', 
            	'not_locked_file_types' => 'gif,jpg,jpeg,png', 
            	'blog_admin_hint' => 'true', 
            	'blog_admin_hint_text' => '[L]', 
            	'core_mod' => 'false', 
            	'hide_empty_categories' => 'true', 
            	'protect_feed' => 'true', 
            	'showPost_content_before_more' => 'false', 
            	'full_access_level' => 10
            );
            
            $uamOptions = get_option($this->adminOptionsName);
            
            if (!empty($uamOptions)) {
                foreach ($uamOptions as $key => $option) {
                    $uamAdminOptions[$key] = $option;
                }
            }
            
            update_option($this->adminOptionsName, $uamAdminOptions);
            $this->adminOptions = $uamAdminOptions;
        }
        return $this->adminOptions;
    }

    /**
     * Prints the admin page
     * 
     * @return null
     */
    function printAdminPage()
    {
        if (isset($_GET['page'])) {
            $cur_admin_page = $_GET['page'];
        }
        
        if ($cur_admin_page == 'uam_settings') {
            include "tpl/adminSettings.php";
        } elseif ($cur_admin_page == 'uam_usergroup') {
            include "tpl/adminGroup.php";
        } elseif ($cur_admin_page == 'uam_setup') {
            include "tpl/adminSetup.php";
        }
    }


    function get_usergroup_info_html($group_id, $style = null)
    {
        $link = '<a class="uam_group_info_link">(' . TXT_INFO . ')</a>';
        $group_info = $this->get_usergroup_info($group_id);
        $content = "<ul class='uam_group_info'";
        if ($style != null) $content.= " style='" . $style . "' ";
        $content.= "><li class='uam_group_info_head'>" . TXT_GROUP_INFO . ":</li>";
        $content.= "<li>" . TXT_READ_ACCESS . ": ";
        if ($group_info->group['read_access'] == "all") {
            $content.= TXT_ALL;
        } elseif ($group_info->group['read_access'] == "group") {
            $content.= TXT_ONLY_GROUP_USERS;
        }
        $content.= "</li>";
        $content.= "<li>" . TXT_WRITE_ACCESS . ": ";
        if ($group_info->group['write_access'] == "all") $content.= TXT_ALL;
        elseif ($group_info->group['write_access'] == "group") $content.= TXT_ONLY_GROUP_USERS;
        $content.= "</li>";
        if (isset($group_info->posts)) {
            $expandcontent = null;
            foreach ($group_info->posts as $post) {
                $expandcontent.= "<li>" . $post->post_title . "</li>";
            }
            $content.= "<li><a class='uam_info_link'>" . count($group_info->posts) . " " . TXT_POSTS . "</a>";
            $content.= "<ul class='uam_info_content expand_deactive'>" . $expandcontent . "</ul></li>";
        } else {
            $content.= "<li>" . TXT_NONE . " " . TXT_POSTS . "</li>";
        }
        if (isset($group_info->pages)) {
            $expandcontent = null;
            foreach ($group_info->pages as $page) {
                $expandcontent.= "<li>" . $page->post_title . "</li>";
            }
            $content.= "<li><a class='uam_info_link'>" . count($group_info->pages) . " " . TXT_PAGES . "</a>";
            $content.= "<ul class='uam_info_content expand_deactive'>" . $expandcontent . "</ul></li>";
        } else {
            $content.= "<li>" . TXT_NONE . " " . TXT_PAGES . "</li>";
        }
        if (isset($group_info->categories)) {
            $expandcontent = null;
            foreach ($group_info->categories as $categorie) {
                $expandcontent.= "<li>" . $categorie->cat_name . "</li>";
            }
            $content.= "<li><a class='uam_info_link'>" . count($group_info->categories) . " " . TXT_CATEGORY . "</a>";
            $content.= "<ul class='uam_info_content expand_deactive'>" . $expandcontent . "</ul></li>";
        } else {
            $content.= "<li>" . TXT_NONE . " " . TXT_CATEGORY . "</li>";
        }
        if (isset($group_info->users)) {
            $expandcontent = null;
            foreach ($group_info->users as $user) {
                $expandcontent.= "<li>" . $user->nickname . "</li>";
            }
            $content.= "<li><a class='uam_info_link'>" . count($group_info->users) . " " . TXT_USERS . "</a>";
            $content.= "<ul class='uam_info_content expand_deactive'>" . $expandcontent . "</ul></li>";
        } else {
            $content.= "<li>" . TXT_NONE . " " . TXT_USERS . "</li>";
        }
        $content.= "</ul>";
        $result->link = $link;
        $result->content = $content;
        return $result;
    }
    
    function get_post_info_html($id)
    {
        $usergroups = $this->getUsergroupsForPost($id);
        if (isset($usergroups) && $usergroups != null) {
            $output = "<ul>";
            foreach ($usergroups as $usergroup) {
                $output.= "<li><a class='uma_user_access_group'>" . $usergroup->name . "</a>";
                $output.= "<ul class='uma_user_access_group_from'>";
                if (isset($usergroup->itself)) $output.= "<li>" . TXT_ITSELF . "</li>";
                if (isset($usergroup->posts)) {
                    foreach ($usergroup->posts as $curId) {
                        $curPost = & get_post($curId);
                        $output.= "<li>$curPost->post_title [$curPost->post_type]</li>";
                    }
                }
                if (isset($usergroup->categories)) {
                    foreach ($usergroup->categories as $curId) {
                        $cur_category = & get_category($curId);
                        $output.= "<li>$cur_category->name [category]</li>";
                    }
                }
                $output = substr($output, 0, -2);
                $output.= "</ul></li>";
            }
            $output.= "</ul>";
        } else {
            $output = TXT_FULL_ACCESS;
        }
        return $output;
    }
    
    function add_post_columns_header($defaults)
    {
        $defaults['uam_access'] = __('Access');
        return $defaults;
    }
    
    function get_post_edit_info_html($id, $style = null)
    {
        global $wpdb;
        $accessgroups = $wpdb->get_results("SELECT *
											FROM " . DB_ACCESSGROUP . "
											ORDER BY groupname", ARRAY_A);
        $recursive_set = $this->getUsergroupsForPost($id);
        if (isset($accessgroups)) {
            $content = "";
            foreach ($accessgroups as $accessgroup) {
                $checked = $wpdb->get_results("	SELECT *
												FROM " . DB_ACCESSGROUP_TO_POST . "
												WHERE post_id = " . $id . "
												AND group_id = " . $accessgroup['ID'], ARRAY_A);
                $set_recursive = null;
                if (isset($recursive_set[$accessgroup['ID']])) $set_recursive = $recursive_set[$accessgroup['ID']];
                $content.= '<p><label for="uam_accesssgroup-' . $accessgroup['ID'] . '" class="selectit" style="display:inline;" >';
                $content.= '<input type="checkbox" id="uam_accesssgroup-' . $accessgroup['ID'] . '"';
                if (isset($checked) || isset($set_recursive->posts) || isset($set_recursive->categories)) $content.= 'checked="checked"';
                if (isset($set_recursive->posts) || isset($set_recursive->categories)) $content.= 'disabled=""';
                $content.= 'value="' . $accessgroup['ID'] . '" name="accessgroups[]"/>';
                $content.= $accessgroup['groupname'];
                $content.= "</label>";
                $group_info_html = $this->get_usergroup_info_html($accessgroup['ID'], $style);
                $content.= $group_info_html->link;
                if (isset($set_recursive->posts) || isset($set_recursive->categories)) $content.= '&nbsp;<a class="uam_group_lock_info_link">[LR]</a>';
                $content.= $group_info_html->content;
                if (isset($set_recursive->posts) || isset($set_recursive->categories)) {
                    $recursive_info = '<ul class="uam_group_lock_info" ';
                    if ($style != null) $recursive_info.= " style='" . $style . "' ";
                    $recursive_info.= '><li class="uam_group_lock_info_head">' . TXT_GROUP_LOCK_INFO . ':</li>';
                    if (isset($set_recursive->posts)) {
                        foreach ($set_recursive->posts as $curId) {
                            $curPost = & get_post($curId);
                            $recursive_info.= "<li>$curPost->post_title [$curPost->post_type]</li>";
                        }
                    }
                    if (isset($set_recursive->categories)) {
                        foreach ($set_recursive->categories as $curId) {
                            $cur_category = & get_category($curId);
                            $recursive_info.= "<li>$cur_category->name [" . TXT_CATEGORY . "]</li>";
                        }
                    }
                    $recursive_info.= "</ul>";
                    $content.= $recursive_info;
                }
                $content.= "</p>";
            }
        } else {
            $content = "<a href='admin.php?page=uam_usergroup'>";
            $content.= TXT_CREATE_GROUP_FIRST;
            $content.= "</a>";
        }
        return $content;
    }
    
    function add_post_column($column_name, $id)
    {
        if ($column_name == 'uam_access') {
            echo $this->get_post_info_html($id);
        }
    }
    
    function edit_post_content($post)
    {
        echo $this->get_post_edit_info_html($post->ID, "padding:0 0 0 36px;");
    }
    
    function save_postdata($postId)
    {
        global $current_user, $wpdb;
        $curUserdata = get_userdata($current_user->ID);
        $uamOptions = $this->getAdminOptions();
        if ($curUserdata->user_level < $uamOptions['full_access_level']) {
            $uamOptions = $this->getAdminOptions();
            $cur_categories = wp_get_post_categories($postId);
            $allowded_categories = get_categories();
            foreach ($cur_categories as $category) {
                foreach ($allowded_categories as $allowded_category) {
                    if ($allowded_category->term_id == $category) {
                        $post_categories[] = $allowded_category->term_id;
                        break;
                    }
                }
            }
            if (!isset($post_categories)) {
                $last_category = array_pop($allowded_categories);
                $post_categories[] = $last_category->term_id;
            }
            wp_set_post_categories($postId, $post_categories);
        }
        if (isset($_POST['accessgroups'])) $accessgroups = $_POST['accessgroups'];
        $wpdb->query("DELETE FROM " . DB_ACCESSGROUP_TO_POST . " WHERE post_id = $postId");
        if (isset($accessgroups)) {
            foreach ($accessgroups as $accessgroup) {
                $wpdb->query("INSERT INTO " . DB_ACCESSGROUP_TO_POST . " (post_id,group_id) VALUES(" . $postId . ", " . $accessgroup . ")");
            }
        }
    }
    
    function save_attachmentdata($post)
    {
        global $wpdb;
        if (isset($post['ID'])) {
            $postId = $post['ID'];
            if (isset($_POST['accessgroups'])) $accessgroups = $_POST['accessgroups'];
            $wpdb->query("DELETE FROM " . DB_ACCESSGROUP_TO_POST . " WHERE post_id = $postId");
            if (isset($accessgroups)) {
                foreach ($accessgroups as $accessgroup) {
                    $wpdb->query("INSERT INTO " . DB_ACCESSGROUP_TO_POST . " (post_id,group_id) VALUES(" . $postId . ", " . $accessgroup . ")");
                }
            }
        }
        return $post;
    }
    
    function remove_postdata($postId)
    {
        global $wpdb;
        $wpdb->query("DELETE FROM " . DB_ACCESSGROUP_TO_POST . " WHERE post_id = $postId");
    }
    
    function add_user_columns_header($defaults)
    {
        $defaults['uam_access'] = __('Access');
        return $defaults;
    }
    
    function add_user_column($column_name, $id)
    {
        global $wpdb;
        if ($column_name == 'uam_access') {
            $usergroups = $wpdb->get_results("	SELECT ag.groupname
												FROM " . DB_ACCESSGROUP . " ag, " . DB_ACCESSGROUP_TO_USER . " agtp
												WHERE agtp.user_id = " . $id . "
													AND ag.ID = agtp.group_id
												GROUP BY ag.groupname", ARRAY_A);
            if (isset($usergroups)) {
                $content.= "<ul>";
                foreach ($usergroups as $usergroup) {
                    $content.= "<li>" . $usergroup['groupname'] . "</li>";
                }
                $content.= "</ul>";
            } else {
                $content = TXT_NO_GROUP;
            }
            return $content;
        }
    }
    
    function show_user_profile()
    {
        global $wpdb, $current_user;
        $user_id = $_GET['user_id'];
        $curUserdata = get_userdata($current_user->ID);
        $cur_edit_userdata = get_userdata($user_id);
        $uamOptions = $this->getAdminOptions();
        if ($curUserdata->user_level >= $uamOptions['full_access_level']) {
            $accessgroups = $wpdb->get_results("SELECT *
												FROM " . DB_ACCESSGROUP . "
												ORDER BY groupname", ARRAY_A);
?>
<h3><?php
            echo TXT_GROUPS; ?></h3>
<table class="form-table">
	<tbody>
		<tr>
			<th><label for="usergroups"><?php
            echo TXT_SET_UP_USERGROUPS; ?></label>
		</th>
		<td><?php
            if (empty($cur_edit_userdata->{$wpdb->prefix . "capabilities"}['administrator'])) {
                if (isset($accessgroups)) {
                    foreach ($accessgroups as $accessgroup) {
                        $checked = $wpdb->get_results("	SELECT *
																		FROM " . DB_ACCESSGROUP_TO_USER . "
																		WHERE user_id = " . $user_id . "
																			AND group_id = " . $accessgroup['ID'], ARRAY_A)
?>
		<p style="margin: 6px 0;"><label
			for="uam_accesssgroup-<?php
                        echo $accessgroup['ID']; ?>"
			class="selectit"> <input type="checkbox"
			id="uam_accesssgroup-<?php
                        echo $accessgroup['ID']; ?>"
			<?php
                        if (isset($checked)) {
                            echo 'checked="checked"';
                        } ?>
			value="<?php
                        echo $accessgroup['ID']; ?>" name="accessgroups[]" /> <?php
                        echo $accessgroup['groupname']; ?>
		</label> <?php
                        $group_info_html = $this->get_usergroup_info_html($accessgroup['ID'], "padding: 0 0 0 32px");
                        echo $group_info_html->link;
                        echo $group_info_html->content;
                        echo "</p>";
                    }
                } else {
                    echo "<a href='admin.php?page=uam_usergroup'>";
                    echo TXT_CREATE_GROUP_FIRST;
                    echo "</a>";
                }
            } else {
                echo TXT_ADMIN_HINT;
            }
?>
		
		</td>
	</tr>
</tbody>
</table>
		<?php
        }
    }
    
    function show_media_file($meta = '', $post)
    {
        $content = $meta;
        $content.= '</td></tr><tr><th class="label"><label>' . TXT_SET_UP_USERGROUPS . '</label></th><td class="field">';
        $content.= $this->get_post_edit_info_html($post->ID, "padding:0 0 0 38px;top:-12px;");
        return $content;
    }
    
    function save_userdata($user_id)
    {
        global $wpdb, $current_user;
        $curUserdata = get_userdata($current_user->ID);
        $uamOptions = $this->getAdminOptions();
        if ($curUserdata->user_level >= $uamOptions['full_access_level']) {
            $accessgroups = $_POST['accessgroups'];
            $wpdb->query("DELETE FROM " . DB_ACCESSGROUP_TO_USER . " WHERE user_id = $user_id");
            if (isset($accessgroups)) {
                foreach ($accessgroups as $accessgroup) {
                    $wpdb->query("INSERT INTO " . DB_ACCESSGROUP_TO_USER . " (user_id,group_id) VALUES(" . $user_id . ", " . $accessgroup . ")");
                }
            }
        }
    }
    
    function remove_userdata($user_id)
    {
        global $wpdb, $current_user;
        $curUserdata = get_userdata($current_user->ID);
        $uamOptions = $this->getAdminOptions();
        if ($curUserdata->user_level >= $uamOptions['full_access_level']) $wpdb->query("DELETE FROM " . DB_ACCESSGROUP_TO_USER . " WHERE user_id = $user_id");
    }
    
    function add_category_columns_header($defaults)
    {
        $defaults['uam_access'] = __('Access');
        return $defaults;
    }
    
    function add_category_column($column_name, $id)
    {
        global $wpdb;
        if ($column_name == 'uam_access') {
            $usergroups = $wpdb->get_results("SELECT ag.groupname
												FROM " . DB_ACCESSGROUP . " ag, " . DB_ACCESSGROUP_TO_CATEGORY . " agtc
												WHERE agtc.category_id = " . $id . "
													AND ag.ID = agtc.group_id
												GROUP BY ag.groupname", ARRAY_A);
            if (isset($usergroups)) {
                $content = "<ul>";
                foreach ($usergroups as $usergroup) {
                    $content.= "<li>" . $usergroup['groupname'] . "</li>";
                }
                $content.= "</ul>";
            } else {
                $content = TXT_NO_GROUP;
            }
            return $content;
        }
    }
    
    function show_cat_edit_form($cat)
    {
        global $wpdb, $current_user;
        if (isset($cat->cat_ID)) $cat_id = $cat->cat_ID;
        $accessgroups = $wpdb->get_results("SELECT *
											FROM " . DB_ACCESSGROUP . "
											ORDER BY groupname", ARRAY_A);
        if (isset($_GET['action'])) $action = $_GET['action'];
        else $action = null;
        if ($action == 'edit') {
?>
<table class="form-table">
	<tbody>
		<tr>
			<th><label for="description"><?php
            echo TXT_SET_UP_USERGROUPS; ?></label>
		</th>
		<td><?php
            if (isset($accessgroups)) {
                $recursive_set = $this->getUsergroupsForPost($cat_id);
                foreach ($accessgroups as $accessgroup) {
                    $checked = $wpdb->get_results("	SELECT *
																	FROM " . DB_ACCESSGROUP_TO_CATEGORY . "
																	WHERE category_id = " . $cat_id . "
																		AND group_id = " . $accessgroup['ID'], ARRAY_A)

                    //$set_recursive = $recursive_set[$accessgroup['groupname']];
                    
?>
		<p style="margin: 6px 0;"><label
			for="uam_accesssgroup-<?php
                    echo $accessgroup['ID']; ?>"
			class="selectit"> <input type="checkbox"
			id="uam_accesssgroup-<?php
                    echo $accessgroup['ID']; ?>"
			<?php
                    if (isset($checked)) {
                        echo 'checked="checked"';
                    } ?>
			value="<?php
                    echo $accessgroup['ID']; ?>" name="accessgroups[]" /> <?php
                    echo $accessgroup['groupname']; ?>
		</label> <?php
                    $group_info_html = $this->get_usergroup_info_html($accessgroup['ID'], "padding:0 0 0 32px;");
                    echo $group_info_html->link;
                    if (isset($set_recursive->posts) || isset($set_recursive->categories)) echo '&nbsp;<a class="uam_group_lock_info_link">[LR]</a>';
                    echo $group_info_html->content;
                    if (isset($set_recursive->posts) || isset($set_recursive->categories)) {
                        $recursive_info = '<ul class="uam_group_lock_info" style="padding:0 0 0 32px;"><li class="uam_group_lock_info_head">' . TXT_GROUP_LOCK_INFO . ':</li>';
                        if (isset($set_recursive->posts)) {
                            foreach ($set_recursive->posts as $curId) {
                                $curPost = & get_post($curId);
                                $recursive_info.= "<li>$curPost->post_title [$curPost->post_type]</li>";
                            }
                        }
                        if (isset($set_recursive->categories)) {
                            foreach ($set_recursive->categories as $curId) {
                                $cur_category = & get_category($curId);
                                $recursive_info.= "<li>$cur_category->name [" . TXT_CATEGORY . "]</li>";
                            }
                        }
                        $recursive_info.= "</ul>";
                        echo $recursive_info;
                    }
                    echo "</p>";
                }
            } else {
                echo "<a href='admin.php?page=uam_usergroup'>";
                echo TXT_CREATE_GROUP_FIRST;
                echo "</a>";
            }
?>
			
			</td>
		</tr>
	</tbody>
</table>
<style type="text/css">
.submit {
	display: none;
	position: relative;
}
</style>
<p class="submit" style="display: block; position: relative;"><input
	class="button-primary" type="submit" value="Update Category"
	name="submit" /></p>
			<?php
        }
    }
    
    function add_styles()
    {
        wp_enqueue_style('UserAccessManager', UAM_URLPATH . "css/uma_admin.css", false, '1.0', 'screen');
    }
    
    function add_scripts()
    {
        wp_enqueue_script('UserAccessManager', UAM_URLPATH . 'js/functions.js', array('jquery'), '1.0');
    }
    
    function saveCategoryData($categoryId)
    {
        global $wpdb;

        if (isset($_POST['accessgroups'])) {
            $accessGroups = $_POST['accessgroups'];
        }
        
        if (isset($accessGroups)) {
            foreach ($accessgroups as $accessGroupId) {
                $uamUserGroup = new uamUserGroup($accessGroupId);
                
                $uamUserGroup->addCategory($categoryId);
            }
        }
    }
    
    /**
     * The function for the delete_category action.
     * 
     * @param integer $categoryId The id of the category.
     * 
     * @return null
     */
    function removeCategoryData($categoryId)
    {
        global $wpdb;
        
        $wpdb->query(
        	"DELETE FROM " . DB_ACCESSGROUP_TO_CATEGORY . " 
        	WHERE category_id = $categoryId"
        );
    }
    
    /**
     * Returns the login bar.
     * 
     * @return string
     */
    function getLoginBar()
    {
        if (!is_user_logged_in()) {
            include '../tpl/loginBar.php';
        }
        return '';
    }

    /**
     * The function for the the_posts filter.
     * 
     * @param arrray $posts The posts.
     * 
     * @return array
     */
    function showPost($posts = array())
    {
        $showPosts = null;
        $uamOptions = $this->getAdminOptions();
        $uamAccessHandler = new UamAccessHandler();
        
        if (!is_feed() 
            || ($uamOptions['protect_feed'] == 'true' && is_feed())
        ) {
            foreach ($posts as $post) {
                $postType = $post->post_type;
                
                if ($uamOptions['hide_'.$postType] == 'true'
                    || $this->atAdminPanel
                ) {
                    if ($uamAccessHandler->checkAccess($post->ID)) {
                        $post->post_title .= $this->adminOutput($post->ID);
                        $showPosts[] = $post;
                    }
                } else {
                    if (!$uamAccessHandler->checkAccess($post->ID)) {
                        $uamPostContent = $uamOptions[$postType.'_content'];
                        $uamPostContent = str_replace(
                        	"[LOGIN_FORM]", 
                            $this->getLoginBar(), 
                            $uamPostContent
                        );
                        
                        if ($uamOptions['hide_'.$postType.'_title'] == 'true') {
                            $post->post_title = $uamOptions[$postType.'_title'];
                        }
                        
                        if ($uamOptions['allow_comments_locked'] == 'false') {
                            $post->comment_status = 'close';
                        }
  
                        if ($uamOptions['showPost_content_before_more'] == 'true'
                        	&& $postType == "post"
                            && preg_match('/<!--more(.*?)?-->/', $post->post_content, $matches)
                        ) {
                            $post->post_content = explode($matches[0], $post->post_content, 2);
                            $uamPostContent = $post->post_content[0] . " " . $uamPostContent;
                        } 
                        
                        $post->post_content = $uamPostContent;
                    }
                    
                    $post->post_title .= $this->adminOutput($post->ID);
                    $showPosts[] = $post;
                }
            }
            $posts = $showPosts;
        }
        
        return $posts;
    }
    
    function show_comment($comments = array())
    {
        $showComments = null;
        $uamOptions = $this->getAdminOptions();
        
        foreach ($comments as $comment) {
            if ($uamOptions['hide_post_comment'] == 'true' 
                || $uamOptions['hide_post'] == 'true' 
                || $this->atAdminPanel
            ) {
                if ($this->check_access($comment->comment_post_ID)) {
                    $showComments[] = $comment;
                }
            } else {
                if (!$this->check_access($comment->comment_post_ID)) {
                    $comment->comment_content = $uamOptions['post_comment_content'];
                }
                
                $showComments[] = $comment;
            }
        }
        
        $comments = $showComments;
        
        return $comments;
    }
    
    function show_page($pages = array())
    {
        $show_pages = null;
        $uamOptions = $this->getAdminOptions();
        foreach ($pages as $page) {
            if ($uamOptions['hide_page'] == 'true' || $this->atAdminPanel) {
                if ($this->check_access($page->ID)) {
                    $page->post_title.= $this->adminOutput($page->ID);
                    $show_pages[] = $page;
                }
            } else {
                if (!$this->check_access($page->ID)) {
                    if ($uamOptions['hide_page_title'] == 'true') $page->post_title = $uamOptions['page_title'];
                    $page->post_content = $uamOptions['page_content'];
                }
                $page->post_title.= $this->adminOutput($page->ID);
                $show_pages[] = $page;
            }
        }
        $pages = $show_pages;
        return $pages;
    }
    
    function show_category($categories = array())
    {
        global $current_user, $wpdb;
        $curUserdata = get_userdata($current_user->ID);
        $uamOptions = $this->getAdminOptions();
        if (!isset($curUserdata->user_level)) $curUserdata->user_level = null;
        if ($curUserdata->user_level < $uamOptions['full_access_level']) {
            $uamOptions = $this->getAdminOptions();
            if ($this->atAdminPanel) {
                $restrictedcategories = $wpdb->get_results("SELECT category_id
															FROM " . DB_ACCESSGROUP_TO_CATEGORY, ARRAY_A);
                if (isset($restrictedcategories)) {
                    foreach ($categories as $category) {
                        foreach ($restrictedcategories as $restrictedcategory) {
                            $has_access = true;
                            if ($restrictedcategory['category_id'] == $category->term_id) {
                                $has_access = false;
                                $access = $wpdb->get_results("	SELECT category_id
																FROM " . DB_ACCESSGROUP_TO_USER . " agtu, " . DB_ACCESSGROUP_TO_CATEGORY . " agtc
																WHERE agtu.user_id = " . $current_user->ID . "
																	AND agtu.group_id = agtc.group_id
																	AND agtc.category_id = " . $category->term_id, ARRAY_A);
                                if (isset($access)) $has_access = true;
                                if (empty($show_categories[$category->term_id]) && !$has_access) $restrict_categories[$category->term_id] = $category;
                            }
                            if ($has_access) {
                                $show_categories[$category->term_id] = $category;
                                if (isset($restrict_categories[$category->term_id])) unset($restrict_categories[$category->term_id]);
                            }
                        }
                    }
                    if (isset($restrict_categories) && $uamOptions['lock_recursive'] == 'true') {
                        foreach ($restrict_categories as $restrict_category) {
                            $args = array('child_of' => $restrict_category->term_id);
                            $child_categories = get_categories($args);
                            foreach ($child_categories as $child_category) unset($show_categories[$child_category->term_id]);
                        }
                    }
                    if (isset($show_categories)) $categories = $show_categories;
                    else $categories = null;
                }
            } else {
                if ($uamOptions['hide_post'] == 'true' || $uamOptions['hide_page'] == 'true') {
                    $args = array('numberposts' => - 1);
                    $posts = get_posts($args);
                    foreach ($categories as $category) {
                        $count = 0;
                        if (isset($posts)) {
                            foreach ($posts as $curPost) {
                                $post_cat_ids = array();
                                $post_cats = get_the_category($curPost->ID);
                                foreach ($post_cats as $post_cat) {
                                    $post_cat_ids[] = $post_cat->term_id;
                                }
                                if (in_array($category->term_id, $post_cat_ids)) {
                                    if (($uamOptions['hide_post'] == 'true' && $curPost->post_type == "post") || ($uamOptions['hide_page'] == 'true' && $curPost->post_type == "page")) {
                                        if ($this->check_access($curPost->ID)) $count++;
                                    } else {
                                        $count++;
                                    }
                                }
                            }
                        }
                        if (($count != 0 || ($uamOptions['hide_empty_categories'] == 'false' && !$this->atAdminPanel))) {
                            $category->count = $count;
                            $cur_show_categories[$category->term_id] = $category;
                        } elseif ($category->taxonomy == "link_category" || $category->taxonomy == "post_tag") {
                            $show_categories[$category->term_id] = $category;
                        } elseif ($count == 0) {
                            $category->count = $count;
                            $empty_categories[$category->term_id] = $category;
                        }
                    }
                    if ($uamOptions['hide_empty_categories'] == 'true') {
                        if (isset($cur_show_categories)) {
                            foreach ($cur_show_categories as $cur_show_category) {
                                $cur_count = $cur_show_category->count;
                                $show_categories[$cur_show_category->term_id] = $cur_show_category;
                                $cur_cat = $cur_show_category;
                                while ($cur_cat->parent != 0 && isset($empty_categories)) {
                                    if (empty($show_categories[$cur_cat->parent])) {
                                        if (isset($empty_categories[$cur_cat->parent])) {
                                            $cur_empty_cat = $empty_categories[$cur_cat->parent];
                                            $cur_empty_cat->count = $cur_count;
                                            $show_categories[$cur_cat->parent] = $cur_empty_cat;
                                        }
                                    }
                                    $curId = $cur_cat->parent;
                                    $cur_cat = & get_category($curId);
                                }
                            }
                        }
                    } else {
                        if (isset($cur_show_categories)) {
                            foreach ($cur_show_categories as $cur_show_category) {
                                $show_categories[$cur_show_category->term_id] = $cur_show_category;
                            }
                        }
                    }
                    if (isset($show_categories)) $categories = $show_categories;
                    else $categories = null;
                }
            }
        }
        return $categories;
    }
    
    function show_title($title, $post = null)
    {
        $uamOptions = $this->getAdminOptions();
        if (isset($post)) $postId = $post->ID;
        else $postId = null;
        if (!$this->check_access($postId) && $post != null) {
            if ($post->post_type == "post") $title = $uamOptions['post_title'];
            elseif ($post->post_type == "page") $title = $uamOptions['page_title'];
        }
        return $title;
    }
    
    /**
     * The function for the get_previous_post_where and 
     * the get_next_post_where filter.
     * 
     * @param string $sql The current sql string.
     * 
     * @return string
     */
    function showNextPreviousPost($sql)
    {
        $uamOptions = $this->getAdminOptions();
        
        if ($uamOptions['hide_post'] == 'true') {
            $posts = get_posts();
            
            if (isset($posts)) {
                foreach ($posts as $post) {
                    if (!$this->check_access($post->ID)) {
                        $excludedPosts[] = $post->ID;
                    }
                }
                
                if (isset($excludedPosts)) {
                    $excludedPostsStr = implode(",", $excludedPosts);
                    $sql.= "AND ID NOT IN($excludedPostsStr)";
                }
            }
        }
        return $sql;
    }
    
    /**
     * The function for the posts_where filter.
     * 
     * @param string $sql The current sql string.
     * 
     * @return string
     */
    function showPostSql($sql)
    {
        $uamOptions = $this->getAdminOptions();
        
        if (($uamOptions['hide_post'] == 'true' && !is_feed()) 
            || (is_feed() && $uamOptions['protect_feed'] == 'true')
        ) {
            $posts = get_posts();
            
            if (isset($posts)) {
                foreach ($posts as $post) {
                    if (!$this->check_access($post->ID)) {
                        $excludedPosts[] = $post->ID;
                    }
                }
                
                if (isset($excludedPosts)) {
                    $excludedPostsStr = implode(",", $excludedPosts);
                    $sql.= "AND ID NOT IN($excludedPostsStr)";
                }
            }
        }
        
        return $sql;
    }
    
    /**
     * Returns the admin hint.
     * 
     * @param integer $postId The post id we want to check.
     * 
     * @return string
     */
    function adminOutput($postId)
    {
        $output = "";
        
        if (!$this->atAdminPanel) {
            $uamOptions = $this->getAdminOptions();
            
            if ($uamOptions['blog_admin_hint'] == 'true') {
                global $current_user;
                
                $curUserdata = get_userdata($current_user->ID);
                

                if (!isset($curUserdata->user_level)) {
                    return $output;
                }
                
                $uamAccessHandler = new UamAccessHandler();
                $groups = $uamAccessHandler->getUserGroupsForPost($postId);
                
                if ($curUserdata->user_level >= $uamOptions['full_access_level'] 
                    && isset($groups)
                ) { 
                    return "&nbsp;" . $uamOptions['blog_admin_hint_text'];
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Redirects the user to his destination.
     * 
     * @return null
     */
    function redirectUser()
    {
        global $wp_query;
        $uamOptions = $this->getAdminOptions();
        
        if (isset($_GET['getfile'])) {
            $curFileId = $_GET['getfile'];
        }
        
        if ($uamOptions['redirect'] != 'false' 
            && ((!$this->check_access() && !$this->atAdminPanel && empty($curFileId)) 
            || (!$this->check_access($curFileId) && !wp_attachment_is_image($curFileId) && isset($curFileId)))
        ) {
            $curId = null;
            $curPost = & get_post($curId);
            
            if ($uamOptions['redirect'] == 'blog') {
                $url = get_option('siteurl');
            } elseif ($uamOptions['redirect'] == 'custom_page') {
                $postToGo = & get_post($uamOptions['redirect_custom_page']);
                $url = $postToGo->guid;
            } elseif ($uamOptions['redirect'] == 'custom_url') {
                $url = $uamOptions['redirect_custom_url'];
            }
            
            $curPosts = $wp_query->get_posts();
            
            if (isset($curPosts)) {
                foreach ($curPosts as $curPost) {
                    if ($this->check_access($curPost->ID)) {
                        $post_to_show = true;
                        break;
                    }
                }
            }
            
            if ($url != "http://" . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"] 
                && empty($post_to_show)
            ) {
                header("Location: $url");
                exit;
            }
        } elseif (isset($_GET['getfile'])) {
            $curId = $_GET['getfile'];
            $curPost = & get_post($curId);
            
            if ($curPost->post_type == 'attachment' 
                && $this->check_access($curPost->ID)
            ) {
                $file = str_replace(get_option('siteurl') . '/', "", $curPost->guid);
                $fileName = basename($file);
                
                if (file_exists($file)) {
                    $len = filesize($file);
                    header('content-type: ' . $curPost->post_mime_type);
                    header('content-length: ' . $len);
                    
                    if (wp_attachment_is_image($curId)) {
                        readfile($file);
                        exit;
                    } else {
                        header('content-disposition: attachment; filename=' . basename($file));
                        if ($uamOptions['download_type'] == 'fopen') {
                            $fp = fopen($file, 'rb');
                            while (!feof($fp)) {
                                set_time_limit(30);
                                $buffer = fread($fp, 1024);
                                echo $buffer;
                            }
                            exit;
                        } else {
                            readfile($file);
                            exit;
                        }
                    }
                } else {
                    echo 'Error: File not found';
                }
            } elseif (wp_attachment_is_image($curId)) {
                $file = UAM_URLPATH . 'gfx/no_access_pic.png';
                $fileName = basename($file);
                
                if (file_exists($file)) {
                    $len = filesize($file);
                    header('content-type: ' . $curPost->post_mime_type);
                    header('content-length: ' . $len);
                    readfile($file);
                    exit;
                } else {
                    echo 'Error: File not found';
                }
            }
        }
    }
    
    /**
     * Returns the url for a locked file.
     * 
     * @param string  $URL The base url.
     * @param integer $ID  The id of the file.
     * 
     * @return string
     */
    function getFile($URL, $ID)
    {
        $uamOptions = $this->getAdminOptions();
        
        if ($uamOptions['lock_file'] == 'true') {
            $curId = $ID;
            $curPost = & get_post($curId);
            $curParentId = $curPost->post_parent;
            $curParent = & get_post($curParentId);
            $type = explode("/", $curPost->post_mime_type);
            $type = $type[1];
            $fileTypes = $uamOptions['locked_file_types'];
            $fileTypes = explode(",", $fileTypes);
            
            if (in_array($type, $fileTypes) 
                || $uamOptions['lock_file_types'] == 'all'
            ) {
                $curGuid = get_bloginfo('url');
                $URL = $curGuid . '?getfile=' . $curPost->ID;
            }
        }
        
        return $URL;
    }
}