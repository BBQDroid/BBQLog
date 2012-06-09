(function($) {
	$.fn.konami = function(callback, code, stay) {
		if(code == undefined) code = "38,38,40,40,37,39,37,39,66,65";
		
		return this.each(function() {
			var kkeys = [];
			$(this).keydown(function(e){
				kkeys.push( e.keyCode );
				while (kkeys.length > code.split(',').length) {
					kkeys.shift();
				}
				if ( kkeys.toString().indexOf( code ) >= 0 ){
					if (!stay) {
						$(this).unbind('keydown', arguments.callee);
					}
					callback(e);
				}
			});
		});
	}

})(jQuery);

// Nebkat
var nebkatTroll = function(){
	//var scaryAudio = $("<audio src='images/scary.mp3' preload='auto' class='troll'></audio>");
	//scaryAudio.appendTo($("body"));
	var usePedobear = false;
	var smallTrollInterval = setInterval(function(){
		var x = Math.floor((Math.random()*($(window).width() + 100))+1) - 50;
		var y = Math.floor((Math.random()*($(window).height() + 100))+1) - 50;
		var image = "troll.png";
		if (usePedobear && Math.random() > 0.75) {
			image = "pedobear.png";
		}

		$("<img src='images/"+image+"' class='troll' style='position:fixed; left:"+x+"px; top:"+y+"px;'></img>").appendTo($("body"));
	}, 1);
	var largeTrollTimeout = setTimeout(function(){
		var x = $(window).width() / 2 - 240;
		var y = $(window).height() / 2 - 240;
		$("<img src='images/troll_large.png' class='troll' style='z-index:1000; position:fixed; left:"+x+"px; top:"+y+"px;'></img>").appendTo($("body"));
		//scaryAudio[0].play();
		$(window).konami(function(){
			usePedobear = true;
		}, "80,69,68,79,66,69,65,82");
	}, 5000);
	$(window).konami(function(){
		clearInterval(smallTrollInterval);
		clearTimeout(largeTrollTimeout);
		$(".troll").remove();
		$(window).konami(nebkatTroll, "78,69,66,75,65,84");
	}, "84,65,75,66,69,78");
};
$(window).konami(nebkatTroll, "78,69,66,75,65,84");
