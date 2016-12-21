<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* mcp_concepts
* Handling the moderation queue
*/
class mcp_concepts
{
	var $p_master;
	var $u_action;

	public function mcp_concepts(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	public function main($id, $mode)
	{
		global $auth, $db, $user, $template, $cache, $request;
		global $config, $phpbb_root_path, $phpEx, $action, $phpbb_container;
		global $phpbb_dispatcher;
		include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

		$forum_id = request_var('f', 0);
		$start = request_var('start', 0);

		$this->page_title = 'MCP_CONCEPTS';

		switch ($action)
		{
			case 'add_concepts':
            
			    include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);

                $form_topics = array();
                $form_topics = $request->variable('topic_id_list', array(''=>''),true);
                $form_posts = array();
                $form_posts = $request->variable('post_id_list', array(''=>''),true);
                //trigger_error('kuku 3 post='.print_r($form_posts,true));
                
 				if (!empty($form_posts))
				{
					self::add_post_concepts($action, $form_posts, 'concepts', $mode);
                    //trigger_error('kuku 3 post='.print_r($post_id_list,true).print_r($concepts,true));
                    
				}
				else if (!empty($form_topics))
				{
					self::add_topic_concepts($action, $form_topics, 'concepts', $mode);
                    //trigger_error('kuku 3 post='.print_r($topic_id_list,true).print_r($concepts,true));
				}
				else
				{
					trigger_error('NO_POST_SELECTED');
				}
                //trigger_error(print_r($post_id_list));
			break;
		}
//trigger_error('kuku 3 $mode='.$mode.' $action='.$action);

		switch ($mode)
		{
			case 'unreviewed_topics':
			case 'unreviewed_posts':
				$m_perm = 'm_approve'; //
				$is_topics = ($mode == 'unreviewed_topics') ? true : false;
				$is_restore = false;
				$visibility_const = ITEM_APPROVED;

				$user->add_lang(array('viewtopic', 'viewforum'));

				$topic_id = $request->variable('t', 0);
				$forum_info = array();
				$pagination = $phpbb_container->get('pagination');

				if ($topic_id)
				{
					$topic_info = phpbb_get_topic_data(array($topic_id));

					if (!sizeof($topic_info))
					{
						trigger_error('TOPIC_NOT_EXIST');
					}

					$topic_info = $topic_info[$topic_id];
					$forum_id = $topic_info['forum_id'];
				}

				$forum_list_approve = get_forum_list($m_perm, false, true); //Obtain authed forums list
				$forum_list_read = array_flip(get_forum_list('f_read', true, true)); // Flipped so we can isset() the forum IDs

				// Remove forums we cannot read
				foreach ($forum_list_approve as $k => $forum_data)
				{
					if (!isset($forum_list_read[$forum_data['forum_id']]))
					{
						unset($forum_list_approve[$k]);
					}
				}
				unset($forum_list_read);

				if (!$forum_id)
				{
					$forum_list = array();
					foreach ($forum_list_approve as $row)
					{
						$forum_list[] = $row['forum_id'];
					}

					if (!sizeof($forum_list))
					{
						trigger_error('NOT_MODERATOR');
					}
                    $sql_array = "SELECT count(ac.forum_id) as cnt_forum_topics 
			             FROM ". NUC_ALL_CONCEPTS_TABLE. " ac
    			         WHERE ". $db->sql_in_set('ac.forum_id', $forum_list). " AND ac.post_id is NULL";
					$result = $db->sql_query($sql_array);
					$forum_info['forum_topics_reviewed'] = (int) $db->sql_fetchfield('cnt_forum_topics');
					$db->sql_freeresult($result);
				}
				else
				{
					$forum_info = phpbb_get_forum_data(array($forum_id), $m_perm);

					if (!sizeof($forum_info))
					{
						trigger_error('NOT_MODERATOR');
					}

					$forum_info = $forum_info[$forum_id];
					$forum_list = $forum_id;
				}

				$forum_options = '<option value="0"' . (($forum_id == 0) ? ' selected="selected"' : '') . '>' . $user->lang['ALL_FORUMS'] . '</option>';
				foreach ($forum_list_approve as $row)
				{
					$forum_options .= '<option value="' . $row['forum_id'] . '"' . (($forum_id == $row['forum_id']) ? ' selected="selected"' : '') . '>' . str_repeat('&nbsp; &nbsp;', $row['padding']) . truncate_string($row['forum_name'], 30, 255, false, $user->lang['ELLIPSIS']) . '</option>';
				}

				$sort_days = $total = 0;
				$sort_key = $sort_dir = '';
				$sort_by_sql = $sort_order_sql = array();
				phpbb_mcp_sorting($mode, $sort_days, $sort_key, $sort_dir, $sort_by_sql, $sort_order_sql, $total, $forum_id, $topic_id);
                                                                // nuc
				$forum_topics = ($total == -1) ? $forum_info['forum_topics_reviewed'] : $total;
				$limit_time_sql = ($sort_days) ? 'AND t.topic_last_post_time >= ' . (time() - ($sort_days * 86400)) : '';

				$forum_names = array();

				if (!$is_topics)
				{
					$sql = 'SELECT p.post_id
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t' . (($sort_order_sql[0] == 'u') ? ', ' . USERS_TABLE . ' u' : '') . '
						WHERE ' . $db->sql_in_set('p.forum_id', $forum_list) . '
							AND ' . $db->sql_in_set('p.post_visibility', $visibility_const) . '
							' . (($sort_order_sql[0] == 'u') ? 'AND u.user_id = p.poster_id' : '') . '
							' . (($topic_id) ? 'AND p.topic_id = ' . $topic_id : '') . "
							AND t.topic_id = p.topic_id
							AND (t.topic_visibility <> p.post_visibility
								OR t.topic_delete_user = 0)
                            AND p.post_reviewed = 0    
							$limit_time_sql
						ORDER BY $sort_order_sql";

					/**
					* Alter sql query to get posts in queue to be accepted
					*
					* @event core.mcp_queue_get_posts_query_before
					* @var	string	sql						Associative array with the query to be executed
					* @var	array	forum_list				List of forums that contain the posts
					* @var	int		visibility_const		Integer with one of the possible ITEM_* constant values
					* @var	int		topic_id				If topic_id not equal to 0, the topic id to filter the posts to display
					* @var	string	limit_time_sql			String with the SQL code to limit the time interval of the post (Note: May be empty string)
					* @var	string	sort_order_sql			String with the ORDER BY SQL code used in this query
					* @since 3.1.0-RC3
					*/
					$vars = array(
						'sql',
						'forum_list',
						'visibility_const',
						'topic_id',
						'limit_time_sql',
						'sort_order_sql',
					);
					extract($phpbb_dispatcher->trigger_event('core.mcp_queue_get_posts_query_before', compact($vars)));

					$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

					$i = 0;
					$post_ids = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$post_ids[] = $row['post_id'];
						$row_num[$row['post_id']] = $i++;
					}
					$db->sql_freeresult($result);

					if (sizeof($post_ids))
					{
						$sql = 'SELECT t.topic_id, t.topic_title, t.forum_id, p.post_id, p.post_subject,p.post_text,  p.post_username, p.poster_id, p.post_time, p.post_attachment, u.username, u.username_clean, u.user_colour
							FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . USERS_TABLE . ' u
							WHERE ' . $db->sql_in_set('p.post_id', $post_ids) . '
								AND t.topic_id = p.topic_id
								AND u.user_id = p.poster_id
							ORDER BY ' . $sort_order_sql;
						$result = $db->sql_query($sql);

						$post_data = $rowset = array();
						while ($row = $db->sql_fetchrow($result))
						{
							$forum_names[] = $row['forum_id'];
							$post_data[$row['post_id']] = $row;
						}
						$db->sql_freeresult($result);

						foreach ($post_ids as $post_id)
						{
							$rowset[] = $post_data[$post_id];
						}
						unset($post_data, $post_ids);
					}
					else
					{
						$rowset = array();
					}
				}
				else
				{
				    $sql = 'SELECT t.topic_id, t.topic_title, t.forum_id, p.post_id, p.post_subject, p.post_text, p.post_username, p.poster_id, p.post_time, p.post_attachment, u.username, u.username_clean, u.user_colour
							FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . USERS_TABLE . ' u
							WHERE ' . $db->sql_in_set('p.forum_id', $forum_list) . '
                                AND  ' . $db->sql_in_set('t.topic_visibility', $visibility_const) . "
								AND t.topic_id = p.topic_id
								AND u.user_id = p.poster_id
                                AND t.topic_delete_user <> 0
                                AND t.topic_posts_reviewed = 0
                                $limit_time_sql
			                    ORDER BY $sort_order_sql";
							

					/**
					* Alter sql query to get information on all topics in the list of forums provided.
					*
					* @event core.mcp_queue_get_posts_for_topics_query_before
					* @var	string	sql						String with the query to be executed
					* @var	array	forum_list				List of forums that contain the posts
					* @var	int		visibility_const		Integer with one of the possible ITEM_* constant values
					* @var	int		topic_id				topic_id in the page request
					* @var	string	limit_time_sql			String with the SQL code to limit the time interval of the post (Note: May be empty string)
					* @var	string	sort_order_sql			String with the ORDER BY SQL code used in this query
					* @since 3.1.0-RC3
					*/
					$vars = array(
						'sql',
						'forum_list',
						'visibility_const',
						'topic_id',
						'limit_time_sql',
						'sort_order_sql',
					);
					extract($phpbb_dispatcher->trigger_event('core.mcp_queue_get_posts_for_topics_query_before', compact($vars)));
//trigger_error($sql);
					$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

					$rowset = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$forum_names[] = $row['forum_id'];
						$rowset[] = $row;
					}
					$db->sql_freeresult($result);
				}

				if (sizeof($forum_names))
				{
					// Select the names for the forum_ids
					$sql = 'SELECT forum_id, forum_name
						FROM ' . FORUMS_TABLE . '
						WHERE ' . $db->sql_in_set('forum_id', $forum_names);
					$result = $db->sql_query($sql, 3600);

					$forum_names = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$forum_names[$row['forum_id']] = $row['forum_name'];
					}
					$db->sql_freeresult($result);
				}

				foreach ($rowset as $row)
				{
					if (empty($row['post_username']))
					{
						$row['post_username'] = $row['username'] ?: $user->lang['GUEST'];
					}
                    
                    $template->assign_block_vars('postrow', array(
						'U_TOPIC'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id']),
						'U_VIEWFORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']),
						'U_VIEWPOST'		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;p=' . $row['post_id']) . (($mode == 'unapproved_posts') ? '#p' . $row['post_id'] : ''),
						'U_VIEW_DETAILS'	=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=queue&amp;start=$start&amp;mode=approve_details&amp;f={$row['forum_id']}&amp;p={$row['post_id']}" . (($mode == 'unapproved_topics') ? "&amp;t={$row['topic_id']}" : '')),

						'POST_AUTHOR_FULL'		=> get_username_string('full', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'POST_AUTHOR'			=> get_username_string('username', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'U_POST_AUTHOR'			=> get_username_string('profile', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),

						'POST_ID'		=> $row['post_id'],
						'TOPIC_ID'		=> $row['topic_id'],
						'FORUM_NAME'	=> $forum_names[$row['forum_id']],
						'POST_SUBJECT'	=> ($row['post_text'] != '') ? 
                                            bbcode_nl2br($row['post_text']) :
                                            $user->lang['NO_SUBJECT'],
						'TOPIC_TITLE'	=> $row['topic_title'],
						'POST_TIME'		=> $user->format_date($row['post_time']),
						'ATTACH_ICON_IMG'	=> ($auth->acl_get('u_download') && $auth->acl_get('f_download', $row['forum_id']) && $row['post_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
					));
				}
                //trigger_error('kuku 3 n=',count($rowset));
				unset($rowset, $forum_names);

				$base_url = $this->u_action . "&amp;f=$forum_id&amp;st=$sort_days&amp;sk=$sort_key&amp;sd=$sort_dir";
				$pagination->generate_template_pagination($base_url, 'pagination', 'start', $total, $config['topics_per_page'], $start);

				// Now display the page
				$template->assign_vars(array(
					'L_DISPLAY_ITEMS'		=> (!$is_topics) ? $user->lang['DISPLAY_POSTS'] : $user->lang['DISPLAY_TOPICS'],
					'L_EXPLAIN'				=> $user->lang['MCP_CONCEPTS_' . strtoupper($mode) . '_EXPLAIN'],
					'L_TITLE'				=> $user->lang['MCP_CONCEPTS_' . strtoupper($mode)],
					'L_ONLY_TOPIC'			=> ($topic_id) ? sprintf($user->lang['ONLY_TOPIC'], $topic_info['topic_title']) : '',

					'S_FORUM_OPTIONS'		=> $forum_options,
					'S_MCP_ACTION'			=> build_url(array('t', 'f', 'sd', 'st', 'sk')),
					'S_TOPICS'				=> $is_topics,
					'S_RESTORE'				=> $is_restore,

					'TOPIC_ID'				=> $topic_id,
					'TOTAL'					=> $user->lang(((!$is_topics) ? 'VIEW_TOPIC_POSTS' : 'VIEW_FORUM_TOPICS'), (int) $total),
				));

                
				$this->tpl_name = 'mcp_concepts';
			break;
		}
	}

	/**
	* Approve/Restore posts
	*
	* @param $action		string	Action we perform on the posts ('approve' or 'restore')
	* @param $post_id_list	array	IDs of the posts to approve/restore
	* @param $id			mixed	Category of the current active module
	* @param $mode			string	Active module
	* @return null
	*/
	static public function add_post_concepts($action, $form_posts, $id, $mode)
	{
		global $db, $template, $user, $config, $request, $phpbb_container, $phpbb_dispatcher;
		global $phpEx, $phpbb_root_path;

        foreach ($form_posts as $k => $v)
		{  if(empty($v)) continue;   
           $post_concept_list[$k] = $v;
           $post_id_list[] = $k;
        }
        //trigger_error('kuku 3 '.print_r($form_posts,true));
//trigger_error('kuku 3 c='.print_r($post_concept_list,true).' i'.print_r($post_id_list,true));

		if (!phpbb_check_ids($post_id_list, POSTS_TABLE, 'post_id', array('m_approve')))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$redirect = $request->variable('redirect', build_url(array('quickmod')));
        //trigger_error($redirect);
		$redirect = reapply_sid($redirect);
		$success_msg = $post_url = '';
		$review_log = array();
		$num_topics = 0;

		$s_hidden_fields = build_hidden_fields(array(
			'i'				=> $id,
			'mode'			=> $mode,
			'post_id_list'	=> $post_id_list,
			'action'		=> $action,
			'redirect'		=> $redirect,
		));
        //trigger_error('kuku 3 '.$redirect.' '.$success_msg.' '.$s_hidden_fields);
		$post_info = phpbb_get_post_data($post_id_list, 'm_approve');
        $post_concepts = $post_concept_list;
        //trigger_error('kuku 3 '.print_r($post_info,true));
//trigger_error('kuku 3 rr='.print_r($post_concept_list,true).' fp='.print_r($form_posts,true).' pi='.print_r($post_info,true));
		if (1)//(confirm_box(true))
		{ //trigger_error('kuku 3 ');
        //trigger_error('kuku 3 rr='.print_r($post_concept_list,true).' fp='.print_r($form_posts,true));
			$notify_poster = ($action == 'add_concepts' && isset($_REQUEST['notify_poster']));
            //trigger_error('kuku 3 kk');
            $sql="";
            //trigger_error('kuku 3 '.print_r($post_info,true));
			// Group the posts by topic_id
			foreach ($post_info as $post_id => $post_data)
			{    
				if ($post_data['post_visibility'] != ITEM_APPROVED)
				{
					continue;
				}
                
                //nuc
                $conc =  mysql_real_escape_string($post_concept_list[$post_id]);
                //$conc =  $post_id;
                //trigger_error('kuku 3 rr='.print_r($post_concept_list,true).' fp='.print_r($form_posts,true));
                //trigger_error('kuku 3 rr='.print_r($post_info,true));
                //trigger_error('kuku 3 rr='.$conc);
                $sql = "INSERT INTO " . NUC_ALL_CONCEPTS_TABLE . 
                    " (concept,hash,forum_id,topic_id,post_id,owner_id,last_post_time) values ('".
                    $conc . "','". md5($conc) . "'," .$post_data['forum_id']. "," .$post_data['topic_id']. "," . $post_id .
                    "," .$post_data['poster_id']. "," . time() . ")";
                $result = $db->sql_query($sql);  
                $db->sql_freeresult($result);  

				$post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$post_data['forum_id']}&amp;t={$post_data['topic_id']}&amp;p={$post_data['post_id']}") . '#p' . $post_data['post_id'];

				$review_log[] = array(
					'forum_id'		=> $post_data['forum_id'],
					'topic_id'		=> $post_data['topic_id'],
					'post_subject'	=> $conc,
				);
			}
            //trigger_error('kuku 3 '.$sql);
            //trigger_error('kuku 3 '.print_r($post_info,true));

            foreach ($review_log as $log_data)
			{
				add_log('mod', $log_data['forum_id'], $log_data['topic_id'], 'LOG_POST_' . strtoupper($action) , $log_data['post_subject']);
			}

			if ($num_topics >= 1)
			{
				$success_msg = ($num_topics == 1) ? 'TOPIC_' . strtoupper($action) . 'D_SUCCESS' : 'TOPICS_' . strtoupper($action) . 'D_SUCCESS';
			}
			else
			{
				$success_msg = (sizeof($post_info) == 1) ? 'POST_' . strtoupper($action) . 'D_SUCCESS' : 'POSTS_' . strtoupper($action) . 'D_SUCCESS';
			}

			/**
			 * Perform additional actions during post(s) approval
			 *
			 * @event core.approve_posts_after
			 * @var	string	action				Variable containing the action we perform on the posts ('approve' or 'restore')
			 * @var	array	post_info			Array containing info for all posts being approved
			 * @var	array	topic_info			Array containing info for all parent topics of the posts
			 * @var	int		num_topics			Variable containing number of topics
			 * @var bool	notify_poster		Variable telling if the post should be notified or not
			 * @var	string	success_msg			Variable containing the language key for the success message
			 * @var string	redirect			Variable containing the redirect url
			 * @since 3.1.4-RC1
			 */
			$vars = array(
				'action',
				'post_info',
				'topic_info',
				'num_topics',
				'notify_poster',
				'success_msg',
				'redirect',
			);
			extract($phpbb_dispatcher->trigger_event('core.approve_posts_after', compact($vars)));

			meta_refresh(3, $redirect);
			$message = $user->lang[$success_msg];

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response;
				$json_response->send(array(
					'MESSAGE_TITLE'		=> $user->lang['INFORMATION'],
					'MESSAGE_TEXT'		=> $message,
					'REFRESH_DATA'		=> null,
					'visible'			=> true,
				));
			}
			$message .= '<br /><br />' . $user->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');

			// If approving one post, also give links back to post...
			if (sizeof($post_info) == 1 && $post_url)
			{
				$message .= '<br /><br />' . $user->lang('RETURN_POST', '<a href="' . $post_url . '">', '</a>');
			}
			trigger_error($message);
		}
		
		redirect($redirect);
	}

	/**
	* Approve/Restore topics
	*
	* @param $action		string	Action we perform on the posts ('approve' or 'restore')
	* @param $topic_id_list	array	IDs of the topics to approve/restore
	* @param $id			mixed	Category of the current active module
	* @param $mode			string	Active module
	* @return null
	*/
	static public function approve_topics($action, $topic_id_list, $id, $mode)
	{
		global $db, $template, $user, $config;
		global $phpEx, $phpbb_root_path, $request, $phpbb_container, $phpbb_dispatcher;

		if (!phpbb_check_ids($topic_id_list, TOPICS_TABLE, 'topic_id', array('m_approve')))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$redirect = $request->variable('redirect', build_url(array('quickmod')));
		$redirect = reapply_sid($redirect);
		$success_msg = $topic_url = '';
		$approve_log = array();

		$s_hidden_fields = build_hidden_fields(array(
			'i'				=> $id,
			'mode'			=> $mode,
			'topic_id_list'	=> $topic_id_list,
			'action'		=> $action,
			'redirect'		=> $redirect,
		));

		$topic_info = phpbb_get_topic_data($topic_id_list, 'm_approve');

		if (confirm_box(true))
		{
			$notify_poster = ($action == 'approve' && isset($_REQUEST['notify_poster'])) ? true : false;

			$phpbb_content_visibility = $phpbb_container->get('content.visibility');
			$first_post_ids = array();

			foreach ($topic_info as $topic_id => $topic_data)
			{
				$phpbb_content_visibility->set_topic_visibility(ITEM_APPROVED, $topic_id, $topic_data['forum_id'], $user->data['user_id'], time(), '');
				$first_post_ids[$topic_id] = (int) $topic_data['topic_first_post_id'];

				$topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$topic_data['forum_id']}&amp;t={$topic_id}");

				$approve_log[] = array(
					'forum_id'		=> $topic_data['forum_id'],
					'topic_id'		=> $topic_data['topic_id'],
					'topic_title'	=> $topic_data['topic_title'],
				);
			}

			if (sizeof($topic_info) >= 1)
			{
				$success_msg = (sizeof($topic_info) == 1) ? 'TOPIC_' . strtoupper($action) . 'D_SUCCESS' : 'TOPICS_' . strtoupper($action) . 'D_SUCCESS';
			}

			foreach ($approve_log as $log_data)
			{
				add_log('mod', $log_data['forum_id'], $log_data['topic_id'], 'LOG_TOPIC_' . strtoupper($action) . 'D', $log_data['topic_title']);
			}

			// Only send out the mails, when the posts are being approved
			if ($action == 'approve')
			{
				// Grab the first post text as it's needed for the quote notification.
				$sql = 'SELECT topic_id, post_text
					FROM ' . POSTS_TABLE . '
					WHERE ' . $db->sql_in_set('post_id', $first_post_ids);
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$topic_info[$row['topic_id']]['post_text'] = $row['post_text'];
				}
				$db->sql_freeresult($result);

				// Handle notifications
				$phpbb_notifications = $phpbb_container->get('notification_manager');

				foreach ($topic_info as $topic_id => $topic_data)
				{
					$topic_data = array_merge($topic_data, array(
						'post_id'		=> $topic_data['topic_first_post_id'],
						'post_subject'	=> $topic_data['topic_title'],
						'post_time'		=> $topic_data['topic_time'],
						'poster_id'		=> $topic_data['topic_poster'],
						'post_username'	=> $topic_data['topic_first_poster_name'],
					));

					$phpbb_notifications->delete_notifications('notification.type.topic_in_queue', $topic_id);

					// Only add notifications, if we are not reapproving post
					// When the topic was already approved, but was edited and
					// now needs re-approval, we don't want to notify the users
					// again.
					if ($topic_data['topic_visibility'] == ITEM_UNAPPROVED)
					{
						$phpbb_notifications->add_notifications(array(
							'notification.type.quote',
							'notification.type.topic',
						), $topic_data);
					}

					$phpbb_notifications->mark_notifications_read('notification.type.quote', $topic_data['post_id'], $user->data['user_id']);
					$phpbb_notifications->mark_notifications_read('notification.type.topic', $topic_id, $user->data['user_id']);

					if ($notify_poster)
					{
						$phpbb_notifications->add_notifications('notification.type.approve_topic', $topic_data);
					}
				}
			}

			/**
			 * Perform additional actions during topics(s) approval
			 *
			 * @event core.approve_topics_after
			 * @var	string	action				Variable containing the action we perform on the posts ('approve' or 'restore')
			 * @var	mixed	topic_info			Array containing info for all topics being approved
			 * @var	array	first_post_ids		Array containing ids of all first posts
			 * @var bool	notify_poster		Variable telling if the poster should be notified or not
			 * @var	string	success_msg			Variable containing the language key for the success message
			 * @var string	redirect			Variable containing the redirect url
			 * @since 3.1.4-RC1
			 */
			$vars = array(
				'action',
				'topic_info',
				'first_post_ids',
				'notify_poster',
				'success_msg',
				'redirect',
			);
			extract($phpbb_dispatcher->trigger_event('core.approve_topics_after', compact($vars)));

			meta_refresh(3, $redirect);
			$message = $user->lang[$success_msg];

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response;
				$json_response->send(array(
					'MESSAGE_TITLE'		=> $user->lang['INFORMATION'],
					'MESSAGE_TEXT'		=> $message,
					'REFRESH_DATA'		=> null,
					'visible'			=> true,
				));
			}
			$message .= '<br /><br />' . $user->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');

			// If approving one topic, also give links back to topic...
			if (sizeof($topic_info) == 1 && $topic_url)
			{
				$message .= '<br /><br />' . $user->lang('RETURN_TOPIC', '<a href="' . $topic_url . '">', '</a>');
			}
			trigger_error($message);
		}
		else
		{
			$show_notify = false;

			if ($action == 'approve')
			{
				foreach ($topic_info as $topic_data)
				{
					if ($topic_data['topic_poster'] == ANONYMOUS)
					{
						continue;
					}
					else
					{
						$show_notify = true;
						break;
					}
				}
			}

			$template->assign_vars(array(
				'S_NOTIFY_POSTER'			=> $show_notify,
				'S_' . strtoupper($action)	=> true,
			));

			confirm_box(false, strtoupper($action) . '_TOPIC' . ((sizeof($topic_id_list) == 1) ? '' : 'S'), $s_hidden_fields, 'mcp_approve.html');
		}

		redirect($redirect);
	}

}
