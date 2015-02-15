<?php
/**
 *
 * @package phpBB Extension - RH Topic Tags
 * @copyright (c) 2014 Robet Heim
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */
namespace robertheim\topictags\tests\service;

use robertheim\topictags\prefixes;
use robertheim\topictags\tables;

class tags_manager_test extends \phpbb_database_test_case
{

	protected function setUp()
	{
		parent::setUp();
		global $table_prefix, $user;
		$this->db = $this->new_dbal();
		$auth = new \phpbb\auth\auth();
		$config = new \phpbb\config\config(array(
			prefixes::CONFIG.'_allowed_tags_regex' => '/^[a-z]{3,30}$/i',
		));
		$this->tags_manager = new \robertheim\topictags\service\tags_manager(
			$this->db, $config, $auth, $table_prefix);
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/tags.xml');
	}

	static protected function setup_extensions()
	{
		return array(
			'robertheim/topictags'
		);
	}

	/**
	 *
	 * @var \phpbb\db\driver\driver_interface
	 */
	protected $db;

	/**
	 *
	 * @var \robertheim\topictags\service\tags_manager
	 */
	protected $tags_manager;

	public function test_remove_all_tags_from_topic()
	{
		global $table_prefix;

		$topic_id = 1;

		// there is one tag assigned to the topic
		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix .
				 tables::TOPICTAGS . '
			WHERE topic_id=' . $topic_id);
		$assigned_tags_count = $this->db->sql_fetchfield('count');
		$this->assertEquals(1, $assigned_tags_count);

		// there exists one unused tag
		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$assigned_tags_count = $this->db->sql_fetchfield('count');
		$this->assertEquals(1, $assigned_tags_count);

		$delete_unused_tags = true;
		$this->tags_manager->remove_all_tags_from_topic($topic_id,
			$delete_unused_tags);

		// there is no tag assigned to the topic
		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix .
				 tables::TOPICTAGS . '
			WHERE topic_id=' . $topic_id);
		$assigned_tags_count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $assigned_tags_count);

		// the unused tag is deleted
		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$assigned_tags_count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $assigned_tags_count);
	}

	public function test_delete_unused_tags()
	{
		global $table_prefix;
		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(1, $count);

		$removed_count = $this->tags_manager->delete_unused_tags();

		$this->assertEquals(1, $removed_count);

		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);
	}

	public function test_calc_count_tags()
	{
		global $table_prefix;

		$result = $this->db->sql_query(
			'SELECT count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);

		$this->tags_manager->calc_count_tags();

		$result = $this->db->sql_query(
			'SELECT count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=1');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(1, $count);

		$result = $this->db->sql_query(
			'SELECT count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);
	}

	public function test_delete_assignments_of_invalid_tags()
	{
		global $table_prefix;

		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix .
				 tables::TOPICTAGS);
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(2, $count);

		// none of the tags is valid to the configured regex [a-z]
		// so all assignments should be deleted.
		$removed_count = $this->tags_manager->delete_assignments_of_invalid_tags();
		$this->assertEquals(2, $removed_count);

		$result = $this->db->sql_query(
			'SELECT COUNT(*) as count
			FROM ' . $table_prefix .
				 tables::TOPICTAGS);
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);

		// both tags are not assigned to any topic now
		$result = $this->db->sql_query(
			'SELECT count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=1');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);

		$result = $this->db->sql_query(
			'SELECT count
			FROM ' . $table_prefix . tables::TAGS . '
			WHERE id=2');
		$count = $this->db->sql_fetchfield('count');
		$this->assertEquals(0, $count);
	}

	public function test_delete_assignments_where_topic_does_not_exist()
	{
		global $table_prefix;
		$none_existing_topic_id = -1;
		$result = $this->db->sql_query(
			'UPDATE ' . $table_prefix . tables::TOPICTAGS . '
			SET topic_id = ' . $none_existing_topic_id . '
			WHERE id=1');
		$removed_count = $this->tags_manager->delete_assignments_where_topic_does_not_exist();
		$this->assertEquals(1, $removed_count);
	}

	public function test_delete_tags_from_tagdisabled_forums()
	{
		global $table_prefix;
		$removed_count = $this->tags_manager->delete_tags_from_tagdisabled_forums(array(1));
		$this->assertEquals(0, $removed_count);
		$removed_count = $this->tags_manager->delete_tags_from_tagdisabled_forums();
		$this->assertEquals(1, $removed_count);
	}
}
