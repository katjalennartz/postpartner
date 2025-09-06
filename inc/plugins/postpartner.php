<?php
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// error_reporting ( -1 );
// ini_set ( 'display_errors', true );

function postpartner_info()
{
  return array(
    "name"          => "Postpartner Suche",
    "description"   => "Mitglieder können im UCP angeben ob sie einen Postpartner suche. Im Header wird zufällig einer dieser angezeigt. Es gibt eine Auflistung aller Suchenden auf einer extra Seite + im UCP. AUf Wunsch wird eine Nachricht in Discord erstellt.",
    "author"        => "risuena",
    "version"       => "1.0",
    "compatibility" => "18*",
  );
}

function postpartner_is_installed()
{
  global $db;
  if ($db->table_exists('postpartner')) {
    return true;
  }
  return false;
}

function postpartner_install()
{
  global $mybb, $db, $cache, $templates;


  postpartner_add_db("install");

  postpartner_add_settings();

  postpartner_add_templates();
}



function postpartner_activate()
{
  require_once(MYBB_ROOT . "inc/adminfunctions_templates.php");
  // UCP find_replace_templatesets('header', "#" . preg_quote('<br>{$zufall_partner}') . "#", '{$postpartner_header}');
  find_replace_templatesets('member_profile', "#" . preg_quote('{$avatar}</td></tr>') . "#", '{$avatar}</td></tr>{$postpartner_search}');
  find_replace_templatesets(
    'usercp_nav_misc',
    "#" . preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">') . "#",
    '<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e"><tr><td class="trow1 smalltext"><a href="usercp.php?action=postpartner">Postpartner</a></td></tr>'
  );
}

function postpartner_zufall_deactivate()
{
  require_once(MYBB_ROOT . "inc/adminfunctions_templates.php");
  find_replace_templatesets('header', "#" . preg_quote('<br>{$zufall_partner}') . "#", '');
  // UCP find_replace_templatesets('header', "#" . preg_quote('<br>{$zufall_partner}') . "#", '');

  find_replace_templatesets('usercp_nav_misc', "#" . preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e"><tr><td class="trow1 smalltext"><a href="usercp.php?action=postpartner">Postpartner</a></td></tr>') . "#", '');
}

function postpartner_uninstall()
{
  global $db;
  // Einstellungen entfernen
  $db->delete_query("settings", "name LIKE 'postpartner%'");
  $db->delete_query('settinggroups', "name = 'postpartner'");
  //feld in user tabelle
  if ($db->field_exists("postpartner", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP postpartner");
  }

  //templates noch entfernen
  rebuild_settings();
}

$plugins->add_hook("usercp_start", "postpartner_usercp");
function postpartner_usercp()
{
  global $db, $mybb, $lang, $cache, $templates, $page, $theme, $headerinclude, $header, $footer, $usercpnav, $yes_pp, $no_pp, $none_pp, $postpartner_ideas;
  if ($mybb->input['action'] != "postpartner") {
    return false;
  }

  add_breadcrumb("$lang->nav_usercp", "usercp.php");
  add_breadcrumb("Postpartner", "usercp.php?action=postpartner");

  $pp_data = $db->fetch_array($db->simple_select("postpartner", "*", "uid = {$mybb->user['uid']}"));
  if ($pp_data['search'] == 1) {

    $yes_pp = "CHECKED";
    $no_pp = "";
    $none_pp = "";
  } else 
  if ($pp_data['search'] == 0) {
    $yes_pp = "";
    $no_pp = "";
    $none_pp = "CHECKED";
  } else {
    $yes_pp = "";
    $no_pp = "CHECKED";
    $none_pp = "";
  }

  $postpartner_ideas = htmlspecialchars_uni($pp_data['ideas']);

  //do_postpartner
  if ($mybb->input['do_postpartner'] && $mybb->request_method == "post") {
    verify_post_check($mybb->input['my_post_key']);
    $check = $db->simple_select("postpartner", "*", "uid = {$mybb->user['uid']} AND search = 1");
    $data = array(
      "uid" => $mybb->user['uid'],
      "search" => $mybb->input['postpartnersearch'],
      "ideas" => $db->escape_string($mybb->input['postpartnerideas'])
    );


    if ($db->num_rows($check)) {
      $db->update_query("postpartner", $data, "uid = {$mybb->user['uid']}");
    } else {
      $db->insert_query("postpartner", $data);
    }
    if ($mybb->input['postpartnersearch'] == 1) {
      postpartner_webhook_discord($mybb->user['uid'], $mybb->input['postpartnerideas']);
    }
    redirect("usercp.php?action=postpartner");
  }

  //list of postpartners
  $pp_dataall = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "postpartner p, " . TABLE_PREFIX . "users u WHERE u.uid = p.uid and search = 1");

  while ($all_data = $db->fetch_array($pp_dataall)) {
    $sceneideascontent = "";

    $postpartner = get_user($all_data['uid']);
    $partner_ava = "<img src=" . $postpartner['avatar'] . " alt=\"ava\"/>";
    $partner_username =  $postpartner['username'];
    $partner_uid = $all_data['uid'];
    if ($all_data['ideas'] != "") {
      require_once MYBB_ROOT . "inc/class_parser.php";

      $parser = new postParser;
      $options = array(
        "allow_html" => $mybb->settings['postpartner_ideasHTML'],
        "allow_mycode" => $mybb->settings['postpartner_ideasMyCode'],
        "allow_smilies" => $mybb->settings['postpartner_ideasSmilies'],
        "allow_imgcode" => $mybb->settings['postpartner_ideasIMG'],
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
      );
      $sceneideascontent = $parser->parse_message($all_data['ideas'], $options);
    }

    eval("\$postpartner_ucplist .= \"" . $templates->get("postpartner_ucp_main_listbit") . "\";");
  }

  eval("\$page = \"" . $templates->get('postpartner_ucp_main') . "\";");
  output_page($page);
  die();
}

$plugins->add_hook("member_profile_end", "postpartner_profile");
function postpartner_profile()
{
  global $db, $mybb, $memprofile, $templates, $postpartner_profile;
  $postpartner_profile = "";
  $searchstr_pp = "";
  //$lang, $cache, $templates, $page, $theme, $headerinclude, $header, $footer, $usercpnav;
  // echo $memprofile['uid'];
  $pp_data = $db->fetch_array($db->simple_select("postpartner", "*", "uid = '{$memprofile['uid']}' AND search = 1"));

  if ($mybb->settings['postpartner_ideas'] != "" && $pp_data['ideas'] != "") {

    require_once MYBB_ROOT . "inc/class_parser.php";

    $parser = new postParser;
    $options = array(
      "allow_html" => $mybb->settings['postpartner_ideasHTML'],
      "allow_mycode" => $mybb->settings['postpartner_ideasMyCode'],
      "allow_smilies" => $mybb->settings['postpartner_ideasSmilies'],
      "allow_imgcode" => $mybb->settings['postpartner_ideasIMG'],
      "filter_badwords" => 0,
      "nl2br" => 1,
      "allow_videocode" => 0
    );
    $sceneideas = $parser->parse_message($pp_data['ideas'], $options);
  }

  if ($pp_data['search'] == 1) {
    $ppclassstr = "pp_yes";
    eval("\$searchstr_pp = \"" . $templates->get("postpartner_profile_pp_yes") . "\";");
  } else 
  if ($pp_data['search'] == 0) {
    $ppclassstr = "pp_no";
    eval("\$searchstr_pp = \"" . $templates->get("postpartner_profile_pp_no") . "\";");
  } else {
    $ppclassstr = "pp_none";
    $searchstr_pp = "";
  }

  eval("\$postpartner_profile = \"" . $templates->get("postpartner_profile") . "\";");
}

$plugins->add_hook("global_intermediate", "postpartner_header");
function postpartner_header()
{

  global $mybb, $db, $memprofile, $lang, $cache, $templates, $theme, $headerinclude, $header, $footer, $partner_uid, $partner_username, $partner_ava, $postpartner_header;

  $erg = $db->query("SELECT * FROM " . TABLE_PREFIX . "postpartner WHERE search = '1' ORDER BY rand() LIMIT 1");
  if ($db->num_rows($erg)) {
    while ($data = $db->fetch_array($erg)) {
      $postpartner = get_user($data['uid']);
      $partner_ava = "<img src=" . $postpartner['avatar'] . " alt=\"ava\"/>";
      if ($mybb->user['uid'] == 0) {
        $partner_ava = "";
      }
      if ($mybb->settings['postpartner_ideas'] != "" && $data['ideas'] != "") {

        require_once MYBB_ROOT . "inc/class_parser.php";

        $parser = new postParser;
        $options = array(
          "allow_html" => $mybb->settings['postpartner_ideasHTML'],
          "allow_mycode" => $mybb->settings['postpartner_ideasMyCode'],
          "allow_smilies" => $mybb->settings['postpartner_ideasSmilies'],
          "allow_imgcode" => $mybb->settings['postpartner_ideasIMG'],
          "filter_badwords" => 0,
          "nl2br" => 1,
          "allow_videocode" => 0
        );
        $sceneideascontent = $parser->parse_message($data['ideas'], $options);

        $sceneideas = " <a onclick=\"$('#scene_header').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[Ideas]</a>";
        $sceneideas_modal = " <div class=\"modal\" id=\"scene_header\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">{$sceneideascontent}</div>
      ";
      } else {
        $sceneideas = "";
      }
      $partner_uid = $postpartner['uid'];
      $partner_username = $postpartner['username'];
    }
    eval("\$postpartner_header = \"" . $templates->get("postpartner_header") . "\";");
  }
}

$plugins->add_hook('build_forumbits_forum', 'postpartner_forumbit');
function postpartner_forumbit(&$forum)
{
  global $db, $mybb, $gesuche, $templates;
  $forum['postpartner'] = "";
  $forumfid = $mybb->settings['postpartner_forum'];
  if ($forum['fid'] == "{$forumfid}") {
    $erg = $db->query("SELECT * FROM " . TABLE_PREFIX . "postpartner WHERE search = '1' ORDER BY rand() LIMIT 1");
    if ($db->num_rows($erg)) {
      while ($data = $db->fetch_array($erg)) {
        $postpartner = get_user($data['uid']);
        $partner_ava = "<img src=" . $postpartner['avatar'] . " alt=\"ava\"/>";
        if ($mybb->user['uid'] == 0) {
          $partner_ava = "";
        }
        if ($mybb->settings['postpartner_ideas'] != "" && $data['ideas'] != "") {

          require_once MYBB_ROOT . "inc/class_parser.php";

          $parser = new postParser;
          $options = array(
            "allow_html" => $mybb->settings['postpartner_ideasHTML'],
            "allow_mycode" => $mybb->settings['postpartner_ideasMyCode'],
            "allow_smilies" => $mybb->settings['postpartner_ideasSmilies'],
            "allow_imgcode" => $mybb->settings['postpartner_ideasIMG'],
            "filter_badwords" => 0,
            "nl2br" => 1,
            "allow_videocode" => 0
          );
          $sceneideascontent = $parser->parse_message($data['ideas'], $options);

          $sceneideas = "
      ";
        } else {
          $sceneideas = "";
        }
        $partner_uid = $postpartner['uid'];
        $partner_username = $postpartner['username'];
      }
      $forum['postpartner'] .= eval($templates->render('postpartner_forumbit'));
    }
  }
}

/**
 * Was passiert wenn ein User gelöscht wird
 * Einträge aus clubliste löschen
 */
$plugins->add_hook("admin_user_users_delete_commit_end", "postpartner_userdelete");
function postpartner_userdelete()
{
  global $db, $cache, $mybb, $user;
  $todelete = (int)$user['uid'];
  $db->delete_query('postpartner', "uid = " . $todelete . "");
}


function postpartner_webhook_discord($uid, $idea)
{
  global $mybb, $db;
  $webhookurl = $mybb->settings['postpartner_discord_webhook_url'];

  if ($idea == "") {
    $idea = "sucht einen Postpartner!";
  }
  $urlstring = "pp_{$uid}";
  $url = $mybb->settings['bburl'] . '/misc.php?action=lists_postpartner#' . $urlstring;

  $headers = ['Content-Type: application/json; charset=utf-8'];
  $user = get_user($uid);

  $as_uid = $user['as_uid'];
  if ($as_uid == 0) {
    $uid = $user['uid'];
  } else {
    $uid = $as_uid;
  }
  $discordfield = $mybb->settings['postpartner_discord_webhook_mention_userid'];

  if ($mybb->settings['postpartner_discord_webhook_mention'] == 1) {
    if (is_numeric($mybb->settings['postpartner_discord_webhook_mention_userid'])) {
      //profilfeld
      $fid = "fid" . $discordfield;
      $discordid = $db->fetch_field($db->simple_select("userfields", "{$fid}}", "ufid='{$uid}'"), "{$fid}");
    } else {
      //steckbriefplugin
      $discordfid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '" . $discordfield . "'"), "id");
      $discordid = $db->fetch_field($db->simple_select("application_ucp_fields", "value", "uid = '$uid' and fieldid = '" . $discordfid . "'"), "value");
    }
  }

  if ($mybb->settings['postpartner_discord_webhook_type'] == 1) {
    //Forum
    if ($mybb->settings['postpartner_discord_webhook_mention'] == 1) {
      //mit Mentions
      $POST = [
        'username' => $user['username'],
        'avatar_url' => $mybb->settings['bburl'] . $user['avatar'],
        'thread_name' =>  $user['username'] . ' sucht einen Postpartner!',
        'content' => "<@{$discordid}>\n{$idea}\n{$url}",
        'allowed_mentions' => [
          'users' => [$discordid]
        ]
      ];
    } else {
      //ohne mentions
      $POST = [
        'username' => $user['username'],
        'avatar_url' => $mybb->settings['bburl'] . $user['avatar'],
        'thread_name' =>  $user['username'] . ' sucht einen Postpartner!',
        'content' => "{$idea}\n{$url}"
      ];
    }
  } else {
    //Textkanal
    if ($mybb->settings['postpartner_discord_webhook_mention'] == 1) {
      $POST = [
        'username' => $user['username'],
        'avatar_url' => $mybb->settings['bburl'] . $user['avatar'],
        'content' => "<@{$discordid}>\n{$idea}\n{$url}",
        'allowed_mentions' => [
          'users' => [$discordid]
        ]
      ];
    } else {
      $POST = [
        'username' => $user['username'],
        'avatar_url' => $mybb->settings['bburl'] . $user['avatar'],
        'content' => "{$idea}\n{$url}"
      ];
    }
  }
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $webhookurl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($POST));
  $response   = curl_exec($ch);


  echo $response;
  curl_close($ch);
}

/**
 * Array mit den Settings für das Plugin
 * @return array settings
 */
function postpartner_settings()
{
  $array = array(
    'postpartner_header' => array(
      'title' => "Anzeige im Header?",
      'description' => "Soll zufällig ein suchender Chara im Header angezeigt werden?",
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    ),
    'postpartner_ideas' => array(
      'title' => "Szenenideen?",
      'description' => "Sollen Mitglieder direkt Szenenideen o.Ä. eintragen können?",
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 2
    ),
    'postpartner_profile' => array(
      'title' => "Anzeige im Profil?",
      'description' => "Soll im Profil angezeigt werden, dass der Charakter sucht (inkl. Szenenideen wenn aktiv)?",
      'optionscode' => 'yesno',
      'value' => '3', // Default
      'disporder' => 2
    ),
    'postpartner_ideasHTML' => array(
      'title' => "HTML in Szenenideen?",
      'description' => "",
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 2
    ),
    'postpartner_ideasMyCode' => array(
      'title' => "MyCode in Szenenideen?",
      'description' => "",
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 2
    ),
    'postpartner_ideasIMG' => array(
      'title' => "Bilder in Szenenideen?",
      'description' => "",
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 2
    ),
    'postpartner_ideasSmilies' => array(
      'title' => "Smilies in Szenenideen?",
      'description' => "",
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_forum' => array(
      'title' => "Soll ein zufälliger Suchender über einem Forum auf dem Index angezeigt werden? Dann gebe hier die Forum ID ein.",
      'description' => "",
      'optionscode' => 'numeric',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_discord_webhook' => array(
      'title' => "Soll automatisch eine Nachricht in Discord erstellt werden?",
      'description' => "",
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_discord_webhook_type' => array(
      'title' => "Discord Kanal Typ",
      'description' => "Handelt es sich um einen einfachen Textkanal oder ein Forum?",
      'optionscode' => "select\n0=Textkanal\n1=Forum",
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_discord_webhook_url' => array(
      'title' => "Discord Webhook",
      'description' => "Trage hier die Webhook Adresse des Kanals ein. (Webhook erstellen -> Servereinstellungen -> Integrationen -> Webhooks)",
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_discord_webhook_mention' => array(
      'title' => "Discord Mention User",
      'description' => "Soll der User, der den Postpartner sucht, in Discord erwähnt werden, damit er den Thread direkt aboniert? (Dazu muss die <a href=\"https://support.discord.com/hc/de/articles/206346498-Wo-kann-ich-meine-Benutzer-Server-Nachrichten-ID-finden#h_01HRSTXPS5H5D7JBY2QKKPVKNA\" target=\"_blank\">Discord ID</a> im Profil hinterlegt sein)",
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'postpartner_discord_webhook_mention_userid' => array(
      'title' => "Discord User ID",
      'description' => "Wo wird die Discord ID im Profil hinterlegt? Wenn als Profilfeld hinterlegt wird, gebe hier einfach nur die ID (nur die Zahl!) des Felds ein, wenn sie über das Steckbrief-Plugin hinterlegt wird gebe hier den eindeutigen Bezeichner des Felds ein.",
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 2
    ),
  );

  return $array;
}
/**
 * Array mit den Templates für das Plugin
 * @return array templates
 */
function postpartner_templates()
{
  global $db;
  $array = array();
  //Templates hinzufügen
  $template[] = array(
    "title" => 'postpartner_ucp_main',
    "template" => $db->escape_string('<html>
      <head>
      <title>{$mybb->settings[\'bbname\']} - Postpartner</title>
      {$headerinclude}
      </head>
      <body>
      {$header}
    
      <table width="100%" border="0" align="center">
      <tr> 
        <td valign="top">
          {$usercpnav}
          <div class="scene_ucp container bl-globalcard">
          <div class="scene_ucp manage alert_item">
        <div class="bl-tabcon__title">
          <div class="forum_line forum_line--profile"></div>
          <span class="bl-boldtitle bl-boldtitle--profile">Postpartner <i class="fas fa-dice" aria-hidden="true"></i></span>
        </div>
            <p>Hier kannst du einstellen ob du einen Postpartner suchst oder nicht. Wenn du ja auswählst, wird er zufällig im Header angezeigt. Schreib doch auch direkt konkrete Idee für Szenen hinein. So lässt sich bestimmt sehr viel leichter ein Partner finden. Bevor du dich selber einträgst, schau die vorhandenen Suchenden an und guck, ob da nicht sogar schon etwas passt. 
          
            </p>
          <div class="bl-tabcon__title">
          <div class="forum_line forum_line--profile"></div>
          <span class="bl-boldtitle bl-boldtitle--profile">settings</span>
        </div>
        
        <form action="usercp.php?action=postpartner" method="post"><!---->
              <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <div class="bl-cardtransparent bl-ucppostpartner">
          <div class="bl-ucppostpartner__item bl-globalcard">
        <label for="index" class="bl-heading4">Suchst du einen Postpartner?</label><br/>
            <input type="radio" name="postpartnersearch" id="postpartner_yes" value="1" {$yes_pp}> <label for="postpartner_yes">Ja</label><br/>
                    <input type="radio" name="postpartnersearch" id="postpartner_no" value="0" {$no_pp}> <label for="postpartner_no">Nein</label><br/>
              <input type="radio" name="postpartnersearch" id="postpartner_none" value="2" {$none_pp}> <label for="postpartner_none">Keine Angabe</label><br />
          </div>
          <div class="bl-ucppostpartner__item bl-globalcard">
            
              <label for="postpartnerideas" class="bl-heading4">Deine Szenenideen</label><br/>
                    <textarea  name="postpartnerideas" id="postpartnerideas" value="1" >{$postpartner_ideas}</textarea>
                  
              </div>
          <div class="bl-ucppostpartner__item bl-ucppostpartner__item--sendbtn">
          <input type="submit" name="do_postpartner" value="speichern" id="index_button" />
          </div>
                      </form>
      
          </div>
        
                <div class="bl-tabcon__title">
          <div class="forum_line forum_line--profile"></div>
          <span class="bl-boldtitle bl-boldtitle--profile">Who is searching?</span>
        </div>
        <div class="bl-cardtransparent bl-ucppostpartner bl-ucppostpartner--list">
          {$postpartner_ucplist}
        </div>
          </div><!--scene_ucp manage alert_item-->
      
      

          </div><!--scene_ucp container-->
        </td>
      </tr>
      </table>
    
      {$footer}
      </body>
      </html>'),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'postpartner_ucp_main_listbit',
    "template" => $db->escape_string('<div class="bl-ucplist__item bl-globalcard">
      <div class="postpartner-search--img">{$partner_ava}<a id="pp_{$postpartner[\'uid\']}"></a></div>
      <div class="postpartner-search--user"><a href="member.php?action=profile&uid={$postpartner[\'uid\']}"><b>{$partner_username}</b></a> </div>
      <div class="postpartner-search--ideas">{$sceneideascontent}</div>
      <div class="postpartner-search--userpn"><i class="fa-solid fa-paper-plane"></i> <a href="private.php?action=send&uid={$partner_uid}">send PM</a></div>
      </div>'),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'postpartner_profile',
    "template" => '
    <div class="{$ppclassstr} postpartner-profile">
	  {$searchstr_pp}
    {$ideas_modal}
    </div>
    ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'postpartner_profile_pp_yes',
    "template" => $db->escape_string('
    Postpartner gesucht <a onclick="$(\'#scene_profile\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[Ideas]</a> <div class="modal" id="scene_profile" style="display: none; padding: 10px; margin: auto; text-align: center;">{$sceneideas}</div>'),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'postpartner_profile_pp_no',
    "template" => $db->escape_string('kein Postpartner gesucht'),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'postpartner_header',
    "template" => $db->escape_string('
    <div class="postpartner-search">
		<div class="postpartner-search--img">{$partner_ava}</div>
		<div class="postpartner-search--user"><a href="member.php?action=profile&uid={$partner_uid}">{$partner_username}</a> sucht einen Postpartner {$sceneideas}</div>
		<div class="postpartner-search--user"><i class="fa-solid fa-paper-plane"></i> <a href="private.php?action=send&uid={$partner_uid}">send PM</a></div>
	</div>'),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

    $template[] = array(
    "title" => 'postpartner_forumbit',
    "template" => $db->escape_string('
      <div class="postpartner-forumbit">
        <div class="postpartner-forumbit__title">play together</div>
        <div class="postpartner-forumbit__postpartnerimg">{$partner_ava}</div>
        <div class="postpartner-forumbit__postpartneruser"><a href="member.php?action=profile&uid={$partner_uid}"><b>{$partner_username}</b></a> sucht einen Postpartner <a onclick="$(\'#scene_forumbit\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[Ideas]</a> <div class="modal" id="scene_forumbit" style="display: none; padding: 10px; margin: auto; text-align: center;">{$sceneideascontent}</div>
        </div>
        <div class="postpartner-forumbit__postpartnerpm"><i class="fa-solid fa-paper-plane"></i> <a href="private.php?action=send&uid={$partner_uid}">send PM</a></div>
      </div>
    
    '),
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  return $template;
}

/**
 * Datenbanktabellen hinzufügen
 * @param string $type install oder update
 */
function postpartner_add_db($type = "install")
{
  global $db;
  if ($type == "install") {
    if (!$db->table_exists('postpartner')) {
      $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "postpartner` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) NOT NULL DEFAULT '0',
        `ideas` varchar(1500) NOT NULL DEFAULT '',
        `search` int(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
      ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
    }
  }
}

/** 
 * Templates hinzufügen
 * @param string $type install oder update
 */
function postpartner_add_templates($type = "install")
{
  global $db;
  //templates bekommen
  $templates = postpartner_templates();
  //installieren oder updaten?
  if ($type == 'install') {
    //Template Gruppe erstellen beim installieren
    $templategrouparray = array(
      'prefix' => 'postpartner',
      'title'  => $db->escape_string('Postpartner'),
      'isdefault' => 1
    );
    $db->insert_query("templategroups", $templategrouparray);
    //templates hinzufügen
    foreach ($templates as $row) {
      $db->insert_query("templates", $row);
    }
  } else {
    // Wir machen ein Update
    foreach ($templates as $row) {
      //templates durchschauen und gucken ob sie vorhanden sind, wenn nicht hinzufügen.
      $check = $db->num_rows($db->simple_select("templates", "title", "title LIKE '{$row['title']}'"));
      if ($check == 0) {
        $db->insert_query("templates", $row);
        // echo "Neues Template {$row['title']} wurde hinzugefügt.<br>";
      }
    }
  }
}

/**
 * Einstellungen hinzufügen
 * @param string $type install oder update
 */
function postpartner_add_settings($type = "install")
{
  global $db;
  //settings bekommen
  $setting_array = postpartner_settings();

  if ($type == "install") {
    // templategruppe erstellen bei Installation
    $setting_group = array(
      'name' => 'postpartner',
      'title' => "Postpartner Suche",
      'description' => "Ein Plugin was Usern die Möglichkeit gibt, anzugeben ob sie einen Postpartner suchen oder nicht. Mögliche zufällige Anzeige im Header.",
      'disporder' => 1,
      'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);

    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
      $db->insert_query('settings', $setting);
    }
  } else {
    //update, wir brauchen nur die ID der Gruppe
    $gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'postpartner'", array("limit" => 1)), "gid");
  }

  if ($type == 'update') {
    //Update, wir checken ob ein setting hinzugefügt oder geupdatet werden muss
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;

      //alte einstellung aus der db holen
      $check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check = $db->num_rows($check);
      if ($check == 0) {
        $db->insert_query('settings', $setting);
        echo "Setting: {$name} wurde hinzugefügt.<br>";
      } else {

        //die einstellung gibt es schon, wir testen ob etwas verändert wurde
        while ($setting_old = $db->fetch_array($check2)) {
          if (
            $setting_old['title'] != $setting['title'] ||
            $setting_old['description'] != $setting['description'] ||
            $setting_old['optionscode'] != $setting['optionscode'] ||
            $setting_old['disporder'] != $setting['disporder']
          ) {
            //wir wollen den value nicht überspeichern, also nur die anderen werte aktualisieren
            $update_array = array(
              'title' => $setting['title'],
              'description' => $setting['description'],
              'optionscode' => $setting['optionscode'],
              'disporder' => $setting['disporder']
            );
            $db->update_query('settings', $update_array, "name='{$name}'");
            echo "Setting: {$name} wurde aktualisiert.<br>";
          }
        }
      }
    }
    echo "<p>Einstellungen wurden aktualisiert</p>";
  }

  rebuild_settings();
}

/*********************
 * UPDATE KRAM
 *********************/

// #####################################
// ### LARAS BIG MAGIC - RPG STUFF MODUL - THE FUNCTIONS ###
// #####################################
$plugins->add_hook("admin_rpgstuff_action_handler", "postpartner_admin_rpgstuff_action_handler");
function postpartner_admin_rpgstuff_action_handler(&$actions)
{
  $actions['postpartner_updates'] = array('active' => 'postpartner_updates', 'file' => 'postpartner_updates');
}

// Benutzergruppen-Berechtigungen im ACP
$plugins->add_hook("admin_rpgstuff_permissions", "postpartner_admin_rpgstuff_permissions");
function postpartner_admin_rpgstuff_permissions(&$admin_permissions)
{
  global $lang;
  $admin_permissions['postpartner'] = "Postpartner: Darf Updates durchführen.";

  return $admin_permissions;
}

// im Menü einfügen
// $plugins->add_hook("admin_rpgstuff_menu", "postpartner_admin_rpgstuff_menu");
// function postpartner_admin_rpgstuff_menu(&$sub_menu)
// {
//   global $lang;
//   $lang->load('postpartner');

//   $sub_menu[] = [
//     "id" => "postpartner",
//     "title" => $lang->postpartner_import,
//     "link" => "index.php?module=rpgstuff-postpartner_transfer"
//   ];
// }



// $plugins->add_hook("admin_load", "postpartner_admin_manage");
// function postpartner_admin_manage()
// {
//   global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $theme;


// }



$plugins->add_hook('admin_rpgstuff_update_plugin', "postpartner_admin_update_plugin");
// postpartner_admin_update_plugin
function postpartner_admin_update_plugin(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_plugin_updates');

  // UPDATE KRAM
  // Update durchführen
  if ($mybb->input['action'] == 'add_update' and $mybb->get_input('plugin') == "postpartner") {

    //Settings updaten
    postpartner_add_settings("update");
    rebuild_settings();

    //templates hinzufügen
    postpartner_add_templates("update");

    //templates bearbeiten wenn nötig
    postpartner_replace_templates();

    //Datenbank updaten
    postpartner_add_db("update");

    //Stylesheets Updaten wen nötig - hinzufügen von neuem css
    //array mit updates bekommen.
    $update_data_all = postpartner_stylesheet_update();
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    //alle Themes bekommen in der es die postpartner.css gibt - sonst erbt sie von irgendwo
    $theme_query = $db->simple_select("themestylesheets", "*", "name='postpartner.css'");

    //styles durchgehen

    while ($theme = $db->fetch_array($theme_query)) {
      //schauen ob es csss zum updaten gibt
      $update_data_all = postpartner_stylesheet_update();

      //array durchgehen mit eventuell hinzuzufügenden strings
      foreach ($update_data_all as $update_data) {
        //hinzuzufügegendes css
        $update_stylesheet = $update_data['stylesheet'];
        //String bei dem getestet wird ob er im alten css vorhanden ist
        $update_string = $update_data['update_string'];
        //updatestring darf nicht leer sein
        if (!empty($update_string)) {
          //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
          $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'postpartner.css' AND stylesheet LIKE '%" . $update_string . "%' ");
          //string war nicht vorhanden
          if ($db->num_rows($test_ifin) == 0) {
            //altes css holen
            $oldstylesheet = $theme['stylesheet'];
            //Hier basteln wir unser neues array zum update und hängen das neue css hinten an das alte dran
            $updated_stylesheet = array(
              "cachefile" => $db->escape_string('postpartner.css'),
              "stylesheet" => $db->escape_string($oldstylesheet . "\n\n" . $update_stylesheet),
              "lastmodified" => TIME_NOW
            );
            $themename = $db->fetch_field($db->simple_select("themes", "name", "tid = '{$theme['tid']}'"), "name");

            $db->update_query("themestylesheets", $updated_stylesheet, "name='postpartner.css' AND tid = '{$theme['tid']}'");
            echo "Im Theme {$themename}(ID:{$theme['tid']}) wurde CSS hinzugefügt -  '$update_string' <br>";
          }
        }
        update_theme_stylesheet_list($theme['tid']);
      }
    }
  }

  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Postpartner") . "</b>", array('width' => '70%'));

  // Überprüfen, ob Update nötig ist 
  $update_check = postpartner_is_updated();

  if ($update_check) {
    $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=postpartner\">" . $lang->plugins_update . "</a>", array('class' => 'align_center'));
  }

  $table->construct_row();
}

/**
 * Funktion um CSS nachträglich oder nach einem MyBB Update wieder hinzuzufügen
 */
$plugins->add_hook('admin_rpgstuff_update_stylesheet', "postpartner_admin_update_stylesheet");
function postpartner_admin_update_stylesheet(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_stylesheet_updates');

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  // HINZUFÜGEN
  if ($mybb->input['action'] == 'add_master' and $mybb->get_input('plugin') == "postpartner") {

    $css = postpartner_stylesheet();

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "postpartner.css"), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
      update_theme_stylesheet_list($theme['tid']);
    }

    flash_message($lang->stylesheets_flash, "success");
    admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
  }

  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Postpartner-Manager") . "</b>", array('width' => '70%'));

  // Ob im Master Style vorhanden
  $master_check = $db->query("SELECT tid FROM " . TABLE_PREFIX . "themestylesheets 
    WHERE name = 'postpartner.css' 
    AND tid = 1");

  if ($db->num_rows($master_check) > 0) {
    $masterstyle = true;
  } else {
    $masterstyle = false;
  }

  if (!empty($masterstyle)) {
    $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=postpartner\">" . $lang->stylesheets_add . "</a>", array('class' => 'align_center'));
  }
  $table->construct_row();
}


/**
 * Aktueller Stylesheet
 * @param int id des themes das hinzugefügt werden soll. Default: 1 -> Masterstylesheet
 * @return array - css array zum eintragen in die db
 */
function postpartner_stylesheet($themeid = 1)
{
  global $db;
  $css = array(
    'name' => 'postpartner.css',
    'tid' => $themeid,
    'attachedto' => '',
    "stylesheet" =>    '
      /* Postpartner Stylesheet */
    ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'postpartner.css')),
    'lastmodified' => time()
  );
  return $css;
}

/**
 * Stylesheet der eventuell hinzugefügt werden muss
 */
function postpartner_stylesheet_update()
{
  // Update-Stylesheet
  // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
  //arrays initialisieren
  $update_array_all = array();

  //array für css welches hinzugefügt werden soll - neuer eintrag in array für jedes neue update

  // $update_array_all[] = array(
  //   'stylesheet' => "
  //     /* update-postpartner - kommentar nicht entfernen */
  //       }
  //   ",
  //   'update_string' => 'update-postpartner'
  // );


  return $update_array_all;
}


function postpartner_replace_templates()
{
  global $db;
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = postpartner_updated_templates();
  if (!empty($update_template_all)) {
    //diese durchgehen
    foreach ($update_template_all as $update_template) {
      //anhand des templatenames holen
      $old_template_query = $db->simple_select("templates", "tid, template", "title = '" . $update_template['templatename'] . "'");
      //in old template speichern
      while ($old_template = $db->fetch_array($old_template_query)) {
        //was soll gefunden werden? das mit pattern ersetzen (wir schmeißen leertasten, tabs, etc raus)

        if ($update_template['action'] == 'replace') {
          $pattern = postpartner_createRegexPattern($update_template['change_string']);
        } elseif ($update_template['action'] == 'add') {
          //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
          $pattern = postpartner_createRegexPattern($update_template['action_string']);
        } elseif ($update_template['action'] == 'overwrite') {
          $pattern = postpartner_createRegexPattern($update_template['change_string']);
        }

        //was soll gemacht werden -> momentan nur replace 
        if ($update_template['action'] == 'replace') {
          //wir ersetzen wenn gefunden wird
          if (preg_match($pattern, $old_template['template'])) {
            $template = preg_replace($pattern, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -replace- {$update_template['templatename']} in {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'add') { //hinzufügen nicht ersetzen
          //ist es schon einmal hinzugefügt wurden? nur ausführen, wenn es noch nicht im template gefunden wird
          if (!preg_match($pattern, $old_template['template'])) {
            $pattern_rep = postpartner_createRegexPattern($update_template['change_string']);
            $template = preg_replace($pattern_rep, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -add- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'overwrite') { //komplett ersetzen
          //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben, wenn nicht überschreiben wir das ganze template
          if (!preg_match($pattern, $old_template['template'])) {
            $template = $update_template['action_string'];
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -overwrite- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
      }
    }
  }
}

/**
 * Hier werden Templates gespeichert, die im Laufe der Entwicklung aktualisiert wurden
 * @return array - template daten die geupdatet werden müssen
 * templatename: name des templates mit dem was passieren soll
 * change_string: nach welchem string soll im alten template gesucht werden
 * action: Was soll passieren - add: fügt hinzu, replace ersetzt (change)string, overwrite ersetzt gesamtes template
 * action_strin: Der string der eingefügt/mit dem ersetzt/mit dem überschrieben werden soll
 */
function postpartner_updated_templates()
{
  global $db;

  //data array initialisieren 
  $update_template = array();


  // $update_template[] = array(
  //   "templatename" => 'postpartner_index_reminder_bit',
  //   "change_string" => '({$lastpostdays} Tage)',
  //   "action" => 'add',
  //   "action_string" => '({$lastpostdays} Tage) - <a href="index.php?action=reminder&sceneid={$sceneid}">[ignore and hide]</a>'
  // );

  return $update_template;
}


/**
 * Funktion um ein pattern für preg_replace zu erstellen
 * und so templates zu vergleichen.
 * @return string - pattern für preg_replace zum vergleich
 */
function postpartner_createRegexPattern($html)
{
  // Entkomme alle Sonderzeichen und ersetze Leerzeichen mit flexiblen Platzhaltern
  $pattern = preg_quote($html, '/');

  // Ersetze Leerzeichen in `class`-Attributen mit `\s+` (flexible Leerzeichen)
  $pattern = preg_replace('/\s+/', '\\s+', $pattern);

  // Passe das Muster an, um Anfang und Ende zu markieren
  return '/' . $pattern . '/si';
}


/**
 * Update Check
 * @return boolean false wenn Plugin nicht aktuell ist
 * überprüft ob das Plugin auf der aktuellen Version ist
 */
function postpartner_is_updated()
{
  global $db, $mybb;
  $needupdate = 0;

  $setting_array = postpartner_settings();
  $gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'activitytracker'"), "gid");

  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
    if ($db->num_rows($check2) > 0) {
      while ($setting_old = $db->fetch_array($check2)) {
        if (
          $setting_old['title'] != $setting['title'] ||
          $setting_old['description'] != $setting['description'] ||
          $setting_old['optionscode'] != $setting['optionscode'] ||
          $setting_old['disporder'] != $setting['disporder']
        ) {
          // echo           $setting_old['title'] ."!=". $setting['title']. "||<br><br>".
          // $setting_old['description'] ."!= ".$setting['description']." ||<br><br>".
          // $setting_old['optionscode'] ."!= ".$setting['optionscode']." ||<br><br>".
          // $setting_old['disporder']." !=". $setting['disporder'] ."<br><br>" ;
          echo "Setting: {$name} muss aktualisiert werden.<br>";
          $needupdate = 1;
        }
      }
    } else {
      echo "Setting: {$name} muss hinzugefügt werden.<br>";
      $needupdate = 1;
    }
  }

  //Testen ob im CSS etwas fehlt
  $update_data_all = postpartner_stylesheet_update();
  //alle Themes bekommen
  $theme_query = $db->simple_select("themestylesheets", "*", "name='postpartner.css'");

  while ($theme = $db->fetch_array($theme_query)) {
    foreach ($update_data_all as $update_data) {
      $update_stylesheet = $update_data['stylesheet'];
      $update_string = $update_data['update_string'];
      if (!empty($update_string)) {
        $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'postpartner.css' AND stylesheet LIKE '%" . $update_string . "%' ");
        if ($db->num_rows($test_ifin) == 0) {
          if (!empty($update_string)) {
            //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
            $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'postpartner.css' AND stylesheet LIKE '%" . $update_string . "%' ");
            //string war nicht vorhanden
            if ($db->num_rows($test_ifin) == 0) {
              echo ("Mindestens Theme {$theme['tid']} muss aktualisiert werden <br>");
              $needupdate = 1;
            }
          }
        }
      }
    }
  }

  //Testen ob eins der Templates aktualisiert werden muss
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = postpartner_updated_templates();
  //alle themes durchgehen
  foreach ($update_template_all as $update_template) {
    //entsprechendes Tamplate holen
    $old_template_query = $db->simple_select("templates", "tid, template, sid", "title = '" . $update_template['templatename'] . "'");
    while ($old_template = $db->fetch_array($old_template_query)) {
      //pattern bilden
      if ($update_template['action'] == 'replace') {
        $pattern = postpartner_createRegexPattern($update_template['change_string']);
        $check = preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'add') {
        //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
        $pattern = postpartner_createRegexPattern($update_template['action_string']);
        $check = !preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'overwrite') {
        //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben
        $pattern = postpartner_createRegexPattern($update_template['change_string']);
        $check = !preg_match($pattern, $old_template['template']);
      }
      //testen ob der zu ersetzende string vorhanden ist
      //wenn ja muss das template aktualisiert werden.
      if ($check) {
        $templateset = $db->fetch_field($db->simple_select("templatesets", "title", "sid = '{$old_template['sid']}'"), "title");
        echo ("Template {$update_template['templatename']} im Template-Set {$templateset}'(SID: {$old_template['sid']}') muss aktualisiert werden. ({$update_template['change_string']} zu {$update_template['action_string']})<br>");
        $needupdate = 1;
      }
    }
  }
  if ($needupdate == 1) {
    return false;
  }
  return true;
}
