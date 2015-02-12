<?php
/**
 * Staff Page pre-alpha 0.1
 * Author: mrnu <mrnuu@icloud.com>
 *
 * Website: https://github.com/mrnu
 * License: http://opensource.org/licenses/MIT
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Pre-load templates
if(my_strpos($_SERVER['PHP_SELF'], 'memberlist.php') && strtolower($_GET['action']) == 'staff')
{
	global $templatelist;

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}

	$templatelist .= 'staff_page,staff_page_group_row,staff_page_member_row,staff_page_no_groups,staff_page_no_members,staff_page_user_avatar,postbit_pm,postbit_email';
}

// Hooks
$plugins->add_hook("memberlist_start", "memberlist_hook");

function staff_page_info()
{
	return array(
		"name"			=> "Staff Page",
		"description"	=> "A plugin adds a page, which displays a list of the staff members. The list content can be managed and description of users can be added.",
		"website"		=> "http://github.com/mrnu/staff-page",
		"author"		=> "mrnu",
		"authorsite"	=> "http://github.com/mrnu",
		"version"		=> "pre-alpha 0.1",
		"guid" 			=> "",
		"compatibility" => "18*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function hello_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function hello_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function hello_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function hello_deactivate()
 * {
 * }
 */

/**
 * Code hooked to memberlist_start.
 * Display generated staff page.
 *
 */
function memberlist_hook()
{
	// Only for testing purposes.
	recache_staff_groups();

	global $mybb, $lang;

	// Check if the staff page were requested - memberlist.php?action=staff.
	if(strtolower($mybb->input['action']) == 'staff')
	{
		$lang->load('staff_page');

		add_breadcrumb($lang->staff, 'memberlist.php?action=staff');
		$staff_page_template = display_staff_page();
		output_page($staff_page_template);
		exit();
	}
}


/**
 * Function which generates the staff page.
 *
 * @return string Staff page template.
 */
function display_staff_page()
{
	global $db, $lang, $theme, $templates, $plugins, $mybb, $cache;
	global $header, $headerinclude, $footer;

	$members = get_staff_members($mybb->input['group_id'] ? $mybb->input['group_id'] : 0);
	$members = sort_members_by_group_id($members);
	$groups = get_staff_groups();

	if(count($groups))
	{
		$groups_rows = '';

		foreach($groups as $group)
		{
			// Reset alt_trow()
			$reset = 1;

			if(count($members[$group['id']]))
			{
				// Initialize parser
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
				$parser_options = array(
					"allow_html" => 0,
					"allow_mycode" => 1,
					"allow_smilies" => 1,
					"allow_imgcode" => 1,
					"allow_videocode" => 0,
					"filter_badwords" => 0
				);

				$members_rows = '';

				foreach($members[$group['id']] as $member)
				{
					// Get MyBB user details and format it
					$user = get_user($member['user_id']);
					$user['formatted_name'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['formatted_name'], $user['uid']);

					// Parse member's description
					$description = $parser->parse_message($member['description'], $parser_options);

					// Show "Send email" link
					$emailcode = '';

					if($user['hideemail'] != 1)
					{
						eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
					}

					// Show "Send PM" link
					$pmcode = '';

					if($user['receivepms'] != 0 && $mybb->settings['enablepms'] != 0 && my_strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
					{
						$post['uid'] = $user['uid'];
						eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
					}

					// Show avatar
					$useravatar = format_avatar(htmlspecialchars_uni($user['avatar']), $user['avatardimensions'], my_strtolower($mybb->settings['staff_page_maxavatarsize']));
					eval("\$user['avatar'] = \"".$templates->get("staff_page_user_avatar")."\";");

					// Alternate rows.
					$bgcolor = alt_trow($reset);

					// Don't reset alt_trow()
					$reset = 0;

					// Output member row template
					eval('$members_rows .= "'.$templates->get('staff_page_member_row').'";');


				}
			}
			else
			{
				eval('$members_rows = "'.$templates->get('staff_page_no_members').'";');
			}

			eval('$groups_rows .= "'.$templates->get('staff_page_group_row').'";');
		}
	}
	else
	{
		eval('$groups_rows .= "'.$templates->get('staff_page_no_groups').'";');
	}


	eval('$template = "'.$templates->get('staff_page').'";');
	return $template;
}


/**
 * Get members of staff.
 * @param int $group_id Group ID.
 *
 * @return array Members list.
 */
function get_staff_members($group_id = 0)
{
	global $db;

	$members = array();

	$query = $db->simple_select('staff_page_members', '*', $group_id ? ('group_id = ' . intval($group_id)) : '1' );

	if($db->num_rows($query))
	{
		while($row = $db->fetch_array($query))
		{
			$members[] = $row;
		}
	}

	return $members;
}

/**
 * Update the staff groups cache.
 *
 */
function recache_staff_groups()
{
	global $db, $cache;

	$query = $db->simple_select('staff_page_groups', '*', '1', array('order_by' => '`order`', 'order_dir' => 'asc'));

	if($db->num_rows($query))
	{
		while($row = $db->fetch_array($query))
		{
			$groups[] = $row;
		}
	}

	$cache->update('staff_page_groups',$groups);
}

/**
 * Get the staff groups from cachestore.
 *
 * @return array List of staff groups.
 */
function get_staff_groups()
{
	global $cache;

	$groups = $cache->read('staff_page_groups');

	if(!is_array($groups))
	{
		return array();
	}

	return $groups;
}

/**
 * Sort members array by group ID.
 * Adds group ID as a main key.
 *
 * @return array
 */
function sort_members_by_group_id($members_array)
{
	if(!count($members_array))
	{
		return array();
	}

	$new_array = array();

	foreach($members_array as $row)
	{
		$new_array[$row['group_id']][] = $row;
	}

	return $new_array;
}
