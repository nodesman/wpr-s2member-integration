<?php

/*
Plugin Name: WP Autoresponder to s2member Integration Plugin
Plugin URI: http://www.wpresponder.com
Description: Used for adding new users to s2member who subscribe to a newsletter and vice versa. 
Version: 0.4
Author: Raj Sekharan
Author URI: http://www.nodesman.com/
*/

    add_action('admin_menu', '_wprs2_admin_menu');
	add_action('init', "wprs2_init",1);
	register_activation_hook(__FILE__,"_wprs2_install");
	
	function _wprs2_install() {
	   $option = get_option("_wprs2_key");
	   if (empty($option))
	   {
	      $domain = get_bloginfo("url");
		  $time = microtime();
		  $string = $domain.$time;
		  $token = md5($string);
		  update_option("_wprs2_key",$token);
	   }
	}
	
function _wprsd_add_rule($membership,$newsletter,$autoresponder=null)
{
         $s2towprSettings = get_option("_wprs2_s2_to_wpr");
	 if (empty($s2towprSettings)) {
	 
	     $settings = array();		   
	     $settings[$membership][$newsletter] = array("newsletter"=>$newsletter,
	       				                 "autoresponder"=>$autoresponder);
	     update_option("_wprs2_s2_to_wpr",$settings);
	 }
	 else
	 {
                $name = $membership;			
		$s2towprSettings[$membership][$newsletter] = array("newsletter"=>$newsletter,
					         "autoresponder"=>$autoresponder);
		update_option("_wprs2_s2_to_wpr",$s2towprSettings);
	 }
}
function _wprsd_del_rule($membership,$newsletter,$autoresponder=null)
{
        $s2towprSettings = get_option("_wprs2_s2_to_wpr");
	unset($s2towprSettings[$membership][$newsletter]);
	update_option("_wprs2_s2_to_wpr",$s2towprSettings);

}

function _wprs2_get_rules()
{
    $rule  = get_option("_wprs2_s2_to_wpr");
    
    if (!isset($rule) || empty($rule) || !is_array($rule))
    {
        return array();
    }    
    
    return $rule;
    
}

function _wprs2_get_levels()
{

	 global $wp_roles;
	 $roles = $wp_roles->roles;	 
	 $roleInfo = array();
	 foreach ($roles as $name=>$role)
	 {
	      $roleInfo[$name]= $role['name'];
	 }
	return $roleInfo;
}

function wprs2_init() 
{
	global $wpdb;
	if (isset($_GET['wpr-s2-not'])) {
	  	$name = $_GET['name'];
		$email = $_GET['email'];
		$role = $_GET['role'];
		$key = $_GET['key'];
		$key_option = get_option('_wprs2_key');
		if ($key == $key_option)
		{
		   $options = get_option("_wprs2_s2_to_wpr");
		   //get the option for this role
		   $level = $role;
		   //get the corresponding newsletter list
		   
		   if (!isset($options[$level]) || 0 == count($options[$level]))
		      exit;
		   $newsletters = $options[$level];
		   foreach ($newsletters as $nid=>$options) {
		      //check if the subscriber is already present if so, activate
			  $updateSubscriberQuery = sprintf("UPDATE %swpr_subscribers SET active=1, confirmed=1, name='%s' WHERE email='%s' AND nid=%d",$wpdb->prefix,$name, $email,$nid);
			  $wpdb->query($updateSubscriberQuery); 
			  //else add
			  $hash = "";
			  for ($i=0;$i<6;$i++)
				{
					$a[] = rand(65,90);
					$a[] = rand(97,123);
					$a[] = rand(48,57);
					$whichone = rand(0,2);
					$currentCharacter = chr($a[$whichone]);
					$hash .= $currentCharacter;
					unset($a);
				}
				$hash .= time();
  		        $addSubscriberQuery = sprintf("INSERT INTO %swpr_subscribers (nid, name, email, hash, active, confirmed,date) VALUES ('%s','%s','%s','%s',1,1, '%s')",$wpdb->prefix,$nid,$name, $email,$hash, time());
			$wpdb->query($addSubscriberQuery);
                        $id = $wpdb->insert_id;


                        if (!empty($options['autoresponder']) && (0 < intval($options['autoresponder'])))
                            {
                            $time= time();
                            $inesrtAutoresponderQuery = sprintf("INSERT INTO %swpr_followup_subscriptions (sid, type, eid, sequence, doc) VALUES (%d, 'autoresponder', %d, -1, '%s'); ",$wpdb->prefix,$id, $options['autoresponder'], $time);
                            $wpdb->query($inesrtAutoresponderQuery);
                        }
		   }
		   exit;
		   
	    }
		else 
		   exit();
		
		
		
		
	}
   if (isset($_GET['wprs2'])) {
      $getter = $_GET['wprs2'];
	  _wprs2_process();
   }   
   else
       return 0;
	//define the interface that will show the options for the plugin.
}

function _wprs2_autoresponder_list($nid)
{
    global $wpdb;
    $listOAutores = sprintf("SELECT * FROM %swpr_autoresponders WHERE nid=%d",$wpdb->prefix,$nid);

    $autores = $wpdb->get_results($listOAutores);
    return $autores;
}
function _wprs2_newsletter_list() {
    global $wpdb;
	$listOfNewslettersQuery = sprintf("SELECT * FROM %swpr_newsletters",$wpdb->prefix);
	$listOfNewslettersRes = $wpdb->get_results($listOfNewslettersQuery);
        foreach ($listOfNewslettersRes as $index=>$newsletter)
            {
            $listOfNewslettersRes[$index]->autoresponders = _wprs2_autoresponder_list($newsletter->id);
        }
	return $listOfNewslettersRes;
}
function _wprs2_newsletter_get($nid) {
    global $wpdb;
	$listOfNewslettersQuery = sprintf("SELECT * FROM %swpr_newsletters WHERE id=%d",$wpdb->prefix,$nid);

	$listOfNewslettersRes = $wpdb->get_results($listOfNewslettersQuery);
	if (count($listOfNewslettersRes) >0)
		return $listOfNewslettersRes[0];
	else
		return 0;
}

function _wprs2_autoresponder_get($nid) {
    global $wpdb;
	$listOfAutorespondersQuery = sprintf("SELECT * FROM %swpr_autoresponders WHERE id=%d",$wpdb->prefix,$nid);
	$listOfAutoresponders = $wpdb->get_results($listOfAutorespondersQuery);
	if (count($listOfAutoresponders) >0)
		return $listOfAutoresponders[0];
	else
		return 0;
}

function _wprs2_admin_menu()
{

	add_menu_page('WPR s2 Integration','WPR s2 Integration','install_plugins',__FILE__);
	//add_submenu_page(__FILE__,'WPR to s2Member','WPR to s2Member','install_plugins',__FILE__,"_wprs2_wpr_to_s2");
	add_submenu_page(__FILE__,'s2Member to WPR','s2Member to WPR','install_plugins',__FILE__,"_wprs2_s2_to_wpr");
}

function _wprs2_s2_to_wpr() {
	
	if (isset($_GET['act']) && $_GET['act']=='deletetrans') {
	   $nonce = $_POST['_wpnonce'];
	   if (! wp_verify_nonce($nonce, 's2towprdel') ) 
	     die("Security check failed.");
	   
	   $nid = $_GET['nid'];
	   $membership = $_GET['level'];
	   _wprsd_del_rule($membership,$nid);
	}

   if (isset($_POST['s2towpr'])) {
      
	  $nonce = $_POST['_wpnonce'];
	  if (! wp_verify_nonce($nonce, 's2towpradd') ) 
	    die("Security check failed.");
	
	  $membership = $_POST['membership'];
          $autoresponder = intval($_POST['autoresponder']);
	  if (!isset($membership))
	   {
	       $error [] = __("No membership level specified");
	   }
	   
	   $newsletter = $_POST['newsletter'];
	   if (count($newsletter) == 0 )
	   {
	       $error[] = __("No newsletter selected for subscription");
	   }

           $s2towprSettings = get_option("_wprs2_s2_to_wpr");
           
           

          if (!empty($s2towprSettings[$membership][$newsletter]))
               $error[] = __("A rule exists for that newsletter-membership level combination. Please delete the existing rule to add a new one.");

	   if (count($error) != 0)
	   {
		  foreach ($error as $er) {
		    ?>
<div class="error fade" style="padding: 10px;"><big><strong><?php echo $er ?></strong></big></div>
		    <?php
		  }
	   }
	   else {
	     
		 $s2towprSettings = get_option("_wprs2_s2_to_wpr");

		 if (0 == $autoresponder)
		   _wprsd_add_rule($membership, $newsletter);
		 else
		   _wprsd_add_rule($membership, $newsletter,$autoresponder);
		
	}
	  
   }
   		$levels = _wprs2_get_levels();

     
	
	$key = get_option("_wprs2_key");
	
	$real_options = _wprs2_get_rules();

   ?>
                        

<div class="wrap">
<h2>s2Member to WP Autoresponder Integration</h2>
<br/>

<h3>s2Member Notification URL:</h3>

Go to s2Mmeber > API / Notifications > Expand Registration Notifications and add the following there:
<fieldset style="padding: 10px; margin:10px; background-color: #fafa99; border: 1px solid #000;" >
<?php echo get_bloginfo("url") ?>/?wpr-s2-not=1&name=%%user_full_name%%&email=%%user_email%%&role=%%role%%&key=<?php echo $key; ?>
</fieldset>

<h3>Currently defined rules</h3>
Below are the rules defined to subscribe s2member users to newsletters of WPR:<br/><br/>
<table class="widefat">
   <thead>
      <tr>
	     <th width="50">S.No.</th>
		 <th>Membership Level</th>
		 <th>Newsletter(s)</th>
	</tr>
	</thead>    

<?php


$count = 0;
  if (count($real_options) > 0 )
  foreach ($real_options as $level=>$option) {
  if (count($option) == 0 )
     continue;
   ?>
<tr>
   <td><?php echo ++$count; ?></td>
   <td><?php
   
   echo $levels[$level];
   ?></td>
    <td>
	<table>
	   <thead>
	     <tr>
		    <th>Newsletter Name</th>
                    <th>Autoresponder Name</th>
			<th>Delete?</th>

		</tr>
	   </thead>
	<?php
        
	foreach ($option as $nid=>$options ) {
	
	   ?>
	   <tr>
	      <td><?php 

		  $n = _wprs2_newsletter_get($nid);
		  
		  echo $n->name; //echo $nid ?></td>
              <td>
                  <?php
                  if (0 < intval($options['autoresponder']))
                  {
		          $a = _wprs2_autoresponder_get($options['autoresponder']);
		          echo $a->name;
		   }
		   else
		   {
		        echo "None";
		   }
                  ?>
                  
              </td>
		  <td><form action="admin.php?page=<?php echo basename(dirname(__FILE__)); ?>/wpr-s2member.php&act=deletetrans&level=<?php echo $level; ?>&nid=<?php echo $nid ?>" method="post"><?php wp_nonce_field('s2towprdel'); ?><input type="hidden" name="nid" value="<?php echo $nid; ?>"/><input class="button" type="submit" value="Delete"></form>
	   </tr>
		  <?php
		  
	}
	?>
	</table></td>
</tr><?php	
   
  }
  else
     {
	 ?>
	 <tr>
	    <td colspan="3"><div align="center">--No Rules Defined--</div></td>
	 <?php
	 
	 }
?>
</table>
</div>   
   
<h3>Add Rule</h3>
<form action="admin.php?page=<?php echo basename(dirname(__FILE__)); ?>/wpr-s2member.php" method="post">
<table style="width: 600px" class="widefat" cellpadding="10">
    <thead>
        <tr>
		    <th width="170">When a member joins...</th>
			<th>...subscribe them to newsletter and optionally autoresponder..</th>
		</tr>
    </thead>
        <tr>
	     <td>
		 <select name="membership" style="height: 150px;min-width: 150px; " size="3">
		 <?php
		 
		 $roleInfo = _wprs2_get_levels();
		 
		 print_r($roleInfo);
		 
		 
		 foreach ($roleInfo as $level=>$level_name)
		 {
		 ?>
		   <option value="<?php echo $level ?>"><?php echo $level_name ?></option>
		 <?php 
		 }
		 ?>
		 </select>
		 </td>
		 <td>
		 <?php 
		 $newsletters = _wprs2_newsletter_list();
                 
                 foreach ($newsletters as $newsletter)
                     {
                     $finalObject->{"n".$newsletter->id} = $newsletter;
                 }
		 ?>
                     <script>
                         var newsletterList = <?php echo json_encode($finalObject); ?>;

                         function updateAutorespondersList()
                         {
                             //currently selected item
                             var item = document.getElementById("newslist");
                             var currentVal = item.value;
                             var autolist= document.getElementById('autolist');
                             autolist.options.length=0;
                             newsletter = newsletterList["n"+currentVal];
                             
                                     for (var i in newsletter.autoresponders)
                                         {
                                             var o = document.createElement("option");
                                             o.innerHTML=newsletter.autoresponders[i].name;
                                             o.value =newsletter.autoresponders[i].id;
                                             autolist.appendChild(o);
                                         }

                         }
                         </script>
                     <select id="newslist" onchange="updateAutorespondersList()" name="newsletter" size="3" style="min-width: 150px; height: 150px">
		    <?php
			if (count($newsletters) ==0)
			{
			   ?>
			   <option disabled="disabled">--No Newsletters Found--</option>
			   <?php
			}
			else
			{
				foreach ($newsletters as $newsletter) {
				   ?>
				   <option value="<?php echo $newsletter->id ?>"><?php echo $newsletter->name ?></option>
				   <?php
				}
			}
			?>
		 </select>

                 <select size="3" style="min-width: 150px; height: 150px" name="autoresponder" id="autolist">
                     
                 </select>
		 
		 </td>
	</tr>
	<tr>
	  <td><input class="button-primary" value="Add Rule" type="submit"></td>
</table>
<?php wp_nonce_field('s2towpradd'); ?>
</table>
<input type="hidden" name="s2towpr" value="1"/>
</form>

   <?php
}

function _wprs2_wpr_to_s2() {
    
	
	if (isset($_POST['wprs2ruledel'])) {
	   $nonce = $_POST['_wpnonce'];
	  if (! wp_verify_nonce($nonce, 'wprtos2del') ) 
	    die("Security check failed..");
      
	  $settings = get_option("_wprs2_wpr_to_s2");
	  
	  $nid = intval($_GET['nid']);
	  $level = intval($_GET['level']);
	  if (!isset($settings[$nid]))
	     $error[] = __("Non existent setting specified");
	  else
	  {
	     $current_setting = $settings[$nid];
		 $new_setting = array_diff($current_setting,array($level));
		 $settings[$nid] = $new_setting;
		 update_option("_wprs2_wpr_to_s2",$settings);
	  }
	  
	}
	
	
	if (isset($_POST['wprtos2add'])) {
	  $nonce = $_POST['_wpnonce'];
	  if (! wp_verify_nonce($nonce, 'wprtos2add') ) 
	    die("Security check failed..");
	  
	  $error = array();
	  
	  if (!isset($_POST['newsletter'])) {
	     $error[] = __("No newsletter was selected for triggering subscriptions");
	  }
	  if (!isset($_POST['membership']) || count($_POST['membershiop']) ==0 )  {
	  
	    $error[] = __("No memberships were selected for subscription");
	  
		$newsletter = $_POST['newsletter'];
		$memberships = $_POST['membership'];
		$settings = get_option("_wprs2_wpr_to_s2");
		
		$field_name = $newsletter;
		if (!isset($settings[$field_name]))
		{
		   $settings[$field_name] = $memberships;
		}
		else
		{ 
		   $current_settings = $settings[$field_name];
		   $current_settings = array_merge($memberships,$current_settings);
		   $current_settings = array_unique($current_settings);
		   $settings[$field_name] = $current_settings;
		}
		update_option("_wprs2_wpr_to_s2",$settings);
	  }
	}
	
	$settings = get_option("_wprs2_wpr_to_s2");
   ?>
<div class="wrap">
 <h2>WP Autoresponder to s2member Integration</h2>
 <br/>
 Below are a set of rules defined to susbcribe new subscribers of a newsletter to a set of membership levels.<br/><br/>
 <table class="widefat">
   <thead>
      <tr>
	     <th width="50">S.No.</th>
		 <th>Newsletter</th>
		 <th>Membership Level(s)</th>
      </tr>
	</thead>    
<?php
$new_settings = array();
foreach ($settings as $nid=>$memberships) {
   if (count($memberships)>0) {
      $new_settings[$nid] = $memberships;
   }
}

foreach ($new_settings as $nid=>$memberships) {
    

   ?>
<tr>
   <td><?php echo ++$count; ?></td>
   <td><?php 
      $newsletter = _wprs2_newsletter_get($nid);
	  echo $newsletter->name;
   ?></td>
    <td>
	<table>
	   <thead>
	     <tr>
		    <th>Newsletter Name</th>
			<th>Delete?</th>
		</tr>
	   </thead>
	   <tr>
	    <?php
	     $options = get_option("ws_plugin__s2member_options");
		 
	

	   foreach ($memberships as $membership) {
	        $name = str_replace("s2member","",$membership);
			?><td> <?php echo $name; ?></td>
			<td><form action="admin.php?page=s2member-to-wp-autoresponder-integration/wpr-s2member.php&act=deleterule&nid=<?php echo $newsletter->id ?>&level=<?php echo $membership ?>" method="post">
			        <input type="submit" value="Delete" class="button"/>
					<?php wp_nonce_field('wprtos2del'); ?>
					<input type="hidden" name="wprs2ruledel" value="1"/>
				 </form>
			</td>
	    </tr>
			<?php
	   }
	   ?>
	   
	</table>
	</td>
</tr><?php
}


function _wprs2_get_s2member_levels()
{
	
}

?>
</table>
 
<h3>Add Rule</h3>
<form action="admin.php?page=s2member-to-wp-autoresponder-integration/wpr-s2member.php" method="post">
<table style="width: 500px" class="widefat" cellpadding="10">
    <thead>
        <tr>
		    <th width="250">When a member subscribes to newsletter...</th>
			<th>...add them to membership level(s)</th>
		</tr>
    </thead>
        <tr>
	     <td>
		 <?php 
		 	 $levels = array();
		 foreach ($options as $name=>$value)
		 {
		 
		     if (preg_match("@level[0-9]+_label@",$name))
		     {
		        preg_match("@level([0-9]+)_label@",$name,$match);
		        $index = $match[1];
		        $label = $value;
		        $levels["$index"] = $value;
		     }

		 }
		 
		 $newsletters = _wprs2_newsletter_list();
		 ?>
		  <select name="newsletter" size="3" style="min-width: 150px; height: 150px">
		    <?php			
			if (count($newsletters) ==0)
			{
			   ?>
			   <option disabled="disabled">--No Newsletters Found--</option>
			   <?php
			}
			else
			{
				foreach ($newsletters as $newsletter) {
				   ?>
				   <option value="<?php echo $newsletter->id ?>"><?php echo $newsletter->name ?></option>
				   <?php
				}
			}
			?>
		 </select>
		 </td>
		 <td>
	<select name="membership[]" style="height: 150px;min-width: 150px; " size="3" multiple="multiple">
		 <?php for ($iter=0;$iter<10;$iter++)
		 {
		 ?>
		   <option value="<?php echo $iter ?>"><?php echo  $levels[$iter]; ?> </option>
		 <?php 
		 }
		 ?>
		 </select><br/>
		 Press Ctrl and select multiple to subscribe to multiple membership levels.
		 
		 </td>
	</tr>
	<tr>
	  <td><input class="button-primary" value="Add Rule" type="submit"></td>
</table>
 <?php wp_nonce_field('wprtos2add'); ?>
 <input type="hidden" name="wprtos2add" value="1"/>
</div>
</form>

   
   <?php

}
