function episode_tag(season, episode) {
	var s = season.index;
	var e = episode.index;
	r = (s<10)?'S0'+s:'S'+s;
	r += (e<10)?'E0'+e:'E'+e;
	return r;
} // end func: episode_tag


function number_format(num) {
	var num = parseInt(num);
	if(num<1000) 
		return num;
	var num = num.toString();
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(num)) {
		num = num.replace(rgx, '$1' + ',' + '$2');
	}
	return num;
} // end func: number_format

function inflect(num, single, plural) {
	if(num==1) 
		return single;
	if(plural) 
		return plural;
	return single+"s";
} // end func: inflect

function humanFileSize(bytes, si=false, dp=1) {
	const thresh = si ? 1000 : 1024;
  
	if (Math.abs(bytes) < thresh) {
	  return bytes + ' B';
	}
  
	const units = si 
	  ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'] 
	  : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
	let u = -1;
	const r = 10**dp;
  
	do {
	  bytes /= thresh;
	  ++u;
	} while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
  
  
	return bytes.toFixed(dp) + ' ' + units[u];
  } // end func: humanFileSize