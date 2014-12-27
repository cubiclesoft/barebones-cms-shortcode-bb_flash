<?php
	// Barebones CMS Content Widget Shortcode Handler for Flash Objects (SWF)
	// (C) 2014 CubicleSoft.  All Rights Reserved.
	// Icons are licensed from the IconShock library.

	if (!defined("BB_FILE"))  exit();

	$g_bb_content_shortcodes["bb_flash"] = array(
		"name" => "Flash",
		"toolbaricon" => $g_fullurl . "/bb_flash_small.png",
		"mainicon" => $g_fullurl . "/bb_flash_large.png",
		"cache" => true,
		"security" => array(
			"" => array("Flash", "Defines who can add and edit Flash Objects (SWF)."),
			"local" => array("Local Flash", "Defines who can preview Flash Objects on the local server."),
			"remote" => array("Remote Flash", "Defines who can preview Flash Objects on remote servers.")
		),
		"nextinstance" => 1
	);

	// 'movie' and 'swliveconnect' don't make sense in this context.
	global $g_bb_content_shortcode_bb_flash_params;

	$g_bb_content_shortcode_bb_flash_params = array(
		"allowfullscreen" => array("" => true, "true" => true, "false" => true),
		"allownetworking" => array("" => true, "all" => true, "internal" => true, "none" => true),
		"allowscriptaccess" => array("" => true, "samedomain" => true, "always" => true, "never" => true),
		"base" => "",
		"bgcolor" => "",
		"devicefont" => array("" => true, "true" => true, "false" => true),
		"flashvars" => "",
		"loop" => array("" => true, "true" => true, "false" => true),
		"menu" => array("" => true, "true" => true, "false" => true),
		"play" => array("" => true, "true" => true, "false" => true),
		"quality" => array("" => true, "autolow" => true, "autohigh" => true, "low" => true, "medium" => true, "high" => true, "best" => true),
		"salign" => array("" => true, "t" => true, "l" => true, "r" => true, "b" => true, "tl" => true, "tr" => true, "bl" => true, "br" => true),
		"scale" => array("" => true, "showall" => true, "noborder" => true, "exactfit" => true),
		"seamlesstabbing" => array("" => true, "true" => true, "false" => true),
		"wmode" => array("" => true, "window" => true, "opaque" => true, "transparent" => true)
	);

	class bb_content_shortcode_bb_flash extends BB_ContentShortcodeBase
	{
		private function GetInfo($sid)
		{
			global $bb_widget, $g_bb_content_shortcode_bb_flash_params;

			$info = $bb_widget->shortcodes[$sid];
			if (!isset($info["src"]))  $info["src"] = "";
			if (!isset($info["width"]))  $info["width"] = 0;
			if (!isset($info["height"]))  $info["height"] = 0;
			if (!isset($info["minflashver"]))  $info["minflashver"] = 5;
			if (!isset($info["alt"]))  $info["alt"] = "";
			if (!isset($info["opt-embed"]))  $info["opt-embed"] = "static";
			if (!isset($info["opt-expressinstall"]))  $info["opt-expressinstall"] = true;
			foreach ($g_bb_content_shortcode_bb_flash_params as $param => $vals)
			{
				if (!isset($info["opt-param-" . $param]))  $info["opt-param-" . $param] = "";
			}
			if (!isset($info["opt-caption"]))  $info["opt-caption"] = "";
			if (!isset($info["opt-caption-width"]))  $info["opt-caption-width"] = 0;
			if (!isset($info["opt-image"]))  $info["opt-image"] = "";
			if (!isset($info["opt-image-alt"]))  $info["opt-image-alt"] = "";

			return $info;
		}

		private function IsValidSWF($data, &$info)
		{
			if (substr($data, 0, 3) != "CWS" && substr($data, 0, 3) != "FWS")  return false;

			// Attempt to automatically extract useful information.
			// [F|C]WS, Flash version, decompressed file size, (compressed data starts here) rectangle in twips (20 twips = 1 pixel).
			$info["minflashver"] = (string)ord(substr($data, 3, 1));
			if (substr($data, 0, 3) == "FWS")
			{
				$data = substr($data, 8);
			}
			else if (substr($data, 0, 3) == "CWS" && function_exists("gzuncompress"))
			{
				$data = @gzuncompress(substr($data, 8));
				if ($data === false)  $data = "";
			}
			else  $data = "";

			if ($data != "")
			{
				require_once ROOT_PATH . "/" . SUPPORT_PATH . "/bits.php";

				$sbs = new StringBitStream;
				$sbs->Init($data);
				$numbits = $sbs->ReadBits(5);
				$x = $sbs->ReadBits($numbits);
				$x2 = $sbs->ReadBits($numbits);
				$y = $sbs->ReadBits($numbits);
				$y2 = $sbs->ReadBits($numbits);

				$info["width"] = (int)(($x2 - $x) / 20);
				$info["height"] = (int)(($y2 - $y) / 20);
			}

			return true;
		}

		public function GenerateShortcode($parent, $sid, $depth)
		{
			global $bb_widget, $g_bb_content_shortcodes, $g_bb_content_shortcode_bb_flash_params, $bb_page;

			$info = $this->GetInfo($sid);
			if ($info["src"] == "")  return "";

			$data = "";
			if ($info["opt-caption"] != "")  $data .= '<div class="flash-caption-wrap"' . ($info["opt-caption-width"] ? ' style="width: ' . $info["opt-caption-width"] . 'px;"' : '') . '><div class="flash-caption-flash">';
			if ($parent !== false && (!$parent->IsShortcodeAllowed("bb_flash", BB_IsLocalURL($info["src"]) ? "local" : "remote") || ($info["opt-image"] != "" && !$parent->IsShortcodeAllowed("bb_flash", BB_IsLocalURL($info["opt-image"]) ? "local" : "remote"))))
			{
				$data .= '<img src="' . htmlspecialchars($g_bb_content_shortcodes["bb_flash"]["mainicon"]) . '"' . ($info["alt"] != "" ? ' alt="' . htmlspecialchars($info["alt"]) . '"' : '') . ' />';
			}
			else
			{
				$objectid = "flash-object-instance-" . $g_bb_content_shortcodes["bb_flash"]["nextinstance"];
				$g_bb_content_shortcodes["bb_flash"]["nextinstance"]++;

				$bb_widget->use_premainjs = true;

				$js = $bb_widget->js;
				$js[ROOT_URL . "/" . SUPPORT_PATH . "/swfobject/swfobject.js"] = ROOT_PATH . "/" . SUPPORT_PATH . "/swfobject/swfobject.js";
				if ($info["opt-image"] != "")
				{
					$js[ROOT_URL . "/" . SUPPORT_PATH . "/jquery-1.11.0.min.js"] = ROOT_PATH . "/" . SUPPORT_PATH . "/jquery-1.11.0.min.js";
					$data .= "<a href=\"#\" id=\"" . htmlspecialchars($objectid) . "-image-wrap\" onclick=\"$('#" . htmlspecialchars(BB_JSSafe($objectid)) . "-image-wrap').hide();  $('#" . htmlspecialchars(BB_JSSafe($objectid)) . "-image-switch-wrap').show();  return false;\"><img src=\"" . htmlspecialchars($info["opt-image"]) . "\"" . ($info["opt-image-alt"] != "" ? " alt=\"" . htmlspecialchars($info["opt-image-alt"]) . "\"" : "") . " /></a>\n";
					$data .= "<div id=\"" . htmlspecialchars($objectid) . "-image-switch-wrap\" style=\"display: none;\">\n";
				}
				$bb_widget->js = $js;

				if ($info["opt-embed"] == "static")
				{
					// Static publishing.
					$data .= "<script type=\"text/javascript\">swfobject.registerObject('" . BB_JSSafe($objectid) . "', '" . BB_JSSafe($info["minflashver"]) . "'" . ($info["opt-expressinstall"] ? ", Gx__RootURL + '/' + Gx__SupportPath + '/swfobject/expressInstall.swf'" : "") . ");</script>\n";
					$data .= "<object id=\"" . htmlspecialchars($objectid) . "\" classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" width=\"" . (int)$info["width"] . "\" height=\"" . (int)$info["height"] . "\">\n";
					$data .= "<param name=\"movie\" value=\"" . htmlspecialchars($info["src"]) . "\" />\n";
					foreach ($g_bb_content_shortcode_bb_flash_params as $param => $vals)
					{
						if ($info["opt-param-" . $param] != "")  $data .= "<param name=\"" . htmlspecialchars($param) . "\" value=\"" . htmlspecialchars($info["opt-param-" . $param]) . "\" />\n";
					}
					$data .= "<!--[if !IE]>-->\n";
					$data .= "<object type=\"application/x-shockwave-flash\" data=\"" . htmlspecialchars($info["src"]) . "\" width=\"" . (int)$info["width"] . "\" height=\"" . (int)$info["height"] . "\">\n";
					$data .= "<!--<![endif]-->\n";
					foreach ($g_bb_content_shortcode_bb_flash_params as $param => $vals)
					{
						if ($info["opt-param-" . $param] != "")  $data .= "<param name=\"" . htmlspecialchars($param) . "\" value=\"" . htmlspecialchars($info["opt-param-" . $param]) . "\" />\n";
					}
					$options = array(
						"doctype" => $bb_page["doctype"]
					);
					$data .= "<div class=\"flash-alternate-content\">\n" . BB_HTMLTransformForWYMEditor($info["alt"], $options) . "</div>\n";
					$data .= "<!--[if !IE]>-->\n";
					$data .= "</object>\n";
					$data .= "<!--<![endif]-->\n";
					$data .= "</object>\n";
				}
				else if ($info["opt-embed"] == "dynamic")
				{
					// Dynamic publishing.
					$params = array();
					foreach ($g_bb_content_shortcode_bb_flash_params as $param => $vals)
					{
						if ($info["opt-param-" . $param] != "")  $params[] = "'" . BB_JSSafe($param) . "' : '" . BB_JSSafe($info["opt-param-" . $param]) . "'";
					}
					$params = implode(", ", $params);

					$data .= "<script type=\"text/javascript\">swfobject.embedSWF('" . BB_JSSafe($info["src"]) . "', '" . BB_JSSafe($objectid) . "', '" . (int)$info["width"] . "', '" . (int)$info["height"] . "', '" . BB_JSSafe($info["minflashver"]) . "', " . ($info["opt-expressinstall"] ? "Gx__RootURL + '/' + Gx__SupportPath + '/swfobject/expressInstall.swf'" : "false") . ($params != "" ? ", false, { " . $params . " }" : "") . ");</script>\n";
					$data .= "<div id=\"" . htmlspecialchars($objectid) . "\">\n";
					$options = array(
						"doctype" => $bb_page["doctype"]
					);
					$data .= "<div class=\"flash-alternate-content\">\n" . BB_HTMLTransformForWYMEditor($info["alt"], $options) . "</div>\n";
					$data .= "</div>\n";
				}

				if ($info["opt-image"] != "")  $data .= "</div>\n";
			}
			if ($info["opt-caption"] != "")  $data .= '</div><div class="flash-caption-text">' . htmlspecialchars($info["opt-caption"]) . '</div></div>';

			return $data;
		}

		public function ProcessShortcodeBBAction($parent)
		{
			global $bb_widget, $bb_widget_id, $bb_dir, $bb_pref_lang, $bb_revision_num, $bb_writeperms, $g_bb_content_shortcode_bb_flash_params;

			$info = $this->GetInfo($parent->GetSID());

			if ($_REQUEST["sc_action"] == "bb_flash_upload_ajaxupload")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_upload_ajaxupload");

				// Confusing in this context but is the AJAX upload upload handler.
				$msg = BB_ValidateAJAXUpload();
				if ($msg != "")
				{
					echo htmlspecialchars(BB_Translate($msg));
					exit();
				}

				// Use official magic numbers for the SWF format to determine the real content type.
				$data = file_get_contents($_FILES["Filedata"]["tmp_name"]);
				if (!$this->IsValidSWF($data, $info))
				{
					echo htmlspecialchars(BB_Translate("Uploaded file is not a valid Flash Object file.  Must be a SWF."));
					exit();
				}

				if (!is_dir($bb_dir . "/flash"))  mkdir($bb_dir . "/flash", 0777, true);
				$dirfile = preg_replace('/\.+/', ".", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $bb_pref_lang . "_" . ($bb_revision_num > -1 ? $bb_revision_num . "_" : "") . trim($_FILES["Filedata"]["name"])));
				if ($dirfile == ".")  $dirfile = "";

				if ($dirfile == "")
				{
					echo htmlspecialchars(BB_Translate("A filename was not specified."));
					exit();
				}

				$pos = strrpos($dirfile, ".");
				if ($pos === false || substr($dirfile, $pos + 1) != "swf")  $dirfile .= ".swf";
				if (!@move_uploaded_file($_FILES["Filedata"]["tmp_name"], $bb_dir . "/flash/" . $dirfile))
				{
					echo htmlspecialchars(BB_Translate("Unable to move temporary file to final location.  Check the permissions of the target directory and destination file."));
					exit();
				}

				@chmod($bb_dir . "/flash/" . $dirfile, 0444 | $bb_writeperms);

				$info["src"] = "flash/" . $dirfile;
				if (!$parent->SaveShortcode($info))
				{
					echo htmlspecialchars(BB_Translate("Unable to save the shortcode."));
					exit();
				}

				echo "OK";

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_upload_ajaxupload");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_upload_submit")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_upload_submit");

				$swfinfo = BB_IsValidURL($_REQUEST["url"], array("protocol" => "http"));
				if (!$swfinfo["success"])  BB_PropertyFormError($swfinfo["error"]);
				if (!$this->IsValidSWF($swfinfo["data"], $info))  BB_PropertyFormError("Uploaded file is not a valid Flash Object file.  Must be a SWF.");

				$dirfile = preg_replace('/\.+/', ".", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $_REQUEST["destfile"]));
				if ($dirfile == ".")  $dirfile = "";

				// Automatically calculate the new filename based on the URL.
				if ($dirfile == "")  $dirfile = $bb_pref_lang . "_" . ($bb_revision_num > -1 ? $bb_revision_num . "_" : "") . BB_MakeFilenameFromURL($swfinfo["url"], "swf", false);

				if (!is_dir($bb_dir . "/flash"))  mkdir($bb_dir . "/flash", 0777, true);
				if (BB_WriteFile($bb_dir . "/flash/" . $dirfile, $swfinfo["data"]) === false)  BB_PropertyFormError("Unable to save the Flash file.");

				$info["src"] = "flash/" . $dirfile;
				if (!$parent->SaveShortcode($info))  BB_PropertyFormError("Unable to save the shortcode.");

?>
<div class="success"><?php echo htmlspecialchars(BB_Translate("Flash file transferred.")); ?></div>
<script type="text/javascript">
LoadProperties(<?php echo $parent->CreateShortcodePropertiesJS(""); ?>);
ReloadIFrame();
</script>
<?php

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_upload_submit");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_upload")
			{
				$parent->CreateShortcodeUploader("", array(), "Configure Flash Object", "Flash Object", "Flash object", "*.swf", "Flash Files");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_swap_image_upload_swfupload")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_swap_image_upload_swfupload");

				// Confusing in this context but is the SWFUpload upload handler.
				$msg = BB_ValidateSWFUpload();
				if ($msg != "")
				{
					echo htmlspecialchars(BB_Translate($msg));
					exit();
				}

				// Use official magic numbers for each format to determine the real content type.
				$data = file_get_contents($_FILES["Filedata"]["tmp_name"]);
				$type = BB_GetImageType($data);
				if ($type != "gif" && $type != "jpg" && $type != "png")
				{
					echo htmlspecialchars(BB_Translate("Uploaded file is not a valid web image.  Must be PNG, JPG, or GIF."));
					exit();
				}

				if (!is_dir($bb_dir . "/images"))  mkdir($bb_dir . "/images", 0777, true);
				$dirfile = preg_replace('/\.+/', ".", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $bb_pref_lang . "_" . ($bb_revision_num > -1 ? $bb_revision_num . "_" : "") . trim($_FILES["Filedata"]["name"])));
				if ($dirfile == ".")  $dirfile = "";

				if ($dirfile == "")
				{
					echo htmlspecialchars(BB_Translate("A filename was not specified."));
					exit();
				}

				$pos = strrpos($dirfile, ".");
				if ($pos === false || substr($dirfile, $pos + 1) != $type)  $dirfile .= "." . $type;
				if (!@move_uploaded_file($_FILES["Filedata"]["tmp_name"], $bb_dir . "/images/" . $dirfile))
				{
					echo htmlspecialchars(BB_Translate("Unable to move temporary file to final location.  Check the permissions of the target directory and destination file."));
					exit();
				}

				@chmod($bb_dir . "/images/" . $dirfile, 0444 | $bb_writeperms);

				$info["opt-image"] = "images/" . $dirfile;
				if (!$parent->SaveShortcode($info))
				{
					echo htmlspecialchars(BB_Translate("Unable to save the shortcode."));
					exit();
				}

				echo "OK";

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_swap_image_upload_swfupload");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_swap_image_upload_submit")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_swap_image_upload_submit");

				$imginfo = BB_IsValidHTMLImage($_REQUEST["url"], array("protocol" => "http"));
				if (!$imginfo["success"])  BB_PropertyFormError($imginfo["error"]);

				$dirfile = preg_replace('/\.+/', ".", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $_REQUEST["destfile"]));
				if ($dirfile == ".")  $dirfile = "";

				// Automatically calculate the new filename based on the URL.
				if ($dirfile == "")  $dirfile = $bb_pref_lang . "_" . ($bb_revision_num > -1 ? $bb_revision_num . "_" : "") . BB_MakeFilenameFromURL($imginfo["url"], $imginfo["type"], false);

				if (!is_dir($bb_dir . "/images"))  mkdir($bb_dir . "/images", 0777, true);
				if (BB_WriteFile($bb_dir . "/images/" . $dirfile, $imginfo["data"]) === false)  BB_PropertyFormError("Unable to save the image.");

				$info["opt-image"] = "images/" . $dirfile;
				if (!$parent->SaveShortcode($info))  BB_PropertyFormError("Unable to save the shortcode.");

?>
<div class="success"><?php echo htmlspecialchars(BB_Translate("Image transferred.")); ?></div>
<script type="text/javascript">
LoadProperties(<?php echo $parent->CreateShortcodePropertiesJS(""); ?>);
ReloadIFrame();
</script>
<?php

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_swap_image_upload_submit");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_swap_image_upload")
			{
				$parent->CreateShortcodeUploader("", array(), "Configure Flash Object Swap Image", "Image", "image", "*.png;*.jpg;*.gif", "Web Image Files");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_alt_edit_load")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_alt_edit_load");

				echo rawurlencode(UTF8::ConvertToHTML($info["alt"]));

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_alt_edit_load");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_alt_edit_save")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_alt_edit_save");

				$info["alt"] = BB_HTMLPurifyForWYMEditor($_REQUEST["content"], array());

				if (!$parent->SaveShortcode($info))  echo htmlspecialchars(BB_Translate("Unable to save content.  Try again."));
				else
				{
					echo "OK\n";
					echo "<script type=\"text/javascript\">ReloadIFrame();</script>";
				}

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_alt_edit_save");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_alt_edit")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_alt_edit");

?>
<script type="text/javascript">
window.parent.LoadConditionalScript(Gx__RootURL + '/' + Gx__SupportPath + '/editcontent.js?_=20090725', true, function(loaded) {
		return ((!loaded && typeof(window.CreateWYMEditorInstance) == 'function') || (loaded && !IsConditionalScriptLoading()));
	}, function(params) {
		$('#contenteditor').show();

		var fileopts = {
			loadurl : Gx__URLBase,
			loadparams : <?php echo $parent->CreateShortcodePropertiesJS("bb_flash_alt_edit_load", array(), true); ?>,
			id : 'wid_<?php echo BB_JSSafe($bb_widget_id); ?>_sc_<?php echo BB_JSSafe($parent->GetSID()); ?>',
			display : '<?php echo BB_JSSafe($bb_widget->_f . " (" . $parent->GetSID() . ") - Flash Alt Content"); ?>',
			saveurl : Gx__URLBase,
			saveparams : <?php echo $parent->CreateShortcodePropertiesJS("bb_flash_alt_edit_save", array(), true); ?>,
			wymtoolbar : 'bold,italic,superscript,subscript,pasteword,undo,redo,createlink,unlink,insertorderedlist,insertunorderedlist,indent,outdent'
		};

		var editopts = {
			ismulti : true,
			closelast : bb_content_ClosedAllContent,
			width : '100%',
			height : '300px'
		};

		CreateWYMEditorInstance('contenteditor', fileopts, editopts);
});
window.parent.CloseProperties2(false);
</script>
<?php

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_alt_edit");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_configure_submit")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_configure_submit");

				$info["width"] = (int)$_REQUEST["width"];
				if ($info["width"] < 0)  $info["width"] = 0;
				$info["height"] = (int)$_REQUEST["height"];
				if ($info["height"] < 0)  $info["height"] = 0;
				$info["minflashver"] = $_REQUEST["minflashver"];
				if ($info["minflashver"] < 1)  $info["minflashver"] = 5;
				$src = $_REQUEST["src"];
				if ($info["src"] != $src)
				{
					$swfinfo = BB_IsValidURL($src, array("protocol" => "http"));
					if ((!$swfinfo["success"] && function_exists("fsockopen")) || ($swfinfo["success"] && !$this->IsValidSWF($swfinfo["data"], $info)))  BB_PropertyFormError("'Flash Object URL' field does not point to a valid Flash object file.");
					$info["src"] = $src;
				}

				// Handle the basic options.
				foreach ($g_bb_content_shortcode_bb_flash_params as $param => $vals)
				{
					$info["opt-param-" . $param] = (isset($_REQUEST["opt-param-" . $param]) && (is_string($vals) || isset($vals[$_REQUEST["opt-param-" . $param]])) ? $_REQUEST["opt-param-" . $param] : "");
				}

				if (isset($_REQUEST["opt-param-flashvars"]))
				{
					$flashvars = explode("\n", Str::ReplaceNewlines("\n", $_REQUEST["opt-param-flashvars"]));
					foreach ($flashvars as $x => $flashvar)
					{
						$pos = strpos($flashvar, "=");
						if ($pos !== false)  $flashvars[$x] = urlencode(str_replace("%3D", "=", substr($flashvar, 0, $pos))) . "=" . urlencode(substr($flashvar, $pos + 1));
						else  $flashvars[$x] = urlencode(str_replace("%3D", "=", $flashvar));
					}
					$info["opt-param-flashvars"] = implode("&", $flashvars);
				}

				// Handle quick configuration.
				$data = $_REQUEST["quickconfig"];
				if ($data != "")
				{
					require_once ROOT_PATH . "/" . SUPPORT_PATH . "/simple_html_dom.php";

					$html = new simple_html_dom();
					$html->load($data);
					$object = $html->find('object', 0);
					if (is_object($object))
					{
						if (isset($object->codebase))
						{
							$pos = strpos($object->codebase, "#version=");
							if ($pos !== false)  $info["minflashver"] = str_replace(",", ".", substr($object->codebase, $pos + strlen("#version=")));
						}
						if (isset($object->width))
						{
							$info["width"] = (int)$object->width;
							if ($info["width"] < 0)  $info["width"] = 0;
						}
						if (isset($object->height))
						{
							$info["height"] = (int)$object->height;
							if ($info["height"] < 0)  $info["height"] = 0;
						}
						$rows = $object->children();
						foreach ($rows as $row)
						{
							if ($row->tag == "param" && isset($row->name) && isset($row->value))
							{
								$param = strtolower($row->name);
								$val = html_entity_decode($row->value);
								if ($param == "flashvars" || (isset($g_bb_content_shortcode_bb_flash_params[$param]) && (is_string($g_bb_content_shortcode_bb_flash_params[$param]) || isset($g_bb_content_shortcode_bb_flash_params[$param][$val]))))
								{
									$info["opt-param-" . $param] = $val;
								}
							}
						}
					}
				}

				$info["opt-embed"] = ($_REQUEST["opt-embed"] == "dynamic" ? "dynamic" : "static");
				$info["opt-expressinstall"] = ($_REQUEST["opt-expressinstall"] == "enable");
				$info["opt-caption"] = $_REQUEST["opt-caption"];
				$info["opt-caption-width"] = (int)$_REQUEST["opt-caption-width"];
				if ($info["opt-caption-width"] < 0)  $info["opt-caption-width"] = 0;
				$src = $_REQUEST["opt-image"];
				if ($info["opt-image"] != $src)
				{
					$imginfo = BB_IsValidHTMLImage($src, array("protocol" => "http"));
					if (!$imginfo["success"] && function_exists("fsockopen"))  BB_PropertyFormError("'Swap Image URL' field does not point to a valid image file.");
					$info["opt-image"] = $src;
				}
				$info["opt-image-alt"] = $_REQUEST["opt-image-alt"];

				if (!$parent->SaveShortcode($info))  BB_PropertyFormError("Unable to save the shortcode.");

?>
<div class="success"><?php echo htmlspecialchars(BB_Translate("Options saved.")); ?></div>
<script type="text/javascript">
CloseProperties();
ReloadIFrame();
</script>
<?php

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_configure_submit");
			}
			else if ($_REQUEST["sc_action"] == "bb_flash_configure")
			{
				BB_RunPluginAction("pre_bb_content_shortcode_bb_flash_configure");

				$desc = "<br />";
				$desc .= $parent->CreateShortcodePropertiesLink(BB_Translate("Upload/Transfer Flash File"), "bb_flash_upload");
				$desc .= " | " . $parent->CreateShortcodePropertiesLink(BB_Translate("Upload/Transfer Swap Image"), "bb_flash_swap_image_upload");
				$desc .= " | " . $parent->CreateShortcodePropertiesLink(BB_Translate("Edit Alternate Content"), "bb_flash_alt_edit", array(), "", true);

				$flashvars = explode("&", $info["opt-param-flashvars"]);
				foreach ($flashvars as $x => $flashvar)
				{
					$pos = strpos($flashvar, "=");
					if ($pos !== false)  $flashvars[$x] = str_replace("=", "%3D", urldecode(substr($flashvar, 0, $pos))) . "=" . urldecode(substr($flashvar, $pos + 1));
					else  $flashvars[$x] = str_replace("=", "%3D", urldecode($flashvar));
				}
				$flashvars = implode("\n", $flashvars);

				$options = array(
					"title" => "Configure Flash Object",
					"desc" => "Configure the Flash object or upload/transfer a new Flash file.",
					"htmldesc" => $desc,
					"bb_action" => $_REQUEST["bb_action"],
					"hidden" => array(
						"sid" => $parent->GetSID(),
						"sc_action" => "bb_flash_configure_submit"
					),
					"fields" => array(
						array(
							"title" => "Flash Object URL",
							"type" => "text",
							"name" => "src",
							"value" => $info["src"],
							"desc" => "The URL of this Flash Object."
						),
						array(
							"title" => "Quick Configure",
							"type" => "textarea",
							"name" => "quickconfig",
							"value" => "",
							"desc" => "Copy and paste the HTML (object tag) that Flash generated to quickly configure these options."
						),
						array(
							"title" => "Width",
							"type" => "text",
							"name" => "width",
							"value" => $info["width"],
							"desc" => "The width (in pixels) of the Flash object."
						),
						array(
							"title" => "Height",
							"type" => "text",
							"name" => "height",
							"value" => $info["height"],
							"desc" => "The height (in pixels) of the Flash object."
						),
						array(
							"title" => "Minimum Flash Version",
							"type" => "text",
							"name" => "minflashver",
							"value" => $info["minflashver"],
							"desc" => "The minimum Flash Player version required."
						),
						array(
							"title" => "Publishing Method/Embed Type",
							"type" => "select",
							"name" => "opt-embed",
							"options" => array(
								"static" => "Static",
								"dynamic" => "Dynamic"
							),
							"select" => $info["opt-embed"],
							"desc" => "Static publishing/embedding is generally better."
						),
						array(
							"title" => "Express Install",
							"type" => "select",
							"name" => "opt-expressinstall",
							"options" => array(
								"enable" => "Enable",
								"disable" => "Disable"
							),
							"select" => ($info["opt-expressinstall"] ? "enable" : "disable"),
							"desc" => "Express Install begins the upgrade process if the minimum Flash version is not installed but Flash 6 or later is installed."
						),
						array(
							"title" => "Parameter: allowfullscreen",
							"type" => "select",
							"name" => "opt-param-allowfullscreen",
							"options" => array(
								"" => "Default",
								"true" => "Yes",
								"false" => "No"
							),
							"select" => $info["opt-param-allowfullscreen"],
							"desc" => "Specifies whether or not the Flash object can use the whole screen."
						),
						array(
							"title" => "Parameter: allownetworking",
							"type" => "select",
							"name" => "opt-param-allownetworking",
							"options" => array(
								"" => "Default",
								"all" => "All",
								"internal" => "Internal",
								"none" => "None"
							),
							"select" => $info["opt-param-allownetworking"],
							"desc" => "Specifies what Flash networking APIs are allowed."
						),
						array(
							"title" => "Parameter: allowscriptaccess",
							"type" => "select",
							"name" => "opt-param-allowscriptaccess",
							"options" => array(
								"" => "Default",
								"samedomain" => "Same Domain",
								"always" => "Always",
								"never" => "Never"
							),
							"select" => $info["opt-param-allowscriptaccess"],
							"desc" => "Specifies what Flash outbound scripting APIs are allowed."
						),
						array(
							"title" => "Parameter: base",
							"type" => "text",
							"name" => "opt-param-base",
							"value" => $info["opt-param-base"],
							"desc" => "The base directory or URL used to resolve relative path statements in ActionScript."
						),
						array(
							"title" => "Parameter: bgcolor",
							"type" => "text",
							"name" => "opt-param-bgcolor",
							"value" => $info["opt-param-bgcolor"],
							"desc" => "Overrides the background color specified in the Flash object."
						),
						array(
							"title" => "Parameter: devicefont",
							"type" => "select",
							"name" => "opt-param-devicefont",
							"options" => array(
								"" => "Default",
								"true" => "Yes",
								"false" => "No"
							),
							"select" => $info["opt-param-devicefont"],
							"desc" => "Specifies whether static text objects are drawn using a device font, regardless of setting, if available."
						),
						array(
							"title" => "Parameter: flashvars",
							"type" => "textarea",
							"name" => "opt-param-flashvars",
							"value" => $flashvars,
							"desc" => "Specify name=value pairs, one per line, to pass to the Flash object.  Do NOT URL encode."
						),
						array(
							"title" => "Parameter: loop",
							"type" => "select",
							"name" => "opt-param-loop",
							"options" => array(
								"" => "Default",
								"true" => "Yes",
								"false" => "No"
							),
							"select" => $info["opt-param-loop"],
							"desc" => "Specifies whether the Flash object loops when it reaches the end of the movie."
						),
						array(
							"title" => "Parameter: menu",
							"type" => "select",
							"name" => "opt-param-menu",
							"options" => array(
								"" => "Default",
								"true" => "Full right-click menu",
								"false" => "Partial right-click menu"
							),
							"select" => $info["opt-param-menu"],
							"desc" => "Specifies whether right-clicking shows the whole menu or just the 'About' and 'Settings' options."
						),
						array(
							"title" => "Parameter: play",
							"type" => "select",
							"name" => "opt-param-play",
							"options" => array(
								"" => "Default",
								"true" => "Yes",
								"false" => "No"
							),
							"select" => $info["opt-param-play"],
							"desc" => "Specifies whether the Flash object begins playing automatically once loaded."
						),
						array(
							"title" => "Parameter: quality",
							"type" => "select",
							"name" => "opt-param-quality",
							"options" => array(
								"" => "Default",
								"autolow" => "Start with low quality (speed preferred)",
								"autohigh" => "Start with high quality (appearance preferred)",
								"low" => "Low",
								"medium" => "Medium",
								"high" => "High",
								"best" => "Best"
							),
							"select" => $info["opt-param-quality"],
							"desc" => "Specifies the default quality setting of the Flash object."
						),
						array(
							"title" => "Parameter: salign",
							"type" => "select",
							"name" => "opt-param-salign",
							"options" => array(
								"" => "Default",
								"t" => "Top",
								"l" => "Left",
								"r" => "Right",
								"b" => "Bottom",
								"tl" => "Top left",
								"tr" => "Top right",
								"bl" => "Bottom left",
								"br" => "Bottom right"
							),
							"select" => $info["opt-param-salign"],
							"desc" => "Specifies the position of the Flash object."
						),
						array(
							"title" => "Parameter: scale",
							"type" => "select",
							"name" => "opt-param-scale",
							"options" => array(
								"" => "Default",
								"showall" => "Show all (Entirely visible with no distortion)",
								"noborder" => "No border (Fills entire area with no distortion but may be cropped)",
								"exactfit" => "Exact fit (Entirely visible but may be distorted)"
							),
							"select" => $info["opt-param-scale"],
							"desc" => "Specifies how Flash fills the browser area with the Flash object."
						),
						array(
							"title" => "Parameter: seamlesstabbing",
							"type" => "select",
							"name" => "opt-param-seamlesstabbing",
							"options" => array(
								"" => "Default",
								"true" => "Yes",
								"false" => "No"
							),
							"select" => $info["opt-param-seamlesstabbing"],
							"desc" => "Specifies whether pressing tab while in the Flash object moves to the next page element."
						),
						array(
							"title" => "Parameter: wmode",
							"type" => "select",
							"name" => "opt-param-wmode",
							"options" => array(
								"" => "Default",
								"window" => "Window",
								"opaque" => "Opaque",
								"transparent" => "Transparent"
							),
							"select" => $info["opt-param-wmode"],
							"desc" => "Specifies the window mode of the Flash object.  Opaque and transparent usually work with iframe menu hacks."
						),
						array(
							"title" => "Caption",
							"type" => "text",
							"name" => "opt-caption",
							"value" => $info["opt-caption"],
							"desc" => "The text to use for a caption below the Flash object."
						),
						array(
							"title" => "Caption Width",
							"type" => "text",
							"name" => "opt-caption-width",
							"value" => $info["opt-caption-width"],
							"desc" => "The width in pixels to constrain the caption to.  Typically the width of the Flash object."
						),
						array(
							"title" => "Swap Image URL",
							"type" => "text",
							"name" => "opt-image",
							"value" => $info["opt-image"],
							"desc" => "The URL of an image to show before displaying the Flash object.  This option imports jQuery."
						),
						array(
							"title" => "Alternate Image Text",
							"type" => "text",
							"name" => "opt-image-alt",
							"value" => $info["opt-image-alt"],
							"desc" => "The alternate text to display if the image is not able to be seen (e.g. visually impaired visitors)."
						)
					),
					"submit" => "Save",
					"focus" => true
				);

				BB_RunPluginActionInfo("bb_content_shortcode_bb_flash_configure_options", $options);

				BB_PropertyForm($options);

				BB_RunPluginAction("post_bb_content_shortcode_bb_flash_configure");
			}
		}
	}
?>