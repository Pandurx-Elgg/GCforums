<<<<<<< HEAD
<?php

$current_entity = get_entity($vars['current_entity']);
$entity_type = $vars['entity_type'];
$group_guid = $vars['group_guid'];
$object = $current_entity;

/// main code
$subtype = $entity_type;

switch ($subtype) {

	case 'hjforumcategory':
		$content = general_information_form($object);
		break;

	case 'hjforum':
		$content = array_merge(general_information_form($object), forums_information_form($object));
		break;

	case 'hjforumtopic':
		$content = array_merge(general_information_form($object), forums_topic_form($object));
		break;

	case 'hjforumpost':
		$content = general_information_form($object);
		break;
}


$labels = array('title', 'description', 'category_filing', 'sticky', 'enable_category', 'enable_posting', 'is_sticky', 'access');

echo "<div class='tab-content tab-content-border'>";
foreach ($labels as $label) {
	if (!is_array($content[$label])) {
		continue;
	}

	$form_input = ($label === 'enable_posting' || $label === 'enable_category' || $label === 'is_sticky')
		? "<p>{$content[$label][1]}</p>"
		: "<p><label> {$content[$label][0]} </label> {$content[$label][1]}</p>";

	echo $form_input;
}


/// hidden forms to pass additional information to the action
$hidden_forms = hidden_information_form($object);
foreach ($hidden_forms as $form) {
	echo $form;
}

$hidden_subtype = elgg_view('input/hidden', array(
	'name' => 'subtype',
	'value' => $subtype,
));

echo $hidden_subtype;

/// save button
$btnSave = elgg_view('input/submit', array(
	'value' => elgg_echo('gcforums:save_button'),
	'name' => 'save'
));

echo "<p>$btnSave</p>";

echo "</div>";



// this deals with comments or replies
/// title, description, and access
function general_information_form($object = null)
{
	$sub_return = array();

	// don't show field for title and access for forum post
	if ($object && $object->getSubtype() !== 'hjforumtopic') {
		$lblTitle = elgg_echo('gforums:title_label');
		$txtTitle = elgg_view('input/text', array(
			'name' => 'txtTitle',
			'value' => '',
			'required' => true
		));
		$sub_return = array('title' => array($lblTitle, $txtTitle));

		$access_id = (!$object->access_id) ? ACCESS_DEFAULT : $object->access_id;
		$lblAccess = elgg_echo('gcforums:access_label');
		$ddAccess = elgg_view('input/access', array(
			'name' => 'ddAccess',
			'value' => $access_id
		));

		$lblDescription = elgg_echo('gforums:description_label');
	}

	// for forum post... make sure the access id matches the topic
	if ($object && $object->getSubtype() === 'hjforumpost') {
		$ddAccess = $object->getContainerEntity()->access_id;
	}

	$txtDescription = elgg_view('input/longtext', array(
		'name' => 'txtDescription',
		'value' => '',
		'required' => true
	));

	$return = array(
		'description' => array($lblDescription, $txtDescription),
		'access' => array($lblAccess, $ddAccess)
	);


	$return = array_merge($return, $sub_return);

	return $return;
}

function forums_topic_form($object)
{
	$is_sticky = 0;
	$lblIsSticky = elgg_echo('gcforums:is_sticky');
	$chkIsSticky = elgg_view('input/checkboxes', array(
		'name' => 'chkIsSticky',
		'class' => 'list-unstyled',
		'options' => array($lblIsSticky => 1),
		'value' => $is_sticky,
	));

	$return = array(
		'is_sticky' => array($lblIsSticky, $chkIsSticky),
	);

	return $return;
}

/// category filing, enable subcategories, and disable posting
function forums_information_form($object)
{
	/// todo: identify if this object is new or not
	$dbprefix = elgg_get_config('dbprefix');

	$sub_return = array();
	$enable_subcategories = 0;
	$lblEnableCategory = elgg_echo('gcforums:enable_categories_label');
	$chkEnableCategory = elgg_view('input/checkboxes', array(
		'name' => 'chkEnableCategory',
		'class' => 'list-unstyled',
		'options' => array($lblEnableCategory => 1),
		'value' => $enable_subcategories,
	));

	$enable_posting = 0;
	$lblEnablePost = elgg_echo('gcforums:enable_posting_label');
	$chkEnablePost = elgg_view('input/checkboxes', array(
		'name' => 'chkEnablePost',
		'class' => 'list-unstyled',
		'options' => array($lblEnablePost => 1),
		'value' => $enable_posting,
	));

	// category option only available if the subcategory is enabled or first level forum in group
	if ($object->enable_subcategories || $object instanceof ElggGroup) {

		// retrieve a list of available categories
		$query = "	SELECT oe.guid, oe.title
					FROM {$dbprefix}entities e, {$dbprefix}entity_relationships r, {$dbprefix}objects_entity oe, {$dbprefix}entity_subtypes es
					WHERE e.subtype = es.id AND es.subtype = 'hjforumcategory' AND e.guid = r.guid_one AND e.container_guid = {$object->getGUID()} AND e.guid = oe.guid";

		$categories = get_data($query);
		$category_list = array();
		foreach ($categories as $category) {
			$category_list[$category->guid] = $category->title;
		}

		$lblCategoryFiling = elgg_echo('gcforums:file_under_category_label');
		$ddCategoryFiling = elgg_view('input/dropdown', array(
			'options_values' => $category_list,
			'name' => 'ddCategoryFiling',
		));

		$sub_return = array('category_filing' => array($lblCategoryFiling, $ddCategoryFiling));
	}

	$return = array(
		'enable_category' => array($lblEnableCategory, $chkEnableCategory),
		'enable_posting' => array($lblEnablePost, $chkEnablePost),
	);

	$return = array_merge($return, $sub_return);

	return $return;
}

function hidden_information_form($object)
{
	// hidden field for guid
	$hidden_object = elgg_view('input/hidden', array(
		'name' => 'entity_guid',
		'value' => $object->getGUID(),
	));

	$hidden_group = elgg_view('input/hidden', array(
		'name' => 'group_guid',
		'value' => $group_guid,
	));

	$base_url = elgg_get_site_entity()->getURL();

	// hidden field for forward url
	$forward_url = "{$base_url}gcforums/view/{$object->getGUID()}";
	$hidden_forward_url = elgg_view('input/hidden', array(
		'name' => 'hidden_forward_url',
		'value' => str_replace('amp;', '', $forward_url),
	));

	$return = array('group_guid' => $hidden_group, 'entity_guid' => $hidden_object, 'forward_url' => $hidden_forward_url);

	return $return;
}
=======
<?php

if (elgg_is_logged_in()) {

	// if this is within a group, set owner to group
	if (!elgg_get_page_owner_guid())
		elgg_set_page_owner_guid($vars['group_guid']);

	error_log('page owner:'.elgg_get_page_owner_guid());

	$gcf_subtype = $vars['subtype'];
	$gcf_group = $vars['group_guid'];
	$gcf_container = $vars['container_guid'];
	
	// variables are passed in the .../gcforums/start.php (form is embedded in the content file)
	$gcf_topic_access = $vars['topic_access']; 
	$gcf_topic_guid = $vars['topic_guid']; // post only
	$hjforumpost_title = "RE: $gcf_topic_guid";	// post only

	$object = get_entity($object_guid);
	
	if (!$gcf_container)
		$gcf_container = $gcf_topic_guid;

	
	if ($gcf_container == 0 || !$gcf_container)
		$gcf_container = $gcf_group;

	if ($gcf_subtype === "hjforumpost")
		$gcf_container = $gcf_topic_guid;

	// debug error_log (will be displayed above forms) comment-out for production!
	if ($gcf_subtype === "hjforumpost")
		echo "create.php :: group: {$gcf_group} / subtype: {$gcf_subtype} / topic_access: {$gcf_topic_access} / topic_guid: {$gcf_topic_guid} / container: {$gcf_container} / title: {$hjforumpost_title}";
	else
		echo "create.php :: subtype: {$gcf_subtype} / group: {$gcf_group} / container: {$gcf_container}";


	// title, description and access (visible)
	if ($gcf_subtype === 'hjforum' || $gcf_subtype === 'hjforumcategory' || $gcf_subtype === 'hjforumtopic') {

		if ($gcf_subtype === 'hjforumtopic') {
			$gcf_sticky_topic_label = elgg_echo('gcforums:is_sticky');
			$gcf_sticky_topic_input = elgg_view('input/checkboxes', array(
				'name' => 'gcf_sticky',
				'id' => 'gcf_sticky',
				'options' => array(
					$gcf_sticky_topic_label => 1),
			));
		}

		$gcf_title_label = elgg_echo("gcforums:title_label_{$gcf_subtype}");
		$gcf_title_input = elgg_view('input/text', array(
			'name' => 'gcf_title',
		));

		$gcf_access_label = elgg_echo('gcforums:access_label');
		$gcf_access_input = elgg_view('input/access', array(
			'name' => 'gcf_access',
		));

		if ($gcf_subtype === 'hjforum') { // enable categories and postings
			$gcf_enable_categories_label = elgg_echo('gcforums:enable_categories_label');
			$gcf_enable_categories_input = elgg_view('input/checkboxes', array(
				'name' => 'gcf_allow_categories',
				'id' => 'categories_id',
				'options' => array(
					$gcf_enable_categories_label => 1),
			));

			$gcf_enable_posting_label = elgg_echo('gcforums:enable_posting_label');
			$gcf_enable_posting_input = elgg_view('input/checkboxes', array(
				'name' => 'gcf_allow_posting',
				'id' => 'posting_id',
				'options' => array(
					$gcf_enable_posting_label => 1),
			));
		}


		if ($gcf_subtype === 'hjforum' && get_entity($gcf_container)->enable_subcategories) {

			if ($gcf_container && $gcf_container != 0) { // this is within the nested forums
				$query = "SELECT  oe.guid, oe.title
						FROM elggentities e, elggentity_relationships r, elggobjects_entity oe
						WHERE e.subtype = 28 AND e.guid = r.guid_one AND e.container_guid = {$gcf_container} AND e.guid = oe.guid";

			} else { // first page of group
				$query = "SELECT  oe.guid, oe.title
						FROM elggentities e, elggentity_relationships r, elggobjects_entity oe
						WHERE e.subtype = 28 AND e.guid = r.guid_one AND e.container_guid = {$gcf_group} AND e.guid = oe.guid";
			}

			$categories = get_data($query);

			$category_list = array();
			foreach ($categories as $category)
				$category_list[$category->guid] = $category->title;

			$gcf_file_under_category_label = elgg_echo('gcforums:file_under_category_label');
			$gcf_file_under_category_input = elgg_view('input/dropdown', array(
				'options_values' => $category_list,
				'name' => 'gcf_file_in_category',

			));
		}
	}

	if (!$gcf_subtype || $gcf_subtype === 'hjforumpost')
		$gcf_description_label = elgg_echo('gcforums:topic_reply'); // reply to topic
	else
		$gcf_description_label = elgg_echo('gcforums:description'); // description

	$gcf_description_input = elgg_view('input/longtext', array(
		'name' => 'gcf_description',
		'id' => 'gcf_description',
	));


	$gcf_submit_button = elgg_view('input/submit', array(
		'value' => elgg_echo('gcforums:submit'),
		'name' => 'gcf_submit',
	));


	// hidden field for guid
	$gcf_container_input = elgg_view('input/hidden', array(
		'name' => 'gcf_container',
		'value' => $gcf_container,
		));

	if ($gcf_subtype === 'hjforumpost') {
		// hidden field for title
		$gcf_title_input = elgg_view('input/hidden', array(
			'name' => 'gcf_title',
			'value' => $hjforumpost_title,
			));
		// hidden field for access id
		$gcf_access_input = elgg_view('input/hidden', array(
			'name' => 'gcf_access',
			'value' => $gcf_topic_access
		));
	}
	// hidden field for type
	$gcf_type_input = elgg_view('input/hidden', array(
		'name' => 'gcf_type',
		'value' => 'object'
		));

	$gcf_group_input = elgg_view('input/hidden', array(
		'name' => 'gcf_group',
		'value' => $gcf_group
		));

	if (!$gcf_subtype) {
		// hidden field for subtype
		$gcf_subtype_input = elgg_view('input/hidden', array(
			'name' => 'gcf_subtype',
			'value' => 'hjforumpost'
			));
	} else {
		// hidden field for subtype
		$gcf_subtype_input = elgg_view('input/hidden', array(
			'name' => 'gcf_subtype',
			'value' => $gcf_subtype
			));
	}

	// hidden field for user
	$gcf_owner_input = elgg_view('input/hidden', array(
		'name' => 'gcf_owner',
		'value' => get_loggedin_user()->getGUID(),
		));

	// hidden field for forward url
	$gcf_forward = $_SERVER['HTTP_REFERER'];
	$gcf_forward_url_input = elgg_view('input/hidden', array(
		'name' => 'gcf_forward_url',
		'value' => $gcf_forward,
		));


	if ($gcf_subtype === 'hjforumpost') { // posts

		echo <<<___HTML

		
<div>
	<label for="gcf_post_reply">$gcf_description_label</label>
	$gcf_description_input
</div>

	<!-- hidden input fields -->
	$gcf_group_input
	$gcf_guid_input
	$gcf_container_input
	$gcf_title_input
	$gcf_type_input
	$gcf_subtype_input
	$gcf_access_input
	$gcf_forward_url_input
	$gcf_owner_input

<div>
	$gcf_submit_button
</div>

___HTML;

	} else { // category, topic and forum

		echo <<<___HTML

<div>
	<label for="gcf_title_input">$gcf_title_label</label>
	$gcf_title_input
</div>

<div>
	<label for="gcf_description_input">$gcf_description_label</label>
	$gcf_description_input
</div>

<div>
	$gcf_enable_categories_input
</div>

<div>
	$gcf_enable_posting_input
</div>

<div>
	$gcf_sticky_topic_input
</div>

<div>
	<label for="gcf_file_under_category_input">$gcf_file_under_category_label</label>
	$gcf_file_under_category_input
</div>

<!-- TODO: display the access id of the container -->
<div>
	<label for="gcf_blog_description">$gcf_access_label</label>
	$gcf_access_input
</div>

	<!-- hidden input fields -->
	$gcf_group_input
	$gcf_guid_input
	$gcf_container_input
	$gcf_type_input
	$gcf_subtype_input
	$gcf_forward_url_input
	$gcf_owner_input

<div>
	$gcf_submit_button
</div>

___HTML;



	}

}
>>>>>>> b450f2b7b84702ad6ef8a3d2354146f7054d53ee
