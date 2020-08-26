/* To avoid CSS expressions while still supporting IE 7 and IE 6, use this script */
/* The script tag referencing this file must be placed before the ending body tag. */

/* Use conditional comments in order to target IE 7 and older:
	<!--[if lt IE 8]><!-->
	<script src="ie7/ie7.js"></script>
	<!--<![endif]-->
*/

(function() {
	function addIcon(el, entity) {
		var html = el.innerHTML;
		el.innerHTML = '<span style="font-family: \'mrchub-icon-pack\'">' + entity + '</span>' + html;
	}
	var icons = {
		'icon-mrchub-down-chevron': '&#xe91f;',
		'icon-mrchub-left-chevron': '&#xe920;',
		'icon-mrchub-right-chevron': '&#xe921;',
		'icon-mrchub-up-chevron': '&#xe922;',
		'icon-mrchub-building': '&#xe923;',
		'icon-mrchub-handshake': '&#xe90a;',
		'icon-mrchub-list': '&#xe90b;',
		'icon-mrchub-settings': '&#xe924;',
		'icon-mrchub-settings-3': '&#xe928;',
		'icon-mrchub-speech-bubbles': '&#xe953;',
		'icon-mrchub-transfer-1': '&#xe90c;',
		'icon-mrchub-transfer-2': '&#xe90d;',
		'icon-mrchub-user2': '&#xe90e;',
		'icon-mrchub-user-1': '&#xe90f;',
		'icon-mrchub-user-2': '&#xe910;',
		'icon-mrchub-user-32': '&#xe911;',
		'icon-mrchub-user-4': '&#xe912;',
		'icon-mrchub-user-10': '&#xe91a;',
		'icon-mrchub-user-11': '&#xe913;',
		'icon-mrchub-user-12': '&#xe91b;',
		'icon-mrchub-user-13': '&#xe914;',
		'icon-mrchub-users-2': '&#xe903;',
		'icon-mrchub-notification-1': '&#xe94a;',
		'icon-mrchub-attach-1': '&#xe94b;',
		'icon-mrchub-controls-4': '&#xe91e;',
		'icon-mrchub-database': '&#xe929;',
		'icon-mrchub-database-1': '&#xe92a;',
		'icon-mrchub-database-2': '&#xe92b;',
		'icon-mrchub-dislike': '&#xe900;',
		'icon-mrchub-edit': '&#xe901;',
		'icon-mrchub-home-1': '&#xe94c;',
		'icon-mrchub-id-card-3': '&#xe915;',
		'icon-mrchub-internet': '&#xe92c;',
		'icon-mrchub-like': '&#xe905;',
		'icon-mrchub-list-1': '&#xe916;',
		'icon-mrchub-locked-6': '&#xe906;',
		'icon-mrchub-map-location-1': '&#xe94d;',
		'icon-mrchub-live-1': '&#xe94e;',
		'icon-mrchub-menu-4': '&#xe907;',
		'icon-mrchub-live-2': '&#xe94f;',
		'icon-mrchub-more': '&#xe91c;',
		'icon-mrchub-more-1': '&#xe908;',
		'icon-mrchub-multiply': '&#xe902;',
		'icon-mrchub-news-1': '&#xe950;',
		'icon-mrchub-photo-camera': '&#xe91d;',
		'icon-mrchub-search': '&#xe92e;',
		'icon-mrchub-search-1': '&#xe92f;',
		'icon-mrchub-server-3': '&#xe92d;',
		'icon-mrchub-settings-5': '&#xe955;',
		'icon-mrchub-star-1': '&#xe951;',
		'icon-mrchub-switch-5': '&#xe957;',
		'icon-mrchub-unlocked': '&#xe909;',
		'icon-mrchub-user': '&#xe917;',
		'icon-mrchub-user-3': '&#xe918;',
		'icon-mrchub-users-1': '&#xe904;',
		'icon-mrchub-graph': '&#xe958;',
		'icon-mrchub-graph-1': '&#xe959;',
		'icon-mrchub-graph-2': '&#xe95a;',
		'icon-mrchub-chart-1': '&#xe952;',
		'icon-mrchub-pie-chart-2': '&#xe95b;',
		'icon-mrchub-pie-chart-5': '&#xe95c;',
		'icon-mrchub-speech-bubble-2': '&#xe919;',
		'icon-mrchub-grid-inline1': '&#xe93e;',
		'icon-mrchub-grid-ingrid1': '&#xe93f;',
		'icon-mrchub-close1': '&#xe925;',
		'icon-mrchub-close2': '&#xe926;',
		'icon-mrchub-close3': '&#xe927;',
		'0': 0
		},
		els = document.getElementsByTagName('*'),
		i, c, el;
	for (i = 0; ; i += 1) {
		el = els[i];
		if(!el) {
			break;
		}
		c = el.className;
		c = c.match(/icon-mrchub-[^\s'"]+/);
		if (c && icons[c[0]]) {
			addIcon(el, icons[c[0]]);
		}
	}
}());
