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

class mcp_concepts_info
{
	function module()
	{
		return array(
			'filename'	=> 'mcp_concepts',
			'title'		=> 'MCP_CONCEPTS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'unreviewed_topics'	=> array('title' => 'MCP_CONCEPTS_UNREVIEWED_TOPICS', 'auth' => 'aclf_m_approve', 'cat' => array('MCP_CONCEPTS')),
				'unreviewed_posts'	=> array('title' => 'MCP_CONCEPTS_UNREVIEWED_POSTS', 'auth' => 'aclf_m_approve', 'cat' => array('MCP_CONCEPTS')),

			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}
