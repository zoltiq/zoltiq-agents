<?php

namespace Zoltiq\Agents\Includes\Abilities;

use Zoltiq\Agents\Includes\Zoltiq_Loader;

defined( 'ABSPATH' ) || exit;


final class Zoltiq_Core_Abilities_Bootstrap {


	protected static $instance = null;


	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	private function __construct() {}


	public function register_category_callbacks( Zoltiq_Loader $loader ): void {
		$loader->add_action( 'wp_abilities_api_categories_init', Plugins\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Themes\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', FileManager\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cache\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Database\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Users\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Block\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Settings\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Fonts\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Content\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Taxonomies\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Media\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Comments\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Menus\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Options\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cron\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', SiteHealth\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Core\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', AdminMenu\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', ContentSearch\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Recovery\Category_Registrar::instance(), 'register' );
	}


	public function register_abilities(): void {
		new Plugins\List_Plugins();
		new Plugins\Activate_Plugin();
		new Plugins\Deactivate_Plugin();
		new Plugins\Install_Plugin();
		new Plugins\Update_Plugin();
		new Plugins\Check_Plugin_Updates();
		new Plugins\Read_Plugin_Structure();
		new Plugins\Read_Plugin_Code();
		new Plugins\Manage_Plugin_Files();
		new Plugins\Get_Plugin_Lifecycle_Context();
		new Themes\Activate_Theme();
		new Themes\Delete_Theme();
		new Themes\Install_Theme();
		new Themes\Update_Theme();
		new Themes\List_Themes();
		new Themes\Read_Theme_Structure();
		new Themes\Read_Theme_Code();
		new Themes\Edit_Theme_File();
		new Themes\Get_Theme_Lifecycle_Context();
		new Settings\Get_Permalink_Structure();
		new Settings\Set_Permalink_Structure();
		new Settings\Flush_Permalink_Structure();
		new Settings\Get_Site_Title();
		new Settings\Update_Site_Title();
		new Settings\Get_Tagline();
		new Settings\Update_Tagline();
		new Settings\Update_Site_Logo();
		new Settings\Get_Site_Icon();
		new Settings\Update_Site_Icon();
		new Users\Get_User();
		new Users\List_Users();
		new Users\Create_User();
		new Users\Update_User();
		new Users\Delete_User();
		new Users\Reset_User_Password();
		new Users\List_User_Roles();
		new Users\Get_Role_Capabilities();
		new Users\Get_Current_User_Access();
		new Cache\Flush_Object_Cache();
		new Cache\Flush_Transients();
		new Cache\Flush_Rewrite_Rules();
		new Database\Extract_Db_Schema();
		new Database\Run_Db_Select_Query();
		new Database\Insert_Db_Row();
		new Database\Update_Db_Rows();
		new Database\Delete_Db_Rows();
		new Database\List_Db_Tables();
		new Database\Explain_Db_Query();
		new Database\Get_Db_Stats();
		new Database\Optimize_Db_Tables();
		new FileManager\Read_File();
		new FileManager\Create_File();
		new FileManager\Edit_File();
		new FileManager\Delete_File();
		new FileManager\Read_Wp_Config();
		new FileManager\Edit_Wp_Config();
		new FileManager\Read_Debug_Log();
		new FileManager\Clear_Debug_Log();
		new FileManager\Create_Zip_Backup();
		new FileManager\Upload_Zip_Backup();
		new FileManager\Extract_Zip_Backup();
		new FileManager\Download_Zip_Backup();
		new FileManager\List_Zip_Backups();
		new FileManager\Delete_Zip_Backup();
		new Block\List_Block_Patterns();
		new Block\Read_Block_Pattern();
		new Block\Create_Block_Pattern();
		new Block\Update_Block_Pattern();
		new Block\Delete_Block_Pattern();
		new Block\List_Block_Templates();
		new Block\Read_Block_Template();
		new Block\Create_Block_Template();
		new Block\Update_Block_Template();
		new Block\Delete_Block_Template();
		new Block\List_Global_Styles();
		new Block\Read_Global_Style();
		new Block\Create_Global_Style();
		new Block\Update_Global_Style();
		new Block\Delete_Global_Style();
		new Block\Read_Theme_Json();
		new Block\Update_Theme_Json();
		new Block\List_Block_Style_Variations();
		new Block\Read_Block_Style_Variation();
		new Block\Create_Block_Style_Variation();
		new Block\Update_Block_Style_Variation();
		new Block\Delete_Block_Style_Variation();
		new Block\List_Blocks();
		new Block\Read_Block();
		new Block\List_Block_Template_Parts();
		new Block\Read_Block_Template_Part();
		new Block\Create_Block_Template_Part();
		new Block\Update_Block_Template_Part();
		new Block\Delete_Block_Template_Part();
		new Block\Get_Site_Editor_Context();
		new Block\Refresh_Site_Editor_Context();
		new Block\List_Reusable_Blocks();
		new Block\List_Block_Areas();
		new Fonts\List_Font_Families();
		new Fonts\Get_Font_Family();
		new Fonts\Create_Font_Family();
		new Fonts\Delete_Font_Family();
		new Fonts\List_Font_Faces();
		new Fonts\Get_Font_Face();
		new Fonts\Create_Font_Face();
		new Fonts\Delete_Font_Face();
		new Content\Create_Post();
		new Content\Get_Post();
		new Content\List_Post_Revisions();
		new Content\List_Posts();
		new Content\Update_Post();
		new Content\Delete_Post();
		new Content\Get_Post_Meta();
		new Content\Update_Post_Meta();
		new Content\Create_Page();
		new Content\Get_Page();
		new Content\List_Page_Revisions();
		new Content\List_Pages();
		new Content\Update_Page();
		new Content\List_Post_Types();
		new Content\Create_Cpt_Item();
		new Content\Get_Cpt_Item();
		new Content\List_Cpt_Item_Revisions();
		new Content\List_Cpt_Items();
		new Content\Update_Cpt_Item();
		new Content\Delete_Cpt_Item();
		new Content\List_Post_Translations();
		new Content\Set_Post_Language();
		new Content\Link_Post_Translation();
		new Content\List_Jet_Engine_Options_Pages();
		new Content\Get_Jet_Engine_Options_Page();
		new Content\Update_Jet_Engine_Options_Page_Field();
		new Content\Update_Post_Block();
		new Content\Inspect_Post_Autosaves();
		new Taxonomies\List_Taxonomies();
		new Taxonomies\Get_Taxonomy();
		new Taxonomies\List_Cpt_Taxonomies();
		new Taxonomies\List_Terms();
		new Taxonomies\Get_Term();
		new Taxonomies\Create_Term();
		new Taxonomies\Update_Term();
		new Taxonomies\Delete_Term();
		new Taxonomies\Assign_Cpt_Terms();
		new Taxonomies\Set_Term_Image();
		new Media\Upload_Media();
		new Media\Get_Media();
		new Media\List_Media();
		new Media\Update_Media();
		new Media\Delete_Media();
		new Media\Get_Media_Meta();
		new Media\Update_Media_Meta();
		new Media\List_Upload_Mime_Types();
		new Media\Update_Upload_Mime_Types();
		new Media\Rename_Media_File();
		new Comments\Create_Comment();
		new Comments\Get_Comment();
		new Comments\List_Comments();
		new Comments\Update_Comment();
		new Comments\Delete_Comment();
		new Comments\Approve_Comment();
		new Comments\Unapprove_Comment();
		new Comments\Mark_As_Spam();
		new Comments\Get_Comment_Meta();
		new Comments\Update_Comment_Meta();
		new Comments\Bulk_Update_Comments();
		new Menus\List_Menus();
		new Menus\Get_Menu();
		new Menus\Create_Menu();
		new Menus\Update_Menu();
		new Menus\Delete_Menu();
		new Menus\List_Menu_Items();
		new Menus\Get_Menu_Item();
		new Menus\Create_Menu_Item();
		new Menus\Update_Menu_Item();
		new Menus\Delete_Menu_Item();
		new Menus\Get_Navigation_Context();
		new Menus\List_Navigation_Locations();
		new Options\Get_Option();
		new Options\Update_Option();
		new Options\Delete_Option();
		new Options\List_Options();
		new Options\Search_Options();
		new Cron\List_Cron_Jobs();
		new Cron\Get_Cron_Job();
		new Cron\Get_Next_Cron_Run();
		new Cron\Check_Cron_Job_Exists();
		new Cron\List_Cron_Schedules();
		new Cron\Get_Cron_Schedule();
		new Cron\Get_Cron_Status();
		new Cron\List_Overdue_Cron_Jobs();
		new Cron\Create_Cron_Job();
		new Cron\Update_Cron_Job();
		new Cron\Run_Cron_Job_Now();
		new Cron\Create_Cron_Schedule();
		new Cron\Delete_Cron_Job();
		new Cron\Delete_Cron_Jobs_By_Hook();
		new Cron\Delete_Cron_Schedule();
		new SiteHealth\Get_Site_Health_Status();
		new SiteHealth\Get_Site_Health_Info();
		new SiteHealth\Get_Site_Maintenance_Report();
		new Core\Check_Wp_Core_Update();
		new Core\Update_Wp_Core();
		new Core\Rollback_Wp_Core();
		new Core\Reinstall_Wp_Core();
		new AdminMenu\Get_Admin_Menu_Context();
		new AdminMenu\Refresh_Admin_Menu_Context();
		new AdminMenu\List_Admin_Menu_Pages();
		new AdminMenu\Get_Admin_Menu_Navigation_Target();
		new AdminMenu\List_Admin_Settings();
		new ContentSearch\Refresh_Content_Index_Batch();
		new ContentSearch\Search_Content_Items();
		new ContentSearch\Search_Content_Chunks();
		new ContentSearch\Find_Related_Content();
		new ContentSearch\Find_Internal_Links();
		new ContentSearch\Get_Internal_Link_Policy();
		new ContentSearch\Create_Internal_Link_Suggestions();
		new ContentSearch\List_Internal_Link_Suggestions();
		new ContentSearch\Review_Internal_Link_Suggestion();
		new ContentSearch\Apply_Internal_Link_Suggestion();
		new ContentSearch\Audit_Internal_Links();
		new ContentSearch\Documents_Search();
		new Recovery\Get_Recovery_Mode_Status();
		new Recovery\List_Paused_Plugins();
		new Recovery\List_Paused_Themes();
		new Recovery\Get_Recovery_Exit_Url();
		new Recovery\Unpause_Plugin();
		new Recovery\Unpause_Theme();
		new Recovery\List_Recent_Fatal_Errors();

	}
}
