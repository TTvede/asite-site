<?php
// Asite Site – afinstallation: fjern pluginets indstillinger. Sidernes indhold, billeder og leads røres ikke:
// det er sitets egne data, og de skal overleve, at pluginet tages af.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('asite_site');
delete_site_transient('asite_site_latest');
