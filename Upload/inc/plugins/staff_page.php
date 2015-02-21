<?php
/**
 * Staff Page v1.0
 * Author: mrnu <mrnuu@icloud.com>
 *
 * Website: https://github.com/mrnu
 * License: GPL Version 3, 29 June 2007
 *
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// Pre-load templates
global $mybb, $templatelist;

if(THIS_SCRIPT == 'memberlist.php' && $mybb->input['action'] == 'staff')
{
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}

	$templatelist .= 'staff_page,staff_page_group_row,staff_page_member_row,staff_page_no_groups,staff_page_no_members,staff_page_user_avatar,postbit_pm,postbit_email';
}

// Public hooks
$plugins->add_hook('memberlist_start', 'staff_page_memberlist');
$plugins->add_hook('showteam_start', 'staff_page_showteam');

// Admin CP hooks
$plugins->add_hook('admin_config_menu', 'staff_page_admin_config_menu');
$plugins->add_hook('admin_config_action_handler', 'staff_page_admin_config_action_handler');
$plugins->add_hook('admin_config_permissions', 'staff_page_admin_config_permissions');
$plugins->add_hook('admin_load', 'staff_page_admin');
$plugins->add_hook('admin_formcontainer_end', 'staff_page_admin_formcontainer_end');
$plugins->add_hook('admin_user_groups_edit_commit', 'staff_page_admin_user_groups_edit_commit');

function staff_page_info()
{
	return array(
		'name'			=> 'Staff Page',
		'description'	=> 'A plugin adds a page, which displays a list of the staff members. The list content can be managed and description of users can be added.',
		'website'		=> 'http://github.com/mrnu/staff-page',
		'author'		=> 'mrnu',
		'authorsite'	=> 'http://github.com/mrnu',
		'version'		=> '1.0',
		'compatibility' => '18*',
		'codename'      => 'staff_page'
	);
}

/**
 * Code hooked to memberlist_start.
 * Display generated staff page.
 *
 */
function staff_page_memberlist()
{
	global $mybb, $lang;

	// Check if the staff page were requested - memberlist.php?action=staff.
	if($mybb->input['action'] == 'staff')
	{
		if(!$mybb->usergroup['canseestaffpage'])
		{
			error_no_permission();
		}

		// Load language
		$lang->load('staff_page');

		add_breadcrumb($lang->staff, 'memberlist.php?action=staff');

		// Get the template and output the page
		$staff_page_template = display_staff_page();
		output_page($staff_page_template);
		exit();
	}
}

/**
* Code hooked to showteam_satrt.
* Redirect to the custom staff page.
*
*/
function staff_page_showteam()
{
	global $mybb;

	if($mybb->settings['staff_page_showteam_redirect'])
	{
		header('Location: memberlist.php?action=staff');
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

	// Get staff members and sort them by groups
	$members = get_staff_members($mybb->input['group_id'] ? $mybb->input['group_id'] : 0);
	$members = sort_members_by_group_id($members);

	// Get groups
	$groups = get_staff_groups();

	if(count($groups))
	{
		// Cut time for online status
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		// Output all groups
		$groups_rows = '';

		foreach($groups as $group)
		{
			// Reset alt_trow()
			$reset = 1;

			if(count($members[$group['id']]))
			{
				// Initialize parser to handle MyCode inside members' description
				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new postParser;
				$parser_options = array(
					'allow_html' => 0,
					'allow_mycode' => 1,
					'allow_smilies' => 1,
					'allow_imgcode' => 1,
					'allow_videocode' => 0,
					'filter_badwords' => 0
				);

				// Output members of group
				$members_rows = '';

				foreach($members[$group['id']] as $member)
				{
					// Format MyBB user's details
					$member['formatted_name'] = format_name($member['username'], $member['usergroup'], $member['displaygroup']);
					$member['profilelink'] = build_profile_link($member['formatted_name'], $member['user_id']);
					$member['profileurl'] = get_profile_link($member['user_id']);

					// For the online image
					if($member['lastactive'] > $timecut && ($member['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $member['lastvisit'] != $member['lastactive'])
					{
						$status = "online";
					}
					else
					{
						$status = "offline";
					}

					// Parse member's description
					$description = $parser->parse_message($member['description'], $parser_options);

					// Show "Send email" link
					$emailcode = '';

					if($member['hideemail'] != 1)
					{
						$post['uid'] = $member['user_id'];
						eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
					}

					// Show "Send PM" link
					$pmcode = '';

					if($member['receivepms'] != 0 && $mybb->settings['enablepms'] != 0 && my_strpos(','.$member['ignorelist'].',', ','.$mybb->user['uid'].',') === false)
					{
						$post['uid'] = $member['user_id'];
						eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
					}

					// Show avatar
					$useravatar = format_avatar(htmlspecialchars_uni($member['avatar']), $member['avatardimensions'], my_strtolower($mybb->settings['staff_page_maxavatarsize']));
					eval("\$member['avatar'] = \"".$templates->get("staff_page_user_avatar")."\";");

					// Alternate rows.
					$bgcolor = alt_trow($reset);

					// Don't reset alt_trow()
					$reset = 0;

					// Output member row template
					eval('$members_rows .= "'.$templates->get('staff_page_member_row').'";');


				}
			}
			// No members
			else
			{
				eval('$members_rows = "'.$templates->get('staff_page_no_members').'";');
			}

			eval('$groups_rows .= "'.$templates->get('staff_page_group_row').'";');
		}
	}
	// No groups
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

	if($group_id)
	{
		$where_clause = 'm.group_id = ' . intval($group_id);
	}
	else
	{
		$where_clause = '1';
	}

	//$query = $db->simple_select('staff_page_members', '*', $where_clause, array('order_by'	=>	'user_id', 'order_dir'	=>	'ASC'));

	$query = $db->query('
		SELECT m.*, u.username, u.uid, u.usergroup, u.displaygroup, u.avatar, u.avatardimensions, u.hideemail, u.receivepms, u.ignorelist
		FROM '.TABLE_PREFIX.'staff_page_members m
		LEFT JOIN '.TABLE_PREFIX.'users u ON(m.user_id = u.uid)
		WHERE '.$where_clause.'
		ORDER BY m.list_order ASC, u.username ASC
	');

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

	$query = $db->simple_select('staff_page_groups', '*', '1', array('order_by' => 'list_order', 'order_dir' => 'asc'));

	$groups = array();

	if($db->num_rows($query))
	{
		while($row = $db->fetch_array($query))
		{
			$groups[] = $row;
		}
	}

	$cache->update('staff_page_groups', $groups);
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

/**
 * Code hooked to page_admin_config_menu.
 * Adds link to the staff page configuration in the config menu.
 *
 */
function staff_page_admin_config_menu($sub_menu)
{
	global $lang;

	$lang->load('staff_page');

	$sub_menu[] = array('id' => 'staff_page', 'title' => $lang->staff_page, 'link' => 'index.php?module=config-staff_page');

	return $sub_menu;
}

/**
 * Code hooked to page_admin_config_action_handler.
 * Adds action for the staff page configuration
 *
 */
function staff_page_admin_config_action_handler($actions)
{
	$actions['staff_page'] = array('active' => 'staff_page', 'file' => 'staff_page');

	return $actions;
}

/**
 * Code hooked to page_admin_config_permissions.
 * Add permission to see the staff page configuration.
 *
 */
function staff_page_admin_config_permissions($admin_permissions)
{
	global $lang;

	$lang->load('staff_page');

	$admin_permissions['staff_page'] = $lang->staff_page_admin_permission;

	return $admin_permissions;
}




/**
* Code hooked to page_admin_config_permissions.
* Add group permissions which allows groups to see the staff page.
*
*/
function staff_page_admin_formcontainer_end()
{
	global $run_module, $form_container, $lang, $form, $mybb;

	if($run_module == "user" && !empty($form_container->_title) && !empty($lang->users_permissions) && $form_container->_title == $lang->users_permissions) {

		$options = array();
		$options[] = $form->generate_check_box('canseestaffpage', 1, $lang->can_see_staff_page, array('checked' => $mybb->input['canseestaffpage']));

		$form_container->output_row($lang->staff_page, '', '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $options).'</div>');
	}
}

/**
* Code hooked to admin_user_groups_edit_commit
* Update group permissions which allows groups to see the staff page.
*
*/
function staff_page_admin_user_groups_edit_commit($admin_permissions)
{
	global $updated_group, $mybb;

	$updated_group['canseestaffpage'] = $mybb->input['canseestaffpage'];
}

/**
* Code hooked to admin_load.
* The code of our configuration panel.
*
*/
function staff_page_admin()
{
	global $db, $lang, $mybb, $page, $run_module, $action_file;

	if($run_module == 'config' && $action_file == 'staff_page')
	{
		$lang->load('staff_page');

		$page->add_breadcrumb_item($lang->staff_page, 'index.php?module=config-staff_page');

		$sub_tabs['manage_staff_page'] = array(
			'title'       => $lang->staff_page,
			'link'        => 'index.php?module=config-staff_page',
			'description' => $lang->staff_page_description
		);

		$sub_tabs['add_member'] = array(
			'title' => $lang->add_member,
			'link'  => 'index.php?module=config-staff_page&amp;action=add_member',
			'description' => $lang->add_member_description
		);

		$sub_tabs['add_group'] = array(
			'title' => $lang->add_group,
			'link'  => 'index.php?module=config-staff_page&amp;action=add_group',
			'description'	=>	$lang->add_group_description
		);

		// View groups and members
		if(!$mybb->input['action'])
		{
			$page->output_header($lang->staff_page);
			$page->output_nav_tabs($sub_tabs, 'manage_staff_page');

			$table = new Table;
			$table->construct_header($lang->name);
			$table->construct_header($lang->order);
			$table->construct_header($lang->action, array('class' => "align_center", 'colspan' => 2));

			$members = get_staff_members();
			$members = sort_members_by_group_id($members);
			$groups = get_staff_groups();

			if(count($groups))
			{
				foreach($groups as $group)
				{
					$table->construct_cell('<div class="largetext"><strong>'.$group['name'].'</strong></div><div class="smalltext">'.$group['description'].'</div>');
					$table->construct_cell($group['list_order']);
					$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=edit_group&amp;uid={$group['id']}\">{$lang->edit}</a>");
					$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=delete_group&amp;uid={$group['id']}\">{$lang->delete}</a>");
					$table->construct_row();

					if(count($members[$group['id']]))
					{
						foreach($members[$group['id']] as $member)
						{
							$member['formatted_name'] = format_name($member['username'], $member['usergroup'], $member['displaygroup']);

							$table->construct_cell('<div style="padding-left: 40px;" class="largetext">'.$member['formatted_name'].'</div><div class="smalltext" style="padding-left: 50px;">'.$member['description'].'</div>');
							$table->construct_cell($member['list_order']);
							$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=edit_member&amp;uid={$member['id']}\">{$lang->edit}</a>");
							$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=delete_member&amp;uid={$member['id']}\">{$lang->delete}</a>");
							$table->construct_row();
						}
					}
				}
			}
			else
			{
				$table->construct_cell($lang->no_groups, array('colspan' => 4));
				$table->construct_row();
			}

			$table->output($lang->staff_page);

			$page->output_footer();
			exit();
		}

		// Add group
		if($mybb->input['action'] == 'add_group')
		{
			$page->output_header($lang->staff_page.' - '.$lang->add_group);
			$page->output_nav_tabs($sub_tabs, 'add_group');

			if($mybb->request_method == 'post')
			{
				if(!trim($mybb->input['name']))
				{
					$errors[] = $lang->empty_name;
				}

				if(!$errors)
				{
					$insert_array = array(
						'name'       => $db->escape_string($mybb->input['name']),
						'description' => $db->escape_string($mybb->input['description']),
						'list_order'	=>	intval($mybb->input['list_order'])
					);

					$db->insert_query('staff_page_groups', $insert_array);

					recache_staff_groups();

					admin_redirect('index.php?module=config-staff_page');
				}
			}

			if($errors)
			{
				$page->output_inline_error($errors);
			}

			$form = new Form('index.php?module=config-staff_page&amp;action=add_group', 'post', 'add');
			$form_container = new FormContainer($lang->add_group);
			$form_container->output_row($lang->name, '', $form->generate_text_box('name', $mybb->input['name']));
			$form_container->output_row($lang->order, '', $form->generate_text_box('list_order', $mybb->input['list_order']));
			$form_container->output_row($lang->description, '', $form->generate_text_area('description', $mybb->input['description']));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}

		// Add member
		if($mybb->input['action'] == 'add_member')
		{
			$page->output_header($lang->staff_page.' - '.$lang->add_member);
			$page->output_nav_tabs($sub_tabs, 'add_member');
			$page->add_breadcrumb_item($lang->add_member);

			$groups = get_staff_groups();

			if(!count($groups))
			{
				flash_message($lang->add_group_first, 'error');
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->request_method == 'post')
			{
				// Check if chosen group exists
				$i = 0;

				foreach($groups as $group)
				{
					if($group['id'] == $mybb->input['group_id'])
					{
						$i++;
						break;
					}
				}

				if(!$i)
				{
					$errors[] = $lang->wrong_group;
				}

				// Check if chosen user exists
				if($mybb->input['name'])
				{
					$query = $db->simple_select('users', 'uid', 'username = \''.$db->escape_string($mybb->input['name']).'\'');
					$user = $db->fetch_array($query);
				}
				else
				{
					$user = array('uid' => 0);
				}

				if(!$user['uid'])
				{
					$errors[] = $lang->user_not_exist;
				}

				// Insert member
				if(!$errors)
				{
					$insert_array = array(
						'user_id'	=>	$user['uid'],
						'group_id'	=>	intval($mybb->input['group_id']),
						'list_order'	=>	intval($mybb->input['list_order'])
					);

					$db->insert_query('staff_page_members', $insert_array);

					admin_redirect('index.php?module=config-staff_page');
				}
			}

			if($errors)
			{
				$page->output_inline_error($errors);
			}

			// Prepare groups array to be a select list
			$groups_select = array();

			foreach($groups as $group)
			{
				$groups_select[$group['id']] = $group['name'];
			}

			// Generate a form
			$form = new Form('index.php?module=config-staff_page&amp;action=add_member', 'post', 'add');
			$form_container = new FormContainer($lang->add_member);
			$form_container->output_row($lang->name, '', $form->generate_text_box('name', $mybb->input['name']));
			$form_container->output_row($lang->order, '', $form->generate_text_box('list_order', $mybb->input['list_order']));
			$form_container->output_row($lang->group, '', $form->generate_select_box('group_id', $groups_select, $mybb->input['group_id'], array('id' => 'group_id')));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}

		// Delete group
		if($mybb->input['action'] == 'delete_group')
		{
			$query = $db->simple_select('staff_page_groups', '*', 'id=' . intval($mybb->input['uid']));
			$group = $db->fetch_array($query);

			if(!$group['id'])
			{
				flash_message($lang->group_not_exist, 'error');
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->input['no'])
			{
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->request_method == 'post')
			{
				$db->delete_query('staff_page_groups', 'id = '.$group['id']);
				$db->delete_query('staff_page_members', 'group_id = '.$group['id']);

				recache_staff_groups();

				log_admin_action($group['id']);

				flash_message($lang->group_deleted, 'success');
				admin_redirect('index.php?module=config-staff_page');
			}
			else
			{
				$page->output_confirm_action("index.php?module=config-staff_page&amp;action=delete_group&amp;uid={$group['id']}", $lang->sprintf($lang->do_you_want_to_delete_group, $group['name']));
			}
		}

		// Delete member
		if($mybb->input['action'] == 'delete_member')
		{
			$query = $db->simple_select('staff_page_members', '*', 'id=' . intval($mybb->input['uid']));
			$member = $db->fetch_array($query);
			$user = get_user($member['user_id']);

			if(!$member['id'])
			{
				flash_message($lang->user_not_exist, 'error');
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->input['no'])
			{
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->request_method == 'post')
			{
				$db->delete_query('staff_page_members', 'id = '.$member['id']);

				recache_staff_groups();

				log_admin_action($member['id']);

				flash_message($lang->member_deleted, 'success');
				admin_redirect('index.php?module=config-staff_page');
			}
			else
			{
				$page->output_confirm_action("index.php?module=config-staff_page&amp;action=delete_member&amp;uid={$member['id']}", $lang->sprintf($lang->do_you_want_to_delete_member, $user['username']));
			}
		}

		// Edit member
		if($mybb->input['action'] == 'edit_member')
		{
			$query = $db->simple_select('staff_page_members', '*', 'id=' . intval($mybb->input['uid']));
			$member = $db->fetch_array($query);
			$user = get_user($member['user_id']);

			if(!$member['id'])
			{
				flash_message($lang->user_not_exist, 'error');
				admin_redirect('index.php?module=config-staff_page');
			}

			$groups = get_staff_groups();

			if($mybb->request_method == 'post')
			{
				// Check if chosen group exists
				$i = 0;

				foreach($groups as $group)
				{
					if($group['id'] == $mybb->input['group_id'])
					{
						$i++;
						break;
					}
				}

				if(!$i)
				{
					$errors[] = $lang->wrong_group;
				}


				if(!$errors)
				{
					$update_array = array(
						'description'	=>	$db->escape_string($mybb->input['description']),
						'group_id'	=>	intval($mybb->input['group_id']),
						'list_order'	=>	intval($mybb->input['list_order'])
					);

					$db->update_query('staff_page_members', $update_array, 'id=' . $member['id']);

					recache_staff_groups();

					log_admin_action($member['id']);

					flash_message($lang->member_saved, 'success');
					admin_redirect('index.php?module=config-staff_page');
				}
			}

			$page->add_breadcrumb_item($lang->edit_member);
			$page->output_header($lang->staff_page.' - '.$lang->edit_member);


			if($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{
				$mybb->input = $member;
			}

			// Prepare groups array to be a select list
			$groups_select = array();

			foreach($groups as $group)
			{
				$groups_select[$group['id']] = $group['name'];
			}

			// Generate a form
			$form = new Form('index.php?module=config-staff_page&amp;action=edit_member&amp;uid=' . $member['id'], 'post', 'edit');
			echo $form->generate_hidden_field('uid', $member['id']);

			$form_container = new FormContainer($lang->edit_member);
			$form_container->output_row($lang->name, '', $user['username']);
			$form_container->output_row($lang->order, '', $form->generate_text_box('list_order', $mybb->input['list_order']));
			$form_container->output_row($lang->description, '', $form->generate_text_area('description', $mybb->input['description']));
			$form_container->output_row($lang->group, '', $form->generate_select_box('group_id', $groups_select, $mybb->input['group_id'], array('id' => 'group_id')));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);
			$buttons[] = $form->generate_reset_button($lang->reset);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}

		// Edit group
		if($mybb->input['action'] == 'edit_group')
		{
			$query = $db->simple_select('staff_page_groups', '*', 'id=' . intval($mybb->input['uid']));
			$group = $db->fetch_array($query);

			if(!$group['id'])
			{
				flash_message($lang->group_not_exist, 'error');
				admin_redirect('index.php?module=config-staff_page');
			}

			if($mybb->request_method == 'post')
			{
				if(!$mybb->input['name'])
				{
					$error[] = $lang->empty_name;
				}

				if(!$errors)
				{
					$update_array = array(
						'description'	=>	$db->escape_string($mybb->input['description']),
						'list_order'	=>	intval($mybb->input['list_order']),
						'name'	=>	$db->escape_string($mybb->input['name'])
					);

					$db->update_query('staff_page_groups', $update_array, 'id=' . $group['id']);

					recache_staff_groups();

					log_admin_action($group['id']);

					flash_message($lang->group_saved, 'success');
					admin_redirect('index.php?module=config-staff_page');
				}
			}

			$page->add_breadcrumb_item($lang->edit_group);
			$page->output_header($lang->staff_page.' - '.$lang->edit_group);


			if($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{
				$mybb->input = $group;
			}

			// Generate a form
			$form = new Form('index.php?module=config-staff_page&amp;action=edit_group&amp;uid=' . $group['id'], 'post', 'edit');
			echo $form->generate_hidden_field('uid', $group['id']);

			$form_container = new FormContainer($lang->edit_group);
			$form_container->output_row($lang->name, '', $form->generate_text_box('name', $mybb->input['name']));
			$form_container->output_row($lang->description, '', $form->generate_text_area('description', $mybb->input['description']));
			$form_container->output_row($lang->order, '', $form->generate_text_box('list_order', $mybb->input['list_order']));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);
			$buttons[] = $form->generate_reset_button($lang->reset);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}
	}
}

/**
 * Checks if plugin is installed.
 *
 */
function staff_page_is_installed()
{
	global $db;

	if($db->table_exists('staff_page_groups'))
 	{
  		return true;
	}

	return false;
}

/**
 * That's what happens when the plugin is uninstalled.
 *
 */
function staff_page_uninstall()
{
	global $db, $cache;

	// Remove settings
	$db->delete_query('settings', "name IN ('staff_page_maxavatarsize', 'staff_page_showteam_redirect')");
	$db->delete_query('settinggroups', "name = 'staff_page'");
	rebuild_settings();

	// Delete admin permissions
	change_admin_permission('config', 'staff_page', 0);

	// Remove group permissions
	if($db->field_exists('canseestaffpage', 'usergroups'))
	{
		$db->drop_column('usergroups', 'canseestaffpage');
	}

	// Update the cache
	$cache->update_usergroups();

	// Delete DB schema
	if($db->table_exists('staff_page_members'))
	{
		$db->drop_table('staff_page_members');
	}

	if($db->table_exists('staff_page_groups'))
	{
		$db->drop_table('staff_page_groups');
	}
}

/**
 * Installation of the plugin.
 *
 */
function staff_page_install()
{
	global $db, $lang, $cache;

	// Load language file for settings
	$lang->load('staff_page');

	// Add settings
	$setting_group = array(
		'name' => 'staff_page',
		'title' => $db->escape_string($lang->staff_page_settings),
		'description' => '',
		'disporder' => 99,
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $setting_group);

	$setting_array = array(

		'staff_page_maxavatarsize' => array(
			'title' => $db->escape_string($lang->avatar_size),
			'description' => $db->escape_string($lang->avatar_size_description),
			'optionscode' => 'text',
			'value' => '100x100',
			'disporder' => 1
		),

		'staff_page_showteam_redirect' => array(
			'title' => $db->escape_string($lang->enable_showteam_redirection),
			'description' => $db->escape_string($lang->enable_showteam_redirection_description),
			'optionscode' => 'yesno',
			'value' => 1,
			'disporder' => 2
		)

	);

	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

	// Rebuild settings
	rebuild_settings();

	// Add group permissions
	if(!$db->field_exists('canseestaffpage', 'usergroups'))
	{
		$db->add_column('usergroups', 'canseestaffpage', 'tinyint(1) NOT NULL default \'1\'');
	}

	// Update the cache
	$cache->update_usergroups();

	// Create DB schema
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "staff_page_members (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`user_id` int(11) DEFAULT NULL,
					`group_id` int(11) DEFAULT NULL,
					`list_order` tinyint(127) NOT NULL DEFAULT '0',
					`description` text,
					PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");

	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "staff_page_groups (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`name` varchar(256) DEFAULT NULL,
					`list_order` tinyint(127) NOT NULL DEFAULT '0',
					`description` varchar(256) DEFAULT NULL,
					PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
}

/**
 * Deactivation of plugin.
 *
 */
function staff_page_deactivate()
{
	global $db;

	// Delete cache
	$db->delete_query('datacache', 'title = \'staff_page_groups\'');

	// Delete templates
	$templates = array(
		'staff_page',
		'staff_page_group_row',
		'staff_page_member_row',
		'staff_page_no_groups',
		'staff_page_no_members',
		'staff_page_user_avatar'
	);

	$db->delete_query('templates', 'title IN(\'' . implode('\',\'', $templates) . '\')');

}

/**
 * Activation of plugin.
 *
 */
function staff_page_activate()
{
	global $db;

	// Update schema from 0.3.x to 1.0
	if($db->field_exists('order', 'staff_page_groups'))
	{
		$db->drop_column('staff_page_groups', '`order`');
	}

	if(!$db->field_exists('list_order', 'staff_page_groups'))
	{
		$db->add_column('staff_page_groups', 'list_order', 'tinyint(127) NOT NULL default \'0\'');
	}

	if(!$db->field_exists('list_order', 'staff_page_members'))
	{
		$db->add_column('staff_page_members', 'list_order', 'tinyint(127) NOT NULL default \'0\'');
	}

	// Recache groups
	recache_staff_groups();

	// Install templates
	$templates_array = array();


	// staff_page
	$template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->staff}</title>
{$headerinclude}
</head>
<body>
{$header}

	{$groups_rows}

{$footer}
</body>
</html>';

	$templates_array[] = array(
		'title'    => 'staff_page',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	// staff_page_group_row
	$template = '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="2">
		<strong>{$group[\'name\']}</strong>
		<br />
		<div class="smalltext">
				{$group[\'description\']}
		</div>
		</td>
	</tr>
	{$members_rows}
</table>
<br />';
	$templates_array[] = array(
		'title'    => 'staff_page_group_row',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	// staff_page_member_row
	$template ='<tr>
<td class="{$bgcolor}" align="center" width="1%">
	<a href="{$member[\'profileurl\']}">
		{$member[\'avatar\']}
	</a>
</td>
<td class="{$bgcolor}">
	<div class="largetext">{$member[\'profilelink\']}</div>
	<div class="smalltext">{$description}</div>
	<div class="postbit_buttons">{$emailcode}{$pmcode}</div>
</td>
</tr>';

	$templates_array[] = array(
		'title'    => 'staff_page_member_row',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	// staff_page_no_groups
	$template = '<div class="red_alert">
	{$lang->no_groups}
</div>';

	$templates_array[] = array(
		'title'    => 'staff_page_no_groups',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	// staff_page_no_members
	$template = '<tr>
	<td class="trow1">
		{$lang->no_members}
	</td>
</tr>';

	$templates_array[] = array(
		'title'    => 'staff_page_no_members',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	// staff_page_user_avatar
	$template = '<img src="{$useravatar[\'image\']}" alt="" {$useravatar[\'width_height\']} />';

	$templates_array[] = array(
		'title'    => 'staff_page_user_avatar',
		'template' => $db->escape_string($template),
		'sid'      => '-1',
		'version'  => '',
		'dateline' => TIME_NOW
	);


	foreach ($templates_array as $row)
	{
		$db->insert_query('templates', $row);
	}
}
