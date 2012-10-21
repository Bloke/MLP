
                     L10N MLP (Multi-Lingual Publishing) Pack.
                               Copyright  2007-2012
                Graeme Porteous & Steve (Net-Carver) & Stef Dawson

                     Originally written by private contract for
                                  Marios Buttner
                                        &
                                   Destry Wion


There are a set of three plugins and various other files that comprise the pack. 
Here's the file tree...

MLP
 |-- Licence (GNU GPL licence text)
 |-- README.txt (this file)
 |-- KNOWN_ISSUES.txt (current bug list: please check before submitting bug report)
 |
 |-- example pages (example textpattern pages using l10n tags)
 |        |
 |        |-- default.txt
 |        |-- archive.txt
 |        |-- error_default.txt
 |
 |-- plugins (you will need to install these plugins)
 |        |
 |        |-- l10n.txt (the main l10n plugin, and compressed version)
 |        |-- gbp_admin_library.txt (Graeme's admin lib, and compressed version)
 |        |-- zem_contact_lang-mlp.txt (l10n compatible strings lib)
 |        |
 |        |-- sources (for GNU GPL completeness)
 |                |-- l10n.php (source code for the l10n.txt file)
 |                |-- gbp_admin_library.php (source code for the gbp_admin_library.txt file)
 |                |-- zem_contact_lang_mlp.php (source code for the zem_contact_lang_mlp.txt file)
 | 
 |-- textpattern (you will need to copy these files into your Textpattern installation)
             | 
             |-- l10n.css (the MLP Pack's css file)
             |
             |-- txp_img (l10n images in here)
             |       |
             |       |-- l10n_clone.png
             |       |-- l10n_clone_all.png
             |       |-- l10n_delete.png
             |   
             |-- lib (files used by the l10n pluign)
                  |
                  |-- ** l10n_langs.php (ISO codes and names)
                  |-- txplib_db.php (modified db layer supporting MLP) 
                  |-- l10n_base.php (the basic public+admin file)
                  |-- l10n_admin.php (extra admin routines)
                  |-- l10n_admin_classes.php (classes used only admin side)
                  |-- ** l10n_default_strings.php (Declares which language file to use by default)
                  |-- ** l10n_en-gb_strings.php (English (GB) strings for MLP Pack)
                  |-- ** l10n_el-gr_strings.php (Greek strings for MLP Pack)

		  ** => Edit these files if you need to.



GETTING IT INSTALLED AND CONFIGURED.
===================================

PRE-INSTALL
-----------

1) Install all the Textpattern languages you wish your site to serve. You can add
more later if you wish but the plugin's setup wizard will use this initial list as 
part of it's setup routine to populate the l10n preferences.

To setup languages in Textpattern go to admin > preferences and then hit the 
'manage languages' button to enter the languages page.

If you want a site in English, French and Japanese then make sure you install
those languages now then switch to the language that you wish to consider the
default language of the site.


INSTALL
-------

1) (MANDATORY) Open the 'textpattern' folder and copy the entire contents 
to your textpattern installation.

You will need to overwrite the existing file txplib_db.php when you do so.

NB: If you update your textpattern installation at a later date you will 
overwrite the txplib_db.php file with the new one in the textpattern update.

If you do so, the MLP Pack will no longer work until you restore an MLP 
version of the txplib_db.php file.


2) (MANDATORY) If upgrading from the gbp_l10n plugin disable the gbp_l10n plugin
now. 


3) (MANDATORY) Install and activate the three files from the /plugin(s) 
directory you will need to install and activate the new gbp_admin_library before
you activate the l10n plugin.

Yes, you *must* overwrite the old v0.1 gbp_admin_library supplied by 
Graeme for the original gbp_l10n plugin with at least version 0.4 of the library
as supplied by this pack. 



POST INSTALL
------------

1) (MANDATORY) Run the MLP setup wizard. Once you have completed the above
steps, go to the top level 'contents' tab and you will see a new subtab called
'MLP' clicking on that will bring up the setup wizard.

Please review the steps that the MLP setup will take -- they are quite 
invasive -- as it prepares your Textpattern installation for MLP use.

When you are ready simply click the 'Next' button at the bottom of the wizard
page and l10n will start analysing your existing articles and the currently 
installed list of languages.

If you are upgrading from gbp_l10n then the wizard will reuse the language list
from that installation otherwise the wizard will consider the currently active
admin language as the site default language and will make this the default
choice for all works you start writing via the content>write tab. Also, any
existing articles that cannot have a distinct language determined for it will be
marked as the default language.

A rerun of the setup wizard will use the previous language settings rather than 
the list of languages currently installed on the admin side.



TERMINOLOGY
===========

In standard Textpattern an 'article' represents the totality of an author's ideas
on a  given matter rendered in one language. That's fine and works well when 
the site containing the article only serves up one language. However, in the MLP
arena, an article may need to be visible to the site visitors in more than one 
language simultaneously and the authors/editors or other Textpattern users will also 
need to be able to view and manage the entire set of these translations that now
make up the article translated into 2 or more languages.

In the MLP world of l10n, an article is now the set of translations (hereafter 
'renditions') of that original author's work.

l10n achieves this by simply renaming the 'articles' tab to 'Renditions'. It 
also provides a new summary view of all the site's articles as a table of 
renditions. Each row is an article. Columns are for each available language 
the site can serve publicly. When a rendition of an article is available in a 
language then a summary of it appears in the corresponding cell of the table. 
Each cell is colour coded to allow easy visual inspection of the site's content. 
Filters can be applied to the table to allow you to quickly locate articles with 
certain characteristics -- for example, filtering by author, section or status. 


SO HOW DO I GO ABOUT TRANSLATION IN THIS THING?
===============================================

I quickly abandoned the idea of doing side-by-side editing of articles as they 
have very complex layouts. Instead I opted for a translation model inspired by 
Mary's 'Save New' plugin.

Basically you start with an existing, published, rendition from the articles 
grid and you clone it; assigning each clone a new language and a translator.

The cloned rendition will be automatically set to draft status to stop it 
appearing live anywhere until the translator can do their job.

The translator will (optionally) be notified of their new translation assignment 
via email and can use a link in that email to go straight into the write tab for 
that newly cloned rendition. All they need do then is edit the existing fields, 
*replacing* the existing text *in-situ* as they do so.

No new interface to learn, just the standard write panel.

Once they are happy with the work, they save the article, setting its status
as appropriate. On small installations where the translator is the
owner/publisher/editor that might be 'live' but on larger systems with
editorial control, they might set the status to pending.

Editors can use simple visual checking of the articles matrix to locate pending 
renditions or can apply a status filter to it to match only 'pending' work etc.


LOCALISING FOR REDISTRIBUTION
=============================

If you want to produce a translated version of the MLP Pack that presents all 
strings in a language other than the default en-gb then simply make a copy of 
one of the strings files (like l10n_en-gb_strings.php) in the textpattern/lib
directory and translate the strings to your target language. Rename the file 
so that the 5 character ISO-639-2 code is embedded in the center of the
filename. e.g, If you were creating a Japanese localisation you would call it
l10n_ja-jp_strings.php. Make sure the 5 letter code matches the one Textpattern
uses for your language.

Next, open the l10n_default_strings.php file and edit the language code in the
$l10n_default_strings_lang variable, alter the comment to include the native name
of the language, and save the file.

You can now include your translated strings file in the your localised version of
the MLP Pack and people installing it will be presented with your the setup 
wizard in the language you translated it into.


Please refer to the Plugin help section for the tag and attribute references.

--
Steve
