<?php
/*
Plugin Name: BP Site Groups
Plugin URI: http://schoolpress.me/wp/bp-site-groups/
Description: Remember which site a BuddyPress group was created on and limit access to that group to that site only.
Version: .1
Author: strangerstudios

Some ideas and code were taken from this forum post:
http://buddypress.org/support/topic/buddypress-multisite-shared-users-but-segregated-groups-activity-forums/
*/

// Add meta fields upon group creation
function bpsg_group_meta_save ( $group_id ) 
{
	$blog = get_blog_details( get_current_blog_id(), true );
	
	$fields = array(
		'blog_id' => $blog->blog_id,
		'blog_path' => $blog->path,
		'blog_name' => $blog->blogname
	);
	
	foreach ( $fields as $field => $value ) {
		groups_update_groupmeta( $group_id, $field, $value );
	}
}
add_action( 'groups_created_group', 'bpsg_group_meta_save' );

//get groups by meta value
function bpsg_get_groups_by_meta ( $field, $meta_key, $meta_value ) 
{
	global $wpdb;
	
	if ( is_string( $meta_value) ) $meta_value = "'" . $meta_value . "'";
	
	$sql = $wpdb->prepare( "SELECT $field from {$wpdb->base_prefix}bp_groups_groupmeta WHERE meta_key='$meta_key' AND meta_value=$meta_value", OBJECT );
	$res = $wpdb->get_results( $sql );
	
	return $res;
}

// Build a list of groups with the matching blog_id value
function bpsg_get_groups_by_blogid ( $blog_id = 1 ) 
{
	$list = bpsg_get_groups_by_meta( 'group_id', 'blog_id', $blog_id );
	
	if ( count( $list ) ) {
	$res = "";
		foreach ( $list as $item ) {
		$res .= $item->group_id . ',';
	}
		return substr( $res, 0, -1);
	} else {
		return FALSE;
	}
}

/*
	Filter groups to show only groups for the current site.
	
	$groups here is an array:
	[groups] = groups array
	[total] = number of groups in the array
	
	DEV NOTE: May need to update this to work on the groups property of the 
	BP_Groups_Template class directly since sometimes (when viewing invites) the 
	groups are not generated through groups_get_groups().	
*/
function bpsg_groups_get_groups($groups)
{	
	//get current site/blog
	$current_site = get_blog_details( get_current_blog_id(), true );
		
	//loop through groups and check site
	$newgroups = array();
	for($i = 0; $i < count($groups['groups']); $i++)
	{
		//check site for group
		$group_site = groups_get_groupmeta($groups['groups'][$i]->id, "blog_id");
				
		if($group_site == $current_site->blog_id)
			$newgroups[] = $groups['groups'][$i];
	}
	
	//update groups
	$groups['groups'] = $newgroups;
	
	//update count
	$groups['total'] = count($groups['groups']);

	return $groups;
}
add_filter('groups_get_groups', 'bpsg_groups_get_groups');
	
/*
	Redirect users away from groups that don't belong to the current site.
*/
function bpsg_template_redirect()
{	
	//make sure BP is activated
	if(!function_exists('bp_get_current_group_id'))
		return;

	//get group id
	$group_id = bp_get_current_group_id();
		
	//is this a group page?
	if(!empty($group_id))
	{
		//check the site
		$current_site = get_blog_details( get_current_blog_id(), true );
		$group_site = groups_get_groupmeta($group_id, "blog_id");
				
		if($current_site->blog_id != $group_site)
		{
			//send them home
			wp_redirect(home_url());
			exit;
		}
	}
}
add_action('template_redirect', 'bpsg_template_redirect');

/*
	Redirect admins from editing non-site groups from the dashboard.
*/
function bpsg_admin_init_redirect()
{
	if(!empty($_REQUEST['page']) && $_REQUEST['page'] == 'bp-groups' && !empty($_REQUEST['action']) && $_REQUEST['action'] == 'edit' && !empty($_REQUEST['gid']))
	{
		//get group id
		$group_id = intval($_REQUEST['gid']);
		
		//check the site
		$current_site = get_blog_details( get_current_blog_id(), true );		
		$group_site = groups_get_groupmeta($group_id, "blog_id");
		
		if($current_site->blog_id != $group_site)
		{
			//send them home
			wp_redirect(admin_url());
			exit;
		}
	}
}
add_action('admin_init', 'bpsg_admin_init_redirect');