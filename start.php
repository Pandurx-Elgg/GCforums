<<<<<<< HEAD
<?php

/* GCForums
 *
 * @author Christine Yu <internalfire5@live.com>
 *
 */

elgg_register_event_handler('init', 'system', 'gcforums_init');

define("TOTAL_POST", 1);
define("TOTAL_TOPICS", 2);
define("RECENT_POST", 3);

function gcforums_init()
{
	elgg_register_library('elgg:gcforums:functions', elgg_get_plugins_path() . 'gcforums/lib/functions.php');

	$action_path = elgg_get_plugins_path().'gcforums/actions/gcforums';

	elgg_register_css('gcforums-css', 'mod/gcforums/css/gcforums-table.css');						// styling the forums table
	elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'gcforums_owner_block_menu');	// register menu item in group
	elgg_register_page_handler('gcforums', 'gcforums_page_handler');								// page handler for forums
	add_group_tool_option('forums', elgg_echo('gcforums:enable_group_forums'), false);				// add option for user to enable

	// actions for forum creation/editing/deletion (.../action/gcforums/[action]/...)
	elgg_register_action('gcforums/edit', $action_path.'/edit.php');
	elgg_register_action('gcforums/delete', $action_path.'/delete.php');
	elgg_register_action('gcforums/create', $action_path.'/create.php');
	elgg_register_action('gcforums/subscribe', $action_path.'/subscribe.php');

	elgg_register_action('gcforums/move_topic', $action_path.'/move_topic.php');
	// put a menu item in the site navigation (JMP request), placed in career dropdown
	elgg_register_menu_item('subSite', array(
		'name' => 'Forum',
		'text' => elgg_echo('gcforums:jmp_menu'),
		'href' => elgg_echo('gcforums:jmp_url'),
	));
}


function gcforums_owner_block_menu($hook, $type, $return, $params)
{
	$entity = elgg_extract('entity', $params);
	if ($entity->type === 'group' && $entity->forums_enable === 'yes') { // display only in group menu and only when user selected to enable forums in group
		$url = "gcforums/group/{$params['entity']->guid}";
		$item = new ElggMenuItem('gcforums', elgg_echo('gcforums:group_nav_label'), $url);
		$return[] = $item;
		return $return;
	}
}


/*
 * Page Handler
 */
function gcforums_page_handler($page)
{
	$params = array();

	switch ($page[0]) {
		case 'create':
			gatekeeper();
			$params = render_create_forms($page[2], $page[1]);
			break;

		case 'edit':
			gatekeeper();
			$params = render_edit_forms($page[1]);
			break;

		case 'topic':
			$params = render_forum_topics($page[2]);
			break;

		case 'view':
			$params = render_forums($page[1]);
			break;

		case 'group':
			$params = render_forums($page[1]);
			break;

		default:
			return false;
	}

	$body = elgg_view_layout('forum-content', $params);
	echo elgg_view_page($params['title'], $body);
}


function render_create_forms($entity_guid, $entity_type)
{
	// this is the current form (new forum will be placed in this forum)
	$entity = get_entity($entity_guid);
	assemble_forum_breadcrumb($entity);
	$group_guid = gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID());
	elgg_set_page_owner_guid();
	$vars['current_entity'] = $entity_guid;
	$vars['entity_type'] = $entity_type;
	$vars['group_guid'] = $group_guid;

	$content = elgg_view_form('gcforums/create', array(), $vars);
	$title = elgg_echo('gcforums:edit:new_forum:heading', array(elgg_echo("gcforums:translate:{$entity_type}")));

	$return['filter'] = '';
	$return['title'] = $title;
	$return['content'] = $content;
	return $return;
}

function render_edit_forms($entity_guid, $entity_type = '')
{
	$entity = get_entity($entity_guid);
	assemble_forum_breadcrumb($entity);
	elgg_set_page_owner_guid(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
	$vars['entity_guid'] = $entity_guid;

	$content = elgg_view_form('gcforums/edit', array(), $vars);
	$title = elgg_echo('gcforums:edit:edit_forum:heading', array(elgg_echo("gcforums:translate:{$entity->getSubtype()}")));

	$return['filter'] = '';
	$return['title'] = $title;
	$return['content'] = $content;
	return $return;
}

/**
 * recursively go through the forums and return group entity
 * @return integer
 */
function gcforums_get_forum_in_group($entity_guid_static, $entity_guid)
{
	$entity = get_entity($entity_guid);
	// (base) stop recursing when we reach group guid
	if ($entity instanceof ElggGroup) {
		return $entity_guid;
	} else {
		return gcforums_get_forum_in_group($entity_guid_static, $entity->getContainerGUID());
	}
}


function render_forum_topics($topic_guid)
{
	elgg_load_css('gcforums-css');
	$entity = get_entity($topic_guid);

	if ($entity instanceof ElggEntity) {

		$dbprefix = elgg_get_config('dbprefix');
		$base_url = elgg_get_site_entity()->getURL();
		$group_guid = gcforums_get_forum_in_group($topic_guid, $topic_guid);

		// set the breadcrumb trail
		assemble_forum_breadcrumb($entity);

		if ($entity->getSubtype() === 'hjforumtopic') {

			$options = render_edit_options($entity->getGUID(), $entity->getGUID());
			if ($options == '') $options = '-';
			$topic = get_entity($topic_guid);
			$title = $topic->title;
			$description = $topic->description;

			/// owner information
			$owner = $topic->getOwnerEntity();
			$timestamp = date('Y-m-d H:i:s', $topic->time_created);
			$params = array(
				'entity' => $topic,
				'title' => false,
			);

			$summary = elgg_view('object/elements/summary', $params);
			$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$topic->guid})" : "";
			$owner_icon = elgg_view_entity_icon($topic->getOwnerEntity(), 'medium');

			$content .= "
			<div class='topic-owner-information-content'>
				<div class='topic-information-options'>{$options} {$admin_only}</div>
				<div class='topic-owner-icon'>{$owner_icon}</div>
				<div class='topic-owner-information'><b>".elgg_echo('gcforums:user:name')."</b> {$owner->name} ({$owner->username})</div>
				<div class='topic-owner-information'><b>".elgg_echo('gcforums:user:email')."</b> {$owner->email}</div>
				<div class='topic-owner-information'><b>".elgg_echo('gcforums:user:posting')."</b> {$timestamp}</div>
			</div>";

			$content .= "<div class='topic-content'>{$topic->description}</div>";
			$content .= "<h3>Comments</h3>";

			// some comments were accidentally saved as private 0
			$old_access = elgg_set_ignore_access();
			$comments = elgg_get_entities(array(
				'types' => 'object',
				'container_guids' => $topic->guid,
				'limit' => 0,
			));
			
			/// comments
			$content .= "<div class='topic-main-comments'>";
			foreach ($comments as $comment) {
				// condition, do not change access id all the time
				if ($comment->access_id != $entity->access_id) {
					$comment->access_id = $entity->access_id;
					$comment->save();
				}

				$options = render_edit_options($comment->getGUID(), $comment->getGUID());
				if ($options == '') $options = '-';
				$admin_only = (elgg_is_admin_logged_in()) ? "(id:{$comment->guid} | acl:{$comment->access_id})" : "";
				$owner_icon = elgg_view_entity_icon($comment->getOwnerEntity(), 'small');
				$content .= "
				<div class='topic-comments'>
					<div class='topic-comment-options'>{$options} {$admin_only}</div>
					<div class='comment-owner-information-content'>
						<div class='comment-owner-icon'>{$owner_icon} {$comment->getOwnerEntity()->email}</div>
					</div>
					<div class='topic-comment-content'>{$comment->description}</div>
				</div> <br/>";
			}
			$content .= "</div>";
			
			elgg_set_ignore_access($old_access);

			$vars['group_guid'] = $group_guid;
			$vars['topic_guid'] = $topic->guid;
			$vars['current_entity'] = $topic->guid;
			$vars['topic_access'] = $topic->access_id;
			$vars['entity_type'] = 'hjforumpost';

			if (elgg_is_logged_in() && check_entity_relationship(elgg_get_logged_in_user_entity()->getGUID(), 'member', $group_guid)) {
				$topic_content .= elgg_view_form('gcforums/create', array(), $vars);
				$content .= $topic_content;
			}

			$return['filter'] = '';
			$return['title'] = $title;
			$return['content'] = $content;
			
		}

	} else {

		$return['filter'] = '';
		$return['title'] = '404 - '.elgg_echo('gcforums:notfound');
		$return['content'] = elgg_echo('gcforums:notfound');
		
	}
	return $return;
}



/**
 * @param ElggEntity $entity
 */
function assemble_forum_breadcrumb($entity)
{
	$forum_guid = $entity->guid;
	if ($entity instanceof ElggGroup) {
		elgg_set_page_owner_guid($entity->getGUID());
		elgg_push_breadcrumb(gc_explode_translation($entity->name, get_current_language()), $entity->getURL());
		elgg_push_breadcrumb('Group Forums');
	} else {
		elgg_set_page_owner_guid(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
		$breadcrumb_array = array();
		$breadcrumb_array = assemble_nested_forums(array(), $forum_guid, $forum_guid);
		$breadcrumb_array = array_reverse($breadcrumb_array);

		foreach ($breadcrumb_array as $trail_id => $trail) {
			elgg_push_breadcrumb(gc_explode_translation($trail[1], get_current_language()), $trail[2]);
		}
	}
}

/**
 * Create list of options to modify forums
 *
 * @param int $forum_guid
 *
 */
function render_forums($forum_guid)
{
	elgg_load_css('gcforums-css');
	$entity = get_entity($forum_guid);
	

	if ($entity instanceof ElggEntity) {

		$dbprefix = elgg_get_config('dbprefix');
		$base_url = elgg_get_site_entity()->getURL();
		$group_entity = get_entity(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
		$current_user = elgg_get_logged_in_user_entity();
		// set the breadcrumb trail
		assemble_forum_breadcrumb($entity);

		// forums will always remain as content within a group
		elgg_set_page_owner_guid($group_entity->getGUID());
		$return = array();

		if ($forum_guid !== $group_entity->guid)
			$content .= "<div class='forums-menu-buttons'>".gcforums_menu_buttons($entity->getGUID(), $group_entity->getGUID())."</div> ";


		// administrative only tool to fix up all the topics that were misplaced
		if (elgg_is_admin_logged_in()) {
			$content .= elgg_view_form('gcforums/move_topic');
		}

		$query = "SELECT * FROM elggentities e, elggentity_subtypes es WHERE e.subtype = es.id AND e.container_guid = {$forum_guid} AND es.subtype = 'hjforumtopic'";
		$topics = get_data($query);

		// sort
		usort($topics, function($a, $b) {
		    return $b->guid - $a->guid;
		});

		if (count($topics) > 0 && !$entity->enable_posting) {
			$content .= "
				<div class='topic-main-box'>
					<div style='background: #e6e6e6; width:100%;' >
						<div class='topic-header'>".elgg_echo('gcforums:translate:hjforumtopic')."
							<div class='topic-information'>options</div>
							<div class='topic-information'>".elgg_echo('gcforums:translate:last_posted')."</div>
							<div class='topic-information'>".elgg_echo('gcforums:translate:replies')."</div>
							<div class='topic-information'>".elgg_echo('gcforums:translate:topic_starter')."</div>
						</div>";

			/// topic
			foreach ($topics as $topic) {
				
				$topic = get_entity($topic->guid);
				$hyperlink = "<a href='{$base_url}gcforums/topic/view/{$topic->guid}'><strong>{$topic->title}</strong></a>";
				if (!$topic->guid) continue;
				$query = "SELECT e.guid, ue.username, e.time_created
							FROM {$dbprefix}entities e, {$dbprefix}users_entity ue
							WHERE e.container_guid = {$topic->guid} AND e.owner_guid = ue.guid";
				$replies = get_data($query);


				$total_replies = count($replies);
				$topic_starter = get_user($topic->owner_guid)->username;
				$time_posted = $replies[$total_replies - 1]->time_created;
				$time_posted = date('Y-m-d H:i:s', $time_posted);

				$options = render_edit_options($topic->guid, $group_entity->guid);
				if ($options == '') $options = '-';
				$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$topic->guid})" : "";
				$last_post = ($total_replies <= 0) ? elgg_echo('gcforums:no_posts') : "<div>{$replies[$total_replies - 1]->username}</div> <div>{$time_posted}</div>";

				$content .= "
				<div class='topic-info-header'>
					<div class='topic-description'>{$hyperlink} {$admin_only}</div>
					<div class='topic-options-edit'>{$options}</div>
					<div class='topic-options'>{$last_post}</div>
					<div class='topic-options'>{$total_replies}</div>
					<div class='topic-options'>{$topic_starter}</div>
				</div>";
			}

			$content .= "</div> </div> </p> <br/>";
		}



		/// display the categories if the forum has this enabled
		if ($entity->enable_subcategories || $entity instanceof ElggGroup) {
			
			$categories = elgg_get_entities(array(
				'types' => 'object',
				'subtypes' => 'hjforumcategory',
				'limit' => false,
				'container_guid' => $forum_guid
			));

			/// category
			foreach ($categories as $category) {
				$options = render_edit_options($category->guid, $group_entity->getGUID());
				if ($options == '') $options = '-';
				$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$category->guid})" : "";
				$content .= "
				<p>
					<div class='category-main-box'>
						<div class='category-options'>{$options} {$admin_only}</div>
						<h1>{$category->title}</h1>
						<div class='category-description'>{$category->description}</div>
					</div>";


				$forums = elgg_get_entities_from_relationship(array(
					'relationship' => 'filed_in',
					'relationship_guid' => $category->getGUID(),
					'container_guid' => $entity->getGUID(),
					'inverse_relationship' => true,
					'limit' => false
				));
			
				if (sizeof($forums) > 0) {

					$content .= "<div class='forum-main-box'>
									<div style='background: #e6e6e6; width:100%;' >
										<div class='forum-header'>Forum
											<div class='forum-information'>options</div>
											<div class='forum-information'>".elgg_echo('gcforums:translate:total_topics')."</div>
											<div class='forum-information'>".elgg_echo('gcforums:translate:total_replies')."</div>
											<div class='forum-information'>".elgg_echo('gcforums:translate:last_posted')."</div>
										</div>";

					/// forums
					foreach ($forums as $forum) {
						$total_topics = get_forums_statistics_information($forum->guid, TOTAL_TOPICS);
						$total_posts = get_forums_statistics_information($forum->guid, TOTAL_POST);
						$recent_post = get_forums_statistics_information($forum->guid, RECENT_POST);
						$options = render_edit_options($forum->getGUID(), $group_entity->getGUID());

						$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$forum->guid})" : "";

						$hyperlink = "<a href='{$base_url}gcforums/view/{$forum->getGUID()}'><strong>{$forum->title}</strong></a>";

						$content .= "<div class='forum-info-header'>
										<div class='forum-description'>{$hyperlink} {$admin_only}
											<div class='forum-description-text'>{$forum->description}</div>
										</div>
										<div class='forum-options-edit'>{$options}</div>
										<div class='forum-options'>{$total_topics}</div>
										<div class='forum-options'>{$total_posts}</div>
										<div class='forum-options'>{$recent_post}</div>
									</div>";
					}
					$content .= "</div> </div> </p> <br/>";

				} else {
					$content .= "<div class='forum-empty'>".elgg_echo('gcforums:forums_not_available')."</div>";
				}
			}


			if (elgg_is_admin_logged_in() /*|| $group_entity->getOwnerGUID() == $current_user->guid || check_entity_relationship($current_user->getGUID(), 'operator', $group_entity->getGUID())*/) {

				/// there are the problem where user creates forum in the existing forum with categories enabled, show the forums without categories
				$forums = elgg_get_entities_from_relationship(array(
						'relationship' => 'descendant',
						'container_guid' => $forum_guid,
						'subtypes' => array('hjforum'),
						'relationship_guid' => $forum_guid,
						'inverse_relationship' => true,
						'types' => 'object',
						'limit' => 0,
					));

				if (sizeof($forums) > 0) {
						
					$content .= "
					<div class='forum-category-issue-notice'>
						<section class='alert alert-danger'>
						<strong>This only shows up for administrators</strong>.
						If you don't see a specific forum appearing above, you can correct that by editing the forums below. 
						</section>
						<div class='forum-main-box'>
							<div style='background: #e6e6e6; width:100%;' >
								<div class='forum-header'>Forum
									<div class='forum-information'>options</div>
									<div class='forum-information'>".elgg_echo('gcforums:translate:total_topics')."</div>
									<div class='forum-information'>".elgg_echo('gcforums:translate:total_replies')."</div>
									<div class='forum-information'>".elgg_echo('gcforums:translate:last_posted')."</div>
								</div>";

					foreach ($forums as $forum) {

							$query = "SELECT COUNT(guid_one) AS total FROM elggentity_relationships WHERE guid_one = '{$forum->guid}' AND relationship = 'filed_in' AND guid_two = 0";
							$is_filed_in_category = get_data($query);

							//if ($is_filed_in_category[0]->total == 1) {

								$total_topics = get_forums_statistics_information($forum->guid, TOTAL_TOPICS);
								$total_posts = get_forums_statistics_information($forum->guid, TOTAL_POST);
								$recent_post = get_forums_statistics_information($forum->guid, RECENT_POST);
								$options = render_edit_options($forum->getGUID(), $group_entity->getGUID());
								if ($options == '') $options = '-';
								$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$forum->guid})" : "";
								$hyperlink = "<a href='{$base_url}gcforums/view/{$forum->getGUID()}'><strong>{$forum->title}</strong></a>";

								$content .= "
									<div class='forum-info-header'>
										<div class='forum-description'>{$hyperlink} {$admin_only}
											<div class='forum-description-text'>{$forum->description}</div>
										</div>
										<div class='forum-options-edit'>{$options}</div>
										<div class='forum-options'>{$total_topics}</div>
										<div class='forum-options'>{$total_posts}</div>
										<div class='forum-options'>{$recent_post}</div>
									</div>";
							//}
					}

					$content .= "</div> </div> </div> </p> <br/>";
				}
			}



		} else {

			/// display forums with no categories
			$forums = elgg_get_entities_from_relationship(array(
					'relationship' => 'descendant',
					'subtypes' => array('hjforum'),
					'relationship_guid' => $forum_guid,
					'inverse_relationship' => true,
					'types' => 'object',
					'limit' => 0,
				));

			if (sizeof($forums) > 0) {
				$content .= "
					<div class='forum-main-box'>
						<div style='background: #e6e6e6; width:100%;' >
							<div class='forum-header'>Forum
								<div class='forum-information'>options</div>
								<div class='forum-information'>".elgg_echo('gcforums:translate:total_topics')."</div>
								<div class='forum-information'>".elgg_echo('gcforums:translate:total_replies')."</div>
								<div class='forum-information'>".elgg_echo('gcforums:translate:last_posted')."</div>
							</div>";

				foreach ($forums as $forum) {
					
						if ($forum->getContainerGUID() != $entity->getGUID()) continue;
						
						$total_topics = get_forums_statistics_information($forum->guid, TOTAL_TOPICS);
						$total_posts = get_forums_statistics_information($forum->guid, TOTAL_POST);
						$recent_post = get_forums_statistics_information($forum->guid, RECENT_POST);
						$options = render_edit_options($forum->getGUID(), $group_entity->getGUID());
						if ($options == '') $options = '-';
						$hyperlink = "<a href='{$base_url}gcforums/view/{$forum->getGUID()}'><strong>{$forum->title}</strong></a>";
						$admin_only = (elgg_is_admin_logged_in()) ? "(guid:{$forum->guid})" : "";

						$content .= "
							<div class='forum-info-header'>
								<div class='forum-description'>{$hyperlink} {$admin_only}
									<div class='forum-description-text'>{$forum->description}</div>
								</div>
								<div class='forum-options-edit'>{$options}</div>
								<div class='forum-options'>{$total_topics}</div>
								<div class='forum-options'>{$total_posts}</div>
								<div class='forum-options'>{$recent_post}</div>
							</div>";
				}

				$content .= "</div> </div> </p> <br/>";
			}
		}

		$title = $entity->title;
		if (!$title) $title = elgg_echo('gcforum:heading:default_title');


		$return['filter'] = '';
		$return['title'] = gc_explode_translation($title, get_current_language());
		$return['content'] = $content;
	
	} else {
		$return['filter'] = '';
		$return['title'] = '404 - '.elgg_echo('gcforums:notfound');
		$return['content'] = elgg_echo('gcforums:notfound');
	}

	return $return;
}


/**
 * TOTAL_POST : 1
 * TOTAL_TOPICS : 2
 * RECENT_POST : 3
 * @param integer $type
 */
function get_forums_statistics_information($container_guid, $type)
{
	$dbprefix = elgg_get_config('dbprefix');

	switch ($type) {
		case 1:
			$query = "SELECT COUNT(r.guid_one) AS total
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumpost' AND e.access_id IN (1, 2)";
			break;

		case 2:
			$query = "SELECT COUNT(r.guid_one) AS total
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic' AND e.access_id IN (1, 2)";
				break;

		case 3:
			$query = "SELECT r.guid_one, r.relationship, r.guid_two, e.subtype, es.subtype, max(e.time_created) AS time_created, ue.email, ue.username, ue.name
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es, {$dbprefix}users_entity ue
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic' AND ue.guid = e.owner_guid LIMIT 1";

			$post = get_data($query);
			$recent_poster = elgg_echo("gcforums:no_posts");
			if ($post[0]->email) {
				$timestamp = date('Y-m-d', $post[0]->time_created);
				// Output display name - nick
				$recent_poster = "<div>{$post[0]->username}</div> <div>{$timestamp}</div>";
			}
			return $recent_poster;
			break; // will never reach this break statement

		default:
	}

	$total = get_data($query);
	$total = $total[0]->total;

	return $total;
}


/// recursively go through the nested forums to create the breadcrumb
function assemble_nested_forums($breadcrumb, $forum_guid, $recurse_forum_guid) {
	$lang = get_current_language();
	$entity = get_entity($recurse_forum_guid);
	if ($entity instanceof ElggGroup && $entity->guid != $forum_guid) {
		$breadcrumb[$entity->getGUID()] = array($entity->guid, gc_explode_translation($entity->name,$lang), "profile/{$entity->guid}");
		return $breadcrumb;
	} else {
		$breadcrumb[$entity->guid] = array($entity->guid, gc_explode_translation($entity->title,$lang), "gcforums/view/{$entity->guid}");	
		return assemble_nested_forums($breadcrumb, $forum_guid, $entity->getContainerGUID());
	}
}


/**
 * Create list of options to modify forums
 *
 * @param int $object_guid
 * @param int $group_guid
 *
 */
function render_edit_options($object_guid, $group_guid)
{
	$options = array();
	$group_entity = get_entity($group_guid);
	$current_user = elgg_get_logged_in_user_entity();
	$user = $current_user;
	$entity = get_entity($object_guid);
	$entity_type = $entity->getSubtype();

	if ($entity->getSubtype() !== 'hjforumpost' && elgg_is_admin_logged_in()) {
		$options['access'] = '<strong>' . get_readable_access_level($entity->access_id) . '</strong>';
	}

	// subscription
	if (elgg_is_logged_in()) {
		if (elgg_is_active_plugin('cp_notifications')) {
			$email_subscription = check_entity_relationship($current_user->getGUID(), 'cp_subscribed_to_email', $entity->getGUID());
			$site_subscription = check_entity_relationship($current_user->getGUID(), 'cp_subscribed_to_site_mail', $entity->getGUID());
			$btnSubscribe = ($email_subscription || $site_subscription) ? elgg_echo('gcforums:translate:unsubscribe') : elgg_echo('gcforums:translate:subscribe');
		} else {
			$subscription = check_entity_relationship($user->guid, 'subscribed', $object_guid);
			$btnSubscribe = ($subscription) ? elgg_echo('gcforums:translate:unsubscribe') : elgg_echo('gcforums:translate:subscribe');
		}


		if ($entity->getSubtype() !== 'hjforumcategory') {
			$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/subscribe?guid={$entity->getGUID()}");
			$options['subscription'] = "<div class='edit-options-{$entity_type}'><a href='{$url}'>{$btnSubscribe}</a></div>";
		}


		if (!$entity->enable_posting && check_entity_relationship($current_user->guid, 'member', $group_entity->guid) && $entity->getSubtype() === 'hjforum') {
			$url = elgg_get_site_url()."gcforums/create/hjforumtopic/{$object_guid}";
			$menu_label = elgg_echo("gcforums:translate:new_topic");
			$options['new_topic'] = "<a href='{$url}'>{$menu_label}</a>";
		}
	}

	// checks if user is admin, group owner, or moderator
	if (elgg_is_admin_logged_in() || $group_entity->getOwnerGUID() == $current_user->guid /*|| check_entity_relationship($current_user->getGUID(), 'operator', $group_entity->getGUID())*/) {
		$object_menu_items = ($entity->getSubtype() === 'hjforum') ? array("new_subcategory", "new_subforum", "edit") : array('edit', 'delete');

		if ($entity->getSubtype() === 'hjforumpost') {
			$object_menu_items = array("delete");
		}
		foreach ($object_menu_items as $menu_item) {
			$url = "";
			// check if new posting link and it is disabled (enabled == disabled)
			switch ($menu_item) {
				case 'new_subcategory':
					$url = ($entity->enable_subcategories) ? elgg_get_site_url()."gcforums/create/hjforumcategory/{$object_guid}" : "";
					break;
				case 'new_subforum':
					$url = elgg_get_site_url()."gcforums/create/hjforum/{$object_guid}";
					break;
				case 'edit':
					$url = elgg_get_site_url()."gcforums/edit/{$object_guid}";
					break;
				case 'delete':
					$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/delete?guid={$entity->getGUID()}");
					$style = "style='font-weight:bold; color:#d84e2f;'";
					break;
			}

			if ($url !== "") {
				$menu_label = elgg_echo("gcforums:translate:{$menu_item}");
				$options[$menu_item] = "<a {$style} href='{$url}'>{$menu_label}</a>";
			}
		}
	}

	foreach ($options as $key => $option) {
		$edit_options .= "<div class='edit-options-{$entity_type}'>{$option}</div>";
	}

	if (elgg_is_logged_in() && ($current_user->isAdmin() || $group_entity->getOwnerGUID() == $current_user->guid)
		&& $entity->getSubtype() !== 'hjforumpost' && $entity->getSubtype() !== 'hjforumtopic' && $entity->getSubtype() !== 'hjforumcategory') {
		$edit_options .= elgg_view('alerts/delete', array('entity' => $entity));
	}

	return $edit_options;
}



/**
 * @param integer|null $forum_guid
 * @param integer|null $group_guid
 */
function gcforums_menu_buttons($forum_guid, $group_guid, $is_topic=false)
{
	// main page if forum_guid is not present
	elgg_load_css('gcforums-css');
	$group_entity = get_entity($group_guid);
	$current_user = elgg_get_logged_in_user_entity();
	$entity = get_entity($forum_guid);
	$entity_type = $entity->getSubtype();

	$current_entity_guid = get_entity($forum_guid);


	// @TODO: check if it is a topic
	if (elgg_is_logged_in() && (elgg_is_admin_logged_in() || check_entity_relationship($current_user->getGUID(), 'member', $group_entity->getGUID()))) {
		$isOperator = check_entity_relationship($current_user->getGUID(), 'operator', $group_entity->getGUID());
		$button_class = "elgg-button elgg-button-action btn btn-default";

		// do not display the button menu if the object is a forum topic
		if (($current_user->isAdmin() || $isOperator || $group_entity->getOwnerGUID() === $current_user->getGUID()) && !$is_topic) {
			$gcforum_types = array('hjforumcategory', 'hjforumtopic', 'hjforum');

			$button_array = array();
			foreach ($gcforum_types as $gcforum_type) {
				$url = "gcforums/create/{$gcforum_type}/{$forum_guid}";
				if ($gcforum_type === 'hjforumcategory') {
					$button_array[$gcforum_type] = ($entity->enable_subcategories || $forum_guid == $group_guid)
						? elgg_view('output/url', array(
							"text" => elgg_echo('gcforums:new_hjforumcategory'),
							"href" => $url,
							'class' => $button_class))
						: "";
				}

				if ($gcforum_type === 'hjforumtopic' && $entity->getGUID() !== $group_entity->getGUID()) {
					$button_array[$gcforum_type] = (!$entity->enable_posting && $forum_guid !== null)
						? elgg_view('output/url', array(
							"text" => elgg_echo('gcforums:new_hjforumtopic'),
							"href" => $url,
							'class' => $button_class))
						: "";
				}

				if ($gcforum_type === 'hjforum') {
					$button_array[$gcforum_type] = elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforum'), "href" => $url, 'class' => $button_class));
				}
			}

			/// edit or delete current forum
			if ($forum_guid != $group_guid && ($current_user->isAdmin() || $group_entity->getOwnerGUID() == $current_user->guid)) {
				$url = "gcforums/edit/{$forum_guid}";
				$button_array['edit_forum'] = elgg_view('output/url', array("text" => elgg_echo('gcforums:edit_hjforum'), "href" => $url, 'class' => $button_class));
				$button_array['delete'] = elgg_view('alerts/delete', array('entity' => $entity, 'is_menu_buttons' => true));
			}

			foreach ($button_array as $key => $value) {
				$menu_buttons .= " {$value} ";
			}

			return "<div>{$menu_buttons}</div>";
		}

		if (elgg_is_logged_in() && check_entity_relationship($current_user->guid, 'member', $group_entity->guid) && $group_entity->getGUID() !== $entity->getGUID() && !$entity->enable_posting) {
			$url = "gcforums/create/hjforumtopic/{$forum_guid}";
			$new_forum_topic_button = ($forum_guid) ? elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforumtopic'), "href" => $url, 'class' => $button_class)) : "";
		}

		return "<div>{$new_forum_topic_button}</div>";
	}
}
=======
<?php

/* GCForums
 * 
 * @author Christine Yu <internalfire5@live.com>
 * 
 */

elgg_register_event_handler('init','system','gcforums_init');

function gcforums_init() {
	$action_path = elgg_get_plugins_path().'gcforums/actions/gcforums';

	elgg_register_css('gcforums-css','mod/gcforums/css/gcforums-table.css');						// styling the forums table
	elgg_register_plugin_hook_handler('register','menu:owner_block','gcforums_owner_block_menu');	// register menu item in group
	elgg_register_page_handler('gcforums', 'gcforums_page_handler');								// page handler for forums
	add_group_tool_option('forums', elgg_echo('gcforums:enable_group_forums'), false);				// add option for user to enable

	// actions for forum creation/editing/deletion (.../action/gcforums/[action]/...)
	elgg_register_action('gcforums/edit',$action_path.'/edit.php');
	elgg_register_action('gcforums/delete',$action_path.'/delete.php');
	elgg_register_action('gcforums/create',$action_path.'/create.php');
	elgg_register_action('gcforums/subscribe',$action_path.'/subscribe.php');

	// put a menu item in the site navigation (JMP request)
	elgg_register_menu_item('site', array(
		'name' => 'Forum',
		'text' => elgg_echo('gcforums:jmp_menu'),
		'href' => elgg_echo('gcforums:jmp_url'),
	));
}

function gcforums_owner_block_menu($hook,$type,$return,$params) {
	$entity = elgg_extract('entity', $params);
	if ($entity->type === 'group' && $entity->forums_enable === 'yes') { // display only in group menu and only when user selected to enable forums in group
		$url = "gcforums/group/{$params['entity']->guid}";
		$item = new ElggMenuItem('gcforums',elgg_echo('gcforums:group_nav_label'),$url);
		$return[] = $item;
		return $return;
	}
}


/* Page Handler
 */
function gcforums_page_handler($page) {
	$vars = array();

	//elgg_push_breadcrumb('GCforums', "gcforums/group/151");
	//elgg_push_breadcrumb($crumbs_title, "blog/group/$container->guid/all");

	switch($page[0]) {
		case 'create':
			gatekeeper();	// group members and admins only
			$vars['subtype'] = $page[1];
			$vars['group_guid'] = $page[2];
			$vars['container_guid'] = $page[3]; // when we're not in the main page for the forums in the group
			$title = elgg_echo("gcforums:new_{$page[1]}");
			$content = elgg_view_form('gcforums/create', array(), $vars); // pass some variables to the form (2nd param is empty)
			$body = elgg_view_layout('content',array(
				'content' => $content,
				'title' => $title,
				'filter' => '',	// removes the owner, mine, friends tabs
				));
			echo elgg_view_page($title,$body);

			break;
		case 'edit':
			gatekeeper();	// group members and admins only
			$vars['forum_guid'] = $page[1];
			$entity = get_entity($page[1]);
			$title = $entity->title;
			$content = elgg_view_form('gcforums/edit', array(),$vars);	
			$body = elgg_view_layout('content',array(
				'content' => $content,
				'title' => $entity->title,
				'filter' => '',					
				));
			echo elgg_view_page($entity->title,$body);

			break;
		case 'group':
			$vars['forum_guid'] = $page[2];
			$vars['topic'] = $page[3];
			$entity = get_entity($page[2]);
			$title = $entity->title;

			$content = elgg_view('gcforums/gcforums_content', $vars);
			$body = elgg_view_layout('content',array(
				'content' => $content,
				'title' => $title,
				'filter' => '',
				));
			echo elgg_view_page($title,$body);

			break;
		default:
			return false;
	}

	return true;
}


/*
 * TODO: Transferred to Lib Directory
 */

/* Display Topic and the corresponding comments
 * @params topic
 */
function gcforums_topic_content($topic_guid, $group_guid) {
	elgg_load_css('gcforums-css');
	$dbprefix = elgg_get_config('dbprefix');

	$topic = get_entity($topic_guid);

	$user_information = get_user($topic->owner_guid);
	$topic_content = '';

	$user = elgg_get_logged_in_user_entity();
	if (check_entity_relationship($user->guid, 'subscribed', $topic->guid))
		$subscribe_text = 'Unsubscribe';
	else
		$subscribe_text = 'Subscribe';

	elgg_view('output/url', array('is_action' => TRUE));
	elgg_view('input/securitytoken');
	$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/subscribe?guid={$topic->guid}");
	$subscribe_url = "<a href='{$url}'>{$subscribe_text}</a><br/>";

	// get the topic information and display it
	$topic_content .= "<table class='gcforums-table'>
						<tr class='gcforums-tr'>
							<th class='gcforums-th-topic' width='25%'> <strong>".$user_information->email."</strong> </th>
							<th class='gcforums-th-topic'> <strong>".elgg_echo('gcforums:posted_on',array( date('Y-m-d H:i:s', $topic->time_created) ))."</strong> </th>
							<th class='gcforums-th-topic-options'>".gcforums_category_edit_options($topic->guid)." | {$subscribe_url}"."</th>
						</tr>
						<tr class='gcforums-tr'>
							<td class='gcforums-td-topic'>".gcforums_display_user($user_information)."</td>
							<td colspan='2' class='gcforums-td-topic'>".$topic->description."</td>
						</tr>
						<tr class='gcforums-tr'>
							<td class='gcforums-td-topic'> </td>
							<td colspan='2' class='gcforums-td-topic'> </td>
						</tr>
					</table>";

	// get the comments for this topic
	$comments = elgg_get_entities(array(
		'types' => 'object',
		'container_guids' => $topic->guid,
	));

	$topic_content .= "<br/><br/>"; // TODO: style this
	$topic_content .= "<table class='gcforums-table'>";


	if (!$comments && elgg_is_logged_in()) {
		$topic_content .= "<tr class='gcforums-tr'>
								<th colspan='5' class='gcforums-td-category'>".elgg_echo('gcforums:no_comments')."</th>
							</tr>";
	} else {

		foreach ($comments as $comment) {
			$user_information = get_user($comment->owner_guid);

			elgg_view('output/url', array('is_action' => TRUE));
			elgg_view('input/securitytoken');
			$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/delete?guid={$comment->guid}");
			$url_edit = elgg_get_site_url()."gcforums/edit/{$comment->guid}";

			$comment_menu_content = "<table class='gcforums-comment-table'>
									<tr class='gcforums-comment-tr'>
										<th class='gcforums-comments-menu'>
											<a href='{$url_edit}'>Edit</a>
											<a href='{$url}'>Delete</a>
										</th>
									</tr>
									<tr class='gcforums-tr'>
										<td class='gcforums-comment-td'>
											{$comment->description}
										</td>
									</tr>
								</table>";

			$topic_content .= "	<tr class='gcforums-tr'>
								<th class='gcforums-td-topic' width='25%'>".elgg_view_entity_icon($user_information, 'small')."<br/>{$user_information->email} <br/>".date("Y-m-d H:m:s",$comment->time_created)."</td>
								<th colspan='2' class='gcforums-td-topic'>".$comment_menu_content."</th>
							</tr> ";

			$topic_content .= "";
		}
	}

	$topic_content .= "</table>";
	$topic_content .= "<br/><br/><br/>";

	$vars['group_guid'] = $group_guid;
	$vars['topic_guid'] = $topic_guid;
	$vars['topic_access'] = $topic->access_id;
	$vars['subtype'] = 'hjforumpost';
	$topic_content .= elgg_view_form('gcforums/create', array(), $vars);	// get the longtext input from form

	return $topic_content;
}

function get_total_posts($container_guid) {
	$dbprefix = elgg_get_config('dbprefix');
	$query = "SELECT r.guid_one, r.relationship, r.guid_two, e.subtype, es.subtype
			FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
			WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumpost'";
	$num_post = 0;
	$posts = get_data($query);

	foreach ($posts as $post)
		$num_post++;

	return $num_post;
}

function get_total_topics($container_guid) {
	$dbprefix = elgg_get_config('dbprefix');
	$query = "SELECT r.guid_one, r.relationship, r.guid_two, e.subtype, es.subtype
			FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
			WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic'";
	$num_topic = 0;
	$topics = get_data($query);

	foreach ($topics as $topic)
		$num_topic++;

	return $num_topic;
}

function get_recent_post($container_guid) {
	$dbprefix = elgg_get_config('dbprefix');
	$query = "SELECT r.guid_one, r.relationship, r.guid_two, e.subtype, es.subtype, max(e.time_created) AS time_created, ue.email, ue.username
			FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es, {$dbprefix}users_entity ue
			WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic' AND ue.guid = e.owner_guid";
	$post = get_data($query);

	$recent_poster = elgg_echo("gcforums:no_posts");
	if ($post[0]->email) {
		$timestamp = date('Y-m-d',$post[0]->time_created);
		$recent_poster = "{$post[0]->username} @ {$timestamp}";
	}
	return $recent_poster;
}



/* 
 * Topic & Comment - Display user card
 */
function gcforums_display_user($user_information) {
	$user_table = '';
	$user_table .= "<table class='gcf-user-table'>
				<tr class='gcf-user-tr'>
					<td class='gcf-user-td'>
						".elgg_view_entity_icon($user_information, 'medium')."
					</td>
				</tr>
				<tr class='gcf-user-tr'>
					<td class='gcf-user-td'>
						$user_information->name
					</td>
				</tr>
				<tr class='gcf-user-tr'>
					<td class='gcf-user-td'>
						$user_information->location
					</td>
				</tr>
				<tr class='gcf-user-tr'>
					<td class='gcf-user-td'>
						$user_information->department
					</td>
				</tr>
				</table>";

	return $user_table;
}



/* Display a list of topics within a forum
 */
function gcforums_topics_list($forum_guid, $group_guid, $is_sticky) {
	$forum_topic = '';
	$forum_entity = get_entity($forum_guid);

	if (!$forum_entity->enable_posting) {	// check if posting is enabled
		elgg_load_css('gcforums-css');
		
		$dbprefix = elgg_get_config('dbprefix');		

		if ($is_sticky) {
			$options = array(
					'relationship' => 'descendant',
					'subtypes' => array('hjforumtopic'),
					'relationship_guid' => $forum_guid,
					'inverse_relationship' => true,
					'types' => 'object',
					'metadata_name' => 'sticky',
					'metadata_value' => 1,
				);
		} else { // empty metadata value (if not sticky)
			$options = array(
				'relationship' => 'descendant',
				'subtypes' => array('hjforumtopic'),
				'relationship_guid' => $forum_guid,
				'inverse_relationship' => true,
				'types' => 'object',
				'metadata_name' => 'sticky',
				'metadata_value' => 0,
				'metadata_value' => null,
				);
		}

		$topics = elgg_get_entities_from_relationship($options);

		if ($is_sticky)
			$sticky_topic_title = elgg_echo('gcforums:sticky_topic');
		else
			$sticky_topic_title = elgg_echo('gcforums:topics');

		$forum_topic .= "<table class='gcforums-table'>
						<tr class='gcforums-tr'>
							<th class='gcforums-th' width='60%'>{$sticky_topic_title}</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:topic_starter')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:replies')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:last_posted')."</th>
							<th class='gcforums-th'>".'Options'."</th>
						</tr>";

		if (!$topics) { 
			$forum_topic .= "<tr class='gcforums-tr'>
							<th colspan='5' class='gcforums-td-forums'>".elgg_echo('gcforums:topics_not_available')."</th>
						</tr>";
		} else {

			foreach ($topics as $topic) {

				if ($topic->sticky == $is_sticky) {
					// get number of replies
					$query = "SELECT e.guid, ue.username, e.time_created
					FROM {$dbprefix}entities e, {$dbprefix}users_entity ue
					WHERE e.container_guid = {$topic->guid} AND e.owner_guid = ue.guid;";
					$replies = get_data($query);

					$user = get_user($topic->owner_guid);
					$num_replies = count($replies);	// get number of replies of topic

					$time_posted = $replies[$num_replies-1]->time_created;
					if ($time_posted == '')
						$time_posted = '';
					else
						$time_posted = date('Y-m-d H:i:s',$time_posted);

					$last_post_info = "{$replies[$num_replies-1]->username} / {$time_posted}";
					if ($last_post_info === ' / ') 
						$last_post_info = elgg_echo('gcforums:no_posts');

					$user = elgg_get_logged_in_user_entity();
					if (check_entity_relationship($user->guid, 'subscribed', $topic->guid))
						$subscribe_text = 'Unsubscribe';
					else
						$subscribe_text = 'Subscribe';

					elgg_view('output/url', array('is_action' => TRUE));
					elgg_view('input/securitytoken');
					$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/subscribe?guid={$topic->guid}");
					$subscribe_url = "<a href='{$url}'>{$subscribe_text}</a><br/>";

					$url = "<strong><a href='".elgg_get_site_url()."gcforums/group/{$group_guid}/{$topic->guid}/hjforumtopic'>{$topic->title}</a></strong>";
					$forum_topic .=	"<tr class='gcforums-tr'>
										<td class='gcforums-td-topics'>{$url}</td>
										<td class='gcforums-td'>{$user->username}</td>
										<td class='gcforums-td'>{$num_replies}</td>
										<td class='gcforums-td'>{$last_post_info}</td>
										<td class='gcforums-td-forums-options'>{$subscribe_url}</td>
									</tr>";
				}
			}
		}
		$forum_topic .= "</table>";
	}

	return $forum_topic;
}


/* Categoried Forums
 */
function gcforums_category_content($guid, $group_guid, $forums=false) {

	elgg_load_css('gcforums-css');
	$categories = elgg_get_entities(array(
		'types' => 'object',
		'subtypes' => 'hjforumcategory',
		'limit' => false,	// don't put a limit on it
		'container_guid' => $guid
	));

	$group = get_entity($guid);

	if (elgg_is_logged_in()) 
		$edit_option_string = elgg_echo('gcforums:edit');
	else 
		$edit_option_string = ' - ';

	$forum_category = '';
	$forum_category .= "<table class='gcforums-table'>
						<tr class='gcforums-tr'>
							<th class='gcforums-th' width='60%'>".elgg_echo('gcforums:forum_title')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:topics')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:posts')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:latest')."</th>
							<th class='gcforums-th'>{$edit_option_string}</th>
						</tr>";

	if (!$categories) { // check if there are forums filed under category
		$forum_category .= "<tr class='gcforums-tr'>
								<th colspan='5' class='gcforums-td-category'>".elgg_echo('gcforums:categories_not_available')."</th>
							</tr>";
	} else {
		// display the category title and description
		foreach ($categories as $category) {
			$forum_category .= "<tr class='gcforums-tr'>
							<th class='gcforums-th-category'><strong> {$category->title} </strong> {$category->description} </th>
							<th colspan='4' class='gcforums-th-category-options'>".gcforums_category_edit_options($category->guid)."</th>
						</tr>";

			$forums = elgg_get_entities_from_relationship(array(
				'relationship' => 'filed_in',
				'relationship_guid' => $category->guid,
				'container_guid' => guid,
				'inverse_relationship' => true,
			));

			if (!$forums) {
				$forum_category .= "<tr class='gcforums-tr'>
						<th colspan='5' class='gcforums-td-forums'>".elgg_echo('gcforums:forums_not_available')."</th>
					</tr>";
			} else {
				// display the forum in the category
				foreach ($forums as $forum) {
					$url = "<strong><a href='".elgg_get_site_url()."gcforums/group/{$group_guid}/{$forum->guid}'>{$forum->title}</a></strong>";

					$forum_category .= "<tr class='gcforums-tr'>
						<th class='gcforums-td-forums'>{$url}{$forum->description}</th>
						<th class='gcforums-td'>".get_total_topics($forum->guid)."</th>
						<th class='gcforums-td'>".get_total_posts($forum->guid)."</th>
						<th class='gcforums-td'>".get_recent_post($forum->guid)."</th>
						<th class='gcforums-td-forums-options'>".gcforums_forums_edit_options($forum->guid, $group_guid)."</th>
					</tr>";
				}
			}
		}
	}
	$forum_category .= "</table>";

	return $forum_category;
}




/* Create list of options to modify forums
 */
function gcforums_forums_edit_options($object_guid,$group_guid) {

	if (elgg_is_logged_in() && ( elgg_get_logged_in_user_entity()->isAdmin() || get_entity($group_guid)->getOwnerGUID() == elgg_get_logged_in_user_guid())) {
	
		$object_menu_items = array("New subforum", "New Posting", "Edit");

		$entity = get_entity($object_guid);
		
		// options given to users: New subforum / New Posting (if enabled) / Edit current / Delete current
		$edit_options = "<strong>".get_readable_access_level($entity->access_id)."</strong> <br/>";
		foreach ($object_menu_items as $menu_item) {
			if ($menu_item === 'New Posting' && $entity->enable_posting) { // check if new posting link and it is disabled (enabled == disabled)

			} else {
				$url = elgg_get_site_url()."gcforums/edit/{$object_guid}";
				$edit_options .= "<a href='{$url}'>{$menu_item}</a><br/>";
			}
		}

		// subscription functionality... users will get notified if any action occurs		
		$user = elgg_get_logged_in_user_entity();
		if (check_entity_relationship($user->guid, 'subscribed', $object_guid))
			$subscribe_text = "Unsubscribe";
		else
			$subscribe_text = "Subscribe";

		elgg_view('output/url', array('is_action' => TRUE));
		elgg_view('input/securitytoken');
		$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/subscribe?guid={$object_guid}");
		$edit_options .= "<a href='{$url}'>{$subscribe_text}</a><br/>";
		
		elgg_view('output/url', array('is_action' => TRUE));
		elgg_view('input/securitytoken');
		$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/delete?guid={$object_guid}");
		$edit_options .= "<a href='{$url}'>Delete</a>";

		return $edit_options;
	}
	return "";
}


/* Create list of options to modify categories
 */
function gcforums_category_edit_options($object_guid) {

	if (elgg_is_logged_in()) {
		if (elgg_get_logged_in_user_entity()->isAdmin() || get_entity($object_guid)->getOwnerGUID() == elgg_get_logged_in_user_guid()) {
			$dbprefix = elgg_get_config('dbprefix');
			$query = "SELECT access_id
					FROM {$dbprefix}entities
					WHERE guid = {$object_guid};";
			$object_access = get_data_row($query);

			$edit_options = "<strong>".get_readable_access_level($object_access->access_id)."</strong> ";
			$url = elgg_get_site_url()."gcforums/edit/{$object_guid}";
			$edit_options .= "<a href='{$url}'>Edit</a> ";
			elgg_view('output/url', array('is_action' => TRUE));
			elgg_view('input/securitytoken');
			$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/delete?guid={$object_guid}");
			$edit_options .= "<a href='{$url}'>Delete</a>";

			return $edit_options;
		}
	}
	return "";
}

/* Uncategoried Forums
 */
function gcforums_forum_list($forum_guid, $group_guid) {
	elgg_load_css('gcforums-css');
	$forum_list = '';
	$dbprefix = elgg_get_config('dbprefix');
	$prev_guid = 0;

	$options = array(
			'relationship' => 'descendant',
			'subtypes' => array('hjforum'),
			'relationship_guid' => $forum_guid,
			'inverse_relationship' => true,
			'types' => 'object',
		);
	$forums = elgg_get_entities_from_relationship($options);

	$forum_list .= "<table class='gcforums-table'>
						<tr class='gcforums-tr'>
							<th class='gcforums-th' width='60%'>".elgg_echo('gcforums:forum_title')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:topics')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:posts')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:latest_posts')."</th>
							<th class='gcforums-th'>".elgg_echo('gcforums:edit')."</th>
						</tr>";

	if (!$forums) {
		$forum_list .= "<tr class='gcforums-tr'>
						<th colspan='5' class='gcforums-td-forums'>".elgg_echo('gcforums:forums_not_available')."</th>
					</tr>";
	} else {
		foreach ($forums as $forum) {
				if ($forum->title && !check_entity_relationship($forum->guid, 'descendant', $prev_guid)) {
					$url = "<strong><a href='".elgg_get_site_url()."gcforums/group/{$group_guid}/{$forum->guid}'>{$forum->title}</a></strong>";

					$forum_list .="	<tr class='gcforums-tr'>
								<td class='gcforums-td-forums'>{$url} {$forum->description}</td>
								<td class='gcforums-td'>".get_total_topics($forum->guid)."</td>
								<td class='gcforums-td'>".get_total_posts($forum->guid)."</td>
								<td class='gcforums-td'>".get_recent_post($forum->guid)."</td>
								<th class='gcforums-td-forums-options'>".gcforums_forums_edit_options($forum->guid,$group_guid)."</td>
							</tr> ";
				}
			$prev_guid = $forum->guid;
		}
	}

	$forum_list .="</table>";
	return $forum_list;
}


function gcforums_menu_buttons($forum_guid,$group_guid, $is_topic=false) { // main page if forum_guid is not present
	// user must be logged in AND (either an admin or group owner/admin/operator)
	if (elgg_is_logged_in() && ( elgg_get_logged_in_user_entity()->isAdmin() || get_entity($group_guid)->getOwnerGUID() == elgg_get_logged_in_user_guid())) {

		elgg_load_css('gcforums-css');
		if (!$forum_guid) $forum_guid = 0;
		$forum_object = get_entity($forum_guid);

		if (!$is_topic) { // if object is a hjforumtopic, then do not display menu
			// new category
			if ($forum_object->enable_subcategories || !$forum_guid) // check if subcategories is enabled or this is the main forum page in group
				$new_category_button = elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforumcategory'), "href" => "gcforums/create/hjforumcategory/{$group_guid}/{$forum_guid}", 'class' => 'elgg-button elgg-button-action'));
			// new topic
			if (!$forum_object->enable_posting && $forum_guid) // check if postings is enabled and this is not the main first page of forum in group
				$new_forum_topic_button = elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforumtopic'), "href" => "gcforums/create/hjforumtopic/{$group_guid}/{$forum_guid}", 'class' => 'elgg-button elgg-button-action'));
			// new current forum
			$new_forum_button = elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforum'), "href" => "gcforums/create/hjforum/{$group_guid}/{$forum_guid}", 'class' => 'elgg-button elgg-button-action'));
			
			if ($forum_guid != 0) {
				// edit current forum
				$edit_forum_button = elgg_view('output/url', array("text" => elgg_echo('gcforums:edit_hjforum'), "href" => "gcforums/edit/{$forum_guid}", 'class' => 'elgg-button elgg-button-action'));
				
				// delete current forum
				elgg_view('output/url', array('is_action' => TRUE));
				elgg_view('input/securitytoken');
				$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/delete?guid={$forum_guid}");
				$delete_forum_button = elgg_view('output/url', array("text" => elgg_echo('gcforums:forum_delete'), "href" => $url, 'class' => 'elgg-button elgg-button-action'));
				
				$separator = " | ";
			}
		}	
		return "<div class='gcforums-menu'>{$new_category_button} {$new_forum_button} {$new_forum_topic_button} {$separator} {$edit_forum_button} {$delete_forum_button}</div> <br/><br/>";
	}

	return "";
}
>>>>>>> b450f2b7b84702ad6ef8a3d2354146f7054d53ee
